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
            WHEN HOUR(check_in_time) BETWEEN 7 AND 11 THEN 'Morning'
            WHEN HOUR(check_in_time) BETWEEN 12 AND 16 THEN 'Afternoon'
            WHEN HOUR(check_in_time) BETWEEN 17 AND 22 THEN 'Evening'
            ELSE 'Closed'
        END as period,
        AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)) as avg_min
    FROM `transaction`
    WHERE payment_status = 'paid' AND check_out_time BETWEEN ? AND ?
    GROUP BY period
");
$dwell_period->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$dwell_period = $dwell_period->fetchAll();



// --- TURNOVER CALCULATION ---
$total_slot_count = count($slots) ?: 1;
$turnover_trend = [];
foreach ($rev_daily as $day) {
    $turnover_trend[] = [
        'date' => date('d M', strtotime($day['date'])),
        'ratio' => round($day['volume'] / $total_slot_count, 2)
    ];
}
$avg_turnover = count($turnover_trend) ? array_sum(array_column($turnover_trend, 'ratio')) / count($turnover_trend) : 0;
$turnover_score = ($avg_turnover > 5) ? 'High Efficiency' : (($avg_turnover > 2) ? 'Moderate' : 'Low Utilization');

include '../../includes/header.php';
?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .scroll-section { scroll-margin-top: 180px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    
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

<div class="px-10 py-10">
    
    <!-- TOP FILTER BAR -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-3xl font-manrope font-extrabold text-primary tracking-tight">Analytics Dashboard</h2>
            <p class="text-sm font-inter text-tertiary mt-1">Insights for <span class="text-primary font-bold"><?= ucfirst($range) ?></span> (<?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?>)</p>
        </div>

        <div class="flex items-center gap-4">
            <div class="relative">
                <button type="button" onclick="toggleRangeDropdown(event)"
                        class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                    <span id="rangeLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary"><?= ucfirst($range) ?></span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-tertiary"></i>
                </button>
                <div id="rangeDropdown" class="hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-xl shadow-xl z-50 py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                    <button type="button" onclick="setRange('today', 'Today')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Today</button>
                    <button type="button" onclick="setRange('1week', '1 Week')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Week</button>
                    <button type="button" onclick="setRange('1month', '1 Month')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Month</button>
                    <button type="button" onclick="setRange('1year', '1 Year')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Year</button>
                </div>
                <input type="hidden" name="range" id="range-value" value="<?= $range ?>">
                <input type="hidden" name="start_date" id="start_date" value="<?= $start_date ?>">
                <input type="hidden" name="end_date" id="end_date" value="<?= $end_date ?>">
            </div>
        </div>
    </div>

    <!-- STICKY JUMP MENU -->
    <div class="sticky top-[72px] lg:top-20 z-40 bg-page/80 backdrop-blur-xl py-4 -mx-10 px-10 border-b border-color shadow-sm mb-10 transition-all">
        <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
            <?php 
            $sections = [
                ['id' => 'overview', 'icon' => 'fa-th-large', 'label' => 'Overview'],
                ['id' => 'heatmap', 'icon' => 'fa-fire', 'label' => 'Heatmap'],
                ['id' => 'financial', 'icon' => 'fa-money-bill-trend-up', 'label' => 'Financial'],
                ['id' => 'duration', 'icon' => 'fa-hourglass-half', 'label' => 'Duration'],
                ['id' => 'operations', 'icon' => 'fa-users-gear', 'label' => 'Operations'],
            ];
            foreach($sections as $s): ?>
            <a href="#<?= $s['id'] ?>" class="jump-link flex items-center gap-2 px-4 py-2 rounded-xl text-[11px] font-inter font-medium tracking-wider transition-all <?= $s['id'] === 'overview' ? 'bg-brand text-white shadow-lg' : 'text-tertiary hover:text-brand hover:bg-surface-alt' ?>">
                <i class="fa-solid <?= $s['icon'] ?> text-sm"></i>
                <?= $s['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 1. OVERVIEW SECTION -->
    <section id="overview" class="scroll-section space-y-6 mb-24">
        <div class="flex items-center justify-between pt-5 pb-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-chart-line text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Executive Summary</h3>
                    <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Critical performance metrics for the active window.</p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bento-card overflow-hidden group relative">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-money-bill-trend-up text-6xl"></i>
                </div>
                <div class="p-8 relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10"><?= $range === 'today' ? 'Revenue Today' : 'Total Revenue' ?></p>
                    <div class="flex items-baseline gap-2 whitespace-nowrap">
                        <span class="text-xl font-black text-tertiary">Rp</span>
                        <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= number_format((float)$data['summary']['revenue_today'], 0, ',', '.') ?></p>
                    </div>
                    <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-secondary uppercase tracking-widest">
                        <i class="fa-solid fa-arrow-trend-up text-brand"></i> Intelligence Sync Active
                    </div>
                </div>
            </div>

            <div class="bento-card overflow-hidden group relative">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-gauge-high text-6xl"></i>
                </div>
                <div class="p-8 relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Occupancy Rate</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['active_vehicles'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Units Active</p>
                    </div>
                    <div class="mt-8 h-2 w-full bg-surface-alt rounded-full overflow-hidden">
                        <?php $occ_rate = ($data['summary']['active_vehicles'] / max(1, $data['summary']['available_slots'] + $data['summary']['active_vehicles'])) * 100; ?>
                        <div class="h-full bg-brand shadow-[0_0_15px_rgba(99,102,241,0.5)]" style="width: <?= min(100, $occ_rate) ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bento-card overflow-hidden group relative">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-15 group-hover:opacity-25 transition-opacity">
                    <img src="../../assets/img/logo_p.png" alt="Logo" class="w-16 h-16 object-contain grayscale brightness-0">
                </div>
                <div class="p-8 relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Intake Capacity</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['available_slots'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Slots Free</p>
                    </div>
                    <div class="mt-8 flex items-center gap-3">
                        <div class="flex -space-x-3">
                            <div class="w-8 h-8 rounded-full status-badge-reserved border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-clock text-[8px]"></i></div>
                            <div class="w-8 h-8 rounded-full status-badge-available border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-check text-[8px]"></i></div>
                        </div>
                        <span class="text-[10px] font-black text-secondary uppercase tracking-widest"><?= $data['summary']['total_reservations'] ?> RSV Today</span>
                    </div>
                </div>
            </div>

            <div class="bento-card overflow-hidden group relative">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-route text-6xl"></i>
                </div>
                <div class="p-8 relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10"><?= $range === 'today' ? 'Traffic Today' : 'Total Traffic' ?></p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['entries_today'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Flow-ins</p>
                    </div>
                    <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-secondary uppercase tracking-widest">
                        <i class="fa-solid fa-circle-check text-brand"></i> Hardware-validated scans
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. HEATMAP SECTION -->
    <section id="heatmap" class="scroll-section space-y-6 mb-24">
        <div class="flex items-center justify-between pt-5 pb-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-layer-group text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Facility Heatmap</h3>
                    <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Visual slot distribution across vertical levels.</p>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <?php foreach($display_areas as $area): ?>
            <div class="bento-card p-8 border-color shadow-xl bg-surface/50 backdrop-blur-sm">
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
                    <div class="flex gap-4">
                        <div class="status-badge-available px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                            <span class="status-dot-available"></span> Available
                        </div>
                        <div class="status-badge-parked px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                            <span class="status-dot-parked"></span> Occupied
                        </div>
                        <div class="status-badge-reserved px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                            <span class="status-dot-reserved"></span> Reserved
                        </div>
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

    <!-- 3. FINANCIAL & FLOW SECTION -->
    <section id="financial" class="scroll-section space-y-6 mb-24">
        <div class="flex items-center justify-between pt-5 pb-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-money-bill-trend-up text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Financial & Flow Intelligence</h3>
                    <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Revenue trajectory and traffic intelligence.</p>
                </div>
            </div>
        </div>
        
        <!-- Revenue Trajectory Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bento-card p-8 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Daily Trajectory</h4>
                    <span class="badge-soft badge-soft-indigo px-4 py-2">14-Day View</span>
                </div>
                <div class="h-[280px] w-full">
                    <canvas id="revDailyChart"></canvas>
                </div>
            </div>
            <div class="bento-card p-8 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Monthly Performance</h4>
                    <span class="badge-soft badge-soft-slate px-4 py-2">H1 Analysis</span>
                </div>
                <div class="h-[280px] w-full">
                    <canvas id="revMonthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Traffic Intelligence Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="bento-card p-8 border-color shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-black text-lg mb-12 w-full text-primary text-center">Fleet Composition</h4>
                <div class="w-full max-w-[220px] relative">
                    <canvas id="vehicleMixChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-manrope font-black text-primary"><?= array_sum(array_column($data['vehicle_stats'], 'total_count')) ?></span>
                        <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Total</span>
                    </div>
                </div>
                <div class="mt-12 grid grid-cols-2 gap-8 w-full text-center border-t border-color pt-10">
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <div class="w-2 h-2 rounded-full bg-[#6366f1]"></div>
                            <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Automobiles</span>
                        </div>
                        <span class="text-2xl font-black text-primary"><?= array_sum(array_column($rev_daily, 'cars')) ?></span>
                    </div>
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <div class="w-2 h-2 rounded-full bg-[#f43f5e]"></div>
                            <span class="text-[9px] font-black text-tertiary uppercase tracking-widest">Two-Wheelers</span>
                        </div>
                        <span class="text-2xl font-black text-primary"><?= array_sum(array_column($rev_daily, 'motos')) ?></span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2 bento-card p-8 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Hourly Intake Distribution</h4>
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-clock-rotate-left text-brand text-xs"></i>
                        <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Peak Load Tracking</span>
                    </div>
                </div>
                <div class="h-[280px] w-full">
                    <canvas id="peakChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. DURATION & EFFICIENCY SECTION -->
    <section id="duration" class="scroll-section space-y-6 mb-24">
        <div class="flex items-center justify-between pt-5 pb-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-hourglass-half text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Duration & Efficiency</h3>
                    <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Turnover ratios and stay length analysis.</p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="bento-card p-8 border-color shadow-xl">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Utilization Velocity</p>
                <div class="flex items-baseline gap-3 mb-8">
                    <p class="text-6xl font-manrope font-black text-primary tracking-tighter"><?= number_format($avg_turnover, 1) ?></p>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-tertiary uppercase">Turnover</span>
                        <span class="text-[11px] font-black text-brand uppercase">Ratio</span>
                    </div>
                </div>
                <div class="p-4 rounded-2xl bg-surface-alt border border-color mb-8">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Efficiency Status</span>
                        <span class="text-[10px] font-black text-brand uppercase"><?= $turnover_score ?></span>
                    </div>
                    <div class="h-2 w-full bg-surface rounded-full overflow-hidden">
                        <div class="h-full bg-brand" style="width: <?= min(100, $avg_turnover * 10) ?>%"></div>
                    </div>
                </div>
                <p class="text-[11px] text-tertiary leading-relaxed">
                    Each parking slot is used by an average of <span class="text-primary font-bold"><?= number_format($avg_turnover, 1) ?> different vehicles</span> per day. 
                    The ideal target for commercial areas is <span class="text-brand font-bold">4.0 - 6.0</span>.
                </p>
            </div>

            <div class="lg:col-span-2 bento-card p-8 border-color shadow-xl">
                <div class="flex items-center justify-between mb-10">
                    <h4 class="font-manrope font-black text-lg text-primary">Turnover Trend Analysis</h4>
                    <span class="badge-soft badge-soft-indigo px-4 py-2">Velocity Tracking</span>
                </div>
                <div class="h-[280px] w-full">
                    <canvas id="turnoverChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bento-card p-8 border-color shadow-xl">
                <h4 class="font-manrope font-black text-lg mb-10 text-primary">Avg Duration per Vehicle Class</h4>
                <div class="h-[280px] w-full">
                    <canvas id="dwellTypeChart"></canvas>
                </div>
            </div>
            <div class="bento-card p-8 border-color shadow-xl">
                <h4 class="font-manrope font-black text-lg mb-10 text-primary">Stay Length by Inbound Period</h4>
                <div class="h-[280px] w-full">
                    <canvas id="dwellPeriodChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. OPERATIONS & PERSONNEL SECTION -->
    <section id="operations" class="scroll-section space-y-6 mb-24">
        <div class="flex items-center justify-between pt-5 pb-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-users-gear text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Operations & Personnel</h3>
                    <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">System efficiency and personnel intelligence.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bento-card p-8 border-color shadow-xl flex flex-col items-center">
                <h4 class="font-manrope font-black text-lg mb-12 w-full text-primary">Reservation Conversion</h4>
                <div class="w-full max-w-[260px]"><canvas id="resConversionChart"></canvas></div>
            </div>
            <div class="bento-card bg-surface border-color p-8 shadow-2xl flex flex-col justify-center space-y-10 relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-tertiary text-[10px] font-black uppercase tracking-[0.25em] mb-12">System Efficiency</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-12">
                        <div class="space-y-3">
                            <span class="block status-text-available text-4xl font-manrope font-black">89.4%</span>
                            <span class="text-secondary text-[10px] font-black uppercase tracking-widest leading-relaxed">Utilization via Pre-booking</span>
                        </div>
                        <div class="space-y-3">
                            <span class="block status-text-lost text-4xl font-manrope font-black">4.2%</span>
                            <span class="text-secondary text-[10px] font-black uppercase tracking-widest leading-relaxed">No-show Variance Impact</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card overflow-hidden shadow-xl border-color">
            <div class="p-8 border-b border-color bg-surface-alt/20">
                <h4 class="font-manrope font-black text-lg text-primary">Personnel Intelligence</h4>
            </div>
            <table class="w-full activity-table font-inter border-separate border-spacing-0">
                <thead>
                    <tr class="bg-surface-alt/50">
                        <th class="text-left px-8 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Operator</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Shift</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Throughput</th>
                        <th class="text-right px-8 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Total Handled</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
                    <?php if (!empty($data['operator_performance'])): ?>
                    <?php foreach($data['operator_performance'] as $op): 
                        $opName = trim((string)($op['full_name'] ?? 'Unknown Operator'));
                        $opInitial = strtoupper(substr($opName, 0, 1));
                    ?>
                    <tr class="hover:bg-surface-alt/30 transition-all group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-full bg-brand flex items-center justify-center text-white text-base font-black shadow-lg shadow-brand/20 group-hover:scale-110 transition-all duration-300"><?= $opInitial ?: 'U' ?></div>
                                <div>
                                    <span class="text-base font-extrabold text-primary block leading-tight"><?= htmlspecialchars($opName) ?></span>
                                    <span class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-1 block">ID: OP-<?= (int)($op['operator_id'] ?? 0) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <?php 
                                $shift = (string)($op['shift'] ?? 'N/A');
                                $shiftBadge = 'badge-soft-slate';
                                if (strpos($shift, '1') !== false || strpos($shift, 'Day') !== false) $shiftBadge = 'badge-soft-emerald';
                                elseif (strpos($shift, '2') !== false) $shiftBadge = 'badge-soft-indigo';
                                elseif (strpos($shift, '3') !== false || strpos($shift, 'Night') !== false) $shiftBadge = 'badge-soft-rose';
                            ?>
                            <span class="badge-soft <?= $shiftBadge ?> px-5 py-2"><?= htmlspecialchars($shift) ?></span>
                        </td>
                        <td class="px-6 py-6 text-center font-manrope font-black text-primary text-xl"><?= (int)($op['total_transactions'] ?? 0) ?></td>
                        <td class="px-8 py-5 text-right font-manrope font-black status-text-available text-xl"><?= fmt_idr((float)($op['total_revenue_handled'] ?? 0)) ?></td>
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
    green: '#10b981',    // Revenue
    rose: '#f43f5e',     // Motorcycles
    amber: '#f59e0b',    // Reservations
    cyan: '#06b6d4',     // Turnover & Dwell
    indigo: '#6366f1',   // Brand/Misc
    slate: '#64748b',    // Neutral
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
            ticks: { font: { weight: '800', size: 10 }, color: chartTextColor, padding: 10, precision: 0 }
        },
        x: { 
            grid: { display: false },
            ticks: { font: { weight: '800', size: 10 }, color: chartTextColor, padding: 10 }
        }
    }
};

const createChartGradient = (ctx, color) => {
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, color.replace('0.1)', '0.5)').replace('0.03)', '0.5)'));
    gradient.addColorStop(1, color.replace('0.1)', '0.01)').replace('0.03)', '0.01)'));
    return gradient;
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
            borderColor: chartColors.green, borderWidth: 4, tension: 0.4, fill: true, 
            backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(16, 185, 129, 0.05)'), 
            pointRadius: 0, pointHoverRadius: 8, pointHoverBackgroundColor: chartColors.green, pointHoverBorderWidth: 4, pointHoverBorderColor: '#fff'
        }]
    },
    options: commonOptions
});

initChart('revMonthlyChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($rev_monthly, 'month')) ?>,
        datasets: [{ data: <?= json_encode(array_column($rev_monthly, 'revenue')) ?>, backgroundColor: chartColors.green, borderRadius: 12, hoverBackgroundColor: '#059669' }]
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
            backgroundColor: ['#6366f1', '#f43f5e'], 
            borderWidth: 0, 
            hoverOffset: 20 
        }]
    },
    options: { 
        cutout: '72%', 
        plugins: { 
            legend: { display: false },
            tooltip: {
                enabled: true,
                padding: 12,
                cornerRadius: 12,
                titleFont: { weight: 'bold', size: 14 },
                bodyFont: { size: 13 }
            }
        } 
    }
});

initChart('peakChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($h) => sprintf("%02d:00", $h['hour']), $data['hourly_distribution'])) ?>,
        datasets: [
            {
                label: 'Cars',
                data: <?= json_encode(array_column($data['hourly_distribution'], 'cars')) ?>,
                borderColor: '#6366f1',
                backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(99, 102, 241, 0.1)'),
                fill: true, tension: 0.4, borderWidth: 4, pointRadius: 0, stacked: true
            },
            {
                label: 'Motorcycles',
                data: <?= json_encode(array_column($data['hourly_distribution'], 'motos')) ?>,
                borderColor: '#f43f5e',
                backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(244, 63, 94, 0.1)'),
                fill: true, tension: 0.4, borderWidth: 4, pointRadius: 0, stacked: true
            },
            {
                label: 'Reservations',
                data: <?= json_encode(array_column($data['hourly_distribution'], 'reservations')) ?>,
                borderColor: '#f59e0b',
                backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(245, 158, 11, 0.1)'),
                fill: true, tension: 0.4, borderWidth: 4, pointRadius: 0, stacked: true
            }
        ]
    },
    options: {
        ...commonOptions,
        plugins: {
            ...commonOptions.plugins,
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: { weight: '800', size: 10 },
                    color: chartTextColor
                }
            }
        },
        scales: {
            ...commonOptions.scales,
            y: { ...commonOptions.scales.y, stacked: true },
            x: { ...commonOptions.scales.x, stacked: true }
        }
    }
});

// 3. Dwell Charts
initChart('dwellTypeChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($dwell_avg, 'vehicle_type')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_avg, 'avg_min')) ?>, backgroundColor: chartColors.cyan, borderRadius: 20 }]
    },
    options: commonOptions
});

initChart('dwellPeriodChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dwell_period, 'period')) ?>,
        datasets: [{ data: <?= json_encode(array_column($dwell_period, 'avg_min')) ?>, borderColor: chartColors.cyan, borderWidth: 5, tension: 0.5, pointRadius: 0 }]
    },
    options: commonOptions
});

initChart('turnoverChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($turnover_trend, 'date')) ?>,
        datasets: [{
            label: 'Turnover Ratio',
            data: <?= json_encode(array_column($turnover_trend, 'ratio')) ?>,
            borderColor: chartColors.cyan,
            backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(6, 182, 212, 0.1)'),
            fill: true, tension: 0.4, borderWidth: 4, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderWidth: 3
        }]
    },
    options: {
        ...commonOptions,
        plugins: { ...commonOptions.plugins, legend: { display: false } }
    }
});

// 4. Misc Charts
initChart('resConversionChart', {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($data['reservation_summary'], 'status')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['reservation_summary'], 'count')) ?>, backgroundColor: [chartColors.amber, chartColors.car, chartColors.moto, '#94a3b8'], borderWidth: 0 }]
    },
    options: { cutout: '80%', plugins: { legend: { display: false } } }
});

initChart('paymentChart', {
    type: 'polarArea',
    data: {
        labels: <?= json_encode(array_column($data['payment_methods'], 'payment_method')) ?>,
        datasets: [{ data: <?= json_encode(array_column($data['payment_methods'], 'count')) ?>, backgroundColor: [chartColors.green, chartColors.cyan, chartColors.amber], borderWidth: 0 }]
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

// Global theme toggle listener for smooth chart updates
document.getElementById('theme-toggle')?.addEventListener('click', () => {
    setTimeout(() => {
        const root = document.documentElement;
        const rootStyles = getComputedStyle(root);
        const textColor = rootStyles.getPropertyValue('--text-primary').trim();
        const secondaryColor = rootStyles.getPropertyValue('--text-secondary').trim();
        const borderColor = rootStyles.getPropertyValue('--border-color').trim();
        const surfaceColor = rootStyles.getPropertyValue('--surface').trim();

        const charts = [occupancyLineChart, trafficFlowBarChart, revenueStreamChart, radarCapacityChart];
        
        charts.forEach(chart => {
            if (!chart) return;
            
            // Update Global Tooltip
            if (chart.options.plugins.tooltip) {
                chart.options.plugins.tooltip.backgroundColor = surfaceColor;
                chart.options.plugins.tooltip.titleColor = textColor;
                chart.options.plugins.tooltip.bodyColor = textColor;
                chart.options.plugins.tooltip.borderColor = borderColor;
            }

            // Update Scales
            if (chart.options.scales) {
                Object.values(chart.options.scales).forEach(scale => {
                    if (scale.ticks) scale.ticks.color = secondaryColor;
                    if (scale.grid) scale.grid.color = borderColor;
                    if (scale.border) scale.border.color = borderColor;
                });
            }
            
            chart.update('none');
        });
    }, 100);
});
</script>

<script>
function toggleRangeDropdown(e) {
    e.stopPropagation();
    const dd = document.getElementById('rangeDropdown');
    if (dd) dd.classList.toggle('hidden');
}

function setRange(value, label) {
    document.getElementById('range-value').value = value;
    document.getElementById('rangeLabel').textContent = label;
    document.getElementById('rangeDropdown').classList.add('hidden');

    const params = new URLSearchParams({
        range: value,
        start_date: document.getElementById('start_date').value,
        end_date: document.getElementById('end_date').value
    });
    window.location.href = '?' + params.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        const dd = document.getElementById('rangeDropdown');
        if (dd && !e.target.closest('[onclick*="toggleRangeDropdown"]')) {
            dd.classList.add('hidden');
        }
    });

    // Set initial label
    const rangeLabels = {
        'today': 'Today',
        '1week': '1 Week',
        '1month': '1 Month',
        '1year': '1 Year'
    };
    const label = rangeLabels['<?= $range ?>'] || '1 Week';
    const labelEl = document.getElementById('rangeLabel');
    if (labelEl) labelEl.textContent = label;

    // --- SCROLLSPY LOGIC ---
    const sections = document.querySelectorAll('section.scroll-section');
    const navLinks = document.querySelectorAll('.jump-link');

    const activeClasses = ['bg-brand', 'text-white', 'shadow-lg'];
    const inactiveClasses = ['text-tertiary', 'hover:text-brand', 'hover:bg-surface-alt'];

    const observerOptions = {
        root: null,
        rootMargin: '-20% 0px -70% 0px',
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                navLinks.forEach(link => {
                    if (link.getAttribute('href') === `#${id}`) {
                        link.classList.remove(...inactiveClasses);
                        link.classList.add(...activeClasses);
                    } else {
                        link.classList.remove(...activeClasses);
                        link.classList.add(...inactiveClasses);
                    }
                });
            }
        });
    }, observerOptions);

    sections.forEach(section => observer.observe(section));
});
</script>
<?php include '../../includes/footer.php'; ?>
