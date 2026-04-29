<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

sync_slot_statuses($pdo);
$summary   = get_slot_summary($pdo);
$car_avail = $summary['car']['avail'] ?? 0;
$car_total = $summary['car']['total'] ?? 0;
$car_occ   = $summary['car']['occupied'] ?? 0;
$car_res   = $summary['car']['reserved'] ?? 0;
$car_mnt   = $summary['car']['maintenance'] ?? 0;

$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;
$moto_occ   = $summary['motorcycle']['occupied'] ?? 0;
$moto_res   = $summary['motorcycle']['reserved'] ?? 0;
$moto_mnt   = $summary['motorcycle']['maintenance'] ?? 0;

$car_pct  = $car_total  > 0 ? round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? round(($moto_avail / $moto_total) * 100) : 100;

$active = ($summary['car']['occupied'] ?? 0) + ($summary['motorcycle']['occupied'] ?? 0);
$res_count = ($summary['car']['reserved'] ?? 0) + ($summary['motorcycle']['reserved'] ?? 0);

$mnt_count = $pdo->query("SELECT COUNT(*) FROM parking_slot WHERE status='maintenance'")->fetchColumn();

$today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();
$month_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND MONTH(check_out_time)=MONTH(CURDATE()) AND YEAR(check_out_time)=YEAR(CURDATE())")->fetchColumn();
$year_rev  = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND YEAR(check_out_time)=YEAR(CURDATE())")->fetchColumn();

$yesterday_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$last_month_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND MONTH(check_out_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(check_out_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn();
$last_year_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND YEAR(check_out_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))")->fetchColumn();

$all_time_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid'")->fetchColumn();

// Today vs Yesterday Revenue Logic
$rev_pct_today = calc_trend($today_rev, $yesterday_rev);
$is_rev_up = ($today_rev >= $yesterday_rev) && ($today_rev > 0);

// Peak Occupancy Logic
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
        SELECT r.reserved_from as t_time, ps.slot_type as vtype 
        FROM `reservation` r 
        JOIN `parking_slot` ps ON r.slot_id = ps.slot_id 
        WHERE DATE(r.reserved_from) = CURDATE() 
          AND r.status = 'confirmed' 
          AND ps.status = 'available'
    ) combined
    GROUP BY hr
    ORDER BY count DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$peak_time = $peak ? sprintf("%02d:00 - %02d:00", $peak['hr'], ($peak['hr']+1)%24) : "No traffic yet";
$peak_vol  = $peak ? ($peak['count'] ?? 0) : 0;
$peak_dom  = $peak ? (($peak['cars'] ?? 0) >= ($peak['motos'] ?? 0) ? ($peak['cars'] > 0 ? 'Cars' : 'N/A') : 'Motorcycles') : 'N/A';
$peak_pct  = ($peak && $peak_vol > 0) ? round(($peak['cars'] / $peak_vol) * 100) : 0;

// Capacity stats for Active Vehicles card (Includes Parked + Reserved)
$total_used = $active + $res_count;
$total_cap  = $car_total + $moto_total;
$occ_pct    = $total_cap > 0 ? round(($total_used / $total_cap) * 100) : 0;

// Trend Analysis (Last 7 Days vs Previous 7 Days)
$trends = $pdo->query("
    SELECT 
        SUM(CASE WHEN DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as curr_total,
        SUM(CASE WHEN DATE(check_in_time) BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY) THEN 1 ELSE 0 END) as prev_total,
        SUM(CASE WHEN DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND v.vehicle_type = 'car' THEN 1 ELSE 0 END) as curr_car,
        SUM(CASE WHEN DATE(check_in_time) BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY) AND v.vehicle_type = 'car' THEN 1 ELSE 0 END) as prev_car,
        SUM(CASE WHEN DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND v.vehicle_type = 'motorcycle' THEN 1 ELSE 0 END) as curr_moto,
        SUM(CASE WHEN DATE(check_in_time) BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY) AND v.vehicle_type = 'motorcycle' THEN 1 ELSE 0 END) as prev_moto
    FROM `transaction` t
    JOIN `vehicle` v ON t.vehicle_id = v.vehicle_id
")->fetch(PDO::FETCH_ASSOC);

function calc_trend($curr, $prev) {
    if ($prev <= 0) return $curr > 0 ? 100 : 0;
    $trend = (($curr - $prev) / $prev) * 100;
    return max(-100, min(100, $trend));
}

$trend_total = calc_trend($trends['curr_total'], $trends['prev_total']);
$trend_car   = calc_trend($trends['curr_car'], $trends['prev_car']);
$trend_moto  = calc_trend($trends['curr_moto'], $trends['prev_moto']);

// Average Duration Logic (Completed Transactions)
$avg_duration_min = $pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time IS NOT NULL
")->fetchColumn();

// This Month Average for trend
$this_month_avg_duration = $pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' 
    AND check_out_time IS NOT NULL
    AND MONTH(check_out_time) = MONTH(CURDATE())
    AND YEAR(check_out_time) = YEAR(CURDATE())
")->fetchColumn();

$avg_duration_h = floor($avg_duration_min / 60);
$avg_duration_m = round($avg_duration_min % 60);
$avg_duration_str = ($avg_duration_h > 0 ? $avg_duration_h . "h " : "") . $avg_duration_m . "m";

// Previous Month for comparison
$prev_avg_duration = $pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' 
    AND check_out_time IS NOT NULL
    AND MONTH(check_out_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(check_out_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetchColumn();

$duration_trend = calc_trend($this_month_avg_duration, $prev_avg_duration);

$page_title = 'Dashboard';
$page_subtitle = date('l, d F Y');

// Attendance logic
$on_duty = is_on_duty();
$staff_list = [];
if (!$on_duty) {
    $search_type = ($_SESSION['role'] === 'admin') ? 'admin' : 'operator';
    $stmt = $pdo->prepare("SELECT operator_id, full_name, shift FROM operator WHERE staff_type = ? ORDER BY full_name");
    $stmt->execute([$search_type]);
    $staff_list = $stmt->fetchAll();
}

include 'includes/header.php';
?>

    <div class="px-10 py-10">

        <!-- Alerts -->
        <?php 
        $show_car_warn = ($car_pct <= 20 && $car_total > 0);
        $show_moto_warn = ($moto_pct <= 20 && $moto_total > 0);
        if ($show_car_warn || $show_moto_warn): 
        ?>
        <div class="bento-card p-4 mb-6 relative overflow-hidden group">
            <div class="absolute -right-12 -top-12 w-24 h-24 bg-accent-glow rounded-full blur-2xl group-hover:bg-accent-glow transition-all"></div>
            <div class="flex items-center justify-between relative z-10">
                <!-- Warning Identity -->
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl status-badge-over flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-lg trend-down"></i>
                    </div>
                    <div>
                        <p class="font-manrope font-extrabold text-primary text-sm leading-tight">Capacity Warning</p>
                        <p class="font-inter text-tertiary text-[11px] mt-0.5">Sectors approaching full occupancy.</p>
                    </div>
                </div>

                <!-- Sector Details -->
                <div class="flex items-center gap-10">
                    <?php if ($show_car_warn): ?>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-car text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-manrope font-extrabold text-primary text-sm leading-none"><?= $car_avail ?> Slots</span>
                            <span class="text-[11px] font-inter text-tertiary mt-0.5">Car Sector</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_moto_warn): ?>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-motorcycle text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-manrope font-extrabold text-primary text-sm leading-none"><?= $moto_avail ?> Slots</span>
                            <span class="text-[11px] font-inter text-tertiary mt-0.5">Motorcycle Sector</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bento Grid -->
        <div class="grid grid-cols-12 gap-6 items-stretch">

            <div class="col-span-12 lg:col-span-4">
                <div class="bento-card p-4 h-full flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-car-side text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight">Active Vehicles</h3>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-end justify-between">
                        <div class="flex flex-col">
                            <span class="font-manrope font-semibold text-5xl text-primary tracking-tighter leading-none"><?= $total_used ?></span>
                            <span class="text-tertiary text-xs font-inter mt-1">Vehicles Parked</span>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-manrope font-bold text-primary leading-none"><?= $occ_pct ?>%</span>
                            <p class="text-tertiary text-xs font-inter mt-1">Occupancy</p>
                        </div>
                    </div>

                    <div class="w-full mt-auto pt-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="status-badge status-badge-available">
                                Available: <?= $car_avail + $moto_avail ?>
                            </span>
                            <span class="status-badge status-badge-reserved">
                                Reserved: <?= $res_count ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="bento-card p-4 h-full flex flex-col relative overflow-hidden group">
                    <!-- Premium Background Accent (Subtle) -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-accent-glow rounded-full blur-3xl group-hover-bg-accent-glow transition-all"></div>
                    
                    <div class="flex items-center gap-4 relative z-10 mb-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-wallet text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Today Revenue</h3>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-end relative z-10">
                        <div class="flex items-end gap-3 mb-4">
                            <span class="font-manrope font-semibold text-5xl text-primary leading-none tracking-tight">
                                <?= fmt_idr((float)$today_rev) ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1.5 text-xs font-bold <?= $rev_pct_today >= 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fa-solid <?= $rev_pct_today >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= number_format(abs($rev_pct_today), 1) ?>%
                            </span>
                            <span class="text-tertiary text-xs font-inter">Vs Yesterday</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <!-- Car Slots -->
                <div class="bento-card p-4 flex items-center gap-4 flex-1">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-car text-lg"></i>
                    </div>
                    <div class="flex flex-col min-w-0 flex-1">
                        <span class="text-2xl font-manrope font-semibold text-primary leading-none mb-1"><?= $car_avail ?></span>
                        <span class="text-[13px] font-inter text-tertiary truncate">Car Slot Available</span>
                        <div class="mt-3">
                            <div class="w-full h-2 progress-track rounded-full overflow-hidden">
                                <div class="h-full progress-fill animate-growth rounded-full" style="width: <?= $car_pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motorcycle Slots -->
                <div class="bento-card p-4 flex items-center gap-4 flex-1">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-motorcycle text-lg"></i>
                    </div>
                    <div class="flex flex-col min-w-0 flex-1">
                        <span class="text-2xl font-manrope font-semibold text-primary leading-none mb-1"><?= $moto_avail ?></span>
                        <span class="text-[13px] font-inter text-tertiary truncate">Motorcycle Slot Available</span>
                        <div class="mt-3">
                            <div class="w-full h-2 progress-track rounded-full overflow-hidden">
                                <div class="h-full progress-fill animate-growth rounded-full" style="width: <?= $moto_pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Column -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6 h-full">
                <!-- 1: Peak Time Analytics -->
                <div class="bento-card p-4 flex flex-col flex-1">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-fire text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Today Peak Trend</h3>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-center">
                        <div class="flex items-end gap-3 mb-4">
                            <span class="font-manrope font-semibold text-2xl text-primary leading-none tracking-tight"><?= $peak_time ?></span>
                            <span class="text-tertiary text-xs font-inter pb-0.5">Peak Time</span>
                        </div>

                        <div class="flex items-center justify-between mt-2 pt-2 text-xs font-inter text-tertiary">
                            <span class="whitespace-nowrap">Volume: <span class="text-primary font-medium"><?= $peak_vol ?> Vehicles</span></span>
                            <span class="whitespace-nowrap text-right">Dominant: <span class="text-primary font-medium"><?= $peak_dom ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- 2: IoT System Health Monitor (Swapped to Row 2) -->
                <div class="bento-card p-4 flex flex-col flex-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-video text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight">CCTV Check</h3>
                            </div>
                        </div>
                        <div class="status-badge status-badge-over gap-2">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                            </span>
                            <span class="text-primary">Live</span>
                        </div>
                    </div>

                    <!-- Camera Selection Toggle -->
                    <div class="segmented-toggle mb-4">
                        <div id="cam-slider" class="segmented-toggle-slider pointer-events-none"></div>
                        <button onclick="switchCam('entry')" id="btn-cam-entry" class="segmented-toggle-btn text-white">
                            Entry
                        </button>
                        <button onclick="switchCam('exit')" id="btn-cam-exit" class="segmented-toggle-btn text-secondary">
                            Exit
                        </button>
                    </div>

                    <!-- Fake CCTV Stream -->
                    <div class="cctv-frame relative w-full aspect-video rounded-3xl overflow-hidden group/cctv">
                        <img id="cctv-img" src="assets/img/entry_gate.jpg" class="w-full h-full object-cover opacity-60 grayscale-[20%] transition-opacity duration-500">
                        <div class="absolute inset-0 p-3 flex flex-col justify-between pointer-events-none">
                            <div class="flex justify-between items-start">
                                <span id="cctv-label" class="text-[8px] font-mono text-white/80 bg-slate-900/40 px-1.5 py-0.5 rounded leading-none uppercase tracking-widest">CAM_01_ENTRY</span>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-transparent h-4 w-full animate-scan pointer-events-none opacity-20"></div>
                    </div>
                </div>
            </div>

            <!-- 2: Parking Intensity Chart (Swapped to Row 2) -->
            <div class="col-span-12 lg:col-span-8">
                <div class="bento-card p-4 flex flex-col h-full overflow-hidden transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-chart-line text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight">Parking Intensity</h3>
                            </div>
                        </div>
                        <div class="flex items-center px-2.5 py-1 bg-surface-alt border border-color rounded-full shrink-0">
                            <span class="text-[10px] font-bold text-primary uppercase tracking-widest">Today</span>
                        </div>
                    </div>
 
                    <div class="relative flex-1 h-0 min-h-[300px] min-w-0">
                        <canvas id="trafficChart" class="w-full h-full"></canvas>
                    </div>
 
                    <div class="mt-auto pt-4 flex items-center justify-between">
                        <div class="flex gap-8">
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-3 h-3 rounded-full traffic-dot-car"></div>
                                <span class="text-xs font-inter text-tertiary">Cars</span>
                            </div>
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-3 h-3 rounded-full traffic-dot-moto"></div>
                                <span class="text-xs font-inter text-tertiary">Motorcycles</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- 2: Recent Activity Log (Swapped to Row 3) -->
            <?php
                // Merged query for both active/completed transactions and upcoming/active reservations
                $recent_logs = $pdo->query("
                    (SELECT 
                        'transaction' as log_type,
                        t.transaction_id as id,
                        t.check_in_time as entry_time,
                        t.check_out_time as exit_time,
                        v.plate_number,
                        v.vehicle_type,
                        t.ticket_code as code,
                        COALESCE(t.total_fee, 
                            pr.first_hour_rate + (GREATEST(0, CEIL(TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) / 60) - 1) * pr.next_hour_rate)
                        ) as total_fee,
                        t.is_lost_ticket,
                        t.is_force_checkout,
                        NULL as status
                     FROM `transaction` t
                     JOIN vehicle v ON t.vehicle_id = v.vehicle_id
                     JOIN parking_rate pr ON t.rate_id = pr.rate_id
                    )
                    UNION ALL
                    (SELECT 
                        'reservation' as log_type,
                        r.reservation_id as id,
                        r.reserved_from as entry_time,
                        r.reserved_until as exit_time,
                        v.plate_number,
                        v.vehicle_type,
                        r.reservation_code as code,
                        0 as total_fee,
                        0 as is_lost_ticket,
                        0 as is_force_checkout,
                        r.status as status
                     FROM reservation r
                     JOIN vehicle v ON r.vehicle_id = v.vehicle_id
                     WHERE r.status IN ('pending', 'confirmed', 'used')
                       AND NOT EXISTS (SELECT 1 FROM `transaction` t WHERE t.reservation_id = r.reservation_id)
                    )
                    ORDER BY entry_time DESC 
                    LIMIT 7
                ")->fetchAll();
            ?>
            <div class="col-span-12 lg:col-span-8 bento-card py-4 flex flex-col self-start transition-all duration-300">
                <div class="flex items-center justify-between mb-4 px-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Recent Activity Log</h3>
                        </div>
                    </div>
                    <a href="modules/operations/scan_log.php" class="text-xs font-inter text-tertiary hover:text-primary transition-colors">View All</a>
                </div>

                <?php if (empty($recent_logs)): ?>
                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                        <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 opacity-20"></i>
                        <p class="text-sm font-inter">No recent activity detected.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full font-inter border-collapse table-fixed activity-table">
                            <thead>
                                <tr class="border-b border-color">
                                    <th class="py-3 pl-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left">Vehicle</th>
                                    <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Plate/Ticket</th>
                                    <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Entry</th>
                                    <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Exit</th>
                                    <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Fee</th>
                                    <th class="py-3 pr-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-color">
                                <?php foreach ($recent_logs as $log): ?>
                                <tr class="group hover:bg-surface-alt/50 transition-colors">
                                    <td class="py-2 pl-4 text-left align-middle">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                                <i class="fa-solid <?= $log['vehicle_type'] === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 text-center align-middle">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="text-sm font-manrope font-semibold text-primary leading-none mb-1">
                                                <?= $log['plate_number'] ?: '------' ?>
                                            </span>
                                            <span class="text-[10px] font-inter text-tertiary leading-none">
                                                <?= $log['code'] ?: 'PENDING' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-sm font-manrope font-semibold text-primary">
                                                <?= date('H:i', strtotime($log['entry_time'])) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-sm font-manrope font-semibold text-primary">
                                                <?= ($log['log_type'] === 'reservation') ? '--:--' : ($log['exit_time'] ? date('H:i', strtotime($log['exit_time'])) : '--:--') ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-sm font-manrope font-semibold text-primary">
                                                <?= $log['total_fee'] !== null ? fmt_idr((float)$log['total_fee']) : 'Rp 0' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 pr-4 text-right align-middle">
                                        <div class="flex justify-end items-center">
                                            <?php if ($log['log_type'] === 'reservation'): ?>
                                                <?php if ($log['status'] === 'used'): ?>
                                                    <span class="status-badge status-badge-parked">Inside</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-badge-reserved">Reserved</span>
                                                <?php endif; ?>
                                            <?php elseif ($log['is_lost_ticket']): ?>
                                                <span class="status-badge status-badge-issue">Lost Ticket</span>
                                            <?php elseif ($log['is_force_checkout']): ?>
                                                <span class="status-badge status-badge-issue">Forced Exit</span>
                                            <?php elseif (!$log['exit_time']): ?>
                                                <span class="status-badge status-badge-parked">Parked</span>
                                            <?php else: ?>
                                                <span class="status-badge status-badge-departed">Departed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Operations & System Health (Right Side) -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <!-- Active Duty - Dynamic List -->
                <?php
                    $active_staff = get_active_attendance($pdo);
                    $display_staff = $active_staff;
                ?>
                <div class="bento-card p-4 flex flex-col h-[230px] transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-user-shield text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight">Active Duty</h3>
                            </div>
                        </div>
                    </div>


                    <div class="space-y-2 flex-grow overflow-y-auto custom-scrollbar pr-1">
                        <?php if (empty($display_staff)): ?>
                            <div class="flex flex-col items-center justify-center py-10 text-tertiary">
                                <i class="fa-solid fa-user-slash text-3xl mb-3 opacity-20"></i>
                                <p class="text-xs font-inter">No personnel on duty.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($display_staff as $st): ?>
                            <div class="flex items-center justify-between p-2.5 bg-page rounded-2xl border border-color group transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <div class="w-9 h-9 rounded-full bg-brand flex items-center justify-center text-white text-[10px] font-bold font-manrope">
                                            <?= strtoupper(substr($st['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="absolute bottom-0 right-0 w-2.5 h-2.5 status-dot-available status-dot-ring rounded-full translate-x-[-0.5px] translate-y-[-0.5px]"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-primary truncate"><?= htmlspecialchars($st['full_name']) ?></p>
                                        <p class="text-xs font-inter text-tertiary"><?= htmlspecialchars($st['shift'] ?? 'Duty') ?> Shift</p>
                                    </div>
                                </div>
                                <div class="text-right whitespace-nowrap">
                                    <p class="text-xs font-inter text-secondary">IN: <?= date('H:i', strtotime($st['check_in_time'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>


                </div>

                <!-- 2: Average Duration Card (Swapped to Row 3) -->
                <div class="bento-card p-4 flex flex-col flex-1 transition-all duration-300">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center">
                            <i class="fa-solid fa-clock text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Average Duration</h3>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-end">
                        <div class="flex items-end gap-3 mb-4">
                            <span class="font-manrope font-semibold text-5xl text-primary leading-none tracking-tight"><?= $avg_duration_str ?></span>
                            <span class="text-tertiary text-xs font-inter pb-1">Per Session</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 text-xs font-bold <?= $duration_trend >= 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fa-solid <?= $duration_trend >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= abs(round($duration_trend, 1)) ?>%
                            </div>
                            <p class="text-xs font-inter text-tertiary">Vs Last Month</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

<!-- Attendance Modal -->
<?php if (!$on_duty): ?>
<div id="attendanceOverlay" class="fixed inset-0 z-[100] backdrop-blur-xl bg-slate-900/20 flex items-center justify-center">
    <div class="modal-surface rounded-3xl shadow-2xl p-10 w-full max-w-sm mx-4 border animate-in fade-in zoom-in duration-300">
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-xl" style="background-color: var(--brand); color: var(--surface); box-shadow: 0 10px 25px var(--shadow-color);">
                <i class="fa-solid fa-user-check text-3xl"></i>
            </div>
            <h2 class="font-manrope font-extrabold text-2xl text-primary mb-2">Duty Check-in</h2>
            <p class="text-tertiary text-sm font-inter">Identify yourself to access the console.</p>
        </div>

        <form id="attendanceForm" class="space-y-6">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-tertiary font-inter mb-3">Personnel Profile</label>
                <div class="relative">
                    <select name="staff_id" required
                            class="w-full modal-input border-2 rounded-xl px-5 py-4 text-sm focus:outline-none transition-all font-inter font-bold appearance-none">
                        <option value="">Select Profile</option>
                        <?php foreach ($staff_list as $s): ?>
                            <option value="<?= $s['operator_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> — <?= $s['shift'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>
            <button type="submit" id="attendBtn"
                    class="w-full btn-primary font-semibold font-inter rounded-xl text-sm py-4 transition-all hover:-translate-y-0.5 active:translate-y-0">
                Establish Connection
            </button>
        </form>

        <div id="attendMsg" class="mt-4 hidden"></div>
    </div>
</div>

<script>
document.getElementById('attendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = document.getElementById('attendBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    fetch('api/submit_attendance.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('attendanceOverlay').remove();
                location.reload();
            } else {
                const msg = document.getElementById('attendMsg');
                msg.className = 'mt-4 flex items-center gap-2 text-red-600 text-sm font-inter';
                msg.innerHTML = '<i class="fa-solid fa-circle-exclamation text-base"></i>' + data.message;
                msg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Start Duty →';
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'Start Duty →'; });
});
</script>
<?php endif; ?>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let trafficChart = null;

function initChart(data) {
    const ctx = document.getElementById('trafficChart').getContext('2d');
    const rootStyles = getComputedStyle(document.documentElement);
    const textColor = rootStyles.getPropertyValue('--text-primary').trim();
    const secondaryColor = rootStyles.getPropertyValue('--text-secondary').trim();
    const borderColor = rootStyles.getPropertyValue('--border-color').trim();
    const surfaceColor = rootStyles.getPropertyValue('--surface').trim();
    const brandColor = rootStyles.getPropertyValue('--brand').trim();
    // Cleanup if exists
    if (trafficChart) {
        trafficChart.destroy();
    }

    trafficChart = new Chart(ctx, {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: { display: false },
                    tooltip: {
                        backgroundColor: surfaceColor,
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: borderColor,
                        borderWidth: 1,
                        titleFont: { family: 'Inter', weight: 'bold', size: 12 },
                        bodyFont: { family: 'Inter', size: 11 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        usePointStyle: true,
                        boxPadding: 8, // Wider space between circle and text
                        callbacks: {
                            labelColor: function(context) {
                                return {
                                    borderColor: surfaceColor, // Match legend ring
                                    backgroundColor: context.dataset.backgroundColor,
                                    borderWidth: 2
                                };
                            }
                        }
                    }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    border: {
                        display: true,
                        color: borderColor,
                        width: 1
                    },
                    ticks: {
                        font: { family: 'Inter', size: 10, weight: '500' },
                        color: secondaryColor
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { 
                        display: true,
                        color: borderColor,
                        drawBorder: false,
                        borderDash: [5, 5]
                    },
                    border: { display: false },
                    ticks: {
                        font: { family: 'Inter', size: 10 },
                        color: secondaryColor,
                        padding: 8,
                        precision: 0
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            },
            animations: {
                y: {
                    from: (ctx) => ctx.chart.scales.y.getPixelForValue(0),
                    delay: (ctx) => {
                        if (ctx.type !== 'data') return 0;
                        return (ctx.dataIndex * 30) + (ctx.datasetIndex * 800);
                    }
                },
                base: {
                    from: (ctx) => ctx.chart.scales.y.getPixelForValue(0),
                    delay: (ctx) => {
                        if (ctx.type !== 'data') return 0;
                        return (ctx.dataIndex * 30) + (ctx.datasetIndex * 800);
                    }
                }
            }
        }
    });
}

    // Global listener for theme toggle to refresh charts
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        // Small timeout to allow CSS variables to update in the DOM
        setTimeout(() => {
            const rootStyles = getComputedStyle(document.documentElement);
            const textColor = rootStyles.getPropertyValue('--text-primary').trim();
            const secondaryColor = rootStyles.getPropertyValue('--text-secondary').trim();
            const borderColor = rootStyles.getPropertyValue('--border-color').trim();
            const surfaceColor = rootStyles.getPropertyValue('--surface').trim();

            if (trafficChart) {
                // Update options without destroying/re-fetching
                trafficChart.options.scales.x.ticks.color = secondaryColor;
                trafficChart.options.scales.x.border.color = borderColor;
                trafficChart.options.scales.y.ticks.color = secondaryColor;
                trafficChart.options.scales.y.grid.color = borderColor;
                
                trafficChart.options.plugins.tooltip.backgroundColor = surfaceColor;
                trafficChart.options.plugins.tooltip.titleColor = textColor;
                trafficChart.options.plugins.tooltip.bodyColor = textColor;
                trafficChart.options.plugins.tooltip.borderColor = borderColor;
                
                trafficChart.update('none'); // Update without animation
            }
            
            if (window.statusDoughnut) {
                const c_surface = rootStyles.getPropertyValue('--surface').trim() || '#ffffff';
                const c_text = rootStyles.getPropertyValue('--text-primary').trim() || '#0f172a';
                const c_border = rootStyles.getPropertyValue('--border-color').trim() || 'rgba(0,0,0,0.1)';
                
                window.statusDoughnut.options.plugins.tooltip.backgroundColor = c_surface;
                window.statusDoughnut.options.plugins.tooltip.titleColor = c_text;
                window.statusDoughnut.options.plugins.tooltip.bodyColor = c_text;
                window.statusDoughnut.options.plugins.tooltip.borderColor = c_border;
                
                // Update dataset border for dark/light mode surface parity
                window.statusDoughnut.data.datasets[0].borderColor = c_surface;
                window.statusDoughnut.data.datasets[0].hoverBorderColor = c_surface;
                
                window.statusDoughnut.update('none');
            } else {
                initStatusDoughnut();
            }
        }, 100);
    });

function updateChart(range) {
    // Fetch Data
    fetch(`api/get_traffic_data.php?range=${range}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            if (!trafficChart) {
                initChart(data);
            } else {
                trafficChart.data.labels = data.labels;
                trafficChart.data.datasets = data.datasets;
                trafficChart.update();
            }
        })
        .catch(err => console.error('Traffic Chart Error:', err));
}

// Camera Switching Logic
function switchCam(type) {
    const img = document.getElementById('cctv-img');
    const label = document.getElementById('cctv-label');
    const btnEntry = document.getElementById('btn-cam-entry');
    const btnExit = document.getElementById('btn-cam-exit');

    img.style.opacity = '0.3';

    setTimeout(() => {
        const slider = document.getElementById('cam-slider');
        
        if (type === 'entry') {
            img.src = 'assets/img/entry_gate.jpg';
            label.textContent = 'CAM_01_ENTRY';
            
            slider.style.transform = 'translateX(0)';
            btnEntry.classList.replace('text-secondary', 'text-white');
            btnExit.classList.replace('text-white', 'text-secondary');
        } else {
            img.src = 'assets/img/exit_gate.jpg';
            label.textContent = 'CAM_02_EXIT';
            
            slider.style.transform = 'translateX(100%)';
            btnExit.classList.replace('text-secondary', 'text-white');
            btnEntry.classList.replace('text-white', 'text-secondary');
        }
        img.style.opacity = '0.6';
    }, 200);
}

function initStatusDoughnut() {
    // Register custom positioner to avoid center overlap
    Chart.Tooltip.positioners.outside = function(items) {
        const pos = Chart.Tooltip.positioners.average(items);
        if (pos === false) return false;
        const chart = this.chart;
        const centerX = chart.chartArea.left + chart.chartArea.width / 2;
        const centerY = chart.chartArea.top + chart.chartArea.height / 2;
        const dx = pos.x - centerX;
        const dy = pos.y - centerY;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const radius = chart.outerRadius || 60;
        const offset = 20; // Distance from outer edge
        return {
            x: centerX + (dx / dist) * (radius + offset),
            y: centerY + (dy / dist) * (radius + offset)
        };
    };

    const rootStyles = getComputedStyle(document.documentElement);
    const c_parked = rootStyles.getPropertyValue('--status-parked-bg').trim() || '#0f172a';
    const c_reserved = rootStyles.getPropertyValue('--status-reserved-bg').trim() || '#CAC7D1';
    const c_free = rootStyles.getPropertyValue('--status-available-bg').trim() || '#10b981';
    const c_maint = rootStyles.getPropertyValue('--status-maintenance-bg').trim() || '#f59e0b';
    const c_surface = rootStyles.getPropertyValue('--surface').trim() || '#ffffff';
    const c_text = rootStyles.getPropertyValue('--text-primary').trim() || '#0f172a';
    const c_border = rootStyles.getPropertyValue('--border-color').trim() || 'rgba(0,0,0,0.1)';

    const ctx = document.getElementById('activeStatusDoughnut').getContext('2d');
    if (window.statusDoughnut) window.statusDoughnut.destroy();
    window.statusDoughnut = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Parked', 'Reserved', 'Free', 'Maint'],
            datasets: [{
                data: [<?= $active ?>, <?= $res_count ?>, <?= $car_avail + $moto_avail ?>, <?= $mnt_count ?>],
                backgroundColor: [c_parked, c_reserved, c_free, c_maint],
                borderWidth: 2,
                borderColor: c_surface,
                hoverBorderWidth: 2,
                hoverBorderColor: c_surface,
                borderRadius: 10,
                spacing: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '72%',
            maintainAspectRatio: false,
            layout: {
                padding: 6
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c_surface,
                    titleColor: c_text,
                    bodyColor: c_text,
                    borderColor: c_border,
                    borderWidth: 1,
                    titleAlign: 'center',
                    bodyAlign: 'center',
                    titleFont: { size: 10, weight: 'bold' },
                    bodyFont: { size: 12, weight: '700', lineHeight: 1.1 },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: true,
                    boxWidth: 10,
                    boxHeight: 10,
                    boxPadding: 3,
                    usePointStyle: true,
                    callbacks: {
                        labelPointStyle: function(context) {
                            const color = context.dataset.backgroundColor[context.dataIndex];
                            const dpr = window.devicePixelRatio || 1;
                            const canvas = document.createElement('canvas');
                            const size = 14;
                            canvas.width = size * dpr;
                            canvas.height = size * dpr;
                            const ctx = canvas.getContext('2d');
                            ctx.scale(dpr, dpr);
                            
                            ctx.fillStyle = color;
                            ctx.beginPath();
                            ctx.arc(7, 7, 5, 0, Math.PI * 2);
                            ctx.fill();
                            return {
                                pointStyle: canvas,
                                rotation: 0
                            };
                        }
                    },
                    // Force tooltip to the outside
                    position: 'outside',
                    caretPadding: 12,
                    caretSize: 0,
                }
            },
            devicePixelRatio: window.devicePixelRatio,
            hover: {
                mode: 'nearest',
                intersect: true
            }
        },
        plugins: [{
            id: 'centerText',
            afterDraw: (chart) => {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta.data[0]) return;
                
                const centerX = meta.data[0].x;
                const centerY = meta.data[0].y;

                ctx.save();
                
                // Draw Percentage
                const pctText = '<?= $occ_pct ?>%';
                ctx.font = '600 22px Inter';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#353134';
                ctx.fillStyle = textColor;
                ctx.fillText(pctText, centerX, centerY - 6);

                // Draw Label
                ctx.font = '400 11px Inter';
                const labelColor = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#CAC7D1';
                ctx.fillStyle = labelColor; 
                ctx.fillText('Occupied', centerX, centerY + 12);
                
                ctx.restore();
            }
        }]
    });
}

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    updateChart('today');
    initStatusDoughnut();
});
</script>

<?php include 'includes/footer.php'; ?>
