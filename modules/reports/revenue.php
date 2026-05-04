<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

sync_slot_statuses($pdo);

$page_title = 'Revenue Intelligence';

// --- DATE FILTER LOGIC ---
$range = $_GET['range'] ?? '1week';
$range = rtrim($range, 's');
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$end_dt = new DateTime();
$end_date = $end_dt->format('Y-m-d');

switch ($range) {
    case 'today': 
        $start_date = date('Y-m-d'); 
        break;
    case '24h': 
        $start_date = date('Y-m-d H:i:s', strtotime('-24 hours')); 
        break;
    case '1week': 
        $start_date = date('Y-m-d', strtotime('-7 days')); 
        break;
    case '1month': 
        $start_date = date('Y-m-d', strtotime('-30 days')); 
        break;
    case '1year': 
        $start_date = date('Y-m-d', strtotime('-1 year')); 
        break;
    case 'all_time':
        $start_date = '1000-01-01';
        $end_date   = date('Y-m-d');
        break;
    case 'custom':
        // start_date and end_date come from GET params; keep as-is
        if (!$start_date) $start_date = date('Y-m-d', strtotime('-7 days'));
        if (!$end_date)   $end_date   = date('Y-m-d');
        break;
    default: 
        $start_date = date('Y-m-d', strtotime('-7 days')); 
        $range = '1week';
        break;
}

// Map range to readable labels for the UI (Parity with Scan Log)
$range_labels = [
    'today'    => 'Today',
    '24h'      => 'Past 24 Hours',
    '1week'    => '1 Week',
    '1weeks'   => '1 Week',
    '1month'   => '1 Month',
    '1months'  => '1 Month',
    '1year'    => '1 Year',
    '1years'   => '1 Year',
    'all_time' => 'All Time',
    'custom'   => 'Custom Range'
];
if ($range === 'custom' && $start_date && $end_date) {
    $current_range_label = 'Custom Range';
    $custom_date_label   = date('d M', strtotime($start_date)) . ' – ' . date('d M Y', strtotime($end_date));
} else {
    $current_range_label = $range_labels[$range] ?? 'Last 7 Days';
    $custom_date_label   = '';
}
$page_subtitle = 'Financial auditing for ' . $current_range_label;

$db_start = $start_date . (strlen($start_date) <= 10 ? ' 00:00:00' : '');
$db_end = $end_date . (strlen($end_date) <= 10 ? ' 23:59:59' : '');

$daily_stmt = $pdo->prepare("
    SELECT CAST(t.check_out_time AS DATE) AS date,
           SUM(v.vehicle_type='car')         AS cars,
           SUM(v.vehicle_type='motorcycle')  AS motos,
           COUNT(*)                          AS total_count,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS revenue_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS revenue_moto,
           SUM(t.total_fee) AS total_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' 
      AND t.check_out_time BETWEEN ? AND ?
    GROUP BY CAST(t.check_out_time AS DATE)
    ORDER BY date DESC
");
$daily_stmt->execute([$db_start, $db_end]);
$daily = $daily_stmt->fetchAll();

$totals_stmt = $pdo->prepare("
    SELECT SUM(v.vehicle_type='car')         AS total_cars,
           SUM(v.vehicle_type='motorcycle')  AS total_motos,
           COUNT(*)                          AS grand_total,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_moto,
           SUM(t.total_fee) AS grand_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' 
      AND t.check_out_time BETWEEN ? AND ?
");
$totals_stmt->execute([$db_start, $db_end]);
$totals = $totals_stmt->fetch();

include '../../includes/header.php';
?>



<div class="px-10 py-10 max-w-[1750px] mx-auto">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>

        <div class="flex items-center gap-3">
            <form id="filterForm" method="GET" class="relative flex items-center gap-3">
                <div class="relative">
                    <button type="button" onclick="toggleRangeDropdown(event)"
                            class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                        <div class="flex flex-col leading-none">
                            <span id="rangeLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary"><?= $current_range_label ?></span>
                            <?php if ($custom_date_label): ?>
                            <span class="text-[9px] font-inter text-tertiary tracking-wide mt-0.5"><?= $custom_date_label ?></span>
                            <?php endif; ?>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-tertiary"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="rangeDropdown" class="hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-xl shadow-xl z-50 py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                        <button type="button" onclick="setRange('today', 'Today')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Today</button>
                        <button type="button" onclick="setRange('24h', 'Past 24 Hours')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Past 24 Hours</button>
                        <button type="button" onclick="setRange('1week', '1 Week')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Week</button>
                        <button type="button" onclick="setRange('1month', '1 Month')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Month</button>
                        <button type="button" onclick="setRange('1year', '1 Year')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">1 Year</button>
                        <button type="button" onclick="setRange('all_time', 'All Time')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">All Time</button>
                        <button type="button" onclick="setRange('custom', 'Custom Range')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Custom Range</button>
                    </div>

                    <!-- Hidden inputs -->
                    <input type="hidden" name="range" id="range-value" value="<?= $range ?>">
                    <input type="hidden" name="start_date" id="start_date" value="<?= $start_date ?>">
                    <input type="hidden" name="end_date"   id="end_date"   value="<?= $end_date ?>">
                    <input type="text"   id="range-picker-trigger" class="absolute opacity-0 pointer-events-none w-0 h-0">
                </div>

            </form>
        </div>
    </div>

    <!-- SUMMARY GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Net Revenue -->
        <div class="bento-card p-6 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-money-bill-trend-up text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Net Revenue</p>
                <div class="flex items-baseline gap-2 whitespace-nowrap">
                    <span class="text-xl font-black text-tertiary">Rp</span>
                    <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= number_format((float)($totals['grand_revenue'] ?? 0), 0, ',', '.') ?></p>
                </div>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-secondary uppercase tracking-widest">
                    <i class="fa-solid fa-shield-check text-brand"></i> Financial Audit Verified
                </div>
            </div>
        </div>

        <!-- Total Transactions -->
        <div class="bento-card p-6 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-receipt text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Total Inflow</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= number_format($totals['grand_total'] ?? 0) ?></p>
                    <p class="text-[11px] font-black text-tertiary uppercase">Tickets</p>
                </div>
                <div class="mt-8 flex items-center gap-3">
                    <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Settled Assets</span>
                </div>
            </div>
        </div>

        <!-- Car Revenue -->
        <div class="bento-card p-6 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-car text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Car Segments</p>
                <div class="flex items-baseline gap-2 whitespace-nowrap">
                    <span class="text-xl font-black text-tertiary">Rp</span>
                    <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= number_format((float)($totals['rev_car'] ?? 0), 0, ',', '.') ?></p>
                </div>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-tertiary uppercase tracking-widest">
                    <i class="fa-solid fa-car text-brand"></i> <?= number_format($totals['total_cars'] ?? 0) ?> Volume Scans
                </div>
            </div>
        </div>

        <!-- Moto Revenue -->
        <div class="bento-card p-6 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-motorcycle text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Moto Segments</p>
                <div class="flex items-baseline gap-2 whitespace-nowrap">
                    <span class="text-xl font-black text-tertiary">Rp</span>
                    <p class="text-[32px] font-manrope font-black text-primary tracking-tighter"><?= number_format((float)($totals['rev_moto'] ?? 0), 0, ',', '.') ?></p>
                </div>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-tertiary uppercase tracking-widest">
                    <i class="fa-solid fa-motorcycle text-brand"></i> <?= number_format($totals['total_motos'] ?? 0) ?> Volume Scans
                </div>
            </div>
        </div>
    </div>

    <!-- DAILY BREAKDOWN -->
    <div class="bento-card overflow-hidden">
        <div class="flex items-center justify-between py-5 px-4 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-receipt text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Audit Trail</h3>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Chronological Revenue distribution</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar min-h-[350px]">
            <table class="w-full font-inter border-collapse table-fixed activity-table">
                <thead>
                    <tr class="border-b border-color">
                        <th class="text-left px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Operational Date</th>
                        <th class="text-center px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Mix (C/M)</th>
                        <th class="text-center px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Visits</th>
                        <th class="text-right px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Car Rev.</th>
                        <th class="text-right px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Moto Rev.</th>
                        <th class="text-right px-4 py-3 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Daily Total</th>
                    </tr>
                </thead>
                <tbody id="dailyBody" class="divide-y divide-color">
                    <?php if (empty($daily)): ?>
                    <tr>
                        <td colspan="6" class="py-32 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <i class="fa-solid fa-inbox text-5xl mb-6"></i>
                                <p class="text-primary font-black uppercase tracking-[0.2em] text-xs">Zero variance detected</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($daily as $row): ?>
                    <tr class="group hover:bg-surface-alt/50 transition-colors fleet-row" data-timestamp="<?= strtotime($row['date']) ?>">
                        <td class="py-2 pl-4 pr-4 align-middle text-left">
                            <div class="flex items-center h-10">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('D, d M Y', strtotime($row['date'])) ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 align-middle text-center">
                            <div class="flex items-center justify-center">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= $row['cars'] ?> / <?= $row['motos'] ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 align-middle text-center">
                            <div class="flex items-center justify-center">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= number_format($row['total_count']) ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 align-middle text-right">
                            <div class="flex items-center justify-end">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= fmt_idr((float)$row['revenue_car']) ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 align-middle text-right">
                            <div class="flex items-center justify-end">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= fmt_idr((float)$row['revenue_moto']) ?></span>
                            </div>
                        </td>
                        <td class="py-2 pr-4 pl-4 align-middle text-right">
                            <div class="flex items-center justify-end">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= fmt_idr((float)$row['total_revenue']) ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($daily)): ?>
                <tfoot class="bg-surface-alt/40 border-t border-color">
                    <tr>
                        <!-- 1. Audit Summary Label -->
                        <td class="py-2 pl-4 pr-4 align-middle text-left">
                            <div class="flex items-center h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none">Audit Summary</span>
                            </div>
                        </td>

                        <!-- 2. Total Mix (C/M) -->
                        <td class="py-2 px-4 align-middle text-center">
                            <div class="flex items-center justify-center h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none"><?= $totals['total_cars'] ?? 0 ?> / <?= $totals['total_motos'] ?? 0 ?></span>
                            </div>
                        </td>

                        <!-- 3. Total Visits -->
                        <td class="py-2 px-4 align-middle text-center">
                            <div class="flex items-center justify-center h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none"><?= number_format($totals['grand_total'] ?? 0) ?></span>
                            </div>
                        </td>

                        <!-- 4. Total Car Rev -->
                        <td class="py-2 px-4 align-middle text-right">
                            <div class="flex items-center justify-end h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></span>
                            </div>
                        </td>

                        <!-- 5. Total Moto Rev -->
                        <td class="py-2 px-4 align-middle text-right">
                            <div class="flex items-center justify-end h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none"><?= fmt_idr((float)($totals['rev_moto'] ?? 0)) ?></span>
                            </div>
                        </td>

                        <!-- 6. Total Grand Revenue -->
                        <td class="py-2 pr-4 pl-4 align-middle text-right">
                            <div class="flex items-center justify-end h-10">
                                <span class="text-lg font-manrope font-bold text-primary leading-none"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

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
    if (value === 'custom') {
        // Open flatpickr if available, else submit with current dates
        const fp = document.getElementById('range-picker-trigger')?._flatpickr;
        if (fp) { fp.open(); return; }
    }
    document.getElementById('filterForm').submit();
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dd = document.getElementById('rangeDropdown');
    if (dd && !e.target.closest('#rangeDropdown') && !e.target.closest('[onclick*="toggleRangeDropdown"]')) {
        dd.classList.add('hidden');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
