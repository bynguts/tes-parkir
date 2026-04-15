<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$daily = $pdo->query("
    SELECT CAST(t.check_out_time AS DATE) AS date,
           SUM(v.vehicle_type='car')         AS cars,
           SUM(v.vehicle_type='motorcycle')  AS motorcycles,
           COUNT(*)                          AS total_vehicles,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS revenue_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS revenue_motorcycle,
           SUM(t.total_fee) AS total_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
    GROUP BY CAST(t.check_out_time AS DATE)
    ORDER BY date DESC
")->fetchAll();

$totals = $pdo->query("
    SELECT SUM(v.vehicle_type='car')         AS total_cars,
           SUM(v.vehicle_type='motorcycle')  AS total_motorcycles,
           COUNT(*)                          AS grand_total,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_motorcycle,
           SUM(t.total_fee) AS grand_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
")->fetch();

$page_title = 'Laporan Revenue';
include '../../includes/header.php';
?>

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <header class="flex justify-between items-center px-8 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Analytics & Revenue</h1>
            <p class="text-slate-400 text-xs font-inter mt-0.5">Ringkasan agregat finansial dan kinerja harian.</p>
        </div>
    </header>

    <div class="p-8 max-w-[1440px] mx-auto">

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-slate-900 rounded-2xl p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-3">Total Trx</p>
                <div class="font-manrope font-extrabold text-4xl text-white"><?= number_format($totals['grand_total'] ?? 0) ?></div>
                <p class="text-slate-500 text-xs font-inter mt-2">transaksi lunas</p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-3">Mobil Keluar</p>
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-blue-500 text-xl">directions_car</span>
                    <div class="font-manrope font-extrabold text-4xl text-slate-900"><?= number_format($totals['total_cars'] ?? 0) ?></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-3">Motor Keluar</p>
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-emerald-500 text-xl">two_wheeler</span>
                    <div class="font-manrope font-extrabold text-4xl text-slate-900"><?= number_format($totals['total_motorcycles'] ?? 0) ?></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= fmt_idr((float)($totals['rev_motorcycle'] ?? 0)) ?></p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-sm border-l-4 border-amber-400">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-3">Total Net Revenue</p>
                <div class="font-manrope font-extrabold text-2xl text-amber-600"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></div>
                <p class="text-slate-400 text-xs font-inter mt-2">pendapatan kumulatif</p>
            </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400 text-xl">calendar_month</span>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Breakdown Harian</h2>
            </div>
            <div class="overflow-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Tanggal Operasional</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Vol. Mobil</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Vol. Motor</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Total Kunjungan</th>
                            <th class="text-right px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Rev. Mobil</th>
                            <th class="text-right px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Rev. Motor</th>
                            <th class="text-right px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Revenue Harian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($daily)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-16">
                                <span class="material-symbols-outlined text-5xl text-slate-200 block mb-3">folder_open</span>
                                <p class="text-slate-400 text-sm font-inter">Belum ada data transaksi yang tercatat.</p>
                            </td>
                        </tr>
                        <?php else: foreach ($daily as $row): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-inter font-semibold text-sm text-slate-800">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-slate-300 text-base">event</span>
                                    <?= date('D, d M Y', strtotime($row['date'])) ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-blue-50 text-blue-700 text-xs font-bold font-inter px-3 py-1 rounded-full"><?= $row['cars'] ?></span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-emerald-50 text-emerald-700 text-xs font-bold font-inter px-3 py-1 rounded-full"><?= $row['motorcycles'] ?></span>
                            </td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= $row['total_vehicles'] ?></td>
                            <td class="px-4 py-4 text-right text-slate-500 text-sm font-inter"><?= fmt_idr((float)$row['revenue_car']) ?></td>
                            <td class="px-4 py-4 text-right text-slate-500 text-sm font-inter"><?= fmt_idr((float)$row['revenue_motorcycle']) ?></td>
                            <td class="px-6 py-4 text-right font-manrope font-bold text-emerald-700"><?= fmt_idr((float)$row['total_revenue']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($daily)): ?>
                    <tfoot class="bg-slate-50">
                        <tr class="border-t-2 border-slate-200">
                            <td class="px-6 py-4 font-inter font-bold text-xs uppercase tracking-widest text-slate-400">Kumulatif Total</td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($totals['total_cars'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($totals['total_motorcycles'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-center font-manrope font-extrabold text-2xl text-slate-900"><?= number_format($totals['grand_total'] ?? 0) ?></td>
                            <td class="px-4 py-4 text-right font-inter font-bold text-slate-700"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></td>
                            <td class="px-4 py-4 text-right font-inter font-bold text-slate-700"><?= fmt_idr((float)($totals['rev_motorcycle'] ?? 0)) ?></td>
                            <td class="px-6 py-4 text-right font-manrope font-extrabold text-xl text-amber-600"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
