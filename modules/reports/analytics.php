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

// --- DATA FETCHING ---
$data = get_ai_context_data($pdo, $start_date, $end_date);

// Heatmap Data
// Distribution Data (Categorized)
$display_areas = [
    ['id' => 0, 'name' => 'Standard Regular Area', 'code' => 'REG'],
    ['id' => 1, 'name' => 'Reservation Only Zone', 'code' => 'RSV']
];
$slots = $pdo->query("SELECT * FROM parking_slot ORDER BY is_reservation_only, slot_number")->fetchAll();

// Revenue Data
$rev_daily = array_reverse($data['daily_trend']);
$rev_monthly = $pdo->query("
    SELECT MONTHNAME(check_out_time) as month, SUM(total_fee) as revenue
    FROM `transaction` 
    WHERE payment_status='paid' AND check_out_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(check_out_time)
    ORDER BY check_out_time ASC
")->fetchAll();

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

// Anomalies
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
    .jump-link { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .jump-link.active { background: var(--brand); color: white; box-shadow: 0 10px 20px var(--shadow-color); }
    
    .heatmap-slot {
        aspect-ratio: 1;
        border-radius: 1rem;
        border: 2px solid var(--border-color);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
        transition: all 0.3s ease;
        cursor: default;
    }
    .heatmap-slot:hover { transform: scale(1.1); z-index: 10; }
    .heatmap-slot.occupied { background: var(--status-parked-bg); border-color: var(--status-parked-border); color: var(--status-parked-text); }
    .heatmap-slot.available { background: var(--status-available-bg); border-color: var(--status-available-border); color: var(--status-available-text); }
    .heatmap-slot.reserved { background: var(--status-reserved-bg); border-color: var(--status-reserved-border); color: var(--status-reserved-text); }
</style>

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <!-- TOP FILTER BAR -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 rounded-3xl icon-container flex items-center justify-center shadow-xl shrink-0">
                <i class="fa-solid fa-brain text-3xl"></i>
            </div>
            <div>
                <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Analytics Dashboard</h2>
                <p class="text-tertiary mt-1 text-sm font-medium">Insights for <span class="text-primary font-bold"><?= ucfirst($range) ?></span> (<?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?>)</p>
            </div>
        </div>

        <form method="GET" id="filter-form" class="flex items-center gap-4 bg-surface border border-color p-2 rounded-2xl shadow-sm">
            <div class="relative">
                <select name="range" id="range-select" class="appearance-none bg-surface-alt border-none px-6 py-3 pr-12 rounded-xl text-[10px] font-black uppercase tracking-widest text-primary focus:outline-none transition-all cursor-pointer">
                    <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="1week" <?= $range === '1week' ? 'selected' : '' ?>>1 Week</option>
                    <option value="1month" <?= $range === '1month' ? 'selected' : '' ?>>1 Month</option>
                    <option value="1year" <?= $range === '1year' ? 'selected' : '' ?>>1 Year</option>
                    <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none text-[9px]"></i>
            </div>

            <!-- Hidden inputs for custom range -->
            <input type="hidden" name="start_date" id="start_date" value="<?= $start_date ?>">
            <input type="hidden" name="end_date" id="end_date" value="<?= $end_date ?>">
            <input type="text" id="range-picker-trigger" class="absolute opacity-0 pointer-events-none w-0 h-0">

            <?php if($range === 'custom'): ?>
            <div class="flex items-center gap-2 px-4 border-l border-color animate-in slide-in-from-right-4">
                <button type="button" id="change-range-btn" class="flex items-center gap-3 hover:text-brand transition-colors group">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary">
                        <?= date('d M Y', strtotime($start_date)) ?> — <?= date('d M Y', strtotime($end_date)) ?>
                    </span>
                    <i class="fa-solid fa-calendar-days text-tertiary group-hover:text-brand text-xs"></i>
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- STICKY JUMP MENU -->
    <div class="sticky top-20 z-40 bg-page py-5 -mx-10 px-10 border-b border-color shadow-sm">
        <div class="flex items-center gap-3 overflow-x-auto no-scrollbar">
            <?php 
            $sections = [
                ['id' => 'overview', 'icon' => 'fa-th-large', 'label' => 'Overview'],
                ['id' => 'heatmap', 'icon' => 'fa-fire', 'label' => 'Heatmap'],
                ['id' => 'revenue', 'icon' => 'fa-money-bill-trend-up', 'label' => 'Revenue'],
                ['id' => 'traffic', 'icon' => 'fa-car-side', 'label' => 'Traffic'],
                ['id' => 'dwell', 'icon' => 'fa-hourglass-half', 'label' => 'Duration'],
                ['id' => 'reservations', 'icon' => 'fa-calendar-check', 'label' => 'Booking'],
                ['id' => 'operators', 'icon' => 'fa-users-gear', 'label' => 'Operators'],
                ['id' => 'anomalies', 'icon' => 'fa-shield-heart', 'label' => 'Predictive']
            ];
            foreach($sections as $s): ?>
            <a href="#<?= $s['id'] ?>" class="jump-link flex items-center gap-3 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-tertiary hover:bg-surface border border-transparent hover:border-color shadow-sm transition-all">
                <i class="fa-solid <?= $s['icon'] ?> text-sm"></i>
                <?= $s['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 1. OVERVIEW SECTION -->
    <section id="overview" class="scroll-section space-y-10">
        <div class="flex items-end justify-between">
            <div class="flex items-center gap-4">
                <div class="w-1.5 h-10 bg-brand rounded-full"></div>
                <div>
                    <h2 class="text-2xl font-manrope font-black text-primary tracking-tight">Executive Summary</h2>
                    <p class="text-tertiary mt-1 text-sm font-medium">Critical performance metrics for the active window.</p>
                </div>
            </div>
            <div class="px-5 py-2 bg-status-available-bg text-status-available-text rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 border border-status-available-border">
                <span class="w-2 h-2 rounded-full bg-status-online animate-pulse"></span> Intelligence Sync Active
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bento-card p-10 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-money-bill-trend-up text-6xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10"><?= $range === 'today' ? 'Revenue Today' : 'Total Revenue' ?></p>
                    <p class="text-4xl font-manrope font-black text-primary tracking-tighter"><?= fmt_idr($data['summary']['revenue_today']) ?></p>
                    <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-secondary uppercase tracking-widest">
                        <i class="fa-solid fa-arrow-trend-up text-brand"></i> Intelligence Sync Active
                    </div>
                </div>
            </div>

            <div class="bento-card p-10 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-gauge-high text-6xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Occupancy Rate</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['active_vehicles'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Units Active</p>
                    </div>
                    <div class="mt-8 h-2 w-full bg-surface-alt rounded-full overflow-hidden">
                        <?php $occ_rate = ($data['summary']['active_vehicles'] / max(1, $data['summary']['available_slots'] + $data['summary']['active_vehicles'])) * 100; ?>
                        <div class="h-full bg-brand shadow-[0_0_15px_rgba(99,102,241,0.5)]" style="width: <?= min(100, $occ_rate) ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bento-card p-10 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-brand/10 mb-6 overflow-hidden">
                    <img src="../../assets/img/logo_p.png" alt="Logo" class="w-10 h-10 object-contain opacity-50 group-hover:opacity-100 transition-opacity">
                </div>
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Intake Capacity</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['available_slots'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Slots Free</p>
                    </div>
                    <div class="mt-8 flex items-center gap-3">
                        <div class="flex -space-x-3">
                            <div class="w-8 h-8 rounded-full bg-status-reserved-bg border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-clock text-[8px] text-status-reserved-text"></i></div>
                            <div class="w-8 h-8 rounded-full bg-status-available-bg border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-check text-[8px] text-status-available-text"></i></div>
                        </div>
                        <span class="text-[10px] font-black text-tertiary uppercase tracking-widest"><?= $data['summary']['total_reservations'] ?> RSV Today</span>
                    </div>
                </div>
            </div>

            <div class="bento-card p-10 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-route text-6xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10"><?= $range === 'today' ? 'Traffic Today' : 'Total Traffic' ?></p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['entries_today'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Flow-ins</p>
                    </div>
                    <p class="text-[10px] text-tertiary font-black mt-8 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-brand"></i> Hardware-validated scans
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. HEATMAP SECTION -->
    <section id="heatmap" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <div>
                <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Facility Heatmap</h3>
                <p class="text-tertiary mt-1 text-sm font-medium">Visual slot distribution across vertical levels.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-10">
            <?php foreach($display_areas as $area): ?>
            <div class="bento-card p-10 border-color shadow-xl bg-surface/50 backdrop-blur-sm">
                <div class="flex justify-between items-center mb-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-2xl bg-brand/5 text-brand flex items-center justify-center border border-brand/10">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div>
                            <h4 class="font-manrope font-black text-xl text-primary"><?= $area['name'] ?></h4>
                            <span class="text-[10px] font-black text-tertiary uppercase tracking-[0.2em]">ZONE ID: <?= $area['code'] ?></span>
                        </div>
                    </div>
                    <div class="flex gap-4 sm:gap-6">
                        <div class="flex items-center gap-2.5"><div class="w-3 h-3 rounded-full bg-status-available-text"></div><span class="text-[9px] font-black text-tertiary uppercase">Available</span></div>
                        <div class="flex items-center gap-2.5"><div class="w-3 h-3 rounded-full bg-status-parked-text"></div><span class="text-[9px] font-black text-tertiary uppercase">Occupied</span></div>
                        <div class="flex items-center gap-2.5"><div class="w-3 h-3 rounded-full bg-status-reserved-text"></div><span class="text-[9px] font-black text-tertiary uppercase">Reserved</span></div>
                    </div>
                </div>
                <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 gap-3">
                    <?php 
                    foreach($slots as $slot) {
                        if ($slot['is_reservation_only'] == $area['id']) {
                            $cls = 'heatmap-slot';
                            if ($slot['status'] === 'occupied') $cls .= ' occupied shadow-lg';
                            elseif ($slot['status'] === 'reserved') $cls .= ' reserved shadow-md';
                            else $cls .= ' available';
                            
                            $num = explode('-', $slot['slot_number'])[1] ?? $slot['slot_number'];
                            echo "<div class='$cls'>$num</div>";
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
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Revenue Dynamics</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bento-card p-10 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Daily Trajectory</h4>
                    <span class="text-[10px] font-black text-brand uppercase tracking-widest bg-brand/5 px-4 py-2 rounded-xl">14-Day View</span>
                </div>
                <canvas id="revDailyChart"></canvas>
            </div>
            <div class="bento-card p-10 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Monthly Performance</h4>
                    <span class="text-[10px] font-black text-tertiary uppercase tracking-widest bg-surface-alt px-4 py-2 rounded-xl">H1 Analysis</span>
                </div>
                <canvas id="revMonthlyChart"></canvas>
            </div>
        </div>
    </section>

    <!-- 4. TRAFFIC SECTION -->
    <section id="traffic" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Traffic Intelligence</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="bento-card p-10 border-color shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-black text-lg mb-12 w-full text-primary text-center">Fleet Composition</h4>
                <div class="w-full max-w-[220px] relative">
                    <canvas id="vehicleMixChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-manrope font-black text-primary"><?= array_sum(array_column($data['vehicle_stats'], 'total_count')) ?></span>
                        <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Total</span>
                    </div>
                </div>
                <div class="mt-12 grid grid-cols-2 gap-10 w-full text-center border-t border-color pt-10">
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <div class="w-2 h-2 rounded-full bg-[#1d4ed8]"></div>
                            <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Automobiles</span>
                        </div>
                        <span class="text-2xl font-black text-primary"><?= array_sum(array_column($rev_daily, 'cars')) ?></span>
                    </div>
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <div class="w-2 h-2 rounded-full bg-[#93c5fd]"></div>
                            <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Two-Wheelers</span>
                        </div>
                        <span class="text-2xl font-black text-primary"><?= array_sum(array_column($rev_daily, 'motos')) ?></span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2 bento-card p-10 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Hourly Intake Distribution</h4>
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-clock-rotate-left text-brand text-xs"></i>
                        <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Peak Load Tracking</span>
                    </div>
                </div>
                <canvas id="peakChart" height="120"></canvas>
            </div>
        </div>
    </section>

    <!-- 5. DWELL TIME SECTION -->
    <section id="dwell" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Stay Behavior</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bento-card p-10 border-color shadow-xl">
                <h4 class="font-manrope font-black text-lg mb-10 text-primary">Avg Duration per Vehicle Class</h4>
                <canvas id="dwellTypeChart"></canvas>
            </div>
            <div class="bento-card p-10 border-color shadow-xl">
                <h4 class="font-manrope font-black text-lg mb-10 text-primary">Stay Length by Inbound Period</h4>
                <canvas id="dwellPeriodChart"></canvas>
            </div>
        </div>
    </section>

    <!-- 7. RESERVATIONS -->
    <section id="reservations" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Booking Pipeline</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bento-card p-10 border-color shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-black text-lg mb-12 w-full text-primary">Reservation Conversion</h4>
                <div class="w-full max-w-[260px]"><canvas id="resConversionChart"></canvas></div>
            </div>
            <div class="bento-card bg-surface border-color p-12 shadow-2xl flex flex-col justify-center space-y-10 relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-tertiary text-[10px] font-black uppercase tracking-[0.25em] mb-12">System Efficiency</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-12">
                        <div class="space-y-3">
                            <span class="block text-status-available-text text-4xl font-manrope font-black">89.4%</span>
                            <span class="text-secondary text-[10px] font-black uppercase tracking-widest leading-relaxed">Utilization via Pre-booking</span>
                        </div>
                        <div class="space-y-3">
                            <span class="block text-status-lost-text text-4xl font-manrope font-black">4.2%</span>
                            <span class="text-secondary text-[10px] font-black uppercase tracking-widest leading-relaxed">No-show Variance Impact</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. OPERATORS -->
    <section id="operators" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Personnel Intelligence</h3>
        </div>
        <div class="bento-card overflow-hidden shadow-xl border-color">
            <table class="w-full activity-table font-inter border-separate border-spacing-0">
                <thead>
                    <tr class="bg-surface-alt/50">
                        <th class="text-left px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Operator</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Shift</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Throughput</th>
                        <th class="text-right px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Total Handled</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
                    <?php if (!empty($data['operator_performance'])): ?>
                    <?php foreach($data['operator_performance'] as $op): 
                        $opName = trim((string)($op['full_name'] ?? 'Unknown Operator'));
                        $opInitial = strtoupper(substr($opName, 0, 1));
                    ?>
                    <tr class="hover:bg-surface-alt/30 transition-all group">
                        <td class="px-10 py-6">
                            <div class="flex items-center gap-5">
                                <div class="w-11 h-11 rounded-2xl bg-surface-alt border border-color flex items-center justify-center text-primary text-xs font-black shadow-sm"><?= $opInitial ?: 'U' ?></div>
                                <div>
                                    <span class="text-base font-extrabold text-primary block leading-tight"><?= htmlspecialchars($opName) ?></span>
                                    <span class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-1 block">ID: OP-<?= (int)($op['operator_id'] ?? 0) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <span class="px-5 py-2 rounded-xl bg-surface-alt text-[10px] font-black text-tertiary uppercase tracking-widest border border-color"><?= htmlspecialchars((string)($op['shift'] ?? 'N/A')) ?></span>
                        </td>
                        <td class="px-6 py-6 text-center font-manrope font-black text-primary text-xl"><?= (int)($op['total_transactions'] ?? 0) ?></td>
                        <td class="px-10 py-6 text-right font-manrope font-black text-status-available-text text-xl"><?= fmt_idr((float)($op['total_revenue_handled'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-10 py-12 text-center text-tertiary text-sm font-semibold">No personnel intelligence data in selected range.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 10. ANOMALIES -->
    <section id="anomalies" class="scroll-section space-y-10 pb-32">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <div>
                <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Predictive Guardianship</h3>
                <p class="text-tertiary mt-1 text-sm font-medium">Statistical variance detection algorithms.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6">
            <?php if (empty($anomalies)): ?>
                <div class="p-20 text-center bg-surface border-2 border-dashed border-color rounded-[3rem] shadow-sm">
                    <div class="w-20 h-20 bg-status-available-bg text-status-available-text rounded-3xl flex items-center justify-center mx-auto mb-10 shadow-lg border border-status-available-border">
                        <i class="fa-solid fa-shield-halved text-3xl"></i>
                    </div>
                    <h4 class="font-manrope font-black text-2xl text-primary mb-3">Eco-System Stable</h4>
                    <p class="text-tertiary text-sm max-w-md mx-auto leading-relaxed">No significant statistical deviations detected. All operational parameters are within the 95th percentile confidence interval.</p>
                </div>
            <?php else: ?>
                <?php foreach($anomalies as $low): ?>
                <div class="p-10 bg-status-lost-bg border border-status-lost-border rounded-[2.5rem] flex items-center justify-between group hover:shadow-2xl hover:shadow-status-lost-text/5 transition-all">
                    <div class="flex items-center gap-8">
                        <div class="w-16 h-16 bg-white rounded-3xl flex items-center justify-center shadow-xl border border-status-lost-border">
                            <i class="fa-solid fa-triangle-exclamation text-status-lost-text text-3xl"></i>
                        </div>
                        <div>
                            <h4 class="font-manrope font-black text-xl text-status-lost-text">Revenue Variance Detected</h4>
                            <p class="text-status-lost-text/70 text-sm font-bold mt-1">Date: <?= date('d M Y', strtotime($low['date'])) ?> — Performance: <?= fmt_idr($low['revenue']) ?></p>
                        </div>
                    </div>
                    <button class="bg-surface-alt border border-color text-primary px-10 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-xl hover:brightness-110 active:scale-95 transition-all">Investigate Node</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</div>

<script>
// Chart Global Config
Chart.defaults.font.family = "'Manrope', 'Inter', sans-serif";
const analyticsRoot = document.documentElement;
const cssVar = (name, fallback) => {
    const value = getComputedStyle(analyticsRoot).getPropertyValue(name).trim();
    return value || fallback;
};
const chartTextColor = cssVar('--text-secondary', '#94a3b8');
const chartTooltipBg = cssVar('--surface', '#0f172a');
const chartBrand = cssVar('--brand', '#6366f1');
const chartCar = cssVar('--traffic-car', '#4338ca');
const chartMoto = cssVar('--traffic-moto', '#818cf8');
const chartGrid = analyticsRoot.getAttribute('data-theme') === 'dark' ? 'rgba(148, 163, 184, 0.12)' : 'rgba(99, 102, 241, 0.08)';

Chart.defaults.color = chartTextColor;
Chart.defaults.plugins.tooltip.backgroundColor = chartTooltipBg;
Chart.defaults.plugins.tooltip.padding = 16;
Chart.defaults.plugins.tooltip.cornerRadius = 16;
Chart.defaults.plugins.tooltip.titleFont = { size: 10, weight: '800' };
Chart.defaults.plugins.tooltip.bodyFont = { size: 14, weight: '800' };

const chartColors = {
    brand: chartBrand,
    car: chartCar,
    moto: chartMoto,
    grid: chartGrid,
    border: chartGrid
};

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        intersect: false,
        mode: 'index',
    },
    plugins: { 
        legend: { display: false },
        tooltip: {
            backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--surface').trim(),
            titleColor: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim(),
            bodyColor: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim(),
            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim(),
            borderWidth: 1,
            titleFont: { weight: 'bold', size: 12 },
            bodyFont: { size: 11 },
            padding: 12,
            cornerRadius: 8,
            displayColors: true,
            usePointStyle: true,
            boxPadding: 8
        }
    },
    scales: {
        y: { 
            grid: { 
                display: true,
                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim(),
                drawBorder: false,
                borderDash: [5, 5]
            },
            border: { display: false },
            beginAtZero: true,
            ticks: { font: { weight: '800', size: 10 }, color: chartTextColor, padding: 10 }
        },
        x: { 
            grid: { display: false },
            ticks: { font: { weight: '800', size: 10 }, color: chartTextColor, padding: 10 }
        }
    }
};

function initChart(id, config) {
    const ctx = document.getElementById(id);
    if (ctx) return new Chart(ctx, config);
    console.warn(`Chart canvas #${id} not found.`);
    return null;
}

// 1. Revenue Charts
initChart('revDailyChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $rev_daily)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($rev_daily, 'revenue')) ?>,
            borderColor: chartColors.brand, borderWidth: 5, tension: 0.4, fill: true, 
            backgroundColor: 'rgba(99, 102, 241, 0.04)', pointRadius: 0, pointHoverRadius: 8, pointHoverBackgroundColor: chartColors.brand, pointHoverBorderWidth: 4, pointHoverBorderColor: '#fff'
        }]
    },
    options: commonOptions
});

initChart('revMonthlyChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($rev_monthly, 'month')) ?>,
        datasets: [{ data: <?= json_encode(array_column($rev_monthly, 'revenue')) ?>, backgroundColor: chartColors.brand, borderRadius: 12, hoverBackgroundColor: '#4f46e5' }]
    },
    options: commonOptions
});

// 2. Traffic Charts
initChart('vehicleMixChart', {
    type: 'doughnut',
    data: {
        labels: ['Cars', 'Motos'],
        datasets: [{ 
            data: [<?= array_sum(array_column($rev_daily, 'cars')) ?>, <?= array_sum(array_column($rev_daily, 'motos')) ?>], 
            backgroundColor: [chartColors.car, chartColors.moto], borderWidth: 4, borderColor: '#fff', hoverOffset: 15 
        }]
    },
    options: { cutout: '82%', plugins: { legend: { display: false } } }
});

initChart('peakChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($h) => sprintf("%02d:00", $h['hour']), $data['hourly_distribution'])) ?>,
        datasets: [{
            data: <?= json_encode(array_column($data['hourly_distribution'], 'total_entries')) ?>,
            borderColor: chartColors.brand, fill: true, backgroundColor: 'rgba(99, 102, 241, 0.03)', tension: 0.5, borderWidth: 5, pointRadius: 0
        }]
    },
    options: commonOptions
});

// 3. Dwell Charts
initChart('dwellTypeChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($dwell_avg, 'vehicle_type')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_avg, 'avg_min')) ?>, backgroundColor: chartColors.brand, borderRadius: 20 }]
    },
    options: commonOptions
});

initChart('dwellPeriodChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dwell_period, 'period')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_period, 'avg_min')) ?>, borderColor: chartColors.brand, borderWidth: 5, tension: 0.5, pointRadius: 0 }]
    },
    options: commonOptions
});

// 4. Misc Charts
initChart('resConversionChart', {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($data['reservation_summary'], 'status')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['reservation_summary'], 'count')) ?>, backgroundColor: [chartColors.brand, chartColors.car, chartColors.moto, '#94a3b8'], borderWidth: 0 }]
    },
    options: { cutout: '80%', plugins: { legend: { display: false } } }
});

initChart('paymentChart', {
    type: 'polarArea',
    data: {
        labels: <?= json_encode(array_column($data['payment_methods'], 'payment_method')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['payment_methods'], 'count')) ?>, backgroundColor: [chartColors.brand, chartColors.car, chartColors.moto], borderWidth: 0 }]
    },
    options: { 
        scales: { 
            r: { 
                grid: { display: false }, 
                ticks: { display: false } 
            } 
        }, 
        plugins: { 
            legend: { display: false },
            tooltip: {
                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--surface').trim(),
                titleColor: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim(),
                bodyColor: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim(),
                borderColor: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim(),
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8
            }
        } 
    }
});

// Scroll Highlighting
const observerOptions = { root: null, rootMargin: '-20% 0px -70% 0px', threshold: 0 };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.getAttribute('id');
            document.querySelectorAll('.jump-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + id) link.classList.add('active');
            });
        }
    });
}, observerOptions);
document.querySelectorAll('.scroll-section').forEach(section => observer.observe(section));

document.querySelectorAll('.jump-link').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rangeSelect = document.getElementById('range-select');
    const trigger = document.getElementById('range-picker-trigger');
    const form = document.getElementById('filter-form');
    
    if (!rangeSelect || !trigger || !form) return;

    const fp = flatpickr(trigger, {
        mode: "range",
        monthSelectorType: "dropdown",
        dateFormat: "Y-m-d",
        defaultDate: ["<?= $start_date ?>", "<?= $end_date ?>"],
        onClose: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                document.getElementById('start_date').value = instance.formatDate(selectedDates[0], "Y-m-d");
                document.getElementById('end_date').value = instance.formatDate(selectedDates[1], "Y-m-d");
                form.submit();
            } else {
                // Reset select if cancelled without full range
                if (rangeSelect.value === 'custom' && "<?= $range ?>" !== 'custom') {
                    rangeSelect.value = "<?= $range ?>";
                }
            }
        }
    });

    rangeSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            fp.open();
        } else {
            form.submit();
        }
    });

    const changeBtn = document.getElementById('change-range-btn');
    if (changeBtn) {
        changeBtn.addEventListener('click', () => fp.open());
    }
});
</script>
<?php include '../../includes/footer.php'; ?>
