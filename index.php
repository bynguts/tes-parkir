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

// Today vs Last 7 Days Average Logic
$avg_7d_rev = $pdo->query("
    SELECT COALESCE(SUM(total_fee)/7, 0) 
    FROM `transaction` 
    WHERE payment_status='paid' 
    AND DATE(check_out_time) BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
")->fetchColumn();

$rev_diff_7d = $today_rev - $avg_7d_rev;
$rev_pct_7d = 0;
if ($avg_7d_rev > 0) {
    $rev_pct_7d = ($rev_diff_7d / $avg_7d_rev) * 100;
} elseif ($today_rev > 0) {
    $rev_pct_7d = 100;
}
$is_rev_up_7d = ($today_rev >= $avg_7d_rev) && ($today_rev > 0);

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
    return (($curr - $prev) / $prev) * 100;
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

$avg_duration_h = floor($avg_duration_min / 60);
$avg_duration_m = round($avg_duration_min % 60);
$avg_duration_str = ($avg_duration_h > 0 ? $avg_duration_h . "h " : "") . $avg_duration_m . "m";

// Previous Month for comparison (simplified)
$prev_avg_duration = $pdo->query("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
    FROM `transaction` 
    WHERE payment_status='paid' 
    AND check_out_time IS NOT NULL
    AND MONTH(check_out_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetchColumn();

$duration_trend = calc_trend($avg_duration_min, $prev_avg_duration);

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

<style id="dashboard-custom-theme">
    /* 1. Dashboard Content Background */
    main.pl-64 {
        background-color: #F5F4F6 !important;
    }

    /* 2. Top Bar Sticky Header Override */
    header.sticky {
        background-color: rgba(245, 244, 246, 0.9) !important;
        border-bottom: 1px solid #CAC7D1 !important;
        backdrop-filter: blur(12px);
    }

    /* 3. Card Elements Styling */
    .bg-white {
        background-color: #FFFFFF !important;
        border: 1px solid #CAC7D1 !important;
        box-shadow: 0 4px 20px -2px rgba(53, 49, 52, 0.05) !important;
    }

    /* Remove the default Tailwind ring shadows that might clash */
    .ring-1, .ring-slate-900\/5 {
        box-shadow: none !important;
        border: 1px solid #CAC7D1 !important;
    }

    /* 4. Text and Primary Dark Overrides */
    .text-slate-900, 
    .font-manrope.text-slate-900,
    .font-inter.text-slate-900 {
        color: #353134 !important;
    }

    /* Muted and Secondary Text */
    .text-slate-900\/40, 
    .text-slate-900\/30, 
    .text-slate-900\/60,
    .text-slate-900\/70 {
        color: #353134 !important;
        opacity: 0.55 !important;
    }

    /* 5. Icon Backgrounds and Accents */
    .bg-slate-900\/5, 
    .bg-slate-100 {
        background-color: rgba(202, 199, 209, 0.25) !important;
        border-color: #CAC7D1 !important;
    }

    /* Icon Colors */
    .fa-car-side, .fa-car, .fa-motorcycle, .fa-fire, .fa-clock, .fa-user-shield, .fa-magnifying-glass, .fa-file-export, .fa-circle-question, .fa-bell {
        color: #353134 !important;
    }

    /* 6. Special Handling for Dark Today Revenue Card */
    /* Using the exact black (#0f172a) from Today Revenue for highlighted components */
    .bg-slate-900 {
        background-color: #0f172a !important;
        border: none !important;
    }
    
    .bg-slate-900 .text-white {
        color: #FFFFFF !important;
        opacity: 1 !important;
    }
    
    .bg-slate-900 .text-white\/30, 
    .bg-slate-900 .text-white\/40 {
        color: #FFFFFF !important;
        opacity: 0.4 !important;
    }

    /* 7. Progress Bars and Charts */
    .bg-slate-900\/5.rounded-full {
        background-color: #CAC7D1 !important;
    }
    .h-full.bg-slate-900 {
        background-color: #353134 !important; /* Progress fill uses the theme's charcoal */
    }

    /* 8. Table Overrides */
    thead tr.text-slate-900\/40 {
        border-bottom-color: #CAC7D1 !important;
        color: #353134 !important;
        opacity: 0.7 !important;
    }
    
    tbody tr.group:hover {
        background-color: rgba(202, 199, 209, 0.1) !important;
    }
    
    tbody.divide-y > tr {
        border-top-color: #CAC7D1 !important;
    }

    /* 9. Top Bar Input & Buttons */
    header.sticky input {
        background-color: #FFFFFF !important;
        border: 1px solid #CAC7D1 !important;
        color: #353134 !important;
    }
    header.sticky button {
        background-color: #FFFFFF !important;
        border: 1px solid #CAC7D1 !important;
        color: #353134 !important;
    }
    
    /* 10. List Items Hover */
    .bg-slate-900\/5.rounded-2xl {
        background-color: rgba(202, 199, 209, 0.15) !important;
        border: 1px solid #CAC7D1 !important;
    }
</style>

    <div class="px-10 py-10">

        <!-- Alerts -->
        <?php if ($car_pct <= 20 && $car_total > 0): ?>
        <div class="flex items-center gap-4 bg-red-50/10 border border-red-500/20 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
            <div>
                <p class="font-inter font-semibold text-red-700 text-sm">Car Capacity Almost Full!</p>
                <p class="font-inter text-red-500 text-xs">Only <?= $car_avail ?> of <?= $car_total ?> slots available.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
        <div class="flex items-center gap-4 bg-amber-50/10 border border-amber-500/20 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
            <div>
                <p class="font-inter font-semibold text-amber-700 text-sm">Motorcycle Capacity Almost Full!</p>
                <p class="font-inter text-amber-500 text-xs">Only <?= $moto_avail ?> of <?= $moto_total ?> slots available.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bento Grid -->
        <div class="grid grid-cols-12 gap-6 items-stretch">

            <!-- 1: Active Vehicles (Condensed for Symmetry) -->
            <div class="col-span-12 lg:col-span-4">
                <div class="bg-white rounded-3xl p-4 ring-1 ring-slate-900/5 transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/50 group h-[190px] flex flex-col justify-between shadow-[0_8px_30px_rgba(15,23,42,0.04)] relative overflow-hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                                <i class="fa-solid fa-car-side text-slate-900/30 text-lg group-hover:text-white transition-all"></i>
                            </div>
                            <div>
                                <h3 class="font-manrope font-semibold text-base text-slate-900 leading-tight">Active Vehicles</h3>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-end justify-between">
                        <div class="flex flex-col">
                            <span class="font-manrope font-semibold text-5xl text-slate-900 tracking-tighter leading-none"><?= $total_used ?></span>
                            <span class="text-slate-900/40 text-[11px] font-medium mt-1 uppercase tracking-wider">Vehicles Parked</span>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-manrope font-bold text-slate-900 leading-none"><?= $occ_pct ?>%</span>
                            <p class="text-[10px] font-medium text-slate-900/40 uppercase tracking-widest mt-1">Occupancy</p>
                        </div>
                    </div>

                    <div class="w-full mt-auto pt-4 border-t border-slate-900/5">
                        <div class="flex items-center justify-between text-[10px] font-extrabold tracking-widest uppercase">
                            <span class="text-slate-900/40">Reserved: <span class="text-slate-900"><?= $res_count ?></span></span>
                            <span class="text-emerald-600/70 px-4 border-x border-slate-100">Free: <span class="text-emerald-600"><?= $car_avail + $moto_avail ?></span></span>
                            <span class="text-amber-600/70">Maintenance: <span class="text-amber-600"><?= $mnt_count ?></span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2: Total Revenue (Condensed for Symmetry) -->
            <div class="col-span-12 lg:col-span-4">
                <div class="bg-slate-900 rounded-3xl p-4 relative overflow-hidden group hover:-translate-y-1 transition-all duration-300 shadow-xl shadow-slate-900/20 h-[190px] flex flex-col justify-between">
                    <!-- Premium Background Accent -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-violet-600/10 rounded-full blur-3xl group-hover:bg-violet-600/20 transition-all"></div>
                    
                    <div class="flex items-center gap-4 relative z-10 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center border border-white/10 group-hover:bg-white group-hover:text-slate-900 transition-all shrink-0">
                            <i class="fa-solid fa-wallet text-white/30 text-lg group-hover:text-inherit transition-all"></i>
                        </div>
                        <div>
                            <h3 class="font-manrope font-extrabold text-lg text-white leading-tight">Today Revenue</h3>
                        </div>
                    </div>

                    <div class="relative z-10">
                        <div class="font-manrope font-semibold text-3xl text-white leading-none tracking-tight mb-3">
                            <?= fmt_idr((float)$today_rev) ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2.5 py-1 <?= $is_rev_up_7d ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/20' : 'bg-red-500/20 text-red-400 border-red-500/20' ?> text-[10px] font-bold rounded-lg border flex items-center gap-1.5 shadow-sm backdrop-blur-md">
                                <i class="fa-solid <?= $is_rev_up_7d ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= number_format(abs($rev_pct_7d), 1) ?>%
                            </span>
                            <span class="text-white/40 text-[10px] font-medium uppercase tracking-wider">Vs 7d Average</span>
                        </div>
                    </div>

                    <div class="relative z-10 flex items-center justify-between border-t border-white/5 pt-4">
                        <span class="text-[10px] font-extrabold text-white/40 uppercase tracking-widest">Projected</span>
                        <span class="text-[10px] font-extrabold text-white"><?= fmt_idr((float)$today_rev * 1.2) ?></span>
                    </div>
                </div>
            </div>

            <!-- 3: Right Column Stack (Car & Moto) -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-4 h-[190px]">
                <!-- Car Slots -->
                <div class="bg-white rounded-3xl p-4 flex items-center gap-4 transition-all duration-300 ring-1 ring-slate-900/5 group hover:-translate-y-1 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex-1">
                    <div class="w-12 h-12 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-car text-slate-900/30 text-lg group-hover:text-white transition-all"></i>
                    </div>
                    <div class="flex flex-col min-w-0 flex-1">
                        <span class="text-2xl font-manrope font-semibold text-slate-900 leading-none mb-1"><?= $car_avail ?></span>
                        <span class="text-[11px] font-medium text-slate-900/40 font-inter truncate">Car Slots Available</span>
                        <div class="mt-3">
                            <div class="w-full h-2 bg-slate-900/5 rounded-full overflow-hidden">
                                <div class="h-full bg-slate-900 rounded-full" style="width: <?= $car_pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motorcycle Slots -->
                <div class="bg-white rounded-3xl p-4 flex items-center gap-4 transition-all duration-300 ring-1 ring-slate-900/5 group hover:-translate-y-1 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex-1">
                    <div class="w-12 h-12 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-motorcycle text-slate-900/30 text-lg group-hover:text-white transition-all"></i>
                    </div>
                    <div class="flex flex-col min-w-0 flex-1">
                        <span class="text-2xl font-manrope font-semibold text-slate-900 leading-none mb-1"><?= $moto_avail ?></span>
                        <span class="text-[11px] font-medium text-slate-900/40 font-inter truncate">Motorcycle Slots Available</span>
                        <div class="mt-3">
                            <div class="w-full h-2 bg-slate-900/5 rounded-full overflow-hidden">
                                <div class="h-full bg-slate-900 rounded-full" style="width: <?= $moto_pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Column -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-3 h-full">
                <!-- 1: Peak Time Analytics -->
                <div class="bg-white p-4 rounded-3xl ring-1 ring-slate-900/5 flex flex-col flex-1 transition-all duration-300 group hover:-translate-y-1 shadow-[0_8px_30px_rgba(15,23,42,0.04)]">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                            <i class="fa-solid fa-fire text-slate-900/40 text-lg group-hover:text-white transition-all"></i>
                        </div>
                        <div>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Today Peak Trend</h3>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-center">
                        <div class="flex items-end gap-3 mb-4">
                            <span class="font-manrope font-semibold text-2xl text-slate-900 leading-none tracking-tight"><?= $peak_time ?></span>
                            <span class="text-slate-900/40 text-[11px] font-medium tracking-wider pb-0.5">Peak Time</span>
                        </div>

                        <div class="flex items-center gap-6 mt-2 pt-4 border-t border-slate-900/5 text-[10px] font-extrabold tracking-widest uppercase">
                            <span class="text-slate-900/40 whitespace-nowrap">Max Volume: <span class="text-slate-900"><?= $peak_vol ?> Vehicles</span></span>
                            <span class="text-slate-900/40 whitespace-nowrap border-l border-slate-100 pl-6">Dominant: <span class="text-slate-900"><?= $peak_dom ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- 2: IoT System Health Monitor (Swapped to Row 2) -->
                <div class="bg-white rounded-3xl p-4 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col flex-1 transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                                <i class="fa-solid fa-video text-slate-900/40 text-lg group-hover:text-white transition-all"></i>
                            </div>
                            <div>
                                <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">CCTV Check</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 px-3 py-1 bg-emerald-50/50 text-emerald-600 border border-emerald-100 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[9px] font-black uppercase tracking-wider">Live</span>
                        </div>
                    </div>

                    <!-- Camera Selection -->
                    <div class="flex gap-2 mb-4 bg-slate-900/5 p-1 rounded-xl border border-slate-900/5">
                        <button onclick="switchCam('entry')" id="btn-cam-entry" 
                                class="flex-1 py-1.5 text-[10px] font-bold uppercase tracking-widest rounded-lg bg-slate-900 text-white transition-all shadow-sm">
                            Entry
                        </button>
                        <button onclick="switchCam('exit')" id="btn-cam-exit" 
                                class="flex-1 py-1.5 text-[10px] font-bold uppercase tracking-widest rounded-lg text-slate-900/40 hover:text-slate-900 transition-all">
                            Exit
                        </button>
                    </div>

                    <!-- Fake CCTV Stream -->
                    <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-slate-900 group/cctv shadow-inner border border-slate-100">
                        <img id="cctv-img" src="assets/img/entry_gate.jpg" class="w-full h-full object-cover opacity-60 grayscale-[20%] transition-opacity duration-500">
                        <div class="absolute inset-0 p-3 flex flex-col justify-between pointer-events-none">
                            <div class="flex justify-between items-start">
                                <span id="cctv-label" class="text-[8px] font-mono text-white/80 bg-slate-900/40 px-1.5 py-0.5 rounded leading-none uppercase tracking-widest">CAM_01_ENTRY</span>
                                <div class="flex items-center gap-1.5 bg-red-500/80 px-2 py-0.5 rounded shadow-lg">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                    <span class="text-[8px] font-black text-white uppercase tracking-widest">REC</span>
                                </div>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-transparent h-4 w-full animate-scan pointer-events-none opacity-20"></div>
                    </div>
                </div>
            </div>

            <!-- 2: Parking Intensity Chart (Swapped to Row 2) -->
            <div class="col-span-12 lg:col-span-8">
                <div class="bg-white rounded-3xl p-4 ring-1 ring-slate-200/50 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col h-full overflow-hidden group/card transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                            <i class="fa-solid fa-chart-line text-slate-900/40 text-lg group-hover:text-white transition-all"></i>
                        </div>
                        <div>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Parking Intensity</h3>
                        </div>
                    </div>
 
                    <div class="relative flex-1 h-0 min-h-[300px] min-w-0">
                        <canvas id="trafficChart" class="w-full h-full"></canvas>
                    </div>
 
                    <div class="mt-auto pt-6 border-t border-slate-900/5 flex items-center justify-between">
                        <div class="flex gap-8">
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-900"></div>
                                <span class="text-[10px] font-bold uppercase text-slate-900/40 tracking-widest">Cars</span>
                            </div>
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-200"></div>
                                <span class="text-[10px] font-bold uppercase text-slate-900/40 tracking-widest">Motos</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50/50 rounded-lg border border-slate-100/50 group/monitor">
                            <div class="relative w-2 h-2">
                                <span class="absolute inset-0 bg-slate-400 rounded-full animate-ping opacity-25"></span>
                                <span class="relative block w-2 h-2 bg-slate-500 rounded-full"></span>
                            </div>
                            <p class="text-[11px] font-extrabold text-slate-600/60 uppercase tracking-[0.2em] font-inter">Live Monitor</p>
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
                            LEAST(
                                pr.first_hour_rate + (GREATEST(0, CEIL(TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) / 60) - 1) * pr.next_hour_rate),
                                pr.daily_max_rate
                            )
                        ) as total_fee
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
                        0 as total_fee
                     FROM reservation r
                     JOIN vehicle v ON r.vehicle_id = v.vehicle_id
                     WHERE r.status = 'confirmed' AND DATE(r.reserved_from) = CURDATE()
                    )
                    ORDER BY entry_time DESC 
                    LIMIT 7
                ")->fetchAll();
            ?>
            <div class="col-span-12 lg:col-span-8 bg-white rounded-3xl p-4 ring-1 ring-slate-200/50 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col self-start transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                            <i class="fa-solid fa-clock-rotate-left text-slate-900/40 text-lg group-hover:text-white transition-all"></i>
                        </div>
                        <div>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Recent Activity Log</h3>
                        </div>
                    </div>
                    <a href="modules/operations/scan_log.php" class="text-[11px] font-extrabold uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-colors font-inter">VIEW ALL</a>
                </div>

                <?php if (empty($recent_logs)): ?>
                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                        <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 opacity-20"></i>
                        <p class="text-sm font-inter">No recent activity detected.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full font-inter border-collapse table-fixed">
                            <thead>
                                <tr class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 border-b border-slate-900/5">
                                    <th class="py-2 w-[10%] text-left">Vehicle</th>
                                    <th class="py-2 w-[15%] text-center">Plate</th>
                                    <th class="py-2 w-[15%] text-center">In</th>
                                    <th class="py-2 w-[15%] text-center">Out</th>
                                    <th class="py-2 w-[15%] text-center">Price</th>
                                    <th class="py-2 w-[10%] text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-900/5">
                                <?php foreach ($recent_logs as $log): ?>
                                <tr class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="py-2 text-left align-middle">
                                        <div class="flex items-center">
                                            <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-900 border-slate-200 flex items-center justify-center shrink-0 shadow-sm border group-hover:bg-slate-900 group-hover:text-white transition-all">
                                                <i class="fa-solid <?= $log['vehicle_type'] === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 text-center align-middle">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="text-[11px] font-extrabold text-slate-900 uppercase tracking-widest leading-none mb-1">
                                                <?= $log['plate_number'] ?: '------' ?>
                                            </span>
                                            <span class="text-[11px] font-code text-slate-900/40 font-extrabold leading-none">
                                                <?= $log['code'] ?: 'PENDING' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-[11px] text-slate-900 font-extrabold uppercase tracking-widest">
                                                <?= date('H:i', strtotime($log['entry_time'])) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-[11px] text-slate-900 font-extrabold uppercase tracking-widest">
                                                <?= $log['exit_time'] ? date('H:i', strtotime($log['exit_time'])) : '--:--' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex items-center justify-center">
                                            <span class="text-[11px] font-manrope font-extrabold text-slate-900 uppercase tracking-widest">
                                                <?= $log['total_fee'] !== null ? fmt_idr((float)$log['total_fee']) : 'Rp 0' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right align-middle">
                                        <div class="flex justify-end items-center">
                                            <?php if ($log['log_type'] === 'reservation'): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest border bg-slate-50 text-slate-400 border-slate-200">Reserved</span>
                                            <?php elseif (!$log['exit_time']): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest border bg-emerald-50 text-emerald-600 border-emerald-100">Parked</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest border bg-slate-900 text-white border-slate-900">Departed</span>
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
                <div class="bg-white rounded-3xl p-4 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col h-[230px] transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center border border-slate-200 group-hover:bg-slate-50 transition-all">
                                <i class="fa-solid fa-user-shield text-slate-900/40 text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Active Duty</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[11px] font-extrabold uppercase tracking-wider"><?= count($active_staff) ?> Active</span>
                        </div>
                    </div>

                    <div class="space-y-3 flex-grow overflow-y-auto custom-scrollbar pr-1">
                        <?php if (empty($display_staff)): ?>
                            <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                                <i class="fa-solid fa-user-slash text-3xl mb-3 opacity-20"></i>
                                <p class="text-xs font-inter">No personnel on duty.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($display_staff as $st): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-900/5 rounded-2xl border border-slate-900/5 group transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <div class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center text-white text-xs font-bold font-manrope">
                                            <?= strtoupper(substr($st['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-50 border-2 border-white rounded-full"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-extrabold text-slate-900 truncate"><?= htmlspecialchars($st['full_name']) ?></p>
                                        <p class="text-[11px] text-slate-900/40 uppercase tracking-wider font-extrabold"><?= htmlspecialchars($st['shift'] ?? 'Duty') ?> Shift</p>
                                    </div>
                                </div>
                                <div class="text-right whitespace-nowrap">
                                    <p class="text-[11px] font-extrabold text-slate-900/70 uppercase tracking-wider">IN: <?= date('H:i', strtotime($st['check_in_time'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>


                </div>

                <!-- 2: Average Duration Card (Swapped to Row 3) -->
                <div class="bg-white p-4 rounded-3xl ring-1 ring-slate-900/5 flex flex-col flex-1 transition-all duration-300 group hover:-translate-y-1 shadow-[0_8px_30px_rgba(15,23,42,0.04)]">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all shrink-0">
                            <i class="fa-solid fa-clock text-slate-900/40 text-lg group-hover:text-white transition-all"></i>
                        </div>
                        <div>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Average Duration</h3>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-center">
                        <div class="flex items-end gap-3 mb-4">
                            <span class="font-manrope font-semibold text-4xl text-slate-900 leading-none tracking-tight"><?= $avg_duration_str ?></span>
                            <span class="text-slate-900/40 text-[11px] font-medium tracking-wider pb-1">Per Session</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full <?= $duration_trend <= 0 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100' ?> border text-[11px] font-bold">
                                <i class="fa-solid <?= $duration_trend <= 0 ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up' ?>"></i>
                                <?= abs(round($duration_trend, 1)) ?>%
                            </div>
                            <p class="text-[11px] font-medium text-slate-900/40 uppercase tracking-wider">Vs Last Month</p>
                        </div>
                    </div>


                </div>
            </div>

        </div>

    </div>

<!-- Attendance Modal (Tailwind) -->
<?php if (!$on_duty): ?>
<div id="attendanceOverlay" class="fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-sm mx-4">
        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-user-check text-white text-2xl"></i>
            </div>
            <h2 class="font-manrope font-extrabold text-xl text-slate-900 mb-1">Attendance Confirmation</h2>
            <p class="text-slate-400 text-sm font-inter">Hello <span class="font-bold text-slate-700"><?= strtoupper($role) ?></span>, select your personnel identity.</p>
        </div>

        <form id="attendanceForm" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2">Select Personnel Name</label>
                <select name="staff_id" required
                        class="w-full bg-slate-900/5 border-none rounded-lg px-5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all font-inter font-semibold">
                    <option value="">-- Select Your Name --</option>
                    <?php foreach ($staff_list as $s): ?>
                        <option value="<?= $s['operator_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['shift'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="attendBtn"
                    class="w-full bg-slate-900 hover:bg-slate-900/90 text-white font-bold font-inter rounded-lg uppercase tracking-widest text-xs py-3.5 transition-all">
                Start Duty →
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

<script>
function switchCam(type) {
    const img = document.getElementById('cctv-img');
    const label = document.getElementById('cctv-label');
    const btnEntry = document.getElementById('btn-cam-entry');
    const btnExit = document.getElementById('btn-cam-exit');
    
    img.style.opacity = '0';
    
    setTimeout(() => {
        if (type === 'entry') {
            img.src = 'assets/img/entry_gate.jpg';
            label.textContent = 'CAM_01_ENTRY';
            btnEntry.classList.add('bg-slate-900', 'text-white');
            btnEntry.classList.remove('text-slate-900/40');
            btnExit.classList.remove('bg-slate-900', 'text-white');
            btnExit.classList.add('text-slate-900/40');
        } else {
            img.src = 'assets/img/exit_gate.jpg';
            label.textContent = 'CAM_02_EXIT';
            btnExit.classList.add('bg-slate-900', 'text-white');
            btnExit.classList.remove('text-slate-900/40');
            btnEntry.classList.remove('bg-slate-900', 'text-white');
            btnEntry.classList.add('text-slate-900/40');
        }
        img.style.opacity = '0.6';
    }, 300);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let trafficChart = null;

function initChart(data) {
    const ctx = document.getElementById('trafficChart').getContext('2d');
    
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
                    backgroundColor: '#0f172a',
                    titleFont: { family: 'Inter', weight: 'bold', size: 12 },
                    bodyFont: { family: 'Inter', size: 11 },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                    usePointStyle: true,
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Inter', size: 10, weight: '500' },
                        color: '#353134'
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    max: 60,
                    grid: { color: '#CAC7D1' },
                    border: { display: false },
                    ticks: {
                        font: { family: 'Inter', size: 10 },
                        color: '#353134',
                        stepSize: 10
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

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

    // Fade out effect
    img.style.opacity = '0.3';

    setTimeout(() => {
        if (type === 'entry') {
            img.src = 'assets/img/entry_gate.jpg';
            label.textContent = 'CAM_01_ENTRY';
            
            // Active Styles
            btnEntry.className = 'flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg bg-slate-900 text-white transition-all shadow-sm';
            btnExit.className = 'flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg text-slate-900/40 hover:text-slate-900 transition-all';
        } else {
            img.src = 'assets/img/exit_gate.jpg';
            label.textContent = 'CAM_02_EXIT';
            
            // Active Styles
            btnExit.className = 'flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg bg-slate-900 text-white transition-all shadow-sm';
            btnEntry.className = 'flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg text-slate-900/40 hover:text-slate-900 transition-all';
        }
        
        // Fade in effect
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

    const ctx = document.getElementById('activeStatusDoughnut').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Parked', 'Reserved', 'Free', 'Maint'],
            datasets: [{
                data: [<?= $active ?>, <?= $res_count ?>, <?= $car_avail + $moto_avail ?>, <?= $mnt_count ?>],
                backgroundColor: [
                    '#0f172a', // Today Revenue Black
                    '#CAC7D1', // Theme Accent
                    '#10b981', // Emerald-500
                    '#f59e0b'  // Amber-500
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverBorderWidth: 2,
                hoverBorderColor: '#ffffff',
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
                    backgroundColor: '#0f172a',
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
                ctx.fillStyle = '#353134';
                ctx.fillText(pctText, centerX, centerY - 6);

                // Draw Label
                ctx.font = '400 11px Inter';
                ctx.fillStyle = '#CAC7D1'; 
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
