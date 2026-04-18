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
function calculate_fee(int $minutes, float $first_rate, float $next_rate, float $max_rate): array {
    $minutes = max(1, $minutes);
    $hours   = max(1, (int)ceil($minutes / 60));
    $fee     = $hours <= 1
        ? $first_rate
        : $first_rate + ($hours - 1) * $next_rate;
    $fee = min($fee, $max_rate);
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
        SELECT ps.slot_type, COUNT(*) as count
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
    $pdo->exec("UPDATE parking_slot SET status = 'available' WHERE status IN ('occupied', 'reserved')");
    $pdo->exec("
        UPDATE parking_slot s
        JOIN `transaction` t ON s.slot_id = t.slot_id
        SET s.status = 'occupied'
        WHERE t.payment_status = 'unpaid'
    ");
    // Sync Reserved (with 15-minute buffer)
    $pdo->exec("
        UPDATE parking_slot s
        JOIN reservation r ON s.slot_id = r.slot_id
        SET s.status = 'reserved'
        WHERE r.status = 'confirmed' 
          AND r.reserved_from <= DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
          AND r.reserved_until >= NOW()
          AND s.status = 'available'
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
function get_ai_context_data(PDO $pdo): array {

    // ── 1. TODAY'S SUMMARY ────────────────────────────────────────────────
    $today_rev   = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();
    $today_trx   = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE DATE(check_in_time)=CURDATE()")->fetchColumn();
    $active_v    = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
    $total_trx   = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='paid'")->fetchColumn();
    $total_rev   = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid'")->fetchColumn();

    // ── 2. SLOT STATUS (Per Floor & Type) ────────────────────────────────
    $slots = $pdo->query("
        SELECT f.floor_name, ps.slot_type,
               COUNT(*) AS total,
               SUM(ps.status='available') AS available,
               SUM(ps.status='occupied')  AS occupied,
               SUM(ps.status='reserved')  AS reserved,
               SUM(ps.status='maintenance') AS maintenance
        FROM parking_slot ps
        JOIN floor f ON ps.floor_id = f.floor_id
        GROUP BY f.floor_name, ps.slot_type
        ORDER BY f.floor_id, ps.slot_type
    ")->fetchAll();

    // ── 3. DAILY REVENUE TREND (Last 30 Days) ─────────────────────────────
    $daily_trend = $pdo->query("
        SELECT DATE(check_out_time) AS date,
               COUNT(*) AS volume,
               SUM(total_fee) AS revenue,
               SUM(vehicle_type='car') AS cars,
               SUM(vehicle_type='motorcycle') AS motorcycles
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE payment_status='paid' AND check_out_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(check_out_time)
        ORDER BY date DESC
    ")->fetchAll();

    // ── 4. HOURLY DISTRIBUTION (Last 7 Days) ──────────────────────────────
    $hourly = $pdo->query("
        SELECT HOUR(check_in_time) AS hour,
               COUNT(*) AS total_entries,
               SUM(vehicle_type='car') AS cars,
               SUM(vehicle_type='motorcycle') AS motorcycles
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(check_in_time)
        ORDER BY hour ASC
    ")->fetchAll();

    // ── 5. VEHICLE BREAKDOWN ──────────────────────────────────────────────
    $vehicle_stats = $pdo->query("
        SELECT v.vehicle_type,
               COUNT(DISTINCT v.vehicle_id) AS total_registered,
               COUNT(t.transaction_id)      AS total_transactions,
               COALESCE(SUM(t.total_fee),0) AS total_revenue
        FROM vehicle v
        LEFT JOIN `transaction` t ON v.vehicle_id = t.vehicle_id AND t.payment_status='paid'
        GROUP BY v.vehicle_type
    ")->fetchAll();

    // ── 6. OPERATOR PERFORMANCE ───────────────────────────────────────────
    $operator_perf = $pdo->query("
        SELECT o.full_name, o.shift,
               COUNT(t.transaction_id)      AS total_transactions,
               COALESCE(SUM(t.total_fee),0) AS total_revenue_handled,
               COALESCE(AVG(t.duration_hours),0) AS avg_duration_hours
        FROM operator o
        LEFT JOIN `transaction` t ON o.operator_id = t.operator_id AND t.payment_status='paid'
        GROUP BY o.operator_id
        ORDER BY total_transactions DESC
    ")->fetchAll();

    // ── 7. RESERVATION STATUS ─────────────────────────────────────────────
    $reservation_summary = $pdo->query("
        SELECT status, COUNT(*) AS count
        FROM reservation
        GROUP BY status
    ")->fetchAll();

    $active_reservations = $pdo->query("
        SELECT r.reservation_code, v.plate_number, v.vehicle_type,
               ps.slot_number, f.floor_name,
               r.reserved_from, r.reserved_until, r.status
        FROM reservation r
        JOIN vehicle v ON r.vehicle_id = v.vehicle_id
        JOIN parking_slot ps ON r.slot_id = ps.slot_id
        JOIN floor f ON ps.floor_id = f.floor_id
        WHERE r.status IN ('pending','confirmed')
        ORDER BY r.reserved_from ASC
        LIMIT 20
    ")->fetchAll();

    // ── 8. RECENT TRANSACTIONS (Last 10) ─────────────────────────────────
    $recent_transactions = $pdo->query("
        SELECT t.ticket_code, v.plate_number, v.vehicle_type,
               ps.slot_number, f.floor_name,
               t.check_in_time, t.check_out_time,
               t.duration_hours, t.total_fee, t.payment_status,
               t.payment_method, o.full_name AS operator
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        JOIN parking_slot ps ON t.slot_id = ps.slot_id
        JOIN floor f ON ps.floor_id = f.floor_id
        JOIN operator o ON t.operator_id = o.operator_id
        ORDER BY t.check_in_time DESC
        LIMIT 10
    ")->fetchAll();

    // ── 9. PAYMENT METHOD BREAKDOWN ───────────────────────────────────────
    $payment_methods = $pdo->query("
        SELECT payment_method, COUNT(*) AS count, SUM(total_fee) AS revenue
        FROM `transaction` WHERE payment_status='paid'
        GROUP BY payment_method
    ")->fetchAll();

    // ── 10. GATE SCAN LOG SUMMARY (Last 24 Hours) ─────────────────────────
    $gate_log = $pdo->query("
        SELECT scan_type,
               COUNT(*) AS total_scans,
               SUM(matched=1) AS matched,
               SUM(gate_action='open') AS opened,
               SUM(gate_action='reject') AS rejected
        FROM plate_scan_log
        WHERE scan_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY scan_type
    ")->fetchAll();

    // ── 11. PARKING RATES ─────────────────────────────────────────────────
    $rates = $pdo->query("SELECT * FROM parking_rate")->fetchAll();

    // ── 12. FLOOR CAPACITY OVERVIEW ───────────────────────────────────────
    $floors = $pdo->query("SELECT * FROM floor ORDER BY floor_id")->fetchAll();

    return [
        'system_name'         => 'SmartParking Enterprise v2',
        'generated_at'        => date('Y-m-d H:i:s'),
        'summary'             => [
            'revenue_today'     => $today_rev,
            'transactions_today'=> $today_trx,
            'active_vehicles'   => $active_v,
            'all_time_revenue'  => $total_rev,
            'all_time_paid_trx' => $total_trx,
        ],
        'slots'               => $slots,
        'daily_trend'         => $daily_trend,
        'hourly_distribution' => $hourly,
        'vehicle_stats'       => $vehicle_stats,
        'operator_performance'=> $operator_perf,
        'reservation_summary' => $reservation_summary,
        'active_reservations' => $active_reservations,
        'recent_transactions' => $recent_transactions,
        'payment_methods'     => $payment_methods,
        'gate_log_24h'        => $gate_log,
        'rates'               => $rates,
        'floors'              => $floors,
    ];
}

