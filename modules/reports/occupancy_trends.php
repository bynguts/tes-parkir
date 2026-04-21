<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

// --- DATA FETCHING (OCCUPANCY TRENDS) ---
$daily_usage = $pdo->query("
    SELECT 
        DATE(check_in_time) as date,
        COUNT(*) as total_visits,
        AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)) as avg_duration
    FROM `transaction`
    WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(check_in_time)
    ORDER BY date ASC
")->fetchAll();

$page_title = 'Occupancy Trends';
$page_subtitle = 'Historical utilization analysis and dwell time patterns.';

include '../../includes/header.php';
?>

<div class="p-6 space-y-6">
    <div class="bg-white rounded-3xl p-8 ring-1 ring-slate-900/5 shadow-2xl">
        <h3 class="font-manrope font-extrabold text-xl text-slate-900 mb-6">14-Day Utilization Overview</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-900/10">
                        <th class="text-left py-4 text-[11px] font-extrabold uppercase tracking-widest text-slate-400">Date</th>
                        <th class="text-center py-4 text-[11px] font-extrabold uppercase tracking-widest text-slate-400">Total Visits</th>
                        <th class="text-center py-4 text-[11px] font-extrabold uppercase tracking-widest text-slate-400">Avg. Dwell (Min)</th>
                        <th class="text-right py-4 text-[11px] font-extrabold uppercase tracking-widest text-slate-400">Trend Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($daily_usage as $row): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-4 font-manrope font-bold text-slate-900"><?= date('D, M d', strtotime($row['date'])) ?></td>
                        <td class="py-4 text-center">
                            <span class="px-3 py-1 bg-slate-900 text-white text-[10px] font-bold rounded-lg"><?= $row['total_visits'] ?></span>
                        </td>
                        <td class="py-4 text-center font-inter text-sm text-slate-600"><?= round($row['avg_duration'] ?? 0) ?>m</td>
                        <td class="py-4 text-right">
                            <?php $status = ($row['total_visits'] > 50) ? 'High Load' : 'Normal'; ?>
                            <span class="text-[9px] font-black uppercase tracking-widest <?= $status === 'High Load' ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $status ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
