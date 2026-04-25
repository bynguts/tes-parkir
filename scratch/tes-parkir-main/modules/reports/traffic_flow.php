<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

// --- DATA FETCHING (TRAFFIC FLOW) ---
$hourly_flow = $pdo->query("
    SELECT 
        HOUR(check_in_time) as hour,
        COUNT(*) as entries
    FROM `transaction`
    WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY hour
    ORDER BY hour ASC
")->fetchAll();

$max_entries = 0;
foreach($hourly_flow as $hf) { if($hf['entries'] > $max_entries) $max_entries = $hf['entries']; }
$max_entries = $max_entries ?: 1;

$page_title = 'Traffic Flow';
$page_subtitle = 'Hourly entry volume analysis for the past 7 days.';

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">

<style>
.hour-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 1.5rem;
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.hour-card:hover {
    transform: translateY(-4px);
    border-color: var(--brand);
    box-shadow: 0 20px 40px -10px var(--shadow-color);
}
.hour-card::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--brand);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.hour-card:hover::before { opacity: 1; }

.hour-label { font-size: 10px; font-weight: 800; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 8px; }
.hour-value { font-family: 'Manrope', sans-serif; font-weight: 800; font-size: 28px; color: var(--text-primary); }

.bar-container { width: 100%; height: 6px; background: var(--surface-alt); border-radius: 99px; margin-top: 16px; overflow: hidden; }
.bar-fill { height: 100%; background: var(--brand); transition: width 1s ease-out; }
</style>

<div class="px-10 py-10 max-w-[1400px] mx-auto flex flex-col gap-10">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center shadow-lg shrink-0">
                <i class="fa-solid fa-chart-line text-2xl"></i>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold text-primary tracking-tight font-manrope"><?= $page_title ?></h1>
                <p class="text-tertiary text-[13px] font-medium mt-1"><?= $page_subtitle ?></p>
            </div>
        </div>
        
        <div class="flex items-center gap-4 bg-surface border border-color rounded-2xl px-6 py-3 shadow-sm">
            <i class="fa-solid fa-calendar-days text-brand text-sm"></i>
            <span class="text-[11px] font-black uppercase tracking-widest text-primary">Last 7 Days Data Set</span>
        </div>
    </div>

    <!-- Main Distribution Card -->
    <div class="bento-card p-10 shadow-2xl border-color bg-surface/50 backdrop-blur-md">
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-4">
                <div class="w-2 h-8 bg-brand rounded-full"></div>
                <h3 class="card-title text-xl font-extrabold text-primary tracking-tight">Hourly Entrance Distribution</h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-[10px] font-black text-tertiary uppercase tracking-widest">Global Peak:</span>
                <span class="text-[10px] font-black text-brand uppercase tracking-widest"><?= $max_entries ?> Entries</span>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-6">
            <?php foreach($hourly_flow as $hf): ?>
            <div class="hour-card">
                <span class="hour-label"><?= sprintf("%02d:00", $hf['hour']) ?></span>
                <span class="hour-value"><?= $hf['entries'] ?></span>
                <div class="bar-container">
                    <div class="bar-fill" style="width: 0%" data-width="<?= ($hf['entries'] / $max_entries * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($hourly_flow)): ?>
        <div class="py-32 text-center">
            <div class="w-24 h-24 rounded-full bg-surface-alt/50 flex items-center justify-center mx-auto mb-6 text-tertiary opacity-30">
                <i class="fa-solid fa-clock-rotate-left text-4xl"></i>
            </div>
            <p class="text-[12px] font-black uppercase tracking-[0.2em] text-tertiary opacity-40">No traffic data recorded in the last 7 days.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Insight Summary (Bento Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bento-card p-8 flex items-center gap-6 border-color">
            <div class="w-12 h-12 rounded-2xl bg-brand/5 text-brand flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-arrow-trend-up text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-tertiary uppercase tracking-[0.2em]">Peak Hour</p>
                <?php 
                    $peak_h = 0; $peak_v = 0;
                    foreach($hourly_flow as $hf) { if($hf['entries'] > $peak_v) { $peak_v = $hf['entries']; $peak_h = $hf['hour']; } }
                ?>
                <p class="text-xl font-manrope font-black text-primary"><?= sprintf("%02d:00", $peak_h) ?></p>
            </div>
        </div>
        
        <div class="bento-card p-8 flex items-center gap-6 border-color">
            <div class="w-12 h-12 rounded-2xl bg-status-available-bg text-status-available-text flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-users text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-tertiary uppercase tracking-[0.2em]">Avg Entries / Hour</p>
                <p class="text-xl font-manrope font-black text-primary"><?= count($hourly_flow) ? round(array_sum(array_column($hourly_flow, 'entries')) / count($hourly_flow), 1) : 0 ?></p>
            </div>
        </div>

        <div class="bento-card p-8 flex items-center gap-6 border-color">
            <div class="w-12 h-12 rounded-2xl bg-status-reserved-bg text-status-reserved-text flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-tertiary uppercase tracking-[0.2em]">Data Reliability</p>
                <p class="text-xl font-manrope font-black text-primary">High Fidelity</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Animate bars
    setTimeout(() => {
        document.querySelectorAll('.bar-fill').forEach(bar => {
            bar.style.width = bar.dataset.width;
        });
    }, 300);
});
</script>

<?php include '../../includes/footer.php'; ?>
