<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

$page_title = 'Occupancy Analytics';
$page_subtitle = 'Temporal utilization patterns and spatial intelligence.';

// --- DATA FETCHING ---
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

$peak_occupancy = $pdo->query("
    SELECT 
        HOUR(check_in_time) as hour,
        COUNT(*) as volume
    FROM `transaction`
    WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY HOUR(check_in_time)
    ORDER BY hour ASC
")->fetchAll();

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <!-- PREMIUM HEADER -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 rounded-3xl icon-container flex items-center justify-center shadow-xl shrink-0">
                <i class="fa-solid fa-chart-line text-3xl"></i>
            </div>
            <div>
                <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Occupancy Intelligence</h2>
                <p class="text-tertiary mt-1 text-sm font-medium">Tracking temporal load and facility utilization dynamics.</p>
            </div>
        </div>

        <div class="flex items-center gap-3 bg-surface border border-color p-2 rounded-2xl shadow-sm">
            <span class="px-5 py-2 bg-status-available-bg text-status-available-text text-[10px] font-black uppercase tracking-widest rounded-xl border border-status-available-border">
                <i class="fa-solid fa-bolt-lightning mr-2"></i> Real-time Telemetry
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Trend Chart -->
        <div class="lg:col-span-2 bento-card p-10 border-color shadow-xl">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center gap-4">
                    <div class="w-1.5 h-8 bg-brand rounded-full"></div>
                    <h3 class="text-xl font-manrope font-black text-primary tracking-tight">Load Trajectory</h3>
                </div>
                <span class="text-[10px] font-black text-tertiary uppercase tracking-widest bg-surface-alt px-4 py-2 rounded-xl">14-Day Cycle</span>
            </div>
            <div class="h-[300px]">
                <canvas id="loadChart"></canvas>
            </div>
        </div>

        <!-- Dwell Intensity -->
        <div class="bento-card p-10 border-color shadow-xl flex flex-col">
            <h3 class="text-xl font-manrope font-black text-primary tracking-tight mb-10">Stay Intensity</h3>
            <div class="space-y-6 flex-grow">
                <?php 
                $max_dur = max(array_column($daily_usage, 'avg_duration') ?: [1]);
                $recent_usage = array_reverse(array_slice($daily_usage, -5));
                foreach($recent_usage as $row): 
                    $pct = ($row['avg_duration'] / $max_dur) * 100;
                ?>
                <div class="space-y-2">
                    <div class="flex justify-between items-end">
                        <span class="text-[10px] font-black text-primary uppercase tracking-widest"><?= date('D, d M', strtotime($row['date'])) ?></span>
                        <span class="text-xs font-black text-brand"><?= round($row['avg_duration']) ?>m</span>
                    </div>
                    <div class="h-1.5 w-full bg-surface-alt rounded-full overflow-hidden">
                        <div class="h-full bg-brand transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-10 pt-10 border-t border-color">
                <p class="text-[10px] font-black text-tertiary uppercase tracking-widest leading-relaxed">
                    Statistical average dwell time for the current operational window.
                </p>
            </div>
        </div>
    </div>

    <!-- CHRONOLOGICAL TABLE -->
    <div class="bento-card overflow-hidden shadow-xl border-color">
        <div class="px-10 py-8 border-b border-color bg-surface/50">
            <div class="flex items-center gap-4">
                <div class="w-1.5 h-8 bg-brand rounded-full"></div>
                <div>
                    <h3 class="text-xl font-manrope font-black text-primary tracking-tight">Historical Log</h3>
                    <p class="text-tertiary text-[11px] font-black uppercase tracking-widest mt-0.5">Raw utilization metrics per node</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full activity-table font-inter border-separate border-spacing-0">
                <thead>
                    <tr class="bg-surface-alt/50">
                        <th class="text-left px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Date</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Total Load</th>
                        <th class="text-center px-6 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Avg. Dwell</th>
                        <th class="text-right px-10 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Operating Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
                    <?php foreach(array_reverse($daily_usage) as $row): ?>
                    <tr class="hover:bg-surface-alt/30 transition-all group">
                        <td class="px-10 py-6">
                            <span class="text-base font-extrabold text-primary"><?= date('l, d M Y', strtotime($row['date'])) ?></span>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <span class="px-4 py-1.5 rounded-xl bg-surface-alt text-primary text-[11px] font-black shadow-sm border border-color">
                                <?= $row['total_visits'] ?> Units
                            </span>
                        </td>
                        <td class="px-6 py-6 text-center font-manrope font-black text-primary text-lg">
                            <?= round($row['avg_duration']) ?> <span class="text-[10px] text-tertiary uppercase ml-1">Min</span>
                        </td>
                        <td class="px-10 py-6 text-right">
                            <?php 
                            $is_high = $row['total_visits'] > 20; 
                            $status_bg = $is_high ? 'var(--status-parked-bg)' : 'var(--status-available-bg)';
                            $status_text = $is_high ? 'var(--status-parked-text)' : 'var(--status-available-text)';
                            $status_border = $is_high ? 'var(--status-parked-border)' : 'var(--status-available-border)';
                            $label = $is_high ? 'High Frequency' : 'Stable';
                            ?>
                            <span class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border" style="background: <?= $status_bg ?>; color: <?= $status_text ?>; border-color: <?= $status_border ?>;">
                                <?= $label ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('loadChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $daily_usage)) ?>,
        datasets: [{
            label: 'Visits',
            data: <?= json_encode(array_column($daily_usage, 'total_visits')) ?>,
            borderColor: '#6366f1',
            borderWidth: 4,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: '#6366f1',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 3,
            fill: true,
            backgroundColor: 'rgba(99, 102, 241, 0.05)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                grid: { color: 'rgba(99, 102, 241, 0.05)', drawBorder: false },
                ticks: { color: '#94a3b8', font: { weight: '800', size: 10 }, padding: 10 }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#94a3b8', font: { weight: '800', size: 10 }, padding: 10 }
            }
        }
    }
});
</script>

<?php include '../../includes/header.php'; ?>
