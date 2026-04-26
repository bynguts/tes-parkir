<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

$page_title = 'Revenue Intelligence';
$page_subtitle = 'Financial performance metrics and transaction auditing.';

// --- DATE FILTER LOGIC ---
$range = $_GET['range'] ?? '1week';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if ($range !== 'custom') {
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
        case 'all_time':
            $start_date = '1000-01-01 00:00:00';
            $end_date = '9999-12-31 23:59:59';
            break;
        default: 
            $start_date = date('Y-m-d', strtotime('-7 days')); 
            break;
    }
}

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



<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <!-- PREMIUM HEADER -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 rounded-3xl icon-container flex items-center justify-center shadow-xl shrink-0">
                <i class="fa-solid fa-wallet text-3xl"></i>
            </div>
            <div>
                <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Revenue Intelligence</h2>
                <p class="text-tertiary mt-1 text-sm font-medium">Financial auditing for <span class="text-primary font-bold"><?= ucfirst(str_replace('_', ' ', $range)) ?></span></p>
            </div>
        </div>

        <form method="GET" id="filter-form" class="flex items-center gap-4 bg-surface border border-color p-2 rounded-2xl shadow-sm">
            <div class="relative">
                <select name="range" id="range-select" class="appearance-none bg-surface-alt border-none px-6 py-3 pr-12 rounded-xl text-[10px] font-black uppercase tracking-widest text-primary focus:outline-none transition-all cursor-pointer">
                    <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>24 Hours</option>
                    <option value="1week" <?= $range === '1week' ? 'selected' : '' ?>>1 Week</option>
                    <option value="1month" <?= $range === '1month' ? 'selected' : '' ?>>1 Month</option>
                    <option value="all_time" <?= $range === 'all_time' ? 'selected' : '' ?>>All Time</option>
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

    <!-- SUMMARY GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Net Revenue -->
        <div class="bento-card p-10 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-money-bill-trend-up text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Net Revenue</p>
                <p class="text-4xl font-manrope font-black text-primary tracking-tighter"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></p>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-secondary uppercase tracking-widest">
                    <i class="fa-solid fa-shield-check text-brand"></i> Financial Audit Verified
                </div>
            </div>
        </div>

        <!-- Total Transactions -->
        <div class="bento-card p-10 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-receipt text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Total Inflow</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-5xl font-manrope font-black text-primary tracking-tighter"><?= number_format($totals['grand_total'] ?? 0) ?></p>
                    <p class="text-[11px] font-black text-tertiary uppercase">Tickets</p>
                </div>
                <div class="mt-8 flex items-center gap-3">
                    <span class="px-3 py-1 bg-status-available-bg text-status-available-text text-[9px] font-black rounded-full border border-status-available-border uppercase">Paid</span>
                    <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Settled Assets</span>
                </div>
            </div>
        </div>

        <!-- Car Revenue -->
        <div class="bento-card p-10 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-car text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Car Segments</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-manrope font-black text-primary tracking-tighter"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></p>
                </div>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-tertiary uppercase tracking-widest">
                    <i class="fa-solid fa-car text-brand"></i> <?= number_format($totals['total_cars'] ?? 0) ?> Volume Scans
                </div>
            </div>
        </div>

        <!-- Moto Revenue -->
        <div class="bento-card p-10 relative overflow-hidden group">
            <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fa-solid fa-motorcycle text-6xl"></i>
            </div>
            <div class="relative z-10">
                <p class="text-[10px] text-tertiary font-black uppercase tracking-[0.25em] mb-10">Moto Segments</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-manrope font-black text-primary tracking-tighter"><?= fmt_idr((float)($totals['rev_moto'] ?? 0)) ?></p>
                </div>
                <div class="mt-8 flex items-center gap-2 text-[10px] font-black text-tertiary uppercase tracking-widest">
                    <i class="fa-solid fa-motorcycle text-brand"></i> <?= number_format($totals['total_motos'] ?? 0) ?> Volume Scans
                </div>
            </div>
        </div>
    </div>

    <!-- DAILY BREAKDOWN -->
    <div class="bento-card overflow-hidden shadow-xl border-color">
        <div class="px-10 py-8 border-b border-color flex items-center justify-between bg-surface/50">
            <div class="flex items-center gap-4">
                <div class="w-1.5 h-8 bg-brand rounded-full"></div>
                <div>
                    <h3 class="text-xl font-manrope font-black text-primary tracking-tight">Audit Trail</h3>
                    <p class="text-tertiary text-[11px] font-black uppercase tracking-widest mt-0.5">Chronological Revenue distribution</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="w-10 h-10 rounded-xl bg-surface-alt border border-color flex items-center justify-center text-tertiary hover:text-primary transition-all shadow-sm">
                    <i class="fa-solid fa-file-export text-xs"></i>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full activity-table font-inter border-separate border-spacing-0">
                <thead>
                    <tr class="bg-surface-alt/50">
                        <th class="text-left px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Operational Date</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Mix (C/M)</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Visits</th>
                        <th class="text-right px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Car Rev.</th>
                        <th class="text-right px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Moto Rev.</th>
                        <th class="text-right px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Daily Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
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
                    <tr class="hover:bg-surface-alt/30 transition-all group">
                        <td class="px-10 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-surface-alt border border-color flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-calendar-day text-xs text-tertiary"></i>
                                </div>
                                <span class="text-base font-extrabold text-primary"><?= date('D, d M Y', strtotime($row['date'])) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <div class="inline-flex items-center gap-3 px-4 py-1.5 rounded-xl bg-surface-alt border border-color">
                                <span class="text-[11px] font-black text-primary"><?= $row['cars'] ?></span>
                                <span class="w-1 h-1 rounded-full bg-tertiary/30"></span>
                                <span class="text-[11px] font-black text-primary"><?= $row['motos'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-center font-manrope font-black text-primary text-xl"><?= number_format($row['total_count']) ?></td>
                        <td class="px-6 py-6 text-right font-manrope font-extrabold text-tertiary text-sm"><?= fmt_idr((float)$row['revenue_car']) ?></td>
                        <td class="px-6 py-6 text-right font-manrope font-extrabold text-tertiary text-sm"><?= fmt_idr((float)$row['revenue_moto']) ?></td>
                        <td class="px-10 py-6 text-right font-manrope font-black text-primary text-lg"><?= fmt_idr((float)$row['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($daily)): ?>
                <tfoot class="bg-surface-alt/40">
                    <tr>
                        <td class="px-10 py-8 font-black text-[10px] uppercase tracking-[0.2em] text-tertiary">Audit Summary</td>
                        <td colspan="2"></td>
                        <td colspan="3" class="px-10 py-8 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-[9px] font-black text-tertiary uppercase tracking-widest mb-2">Aggregate Revenue</span>
                                <span class="font-manrope font-black text-3xl text-brand tracking-tighter">
                                    <?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?>
                                </span>
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
