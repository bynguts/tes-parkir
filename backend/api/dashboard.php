<?php
/**
 * api/dashboard.php
 * Returns dashboard stats as JSON for the React frontend.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Slot summary ─────────────────────────────────────────────────────────
$summary    = get_slot_summary($pdo);
$car_avail  = (int)($summary['car']['avail']          ?? 0);
$car_total  = (int)($summary['car']['total']          ?? 0);
$moto_avail = (int)($summary['motorcycle']['avail']   ?? 0);
$moto_total = (int)($summary['motorcycle']['total']   ?? 0);

$car_pct  = $car_total  > 0 ? (int)round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? (int)round(($moto_avail / $moto_total) * 100) : 100;

// ── Active & today revenue ────────────────────────────────────────────────
$active    = (int)$pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$today_rev = (float)$pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();

// ── Reservation stats ─────────────────────────────────────────────────────
$total_reservations = (int)$pdo->query("SELECT COUNT(*) FROM reservasi WHERE DATE(jam_masuk_rencana) = CURDATE()")->fetchColumn();
$active_reservations = (int)$pdo->query("SELECT COUNT(*) FROM reservasi WHERE status = 'BOOKED' AND DATE(jam_masuk_rencana) = CURDATE()")->fetchColumn();

// ── Weekly revenue (last 7 days) ──────────────────────────────────────────
$stmt = $pdo->query("
    SELECT DATE(check_out_time) AS day,
           COALESCE(SUM(total_fee), 0) AS revenue
    FROM `transaction`
    WHERE payment_status = 'paid'
      AND check_out_time >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(check_out_time)
    ORDER BY day ASC
");
$raw_weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill all 7 days (including days with 0)
$weekly = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $day_label = date('D', strtotime($date)); // Mon, Tue…
    $weekly[$date] = ['day' => $day_label, 'revenue' => 0];
}
foreach ($raw_weekly as $row) {
    if (isset($weekly[$row['day']])) {
        $weekly[$row['day']]['revenue'] = (float)$row['revenue'];
    }
}
$revenue_weekly = array_values($weekly);

// ── Trend Analysis ──────────────────────────────────────────────────────────
$yesterday_rev = (float)$pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

// ── Peak Occupancy Logic ──────────────────────────────────────────────────
$peak = $pdo->query("
    SELECT 
        HOUR(t_time) as hr, 
        COUNT(*) as count,
        SUM(CASE WHEN vtype = 'car' THEN 1 ELSE 0 END) as cars,
        SUM(CASE WHEN vtype = 'motorcycle' THEN 1 ELSE 0 END) as motos
    FROM (
        SELECT t.check_in_time as t_time, v.vehicle_type as vtype 
        FROM `transaction` t 
        JOIN `vehicle` v ON t.vehicle_id = v.vehicle_id 
        WHERE DATE(t.check_in_time) = CURDATE()
        UNION ALL
        SELECT r.jam_masuk_rencana as t_time, 'car' as vtype 
        FROM `reservasi` r 
        WHERE DATE(r.jam_masuk_rencana) = CURDATE() 
          AND r.status = 'BOOKED'
    ) combined
    GROUP BY hr
    ORDER BY count DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$peak_time = $peak ? sprintf("%02d:00 - %02d:00", $peak['hr'], ($peak['hr']+1)%24) : "No traffic";
$peak_vol  = $peak ? (int)($peak['count'] ?? 0) : 0;
$peak_dom  = $peak ? (($peak['cars'] ?? 0) >= ($peak['motos'] ?? 0) ? ($peak['cars'] > 0 ? 'Cars' : 'N/A') : 'Motorcycles') : 'N/A';

// ── Average Duration ──────────────────────────────────────────────────────
$avg_duration_min = (float)$pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time IS NOT NULL
")->fetchColumn();

$this_month_avg_duration = (float)$pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time IS NOT NULL
      AND MONTH(check_out_time) = MONTH(CURDATE())
      AND YEAR(check_out_time) = YEAR(CURDATE())
")->fetchColumn();

$prev_avg_duration = (float)$pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time IS NOT NULL
      AND MONTH(check_out_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND YEAR(check_out_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetchColumn();

$avg_duration_h = floor($avg_duration_min / 60);
$avg_duration_m = round($avg_duration_min % 60);
$avg_duration_str = ($avg_duration_h > 0 ? $avg_duration_h . "h " : "") . $avg_duration_m . "m";

$duration_trend = 0;
if ($prev_avg_duration > 0) {
    $duration_trend = (($this_month_avg_duration - $prev_avg_duration) / $prev_avg_duration) * 100;
}

// ── Recent Activity Log ───────────────────────────────────────────────────
$recent_logs = $pdo->query("
    (SELECT 
        'transaction' as log_type,
        t.transaction_id as id,
        t.check_in_time as entry_time,
        t.check_out_time as exit_time,
        v.plate_number,
        v.vehicle_type,
        t.ticket_code as code,
        COALESCE(t.total_fee, 0) as total_fee
     FROM `transaction` t
     JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    )
    UNION ALL
    (SELECT 
        'reservation' as log_type,
        r.id as id,
        r.jam_masuk_rencana as entry_time,
        r.jam_keluar_rencana as exit_time,
        r.plat_nomor as plate_number,
        r.vehicle_type as vehicle_type,
        CONCAT('RES-', r.id) as code,
        0 as total_fee
     FROM reservasi r
     WHERE r.status = 'BOOKED' AND DATE(r.jam_masuk_rencana) = CURDATE()
    )
    ORDER BY entry_time DESC 
    LIMIT 7
")->fetchAll(PDO::FETCH_ASSOC);

// ── Active Duty Staff ─────────────────────────────────────────────────────
$active_staff = [];
if (function_exists('get_active_attendance')) {
    $active_staff = get_active_attendance($pdo);
}

echo json_encode([
    'car_avail'          => $car_avail,
    'car_total'          => $car_total,
    'car_pct'            => $car_pct,
    'moto_avail'         => $moto_avail,
    'moto_total'         => $moto_total,
    'moto_pct'           => $moto_pct,
    'active'             => $active,
    'today_rev'          => $today_rev,
    'yesterday_rev'      => $yesterday_rev,
    'total_reservations' => $total_reservations,
    'active_reservations'=> $active_reservations,
    'peak_time'          => $peak_time,
    'peak_vol'           => $peak_vol,
    'peak_dom'           => $peak_dom,
    'avg_duration_str'   => $avg_duration_str,
    'duration_trend'     => $duration_trend,
    'recent_logs'        => $recent_logs,
    'active_staff'       => $active_staff,
    'page_title'         => 'Dashboard',
    'page_subtitle'      => date('l, d F Y'),
    'on_duty'            => is_on_duty(),
    'username'           => htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'),
    'role'               => $_SESSION['role'] ?? 'operator',
    'revenue_weekly'     => $revenue_weekly,
]);
