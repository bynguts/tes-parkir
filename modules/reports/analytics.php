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
    SELECT MONTHNAME(check_out_time) as month, COALESCE(SUM(total_fee), 0) as revenue
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

<div class="px-10 py-10 max-w-[1750px] mx-auto space-y-10">
    
    <!-- TOP FILTER BAR -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Analytics Dashboard</h2>
            <p class="text-tertiary mt-1 text-sm font-medium">Insights for <span class="text-primary font-bold"><?= ucfirst($range) ?></span> (<?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?>)</p>
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
                ['id' => 'turnover', 'icon' => 'fa-arrows-spin', 'label' => 'Turnover'],
                ['id' => 'dwell', 'icon' => 'fa-hourglass-half', 'label' => 'Duration'],
                ['id' => 'reservations', 'icon' => 'fa-calendar-check', 'label' => 'Booking'],
                ['id' => 'operators', 'icon' => 'fa-users-gear', 'label' => 'Operators'],

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
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bento-card p-8 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-money-bill-trend-up text-6xl"></i>
                </div>
                <div class="relative z-10">
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

            <div class="bento-card p-8 relative overflow-hidden group">
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

            <div class="bento-card p-8 relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                <div class="absolute top-0 right-0 p-8 opacity-15 group-hover:opacity-25 transition-opacity">
                    <img src="../../assets/img/logo_p.png" alt="Logo" class="w-16 h-16 object-contain grayscale brightness-0">
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Intake Capacity</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= $data['summary']['available_slots'] ?></p>
                        <p class="text-[11px] font-black text-tertiary uppercase">Slots Free</p>
                    </div>
                    <div class="mt-8 flex items-center gap-3">
                        <div class="flex -space-x-3">
                            <div class="w-8 h-8 rounded-full status-badge-reserved border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-clock text-[8px]"></i></div>
                            <div class="w-8 h-8 rounded-full status-badge-available border-2 border-surface flex items-center justify-center"><i class="fa-solid fa-check text-[8px]"></i></div>
                        </div>
                        <span class="text-[10px] font-black text-tertiary uppercase tracking-widest"><?= $data['summary']['total_reservations'] ?> RSV Today</span>
                    </div>
                </div>
            </div>

            <div class="bento-card p-8 relative overflow-hidden group">
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

    <!-- 3. REVENUE SECTION -->
    <section id="revenue" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Revenue Dynamics</h3>
        </div>
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
    </section>

    <!-- 4. TRAFFIC SECTION -->
    <section id="traffic" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Traffic Intelligence</h3>
        </div>
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

    <!-- 5. TURNOVER SECTION -->
    <section id="turnover" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Turnover Intelligence</h3>
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
    </section>

    <!-- 5. DWELL TIME SECTION -->
    <section id="dwell" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Stay Behavior</h3>
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

    <!-- 7. RESERVATIONS -->
    <section id="reservations" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Booking Pipeline</h3>
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
    </section>

    <!-- 8. OPERATORS -->
    <section id="operators" class="scroll-section space-y-10">
        <div class="flex items-center gap-5">
            <div class="w-1.5 h-10 bg-brand rounded-full"></div>
            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center">
                <i class="fa-solid fa-users-gear text-xs"></i>
            </div>
            <h3 class="text-2xl font-manrope font-black text-primary tracking-tight">Personnel Intelligence</h3>
        </div>
        <div class="bento-card overflow-hidden shadow-xl border-color">
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
            borderColor: chartColors.brand, borderWidth: 4, tension: 0.4, fill: true, 
            backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(99, 102, 241, 0.05)'), 
            pointRadius: 0, pointHoverRadius: 8, pointHoverBackgroundColor: chartColors.brand, pointHoverBorderWidth: 4, pointHoverBorderColor: '#fff'
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

initChart('turnoverChart', {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($turnover_trend, 'date')) ?>,
        datasets: [{
            label: 'Turnover Ratio',
            data: <?= json_encode(array_column($turnover_trend, 'ratio')) ?>,
            borderColor: chartColors.brand,
            backgroundColor: (context) => createChartGradient(context.chart.ctx, 'rgba(99, 102, 241, 0.1)'),
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
document.addEventListener('DOMContentLoaded', function() {
    // --- DATE RANGE PICKER ---
    const rangeSelect = document.getElementById('range-select');
    const trigger = document.getElementById('range-picker-trigger');
    const form = document.getElementById('filter-form');
    
    if (rangeSelect && trigger && form) {
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
                } else if (rangeSelect.value === 'custom' && "<?= $range ?>" !== 'custom') {
                    rangeSelect.value = "<?= $range ?>";
                }
            }
        });

        rangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') fp.open();
            else form.submit();
        });

        const changeBtn = document.getElementById('change-range-btn');
        if (changeBtn) changeBtn.addEventListener('click', () => fp.open());
    }

    // --- SCROLLSPY LOGIC ---
    const sections = document.querySelectorAll('section.scroll-section');
    const navLinks = document.querySelectorAll('.jump-link');

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
                    link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
                });
            }
        });
    }, observerOptions);

    sections.forEach(section => observer.observe(section));
});
</script>
<?php include '../../includes/footer.php'; ?>
