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
    // Auto-expire reservations that are past reserved_until
    $pdo->exec("UPDATE reservation SET status='expired' WHERE status IN ('pending','confirmed') AND reserved_until < NOW()");
    
    // Reset all slots to available first (only those that are occupied or reserved)
    $pdo->exec("UPDATE parking_slot SET status = 'available' WHERE status IN ('occupied', 'reserved')");
    
    // Mark as Occupied based on active transactions
    $pdo->exec("
        UPDATE parking_slot s
        JOIN `transaction` t ON s.slot_id = t.slot_id
        SET s.status = 'occupied'
        WHERE t.payment_status = 'unpaid'
    ");

    // Sync Reserved (with 15-minute buffer for immediate status)
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
function get_ai_context_data(PDO $pdo, ?string $start_date = null, ?string $end_date = null): array {
    $date_cond = "";
    $params = [];

    if ($start_date && $end_date) {
        $date_cond = " AND DATE(check_out_time) BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    } elseif ($start_date) {
        $date_cond = " AND DATE(check_out_time) >= ?";
        $params = [$start_date];
    }

    // ── 1. SUMMARY (Using filters if provided, otherwise defaults to all-time or today where appropriate) ──
    // For "Today's Summary" section, we use the filter if it's narrower than all-time
    $summary_cond = $date_cond ?: " AND DATE(check_out_time)=CURDATE()";
    $summary_params = $params ?: [];

    $rev_total   = $pdo->prepare("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' $date_cond");
    $rev_total->execute($params);
    $total_rev = $rev_total->fetchColumn();

    $trx_total   = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE payment_status='paid' $date_cond");
    $trx_total->execute($params);
    $total_trx = $trx_total->fetchColumn();

    $entry_cond = $date_cond ? str_replace('check_out_time', 'check_in_time', $date_cond) : " AND DATE(check_in_time)=CURDATE()";
    $entry_total = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE 1=1 $entry_cond");
    $entry_total->execute($params);
    $total_entries = $entry_total->fetchColumn();

    $active_v    = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
    $avail_s     = $pdo->query("SELECT COUNT(*) FROM parking_slot WHERE status='available'")->fetchColumn();
    $total_res   = $pdo->query("SELECT COUNT(*) FROM reservation WHERE status IN ('pending','confirmed')")->fetchColumn();

    // ── 2. SLOT STATUS (Current state, not filtered by time) ─────────────────
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

    // ── 3. DAILY REVENUE TREND ─────────────────────────────────────────────
    $trend_limit = $date_cond ?: " AND check_out_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $trend_params = $params ?: [];
    
    $daily_trend = $pdo->prepare("
        SELECT DATE(check_out_time) AS date,
               COUNT(*) AS volume,
               SUM(total_fee) AS revenue,
               SUM(vehicle_type='car') AS cars,
               SUM(vehicle_type='motorcycle') AS motos
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE payment_status='paid' $trend_limit
        GROUP BY DATE(check_out_time)
        ORDER BY date DESC
    ");
    $daily_trend->execute($trend_params);
    $daily_trend = $daily_trend->fetchAll();

    // ── 4. HOURLY DISTRIBUTION (Traffic Peaks - uses check_in_time) ─────────
    $hourly_cond = $date_cond ? str_replace('check_out_time', 'check_in_time', $date_cond) : " AND check_in_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $hourly = $pdo->prepare("
        SELECT HOUR(check_in_time) AS hour,
               COUNT(*) AS total_entries,
               SUM(vehicle_type='car') AS cars,
               SUM(vehicle_type='motorcycle') AS motos
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE check_in_time IS NOT NULL $hourly_cond
        GROUP BY HOUR(check_in_time)
        ORDER BY hour ASC
    ");
    $hourly->execute($params);
    $hourly = $hourly->fetchAll();

    // ── 5. VEHICLE BREAKDOWN ──────────────────────────────────────────────
    $vehicle_stats = $pdo->prepare("
        SELECT v.vehicle_type,
               COUNT(DISTINCT v.vehicle_id) AS total_registered,
               COUNT(t.transaction_id)      AS total_count,
               COALESCE(SUM(t.total_fee),0) AS total_revenue
        FROM vehicle v
        LEFT JOIN `transaction` t ON v.vehicle_id = t.vehicle_id AND t.payment_status='paid' $date_cond
        GROUP BY v.vehicle_type
    ");
    $vehicle_stats->execute($params);
    $vehicle_stats = $vehicle_stats->fetchAll();

    // ── 6. OPERATOR PERFORMANCE ───────────────────────────────────────────
    $operator_perf = $pdo->prepare("
        SELECT o.full_name, o.shift,
               COUNT(t.transaction_id)      AS total_transactions,
               COALESCE(SUM(t.total_fee),0) AS total_revenue_handled,
               COALESCE(AVG(t.duration_hours),0) AS avg_duration_hours
        FROM operator o
        LEFT JOIN `transaction` t ON o.operator_id = t.operator_id AND t.payment_status='paid' $date_cond
        GROUP BY o.operator_id
        ORDER BY total_transactions DESC
    ");
    $operator_perf->execute($params);
    $operator_perf = $operator_perf->fetchAll();

    // ── 7. RESERVATION STATUS (Current state, usually not filtered by historic range but by intent) ──
    $reservation_summary = $pdo->query("SELECT status, COUNT(*) AS count FROM reservation GROUP BY status")->fetchAll();

    // ── 9. PAYMENT METHOD BREAKDOWN ───────────────────────────────────────
    $payment_methods = $pdo->prepare("
        SELECT payment_method, COUNT(*) AS count, SUM(total_fee) AS revenue
        FROM `transaction` WHERE payment_status='paid' $date_cond
        GROUP BY payment_method
    ");
    $payment_methods->execute($params);
    $payment_methods = $payment_methods->fetchAll();

    // ── 10. GATE SCAN LOG SUMMARY ─────────────────────────────────────────
    $gate_limit = $date_cond ? str_replace('check_out_time', 'scan_time', $date_cond) : " AND scan_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $gate_log = $pdo->prepare("
        SELECT scan_type, gate_action, COUNT(*) AS count
        FROM plate_scan_log
        WHERE scan_time IS NOT NULL $gate_limit
        GROUP BY scan_type, gate_action
    ");
    $gate_log->execute($params);
    $gate_log = $gate_log->fetchAll();

    // ── 11. ADDITIONAL CONTEXT (Defining missing variables for return) ────
    $active_reservations = $pdo->query("SELECT r.*, v.plate_number FROM reservation r JOIN vehicle v ON r.vehicle_id = v.vehicle_id WHERE r.status IN ('pending', 'confirmed') ORDER BY r.created_at DESC LIMIT 10")->fetchAll();
    $recent_transactions = $pdo->query("SELECT t.*, v.plate_number, v.vehicle_type FROM `transaction` t JOIN vehicle v ON t.vehicle_id = v.vehicle_id ORDER BY check_in_time DESC LIMIT 10")->fetchAll();
    $rates = $pdo->query("SELECT * FROM parking_rate")->fetchAll();
    $floors = $pdo->query("SELECT * FROM floor ORDER BY floor_id")->fetchAll();

    return [
        'system_name'         => 'SmartParking Enterprise v2',
        'generated_at'        => date('Y-m-d H:i:s'),
        'summary'             => [
            'revenue_today'     => $total_rev,
            'transactions_today'=> $total_trx,
            'active_vehicles'   => $active_v,
            'all_time_revenue'  => $total_rev,
            'all_time_paid_trx' => $total_trx,
            'available_slots'   => $avail_s,
            'total_reservations'=> $total_res,
            'total_entries_today' => $total_entries,
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
        'gate_log'            => $gate_log,
        'rates'               => $rates,
        'floors'              => $floors,
    ];
}

