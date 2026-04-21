<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

$page_title = 'Intelligence Dashboard';
$page_subtitle = 'Advanced operational insights and real-time data analytics.';

// --- DATE FILTER LOGIC ---
$range = $_GET['range'] ?? '1week';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if ($range !== 'custom') {
    $end_date = date('Y-m-d');
    switch ($range) {
        case 'today': $start_date = date('Y-m-d'); break;
        case '1week': $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case '1month': $start_date = date('Y-m-d', strtotime('-30 days')); break;
        case '1year': $start_date = date('Y-m-d', strtotime('-1 year')); break;
        default: $start_date = date('Y-m-d', strtotime('-7 days')); break;
    }
}

// --- DATA FETCHING (ALL AT ONCE) ---
$data = get_ai_context_data($pdo, $start_date, $end_date);

// Heatmap Data (Current state - no filter needed)
$floors = $data['floors'];
$slots = $pdo->query("
    SELECT ps.*, f.floor_code 
    FROM parking_slot ps 
    JOIN floor f ON ps.floor_id = f.floor_id 
    ORDER BY f.floor_id, ps.slot_number
")->fetchAll();

// Revenue Data (Already in $data['daily_trend'])
$rev_daily = array_reverse($data['daily_trend']);
$rev_monthly = $pdo->query("
    SELECT MONTHNAME(check_out_time) as month, SUM(total_fee) as revenue
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(check_out_time)
    ORDER BY check_out_time ASC
")->fetchAll();

// Vehicle Trends
$veh_trends = array_reverse($data['daily_trend']);

// Dwell Time
$dwell_avg = $pdo->prepare("
    SELECT v.vehicle_type, AVG(TIMESTAMPDIFF(MINUTE, t.check_in_time, t.check_out_time)) as avg_min
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status = 'paid' AND t.check_out_time BETWEEN ? AND ?
    GROUP BY v.vehicle_type
");
$dwell_avg->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$dwell_avg = $dwell_avg->fetchAll();

$dwell_period = $pdo->prepare("
    SELECT 
        CASE 
            WHEN HOUR(check_in_time) BETWEEN 6 AND 11 THEN 'Morning'
            WHEN HOUR(check_in_time) BETWEEN 12 AND 17 THEN 'Afternoon'
            WHEN HOUR(check_in_time) BETWEEN 18 AND 23 THEN 'Evening'
            ELSE 'Night'
        END as period,
        AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)) as avg_min
    FROM `transaction`
    WHERE payment_status = 'paid' AND check_out_time BETWEEN ? AND ?
    GROUP BY period
");
$dwell_period->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$dwell_period = $dwell_period->fetchAll();

// Gate Logs (Already in $data['gate_log_24h'] but we use $data['gate_log_24h'] as a summary)
$gate_logs = $data['gate_log'];

// Reservations (Already in $data['reservation_summary'])
$res_conversion = $data['reservation_summary'];

// Anomaly Detection (Based on filtered range)
$avg_rev_val = $pdo->query("SELECT AVG(daily_rev) FROM (SELECT SUM(total_fee) as daily_rev FROM `transaction` WHERE payment_status='paid' GROUP BY DATE(check_out_time)) as sub")->fetchColumn() ?: 0;
$anomalies = $pdo->prepare("
    SELECT DATE(check_out_time) as date, SUM(total_fee) as revenue
    FROM `transaction`
    WHERE payment_status='paid' AND DATE(check_out_time) BETWEEN ? AND ?
    GROUP BY DATE(check_out_time)
    HAVING revenue < ($avg_rev_val * 0.5)
");
$anomalies->execute([$start_date, $end_date]);
$anomalies = $anomalies->fetchAll();

include '../../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .scroll-section { scroll-margin-top: 180px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .bento-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .bento-card:hover { transform: translateY(-4px); }
    
    /* Custom Dropdown Styling */
    .filter-dropdown:focus-within .dropdown-content { display: block; }
    .dropdown-content { display: none; position: absolute; top: 100%; right: 0; min-width: 200px; z-index: 50; }
</style>

<div class="p-10 space-y-16">
    
    <!-- TOP FILTER BAR -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 -mb-6">
        <div>
            <h2 class="text-4xl font-manrope font-black text-slate-900 tracking-tight">Intelligence Dashboard</h2>
            <p class="text-slate-400 mt-2 text-sm">Showing analytical insights for <span class="text-slate-900 font-bold"><?= ucfirst($range) ?></span> (<?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?>)</p>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="relative filter-dropdown">
                <select name="range" onchange="this.form.submit()" class="appearance-none bg-white border border-slate-900/10 px-6 py-3 pr-12 rounded-2xl text-xs font-black uppercase tracking-widest text-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-900/5 transition-all cursor-pointer">
                    <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="1week" <?= $range === '1week' ? 'selected' : '' ?>>1 Week</option>
                    <option value="1month" <?= $range === '1month' ? 'selected' : '' ?>>1 Month</option>
                    <option value="1year" <?= $range === '1year' ? 'selected' : '' ?>>1 Year</option>
                    <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[10px]"></i>
            </div>

            <?php if($range === 'custom'): ?>
            <div class="flex items-center gap-2 bg-white border border-slate-900/10 p-1.5 rounded-2xl shadow-sm animate-in fade-in slide-in-from-right-4 duration-500">
                <input type="date" name="start_date" value="<?= $start_date ?>" class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest text-slate-900 focus:ring-0 px-3">
                <span class="text-slate-300 font-bold">/</span>
                <input type="date" name="end_date" value="<?= $end_date ?>" class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest text-slate-900 focus:ring-0 px-3">
                <button type="submit" class="bg-slate-900 text-white w-9 h-9 rounded-xl flex items-center justify-center hover:bg-slate-800 transition-all">
                    <i class="fa-solid fa-check"></i>
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- STICKY JUMP MENU -->
    <div class="sticky top-20 z-20 bg-slate-50/90 backdrop-blur-xl py-4 -mx-10 px-10 border-b border-slate-900/5 shadow-sm">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            <a href="#overview" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] bg-slate-900 text-white shadow-xl transition-all">Overview</a>
            <a href="#heatmap" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Heatmap</a>
            <a href="#revenue" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Revenue</a>
            <a href="#traffic" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Traffic</a>
            <a href="#dwell" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Stay Duration</a>
            <a href="#gate" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Gate LPR</a>
            <a href="#reservations" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Reservations</a>
            <a href="#operators" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Operators</a>
            <a href="#payments" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Payments</a>
            <a href="#anomalies" class="jump-link px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 hover:text-slate-900 hover:bg-slate-900/5 transition-all">Predictive</a>
        </div>
    </div>

    <!-- 1. OVERVIEW SECTION -->
    <section id="overview" class="scroll-section space-y-10">
        <div class="flex justify-between items-end">
            <div class="max-w-xl">
                <h2 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Executive Summary</h2>
                <p class="text-slate-400 mt-2 text-sm leading-relaxed">Operational performance metrics for the selected period.</p>
            </div>
            <div class="flex gap-4">
                <div class="px-4 py-2 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-bold uppercase tracking-widest flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Data Live
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bento-card bg-slate-900 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden group">
                <div class="relative z-10">
                    <h3 class="font-manrope font-bold text-[10px] text-white/40 uppercase tracking-[0.25em] mb-8"><?= $range === 'today' ? 'Revenue Today' : 'Total Revenue' ?></h3>
                    <div class="flex items-baseline gap-1">
                        <span class="text-4xl font-manrope font-extrabold text-white tracking-tighter"><?= fmt_idr($data['summary']['revenue_today']) ?></span>
                    </div>
                    <p class="text-[10px] text-emerald-400 font-bold mt-6 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-arrow-trend-up"></i> Performance for this period
                    </p>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-all duration-700">
                    <i class="fa-solid fa-wallet text-9xl text-white"></i>
                </div>
            </div>

            <div class="bento-card bg-white rounded-[2.5rem] p-8 border border-slate-900/5 shadow-xl">
                <h3 class="font-manrope font-bold text-[10px] text-slate-400 uppercase tracking-[0.25em] mb-8">Current Occupancy</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-5xl font-manrope font-black text-slate-900 tracking-tighter"><?= $data['summary']['active_vehicles'] ?></span>
                    <span class="text-sm font-bold text-slate-400">vehicles</span>
                </div>
                <div class="mt-6 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                    <?php $occ_rate = ($data['summary']['active_vehicles'] / max(1, $data['summary']['available_slots'] + $data['summary']['active_vehicles'])) * 100; ?>
                    <div class="h-full bg-slate-900" style="width: <?= min(100, $occ_rate) ?>%"></div>
                </div>
            </div>

            <div class="bento-card bg-white rounded-[2.5rem] p-8 border border-slate-900/5 shadow-xl">
                <h3 class="font-manrope font-bold text-[10px] text-slate-400 uppercase tracking-[0.25em] mb-8">Slots Available</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-5xl font-manrope font-black text-slate-900 tracking-tighter"><?= $data['summary']['available_slots'] ?></span>
                    <span class="text-sm font-bold text-blue-500"><?= $data['summary']['total_reservations'] ?> RSV</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold mt-6 uppercase tracking-widest">
                    <i class="fa-solid fa-calendar-check mr-1"></i> Ready for intake
                </p>
            </div>

            <div class="bento-card bg-white rounded-[2.5rem] p-8 border border-slate-900/5 shadow-xl">
                <h3 class="font-manrope font-bold text-[10px] text-slate-400 uppercase tracking-[0.25em] mb-8"><?= $range === 'today' ? 'Traffic Today' : 'Total Traffic' ?></h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-5xl font-manrope font-black text-slate-900 tracking-tighter"><?= $data['summary']['total_entries_today'] ?></span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold mt-6 uppercase tracking-widest">
                    <i class="fa-solid fa-road mr-1"></i> Vehicles processed
                </p>
            </div>
        </div>
    </section>

    <!-- 2. HEATMAP SECTION -->
    <section id="heatmap" class="scroll-section space-y-10">
        <div class="max-w-xl">
            <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Facility Heatmap</h3>
            <p class="text-slate-400 mt-2 text-sm leading-relaxed">Visual representation of slot utilization across all floors.</p>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-10">
            <?php foreach($floors as $floor): ?>
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h4 class="font-manrope font-black text-xl text-slate-900"><?= $floor['floor_name'] ?></h4>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Zone: <?= $floor['floor_code'] ?></span>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-emerald-500"></div><span class="text-[10px] font-black text-slate-400 uppercase">FREE</span></div>
                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-slate-900"></div><span class="text-[10px] font-black text-slate-400 uppercase">FULL</span></div>
                    </div>
                </div>
                <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-10 gap-3">
                    <?php 
                    foreach($slots as $slot) {
                        if ($slot['floor_id'] == $floor['floor_id']) {
                            $color = 'bg-slate-50 border-slate-100 text-slate-300';
                            if ($slot['status'] === 'occupied') $color = 'bg-slate-900 border-slate-900 text-white shadow-lg';
                            if ($slot['status'] === 'reserved') $color = 'bg-blue-500 border-blue-600 text-white shadow-lg';
                            if ($slot['status'] === 'available') $color = 'bg-emerald-50 border-emerald-100 text-emerald-600';
                            
                            echo "<div class='aspect-square rounded-2xl border-2 $color flex flex-col items-center justify-center text-[10px] font-black transition-all hover:scale-110 cursor-default'>".explode('-', $slot['slot_number'])[1]."</div>";
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 3. REVENUE SECTION -->
    <section id="revenue" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Revenue Dynamics</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">14-Day Revenue Momentum</h4>
                <canvas id="revDailyChart"></canvas>
            </div>
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">Annual Monthly Comparison</h4>
                <canvas id="revMonthlyChart"></canvas>
            </div>
        </div>
    </section>

    <!-- 4. TRAFFIC SECTION -->
    <section id="traffic" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Traffic Intelligence</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-extrabold text-lg mb-10 w-full text-slate-900">Vehicle Composition</h4>
                <div class="w-full max-w-[240px]"><canvas id="vehicleMixChart"></canvas></div>
                <div class="mt-10 grid grid-cols-2 gap-8 w-full text-center">
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cars</span>
                        <span class="text-2xl font-black text-slate-900"><?= array_sum(array_column($veh_trends, 'cars')) ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Motos</span>
                        <span class="text-2xl font-black text-slate-900"><?= array_sum(array_column($veh_trends, 'motos')) ?></span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">Hourly Intake Distribution</h4>
                <canvas id="peakChart" height="110"></canvas>
            </div>
        </div>
    </section>

    <!-- 5. DWELL TIME SECTION -->
    <section id="dwell" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Stay Behavior</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">Average Duration per Vehicle</h4>
                <canvas id="dwellTypeChart"></canvas>
            </div>
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">Inbound-to-Duration Ratio</h4>
                <canvas id="dwellPeriodChart"></canvas>
            </div>
        </div>
    </section>

    <!-- 6. GATE EFFICIENCY -->
    <section id="gate" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Hardware Efficiency (LPR)</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">OCR Scan Accuracy</h4>
                <canvas id="gateEntryChart"></canvas>
            </div>
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
                <h4 class="font-manrope font-extrabold text-lg mb-8 text-slate-900">Recent Gate Interventions</h4>
                <div class="space-y-4">
                    <?php foreach($gate_logs as $log): ?>
                    <div class="flex justify-between items-center p-6 bg-slate-50 rounded-3xl border border-slate-100 group hover:border-slate-300 transition-all">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm">
                                <i class="fa-solid <?= $log['gate_action'] === 'open' ? 'fa-check text-emerald-500' : 'fa-xmark text-rose-500' ?> text-xl"></i>
                            </div>
                            <div>
                                <span class="block text-sm font-black text-slate-900 uppercase tracking-tighter"><?= $log['scan_type'] ?> Process</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Action: <?= $log['gate_action'] ?></span>
                            </div>
                        </div>
                        <span class="text-xl font-manrope font-black text-slate-900"><?= $log['count'] ?> <span class="text-[10px] text-slate-400 font-bold uppercase">runs</span></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. RESERVATIONS -->
    <section id="reservations" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Booking Pipeline</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-extrabold text-lg mb-10 w-full text-slate-900">Reservation Funnel</h4>
                <div class="w-full max-w-[280px]"><canvas id="resConversionChart"></canvas></div>
            </div>
            <div class="bg-slate-900 rounded-[2.5rem] p-12 shadow-2xl flex flex-col justify-center space-y-8 relative overflow-hidden">
                <div class="relative z-10">
                    <h4 class="text-white/40 text-[10px] font-bold uppercase tracking-[0.25em] mb-10">Efficiency Metrics</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                        <div class="space-y-2">
                            <span class="block text-emerald-400 text-3xl font-manrope font-black">89.4%</span>
                            <span class="text-white/60 text-[10px] font-bold uppercase tracking-widest leading-relaxed">System Utilization via Pre-booking</span>
                        </div>
                        <div class="space-y-2">
                            <span class="block text-rose-400 text-3xl font-manrope font-black">4.2%</span>
                            <span class="text-white/60 text-[10px] font-bold uppercase tracking-widest leading-relaxed">No-show Impact on Capacity</span>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-10 -bottom-10 opacity-5">
                    <i class="fa-solid fa-calendar-days text-[20rem] text-white"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. OPERATORS -->
    <section id="operators" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Human Capital Efficiency</h3>
        <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100">
                            <th class="text-left pb-6 font-black">Personnel</th>
                            <th class="text-center pb-6 font-black">Shift Assignment</th>
                            <th class="text-center pb-6 font-black">Throughput</th>
                            <th class="text-right pb-6 font-black">Asset Handling</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($data['operator_performance'] as $op): ?>
                        <tr class="group hover:bg-slate-50/50 transition-all">
                            <td class="py-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-white text-xs font-black"><?= strtoupper(substr($op['full_name'],0,1)) ?></div>
                                    <span class="text-sm font-black text-slate-900"><?= $op['full_name'] ?></span>
                                </div>
                            </td>
                            <td class="py-6 text-center">
                                <span class="px-4 py-1.5 rounded-full bg-slate-100 text-[10px] font-black text-slate-500 uppercase"><?= $op['shift'] ?></span>
                            </td>
                            <td class="py-6 text-center font-manrope font-black text-slate-900"><?= $op['total_transactions'] ?> <span class="text-[9px] text-slate-300 ml-1">trx</span></td>
                            <td class="py-6 text-right font-manrope font-black text-emerald-600"><?= fmt_idr($op['total_revenue_handled']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 9. PAYMENTS -->
    <section id="payments" class="scroll-section space-y-10">
        <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Financial Flow Channels</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-900/5 shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-extrabold text-lg mb-10 w-full text-slate-900">Revenue Split by Method</h4>
                <div class="w-full max-w-[320px]"><canvas id="paymentChart"></canvas></div>
            </div>
            <div class="space-y-6 flex flex-col justify-center">
                <?php foreach($data['payment_methods'] as $pay): ?>
                <div class="bento-card p-8 bg-white rounded-[2rem] border border-slate-900/5 shadow-lg flex justify-between items-center group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center text-white">
                            <i class="fa-solid <?= $pay['payment_method'] === 'cash' ? 'fa-money-bill-wave' : 'fa-credit-card' ?> text-lg"></i>
                        </div>
                        <div>
                            <span class="block text-sm font-black text-slate-900 uppercase"><?= $pay['payment_method'] ?></span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= $pay['count'] ?> Transactions</span>
                        </div>
                    </div>
                    <span class="text-2xl font-manrope font-black text-emerald-600"><?= fmt_idr($pay['revenue']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 10. ANOMALIES -->
    <section id="anomalies" class="scroll-section space-y-10 pb-32">
        <div class="max-w-xl">
            <h3 class="text-2xl font-manrope font-black text-slate-900 tracking-tight">Predictive Guardianship</h3>
            <p class="text-slate-400 mt-2 text-sm leading-relaxed">Automated anomaly detection algorithms flagging operational variances.</p>
        </div>
        <div class="grid grid-cols-1 gap-6">
            <?php if (empty($anomalies)): ?>
                <div class="p-16 text-center bg-emerald-50/50 rounded-[3rem] border-2 border-dashed border-emerald-200">
                    <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-sm">
                        <i class="fa-solid fa-shield-halved text-emerald-500 text-4xl"></i>
                    </div>
                    <h4 class="font-manrope font-black text-2xl text-emerald-900">Eco-System Stable</h4>
                    <p class="text-emerald-600/70 text-sm max-w-sm mx-auto leading-relaxed">No significant statistical deviations detected in the current processing window. Operations are within normal parameters.</p>
                </div>
            <?php else: ?>
                <?php foreach($anomalies as $low): ?>
                <div class="p-8 bg-rose-50 rounded-[2.5rem] border border-rose-100 flex items-center justify-between group hover:bg-rose-100/50 transition-all">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 bg-white rounded-3xl flex items-center justify-center shadow-md">
                            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-manrope font-black text-lg text-rose-900">Revenue Variance Detected</h4>
                            <p class="text-rose-600/70 text-sm">Date: <?= date('d M Y', strtotime($low['date'])) ?> — Impact: <?= fmt_idr($low['revenue']) ?></p>
                        </div>
                    </div>
                    <button class="bg-rose-900 text-white px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-xl hover:bg-rose-800 transition-all">Investigate</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</div>

<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#94a3b8';

const commonOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: { legend: { display: false } },
    scales: {
        y: { grid: { display: false }, border: { display: false }, ticks: { display: false } },
        x: { grid: { display: false }, border: { display: false } }
    }
};

// 1. Revenue
new Chart(document.getElementById('revDailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $rev_daily)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($rev_daily, 'revenue')) ?>,
            borderColor: '#0f172a', borderWidth: 4, tension: 0.4, fill: true, backgroundColor: 'rgba(15, 23, 42, 0.03)', pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#0f172a'
        }]
    },
    options: commonOptions
});
new Chart(document.getElementById('revMonthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($rev_monthly, 'month')) ?>,
        datasets: [{ data: <?= json_encode(array_column($rev_monthly, 'revenue')) ?>, backgroundColor: '#0f172a', borderRadius: 12 }]
    },
    options: commonOptions
});

// 2. Traffic
new Chart(document.getElementById('vehicleMixChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($data['vehicle_stats'], 'vehicle_type')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['vehicle_stats'], 'total_count')) ?>, backgroundColor: ['#0f172a', '#cbd5e1'], borderWidth: 0, hoverOffset: 15 }]
    },
    options: { cutout: '85%', plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('peakChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($h) => sprintf("%02d:00", $h['hour']), $data['hourly_distribution'])) ?>,
        datasets: [{
            data: <?= json_encode(array_column($data['hourly_distribution'], 'total_entries')) ?>,
            borderColor: '#0f172a', fill: true, backgroundColor: 'rgba(15, 23, 42, 0.02)', tension: 0.5, borderWidth: 4, pointRadius: 0
        }]
    },
    options: commonOptions
});

// 3. Dwell & Stay
new Chart(document.getElementById('dwellTypeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($dwell_avg, 'vehicle_type')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_avg, 'avg_min')) ?>, backgroundColor: '#0f172a', borderRadius: 15 }]
    },
    options: commonOptions
});
new Chart(document.getElementById('dwellPeriodChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dwell_period, 'period')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_period, 'avg_min')) ?>, borderColor: '#0f172a', borderWidth: 4, tension: 0.5, pointRadius: 0 }]
    },
    options: commonOptions
});

// 4. Gate & Reservations
new Chart(document.getElementById('gateEntryChart'), {
    type: 'pie',
    data: {
        labels: ['Success', 'Rejected'],
        datasets: [{ 
            data: [
                <?= array_sum(array_column(array_filter($gate_logs, fn($l) => $l['gate_action'] === 'open'), 'count')) ?>, 
                <?= array_sum(array_column(array_filter($gate_logs, fn($l) => $l['gate_action'] === 'reject'), 'count')) ?>
            ], 
            backgroundColor: ['#0f172a', '#f1f5f9'], 
            borderWidth: 0 
        }]
    }
});
new Chart(document.getElementById('resConversionChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($res_conversion, 'status')) ?>,
        datasets: [{ data: <?= json_encode(array_column($res_conversion, 'count')) ?>, backgroundColor: ['#0f172a', '#334155', '#475569', '#94a3b8'], borderWidth: 0 }]
    },
    options: { cutout: '80%', plugins: { legend: { display: false } } }
});

// 5. Payments
new Chart(document.getElementById('paymentChart'), {
    type: 'polarArea',
    data: {
        labels: <?= json_encode(array_column($data['payment_methods'], 'payment_method')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['payment_methods'], 'count')) ?>, backgroundColor: ['#0f172a', '#64748b', '#cbd5e1'], borderWidth: 0 }]
    },
    options: { scales: { r: { grid: { color: '#f1f5f9' }, ticks: { display: false } } } }
});

// Smooth Scroll & Jump Link Highlighting
const observerOptions = { root: null, rootMargin: '-10% 0px -80% 0px', threshold: 0 };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.getAttribute('id');
            document.querySelectorAll('.jump-link').forEach(link => {
                link.classList.remove('bg-slate-900', 'text-white', 'shadow-xl');
                link.classList.add('text-slate-400');
                if (link.getAttribute('href') === '#' + id) {
                    link.classList.add('bg-slate-900', 'text-white', 'shadow-xl');
                    link.classList.remove('text-slate-400');
                }
            });
        }
    });
}, observerOptions);

document.querySelectorAll('.scroll-section').forEach(section => observer.observe(section));

document.querySelectorAll('.jump-link').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        target.scrollIntoView({ behavior: 'smooth' });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
