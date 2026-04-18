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

$active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$res_count = $car_res + $moto_res;

$mnt_count = $pdo->query("SELECT COUNT(*) FROM parking_slot WHERE status='maintenance'")->fetchColumn();

$today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();
$yesterday_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

$rev_diff = $today_rev - $yesterday_rev;
$rev_pct = 0;
if ($yesterday_rev > 0) {
    $rev_pct = ($rev_diff / $yesterday_rev) * 100;
} elseif ($today_rev > 0) {
    $rev_pct = 100;
}

$is_rev_up = ($today_rev > $yesterday_rev) && ($today_rev > 0);

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

    <div class="p-6">

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
        <div class="grid grid-cols-12 gap-6 mb-6 items-stretch">

            <!-- Active Vehicles -->
            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl p-6 transition-all duration-300 group ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] hover:-translate-y-1 transform-gpu will-change-transform">
                <div class="flex items-center justify-between mb-5 -mt-2">
                    <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Active Vehicles</p>
                    <div class="w-10 h-10 rounded-lg bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all">
                        <i class="fa-solid fa-clock-rotate-left text-slate-900/30 text-xl group-hover:text-white transition-all"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $total_used ?></span>
                    <span class="text-slate-900/40 text-sm font-inter">cap</span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-lg h-1.5 overflow-hidden">
                    <div class="h-full rounded-lg bg-slate-900 transition-all duration-1000"
                         style="width: <?= $occ_pct ?>%"></div>
                </div>
                
                <div class="grid grid-cols-2 gap-y-2 mt-4">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-1.5 rounded-full bg-slate-900/20"></div>
                        <span class="text-[11px] font-extrabold text-slate-900/60 uppercase tracking-wider font-inter"><?= $active ?> Parked</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-1.5 rounded-full bg-slate-900/20"></div>
                        <span class="text-[11px] font-extrabold text-slate-900/60 uppercase tracking-wider font-inter"><?= $res_count ?> Resv</span>
                    </div>
                    <div class="flex items-center gap-2 text-emerald-500">
                        <div class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></div>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider font-inter"><?= $car_avail + $moto_avail ?> Free</span>
                    </div>
                </div>
            </div>

            <!-- Motorcycle Slots -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6 transition-all duration-300 ring-1 ring-slate-900/5 group transform-gpu will-change-transform shadow-[0_8px_30px_rgba(15,23,42,0.04)] hover:-translate-y-1">
                <div class="flex items-center justify-between mb-5 -mt-2">
                    <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Motorcycle Slots</p>
                    <div class="w-10 h-10 rounded-lg bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all">
                        <i class="fa-solid fa-motorcycle text-slate-900/30 text-xl group-hover:text-white transition-all"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $moto_avail ?></span>
                    <span class="text-slate-900/40 text-sm font-inter">available</span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-lg h-1.5 overflow-hidden">
                    <div class="h-full rounded-lg <?= $moto_pct > 10 ? 'bg-slate-900' : 'bg-red-500' ?> transition-all duration-1000"
                         style="width: <?= $moto_pct ?>%"></div>
                </div>
                <p class="text-slate-900/50 text-[11px] uppercase font-extrabold tracking-wider font-inter mt-4"><?= $moto_pct ?>% Available</p>
            </div>

            <!-- Car Slots -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6 transition-all duration-300 ring-1 ring-slate-900/5 group transform-gpu will-change-transform shadow-[0_8px_30px_rgba(15,23,42,0.04)] hover:-translate-y-1">
                <div class="flex items-center justify-between mb-5 -mt-2">
                    <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Car Slots</p>
                    <div class="w-10 h-10 rounded-lg bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all">
                        <i class="fa-solid fa-car text-slate-900/30 text-xl group-hover:text-white transition-all"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $car_avail ?></span>
                    <span class="text-slate-900/40 text-sm font-inter font-medium">available</span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-lg h-1.5 overflow-hidden">
                    <div class="h-full rounded-lg <?= $car_pct > 10 ? 'bg-slate-900' : 'bg-red-500' ?> transition-all duration-1000"
                         style="width: <?= $car_pct ?>%"></div>
                </div>
                <p class="text-slate-900/50 text-[11px] uppercase font-extrabold tracking-wider font-inter mt-4"><?= $car_pct ?>% Available</p>
            </div>

            <!-- Today Revenue - Strictly Square -->
            <!-- Analytics Stack -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <!-- Today Revenue -->
                <div class="bg-slate-900 rounded-2xl p-8 flex flex-col flex-1 hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden min-h-[280px] shadow-[0_30px_60px_-12px_rgba(15,23,42,0.3)]">
                    <!-- Background Glow -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-violet-600/10 rounded-full blur-3xl group-hover:bg-violet-600/20 transition-all"></div>
                    
                    <div class="flex items-center justify-between relative z-10">
                        <p class="text-[11px] font-extrabold uppercase tracking-widest text-white/40 font-inter">Today's Revenue</p>
                        <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center border border-white/10 group-hover:bg-white/20 transition-all">
                            <i class="fa-solid fa-money-bill-wave text-white text-lg"></i>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-center items-center text-center relative z-10">
                        <div class="font-manrope font-extrabold text-4xl text-white leading-none tracking-tighter drop-shadow-xl mb-3">
                            <?= fmt_idr((float)$today_rev) ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2.5 py-1 <?= $is_rev_up ? 'bg-emerald-50/20 text-emerald-400 border-emerald-500/20' : 'bg-red-50/20 text-red-400 border-red-500/20' ?> text-[11px] font-extrabold rounded-lg border flex items-center gap-1.5 shadow-sm backdrop-blur-md">
                                <span class="w-1.5 h-1.5 <?= $is_rev_up ? 'bg-emerald-50' : 'bg-red-50' ?> rounded-full animate-pulse"></span>
                                <?= ($rev_pct >= 0 ? '+' : '') . number_format($rev_pct, 1) ?>%
                            </span>
                            <span class="text-white/40 text-[11px] font-extrabold uppercase tracking-wide">vs previous day</span>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-white/10 flex items-center justify-between relative z-10 mt-2">
                        <div class="flex items-center gap-3">
                            <p class="text-white/40 text-[11px] font-extrabold uppercase tracking-wider"><?= date('l, d M') ?></p>
                        </div>
                        <div class="px-2 py-1 rounded-lg bg-emerald-50/5 border border-emerald-500/10 text-[11px] font-extrabold text-emerald-500/60 uppercase tracking-widest">
                            Live
                        </div>
                    </div>
                </div>

                <!-- High Occupancy Trend -->
                <div class="bg-slate-50 p-8 rounded-2xl ring-1 ring-slate-900/5 flex flex-col flex-1 hover:-translate-y-1 transition-all duration-300 relative overflow-hidden min-h-[280px] group shadow-[0_8px_30px_rgba(15,23,42,0.04)]">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Occupancy Analytics</p>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Today High Trend Peak</h3>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-slate-900/5 flex items-center justify-center border border-slate-900/10 group-hover:bg-slate-900 group-hover:text-white transition-all">
                            <i class="fa-solid fa-fire text-slate-900/30 text-lg group-hover:text-white transition-all"></i>
                        </div>
                    </div>

                    <div class="flex-grow flex flex-col justify-center">
                        <div class="flex items-end gap-3 mb-6">
                            <span class="font-manrope font-extrabold text-3xl text-slate-900 leading-none"><?= $peak_time ?></span>
                            <span class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-[0.2em] pb-1">Peak Time</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="bg-slate-900/[0.03] p-4 rounded-2xl border border-slate-900/5 transition-all hover:bg-slate-900 group/item">
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 mb-1 group-hover/item:text-white/40">Max Vol</p>
                                <p class="text-lg font-manrope font-extrabold text-slate-900 group-hover/item:text-white"><?= $peak_vol ?></p>
                            </div>
                            <div class="bg-slate-900/[0.03] p-4 rounded-2xl border border-slate-900/5 transition-all hover:bg-slate-900 group/item">
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 mb-1 group-hover/item:text-white/40">Dominant</p>
                                <p class="text-lg font-manrope font-extrabold text-slate-900 group-hover/item:text-white"><?= $peak_dom ?></p>
                            </div>
                        </div>

                        <!-- Mini Distribution Bar -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <p class="text-[11px] font-extrabold text-slate-900/40 uppercase tracking-widest">Distribution at Peak</p>
                                <p class="text-[11px] font-extrabold text-slate-900 bg-slate-100 px-2 py-0.5 rounded-lg"><?= $peak_pct ?>% Cars</p>
                            </div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-lg flex overflow-hidden">
                                <div class="h-full bg-slate-900" style="width: <?= $peak_pct ?>%"></div>
                                <div class="h-full bg-slate-300" style="width: <?= 100 - $peak_pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parking Traffic Intensity - Perfectly Aligned 2:1 -->
            <div class="col-span-12 lg:col-span-8">
                <div class="bg-white rounded-2xl p-8 ring-1 ring-slate-200/50 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col h-full overflow-hidden group/card transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center justify-between mb-5 -mt-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-slate-900 flex items-center justify-center shadow-lg relative overflow-hidden group/chart group-hover/chart:bg-slate-900/90 transition-all">
                                <i class="fa-solid fa-chart-line text-white text-lg relative z-10 transition-transform group-hover/chart:scale-110"></i>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-700/20 to-transparent opacity-0 group-hover/chart:opacity-100 transition-opacity"></div>
                            </div>
                            <div>
                                <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Traffic Analysis</p>
                                <h3 class="font-manrope font-extrabold text-xl text-slate-900 leading-tight">Parking Intensity</h3>
                            </div>
                        </div>
                        
                        <!-- Today Indicator -->
                        <div class="px-4 py-2 bg-slate-50 rounded-lg border border-slate-100 flex items-center gap-2">
                            <i class="fa-solid fa-clock text-slate-400 text-[11px]"></i>
                            <span class="text-[11px] font-extrabold uppercase tracking-widest text-slate-600">Today's Activity</span>
                        </div>
                    </div>
 
                    <div class="relative flex-1 h-0 min-h-0 min-w-0">
                        <canvas id="trafficChart" class="w-full h-full"></canvas>
                    </div>
 
                    <div class="mt-auto pt-6 border-t border-slate-900/5 flex items-center justify-between">
                        <div class="flex gap-8">
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-3 h-3 rounded-full bg-slate-900 shadow-sm shadow-slate-200 group-hover/legend:scale-125 transition-transform"></div>
                                <span class="text-[10px] font-bold uppercase text-slate-900/40 tracking-widest">Cars</span>
                            </div>
                            <div class="flex items-center gap-3 group/legend cursor-default">
                                <div class="w-3 h-3 rounded-full bg-slate-300 shadow-sm shadow-slate-100 group-hover/legend:scale-125 transition-transform"></div>
                                <span class="text-[10px] font-bold uppercase text-slate-900/40 tracking-widest">Motos</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50/50 rounded-lg border border-slate-100/50 group/monitor">
                            <div class="relative w-2 h-2">
                                <span class="absolute inset-0 bg-slate-400 rounded-full animate-ping opacity-25"></span>
                                <span class="relative block w-2 h-2 bg-slate-500 rounded-full"></span>
                            </div>
                            <p class="text-[11px] font-extrabold text-slate-600/60 uppercase tracking-[0.2em] font-inter">Live</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Recent Activity Log (Left Side) -->
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
                    LIMIT 10
                ")->fetchAll();
            ?>
            <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl p-6 ring-1 ring-slate-200/50 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                <div class="flex items-center justify-between mb-6 -mt-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-900/5 flex items-center justify-center border border-slate-900/5 group-hover:bg-slate-900/10 transition-all">
                            <i class="fa-solid fa-list-ul text-slate-900/70 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">System Logs</p>
                            <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Recent Activity Log</h3>
                        </div>
                    </div>
                    <a href="modules/operations/scan_log.php" class="text-[11px] font-extrabold uppercase tracking-widest text-slate-500 hover:text-slate-900 transition-colors font-inter">VIEW ALL</a>
                </div>

                <?php if (empty($recent_logs)): ?>
                    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                        <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 opacity-20"></i>
                        <p class="text-sm font-inter">No recent activity detected.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full font-inter border-collapse table-fixed">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-widest text-slate-900/40 border-b border-slate-900/5">
                                    <th class="pb-3 font-extrabold w-[10%] text-left">Vehicle</th>
                                    <th class="pb-3 font-extrabold w-[15%] text-center">Vehicle ID</th>
                                    <th class="pb-3 font-extrabold w-[15%] text-center">Entry</th>
                                    <th class="pb-3 font-extrabold w-[15%] text-center">Exit</th>
                                    <th class="pb-3 font-extrabold w-[15%] text-center">Price</th>
                                    <th class="pb-3 font-extrabold w-[10%] text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-900/5">
                                <?php foreach ($recent_logs as $log): ?>
                                <tr class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 text-left align-middle">
                                        <div class="flex items-center h-9">
                                            <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-900 border-slate-200 flex items-center justify-center shrink-0 shadow-sm border">
                                                <i class="fa-solid <?= $log['vehicle_type'] === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 text-center align-middle">
                                        <div class="flex flex-col items-center justify-center h-9">
                                            <span class="text-[11px] font-extrabold text-slate-900 uppercase tracking-widest leading-none mb-1">
                                                <?= $log['plate_number'] ?: '------' ?>
                                            </span>
                                            <span class="text-[11px] font-code text-slate-900/40 font-extrabold leading-none">
                                                <?= $log['code'] ?: 'PENDING' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-center align-middle">
                                        <div class="flex items-center justify-center h-9">
                                            <span class="text-[11px] text-slate-900 font-extrabold uppercase tracking-widest">
                                                <?= date('H:i:s', strtotime($log['entry_time'])) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-center align-middle">
                                        <div class="flex items-center justify-center h-9">
                                            <span class="text-[11px] text-slate-900 font-extrabold uppercase tracking-widest">
                                                <?= $log['exit_time'] ? date('H:i:s', strtotime($log['exit_time'])) : '--:--:--' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-center align-middle">
                                        <div class="flex items-center justify-center h-9">
                                            <span class="text-[11px] font-extrabold text-slate-900 uppercase tracking-widest">
                                                <?= $log['total_fee'] !== null ? fmt_idr((float)$log['total_fee']) : 'Rp 0' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-right align-middle">
                                        <div class="flex justify-end items-center h-9">
                                            <?php if ($log['log_type'] === 'reservation'): ?>
                                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold uppercase tracking-widest flex items-center gap-1.5 shadow-sm border bg-slate-100 text-slate-700 border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                    Reserved
                                                </span>
                                            <?php elseif (!$log['exit_time']): ?>
                                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold uppercase tracking-widest flex items-center gap-1.5 shadow-sm border bg-slate-900 text-white border-slate-900">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    Parked
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold uppercase tracking-widest flex items-center gap-1.5 shadow-sm border bg-slate-50 text-slate-600 border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                                    Departed
                                                </span>
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
                <!-- Active Duty - Max 3 -->
                <?php
                    $active_staff = get_active_attendance($pdo);
                    $display_staff = array_slice($active_staff, 0, 3);
                ?>
                <div class="bg-white rounded-2xl p-6 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col h-[310px] transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center justify-between mb-6 -mt-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center border border-slate-200 group-hover:bg-slate-50 transition-all">
                                <i class="fa-solid fa-user-shield text-slate-900 text-lg"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Operations</p>
                                <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">Active Duty</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-50 animate-pulse"></span>
                            <span class="text-[11px] font-extrabold uppercase tracking-wider"><?= count($active_staff) ?> Active</span>
                        </div>
                    </div>

                    <div class="space-y-3 flex-grow overflow-y-auto no-scrollbar">
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

                <!-- IoT Health Monitor -->
                <div class="bg-slate-50 rounded-2xl p-6 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] flex flex-col flex-1 min-h-[300px] transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/40">
                    <div class="flex items-center justify-between mb-6 -mt-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-50/10 flex items-center justify-center border border-blue-500/20 group-hover:bg-slate-50 transition-all">
                                <i class="fa-solid fa-microchip text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter">Network & Hardware</p>
                                <h3 class="font-manrope font-extrabold text-lg text-slate-900 leading-tight">IoT System Health</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50/10 text-emerald-600 border border-emerald-500/20 rounded-lg shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-50 animate-pulse"></span>
                            <span class="text-[11px] font-extrabold uppercase tracking-wider">Normal</span>
                        </div>
                    </div>

                    <!-- Camera Selection -->
                    <div class="flex gap-2 mb-4 bg-slate-900/5 p-1 rounded-2xl border border-slate-900/5">
                        <button onclick="switchCam('entry')" id="btn-cam-entry" 
                                class="flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg bg-slate-900 text-white transition-all shadow-sm">
                            Main Entry
                        </button>
                        <button onclick="switchCam('exit')" id="btn-cam-exit" 
                                class="flex-1 py-2 text-[11px] font-extrabold uppercase tracking-widest rounded-lg text-slate-900/40 hover:text-slate-900 transition-all">
                            Exit Gate
                        </button>
                    </div>

                    <!-- Fake CCTV Stream -->
                    <div class="relative w-full aspect-video rounded-2xl overflow-hidden bg-slate-900 mb-6 group/cctv shadow-inner border border-slate-100">
                        <img id="cctv-img" src="assets/img/entry_gate.jpg" class="w-full h-full object-cover opacity-60 grayscale-[20%] transition-opacity duration-500">
                        
                        <!-- CCTV Overlay -->
                        <div class="absolute inset-0 p-3 flex flex-col justify-between pointer-events-none">
                            <div class="flex justify-between items-start">
                                <div class="flex flex-col gap-0.5">
                                    <span id="cctv-label" class="text-[8px] font-mono text-white/80 bg-slate-900/40 px-1.5 py-0.5 rounded leading-none">CAM_01_ENTRY</span>
                                    <span class="text-[7px] font-mono text-white/50 bg-slate-900/40 px-1.5 py-0.5 rounded leading-none"><?= date('Y-m-d') ?></span>
                                </div>
                                <div class="flex items-center gap-1.5 bg-red-50/80 px-2 py-0.5 rounded shadow-lg">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                    <span class="text-[8px] font-black text-white uppercase tracking-widest">REC</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-end">
                                <div></div>
                                <div class="w-8 h-8 opacity-20 border-r border-b border-white"></div>
                            </div>
                        </div>
                        
                        <!-- Scanning Lines Animation -->
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-transparent h-4 w-full animate-scan pointer-events-none opacity-20"></div>
                        <style>
                            @keyframes scan { from { top: -20%; } to { top: 120%; } }
                            .animate-scan { animation: scan 4s linear infinite; }
                        </style>
                    </div>

                    <!-- Status Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-slate-900/5 rounded-2xl border border-slate-900/5 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-slate-900/40 uppercase tracking-widest">Main Gate</span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-50"></span>
                                <span class="text-[11px] font-extrabold text-emerald-600">Online</span>
                            </span>
                        </div>
                        <div class="p-3 bg-slate-900/5 rounded-2xl border border-slate-900/5 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-slate-900/40 uppercase tracking-widest">QR Scanner</span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-50"></span>
                                <span class="text-[11px] font-extrabold text-emerald-600">Sync</span>
                            </span>
                        </div>
                        <div class="p-3 bg-slate-900/5 rounded-2xl border border-slate-900/5 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-slate-900/40 uppercase tracking-widest">Gate Sensor</span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-50"></span>
                                <span class="text-[11px] font-extrabold text-emerald-600">Active</span>
                            </span>
                        </div>
                        <div class="p-3 bg-slate-900/5 rounded-2xl border border-slate-900/5 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-slate-900/40 uppercase tracking-widest">Exit Gate</span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-50"></span>
                                <span class="text-[11px] font-extrabold text-emerald-600">Ready</span>
                            </span>
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
                        color: '#94a3b8'
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    max: 60,
                    grid: { color: '#f1f5f9' },
                    border: { display: false },
                    ticks: {
                        font: { family: 'Inter', size: 10 },
                        color: '#94a3b8',
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

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    updateChart('today');
});
</script>

<?php include 'includes/footer.php'; ?>
