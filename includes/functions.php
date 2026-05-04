<?php
/**
 * includes/functions.php — Shared utility functions
 * [3NF FIX] get_slot_summary tidak perlu diubah karena hanya query parking_slot langsung.
 *           Namun fungsi lain yang menyebut floor varchar perlu diperhatikan.
 */

// ── CSRF ──────────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token.']));
    }
}

// ── Fee calculation ───────────────────────────────────────────────────────
function calculate_fee(int $minutes, float $first_rate, float $next_rate): array {
    $minutes = max(1, $minutes);
    $hours   = max(1, (int)ceil($minutes / 60));
    $fee     = $hours <= 1
        ? $first_rate
        : $first_rate + ($hours - 1) * $next_rate;
    return [
        'hours'          => $hours,
        'duration_hours' => round($minutes / 60, 2),
        'total_fee'      => $fee,
    ];
}

// ── Ticket code generator ─────────────────────────────────────────────────
function generate_ticket_code(PDO $pdo): string {
    do {
        $code = 'TKT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare("SELECT ticket_id FROM ticket WHERE ticket_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ── Reservation code generator ────────────────────────────────────────────
function generate_reservation_code(PDO $pdo): string {
    do {
        $code = 'RSV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare("SELECT reservation_id FROM reservation WHERE reservation_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ── Guest plate generator ─────────────────────────────────────────────────
function generate_guest_plate(PDO $pdo): string {
    do {
        $plate = 'GUEST-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt  = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
        $stmt->execute([$plate]);
    } while ($stmt->fetch());
    return $plate;
}

// ── Format currency ───────────────────────────────────────────────────────
function fmt_idr(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// ── Slot availability summary ─────────────────────────────────────────────
// [3NF NOTE] Fungsi ini hanya query slot_type & status dari parking_slot,
//            tidak membutuhkan JOIN ke floor — tidak ada perubahan diperlukan.
function get_slot_summary(PDO $pdo): array {
    // 1. Get base counts from parking_slot
    $stmt = $pdo->query("
        SELECT slot_type,
               SUM(status = 'available') AS avail,
               SUM(status = 'occupied') AS occupied,
               SUM(status = 'reserved') AS reserved,
               SUM(status = 'maintenance') AS maintenance,
               COUNT(*) AS total
        FROM parking_slot
        GROUP BY slot_type
    ");
    
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['slot_type']] = [
            'avail'       => (int)$row['avail'],
            'occupied'    => (int)$row['occupied'],
            'reserved'    => (int)$row['reserved'],
            'maintenance' => (int)$row['maintenance'],
            'total'       => (int)$row['total'],
        ];
    }

    // 2. Add upcoming confirmed reservations for today that aren't yet marked 'reserved' in the slot table
    // This ensures availability decreases and "Reserved" count increases as soon as a reservation is made for today.
    $upcoming = $pdo->query("
        SELECT ps.slot_type, COUNT(DISTINCT r.slot_id) as count
        FROM reservation r
        JOIN parking_slot ps ON r.slot_id = ps.slot_id
        WHERE r.status = 'confirmed' 
          AND DATE(r.reserved_from) = CURDATE()
          AND ps.status = 'available'
        GROUP BY ps.slot_type
    ")->fetchAll();

    foreach ($upcoming as $up) {
        if (isset($result[$up['slot_type']])) {
            $count = (int)$up['count'];
            $result[$up['slot_type']]['reserved'] += $count;
            $result[$up['slot_type']]['avail']    -= $count;
            // Ensure avail doesn't go below 0
            if ($result[$up['slot_type']]['avail'] < 0) $result[$up['slot_type']]['avail'] = 0;
        }
    }

    return $result;
}

/**
 * Ensures parking_slot statuses are in sync with active transactions and reservations.
 */
function sync_slot_statuses(PDO $pdo): void {
    // Auto-expire reservations logic removed (entry-only system)
    
    // 1. Mark as Occupied based on active transactions
    $pdo->exec("
        UPDATE parking_slot s
        JOIN `transaction` t ON s.slot_id = t.slot_id
        SET s.status = 'occupied'
        WHERE t.payment_status = 'unpaid'
    ");

    // 2. Sync Reserved (with 15-minute buffer for immediate status)
    $pdo->exec("
        UPDATE parking_slot s
        JOIN reservation r ON s.slot_id = r.slot_id
        SET s.status = 'reserved'
        WHERE r.status = 'confirmed' 
          AND r.reserved_from <= DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
    ");

    // 3. Reset to available only those slots that are NEITHER unpaid transactions NOR active confirmed reservations
    $pdo->exec("
        UPDATE parking_slot SET status = 'available' 
        WHERE slot_id NOT IN (
            SELECT t2.slot_id FROM (
                SELECT slot_id FROM `transaction` WHERE payment_status = 'unpaid' 
                UNION 
                SELECT slot_id FROM `reservation` WHERE status = 'confirmed' AND reserved_from <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            ) as t2
        )
    ");
}


// ── Role check ────────────────────────────────────────────────────────────
function require_role(string ...$roles): void {
    $user_role = $_SESSION['role'] ?? '';
    if (!in_array($user_role, $roles, true)) {
        http_response_code(403);
        header('Location: index.php?error=forbidden');
        exit;
    }
}

// ── Attendance helpers ────────────────────────────────────────────────────
function is_on_duty(): bool {
    if (($_SESSION['role'] ?? '') === 'superadmin') return true;
    return !empty($_SESSION['staff_id']);
}

function get_active_attendance(PDO $pdo, ?string $filter_type = null): array {
    $where = "WHERE sa.is_active = 1";
    if ($filter_type) {
        $where .= " AND o.staff_type = " . $pdo->quote($filter_type);
    }
    
    return $pdo->query("
        SELECT sa.*, o.full_name, o.staff_type, o.shift, au.username
        FROM shift_attendance sa
        JOIN operator o ON sa.staff_id = o.operator_id
        JOIN admin_users au ON sa.user_id = au.user_id
        $where
        ORDER BY o.staff_type ASC, sa.check_in_time DESC
    ")->fetchAll();
}

// ── AI Context Helper — Full Database Aggregator ───────────────────────────
function get_ai_context_data(PDO $pdo, $start_date = null, $end_date = null): array {
    $start_date = $start_date ?? date('Y-m-d', strtotime('-7 days'));
    $end_date   = $end_date   ?? date('Y-m-d');
    // Datetime boundaries for precise range filtering
    $dt_start = $start_date . ' 00:00:00';
    $dt_end   = $end_date   . ' 23:59:59';

    // ── 1. ALL-TIME STATS ───────────────────────────────────────────────────
    $all_time = $pdo->query("
        SELECT 
            COALESCE(SUM(total_fee), 0) AS revenue,
            COUNT(*) AS transactions,
            MIN(check_in_time) AS first_record
        FROM `transaction` 
        WHERE payment_status = 'paid'
    ")->fetch();

    // ── 2. PERIOD STATS (scoped to selected date range) ──────────────────────
    $period_revenue = $pdo->prepare("
        SELECT COALESCE(SUM(total_fee), 0) FROM `transaction`
        WHERE payment_status = 'paid' AND check_out_time BETWEEN ? AND ?
    ");
    $period_revenue->execute([$dt_start, $dt_end]);
    $period_revenue = $period_revenue->fetchColumn() ?: 0;

    $period_entries = $pdo->prepare("
        SELECT COUNT(*) FROM `transaction` WHERE check_in_time BETWEEN ? AND ?
    ");
    $period_entries->execute([$dt_start, $dt_end]);
    $period_entries = $period_entries->fetchColumn();

    $period_transactions = $pdo->prepare("
        SELECT COUNT(*) FROM `transaction`
        WHERE payment_status = 'paid' AND check_out_time BETWEEN ? AND ?
    ");
    $period_transactions->execute([$dt_start, $dt_end]);
    $period_transactions = $period_transactions->fetchColumn() ?: 0;

    $range_stats_stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(t.total_fee), 0) AS revenue,
            COUNT(*) AS transactions,
            SUM(v.vehicle_type = 'car') AS cars,
            SUM(v.vehicle_type = 'motorcycle') AS motos
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE t.payment_status = 'paid' AND t.check_out_time BETWEEN ? AND ?
    ");
    $range_stats_stmt->execute([$dt_start, $dt_end]);
    $range_stats = $range_stats_stmt->fetch();

    // ── 3. YESTERDAY'S STATS ────────────────────────────────────────────────
    $yesterday = $pdo->query("
        SELECT COALESCE(SUM(total_fee), 0) AS revenue, COUNT(*) AS transactions
        FROM `transaction` 
        WHERE payment_status = 'paid' AND DATE(check_out_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ")->fetch();

    // ── 4. CURRENT REAL-TIME STATUS (Zones: Regular vs Reservation) ─────────
    $active_v = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status = 'unpaid'")->fetchColumn();
    $slot_totals = $pdo->query("
        SELECT 
            COUNT(*) AS total_slots,
            SUM(status='available') AS available_slots,
            SUM(status='occupied') AS occupied_slots,
            SUM(status='reserved') AS reserved_slots
        FROM parking_slot
    ")->fetch();
    $avail_s  = (int)($slot_totals['available_slots'] ?? 0);
    
    // Group slots by Category (is_reservation_only)
    $slots = $pdo->query("
        SELECT 
            CASE WHEN ps.is_reservation_only = 1 THEN 'Reservation Only' ELSE 'Standard Regular' END AS zone_name,
            ps.slot_type,
            COUNT(*) AS total,
            SUM(ps.status='available') AS available,
            SUM(ps.status='occupied')  AS occupied,
            SUM(ps.status='reserved')  AS reserved
        FROM parking_slot ps
        GROUP BY ps.is_reservation_only, ps.slot_type
        ORDER BY ps.is_reservation_only DESC, ps.slot_type
    ")->fetchAll();

    // ── 5. TRENDS & DISTRIBUTIONS ──────────────────────────────────────────
    $dt_stmt = $pdo->prepare("
        SELECT DATE(check_out_time) AS date,
               COUNT(*) AS volume,
               SUM(total_fee) AS revenue,
               SUM(vehicle_type='car') AS cars,
               SUM(vehicle_type='motorcycle') AS motos
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE payment_status='paid' AND check_out_time BETWEEN ? AND ?
        GROUP BY DATE(check_out_time)
        ORDER BY date DESC
    ");
    $dt_stmt->execute([$dt_start, $dt_end]);
    $daily_trend = $dt_stmt->fetchAll();

    $hourly_stmt = $pdo->prepare("
        SELECT HOUR(check_in_time) AS hour,
               COUNT(*) AS total_entries,
               SUM(v.vehicle_type = 'car') AS cars,
               SUM(v.vehicle_type = 'motorcycle') AS motos,
               SUM(t.reservation_id IS NOT NULL) AS reservations
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE t.check_in_time BETWEEN ? AND ?
        GROUP BY HOUR(check_in_time)
        ORDER BY hour ASC
    ");
    $hourly_stmt->execute([$dt_start, $dt_end]);
    $hourly = $hourly_stmt->fetchAll();

    // ── 6. VEHICLE & OPERATOR METRICS (scoped to date range) ─────────────────
    $vs_stmt = $pdo->prepare("
        SELECT v.vehicle_type,
               COUNT(DISTINCT v.vehicle_id) AS total_registered,
               COUNT(t.transaction_id) AS total_count,
               COALESCE(SUM(t.total_fee),0) AS total_revenue
        FROM vehicle v
        LEFT JOIN `transaction` t ON v.vehicle_id = t.vehicle_id
            AND t.payment_status='paid'
            AND t.check_out_time BETWEEN ? AND ?
        GROUP BY v.vehicle_type
    ");
    $vs_stmt->execute([$dt_start, $dt_end]);
    $vehicle_stats = $vs_stmt->fetchAll();

    $op_stmt = $pdo->prepare("
        SELECT o.full_name, o.shift, o.operator_id,
               COUNT(t.transaction_id) AS total_transactions,
               COALESCE(SUM(t.total_fee),0) AS total_revenue_handled,
               COALESCE(AVG(TIMESTAMPDIFF(MINUTE, t.check_in_time, t.check_out_time))/60, 0) AS avg_duration_hours
        FROM operator o
        LEFT JOIN `transaction` t ON o.operator_id = t.operator_id
            AND t.payment_status='paid'
            AND t.check_out_time BETWEEN ? AND ?
        GROUP BY o.operator_id
        ORDER BY total_transactions DESC
    ");
    $op_stmt->execute([$dt_start, $dt_end]);
    $operator_perf = $op_stmt->fetchAll();

    // ── 7. RESERVATIONS SUMMARY (scoped to date range) ─────────────────────
    $rs_stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS count FROM reservation
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $rs_stmt->execute([$dt_start, $dt_end]);
    $res_summary = $rs_stmt->fetchAll();

    $active_reservations = $pdo->query("
        SELECT r.reservation_code, v.plate_number, v.vehicle_type, ps.slot_number, r.reserved_from, r.status,
               CASE WHEN ps.is_reservation_only = 1 THEN 'VIP Reservation' ELSE 'Standard Regular' END AS zone
        FROM reservation r 
        JOIN vehicle v ON r.vehicle_id = v.vehicle_id 
        JOIN parking_slot ps ON r.slot_id = ps.slot_id
        WHERE r.status IN ('pending', 'confirmed') 
        ORDER BY r.created_at DESC LIMIT 5
    ")->fetchAll();

    $rt_stmt = $pdo->prepare("
        SELECT t.ticket_code, v.plate_number, v.vehicle_type, ps.slot_number, t.check_in_time, t.check_out_time, t.total_fee, t.payment_status,
               CASE WHEN ps.is_reservation_only = 1 THEN 'VIP Reservation' ELSE 'Standard Regular' END AS zone
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN parking_slot ps ON t.slot_id = ps.slot_id
        WHERE t.check_in_time BETWEEN ? AND ?
        ORDER BY t.transaction_id DESC LIMIT 10
    ");
    $rt_stmt->execute([$dt_start, $dt_end]);
    $recent_transactions = $rt_stmt->fetchAll();

    $pm_stmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) AS count, SUM(total_fee) AS revenue
        FROM `transaction` WHERE payment_status='paid' AND check_out_time BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $pm_stmt->execute([$dt_start, $dt_end]);
    $payment_methods = $pm_stmt->fetchAll();

    $floors = $pdo->query("SELECT * FROM floor ORDER BY floor_id")->fetchAll();
    $rates  = $pdo->query("SELECT * FROM parking_rate")->fetchAll();

    return [
        'system_name'  => 'Cereza Parkhere v2',
        'generated_at' => date('Y-m-d H:i:s'),
        'date_range'   => ['start' => $start_date, 'end' => $end_date],
        'summary' => [
            'revenue_today'          => (float)$period_revenue,      // = selected range
            'transactions_today'     => (int)$period_transactions,
            'entries_today'          => (int)$period_entries,
            'revenue_yesterday'      => (float)$yesterday['revenue'],
            'transactions_yesterday' => (int)$yesterday['transactions'],
            'active_vehicles'        => (int)$active_v,
            'total_slots'            => (int)($slot_totals['total_slots'] ?? 0),
            'available_slots'        => (int)$avail_s,
            'occupied_slots'         => (int)($slot_totals['occupied_slots'] ?? 0),
            'reserved_slots'         => (int)($slot_totals['reserved_slots'] ?? 0),
            'all_time_revenue'       => (float)$all_time['revenue'],
            'all_time_transactions'  => (int)$all_time['transactions'],
            'first_record_date'      => $all_time['first_record'],
            'total_reservations'     => array_sum(array_column($res_summary, 'count')),
        ],
        'slots'               => $slots,
        'daily_trend'         => $daily_trend,
        'hourly_distribution' => $hourly,
        'vehicle_stats'       => $vehicle_stats,
        'operator_performance'=> $operator_perf,
        'active_reservations' => $active_reservations,
        'recent_transactions' => $recent_transactions,
        'reservation_summary' => $res_summary,
        'payment_methods'     => $payment_methods,
        'floors'              => $floors,
        'rates'               => $rates
    ];
}

