<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$daily = $pdo->query("
    SELECT CAST(t.check_out_time AS DATE) AS date,
           SUM(v.vehicle_type='car')         AS cars,
           SUM(v.vehicle_type='motorcycle')  AS motos,
           COUNT(*)                          AS total_count,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS revenue_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS revenue_moto,
           SUM(t.total_fee) AS total_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
    GROUP BY CAST(t.check_out_time AS DATE)
    ORDER BY date DESC
")->fetchAll();

$totals = $pdo->query("
    SELECT SUM(v.vehicle_type='car')         AS total_cars,
           SUM(v.vehicle_type='motorcycle')  AS total_motos,
           COUNT(*)                          AS grand_total,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_moto,
           SUM(t.total_fee) AS grand_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
")->fetch();

$page_title = 'Revenue Report';
$page_subtitle = 'Aggregated financial summary and daily performance.';

include '../../includes/header.php';
?>

    <div class="p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-slate-900 rounded-2xl p-6 ring-1 ring-white/10 shadow-[0_30px_60px_-12px_rgba(15,23,42,0.3)]">
                <div class="flex items-center gap-4 mb-6 -mt-2">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10 group-hover:bg-white group-hover:text-slate-900 transition-all">
                        <i class="fa-solid fa-receipt text-white text-lg group-hover:text-inherit transition-all"></i>
                    </div>
                    <h3 class="font-manrope font-bold text-sm text-white/60">Total Visits</h3>
                </div>
                <div class="font-manrope font-extrabold text-4xl text-white"><?= number_format($totals['grand_total'] ?? 0) ?></div>
                <p class="text-slate-900/40 text-[10px] uppercase font-bold tracking-wider font-inter mt-4">paid transactions</p>
            </div>

            <div class="bg-white rounded-2xl p-6 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)]">
                <div class="flex items-center gap-4 mb-6 -mt-2">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/10">
                        <i class="fa-solid fa-car text-blue-600 text-lg"></i>
                    </div>
                    <h3 class="font-manrope font-bold text-sm text-slate-900">Car Revenue</h3>
                </div>
                <div class="font-manrope font-extrabold text-4xl text-slate-900 mb-1"><?= number_format($totals['total_cars'] ?? 0) ?></div>
                <p class="text-slate-900/40 text-[11px] uppercase font-extrabold tracking-[0.2em] font-inter mt-3"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></p>
            </div>

            <div class="bg-white rounded-2xl p-6 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)]">
                <div class="flex items-center gap-4 mb-6 -mt-2">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/10">
                        <i class="fa-solid fa-motorcycle text-emerald-600 text-lg"></i>
                    </div>
                    <h3 class="font-manrope font-bold text-sm text-slate-900">Moto Revenue</h3>
                </div>
                <div class="font-manrope font-extrabold text-4xl text-slate-900 mb-1"><?= number_format($totals['total_motos'] ?? 0) ?></div>
                <p class="text-slate-900/40 text-[11px] uppercase font-extrabold tracking-[0.2em] font-inter mt-3"><?= fmt_idr((float)($totals['rev_moto'] ?? 0)) ?></p>
            </div>

            <div class="bg-white rounded-2xl p-6 ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] border-l-4 border-amber-400">
                <div class="flex items-center gap-4 mb-6 -mt-2">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/10">
                        <i class="fa-solid fa-wallet text-amber-600 text-lg"></i>
                    </div>
                    <h3 class="font-manrope font-bold text-sm text-slate-900">Net Total</h3>
                </div>
                <div class="font-manrope font-extrabold text-2xl text-amber-600"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></div>
                <p class="text-slate-900/40 text-[11px] uppercase font-extrabold tracking-[0.2em] font-inter mt-4">cumulative revenue</p>
            </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-900/10 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-slate-900/40 text-lg"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Daily Breakdown</h2>
            </div>
            <div class="overflow-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-900/10">
                            <th class="text-left px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Operational Date</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Car Vol.</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Moto Vol.</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Total Visits</th>
                            <th class="text-right px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Car Rev.</th>
                            <th class="text-right px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Moto Rev.</th>
                            <th class="text-right px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Daily Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-900/[0.03]">
                        <?php if (empty($daily)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-16">
                                <i class="fa-solid fa-folder-open text-5xl text-slate-900/10 block mb-3"></i>
                                <p class="text-slate-900/40 text-sm font-inter">No transaction data recorded yet.</p>
                            </td>
                        </tr>
                        <?php else: foreach ($daily as $row): ?>
                        <tr class="hover:bg-slate-900/[0.02] transition-colors">
                            <td class="px-6 py-4 font-inter font-semibold text-sm text-slate-900">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-calendar text-slate-900/20 text-sm"></i>
                                    <?= date('D, d M Y', strtotime($row['date'])) ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-blue-50/10 text-blue-700 border border-blue-500/10 text-[10px] font-bold font-inter uppercase tracking-widest px-3 py-1 rounded-lg"><?= $row['cars'] ?></span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-emerald-50/10 text-emerald-700 border border-emerald-500/10 text-[10px] font-bold font-inter uppercase tracking-widest px-3 py-1 rounded-lg"><?= $row['motos'] ?></span>
                            </td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= $row['total_count'] ?></td>
                            <td class="px-4 py-4 text-right text-slate-900/40 text-sm font-inter"><?= fmt_idr((float)$row['revenue_car']) ?></td>
                            <td class="px-4 py-4 text-right text-slate-900/40 text-sm font-inter"><?= fmt_idr((float)$row['revenue_moto']) ?></td>
                            <td class="px-6 py-4 text-right font-manrope font-bold text-emerald-700"><?= fmt_idr((float)$row['total_revenue']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($daily)): ?>
                    <tfoot class="bg-slate-900/[0.03]">
                        <tr class="border-t-2 border-slate-900/10">
                            <td class="px-6 py-4 font-inter font-bold text-[10px] uppercase tracking-widest text-slate-900/40">Cumulative Total</td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($totals['total_cars'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($totals['total_motos'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-center font-manrope font-extrabold text-2xl text-slate-900"><?= number_format($totals['grand_total'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-right font-inter font-bold text-slate-900"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></td>
                            <td class="px-4 py-4 text-right font-inter font-bold text-slate-900"><?= fmt_idr((float)($totals['rev_moto'] ?? 0)) ?></td>
                            <td class="px-6 py-4 text-right font-manrope font-extrabold text-xl text-amber-600"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

<?php include '../../includes/footer.php'; ?>
