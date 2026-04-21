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

$page_title = 'Traffic Flow';
$page_subtitle = 'Hourly entry volume analysis for the past 7 days.';

include '../../includes/header.php';
?>

<div class="p-6">
    <div class="bg-slate-900 rounded-3xl p-8 shadow-2xl">
        <h3 class="font-manrope font-extrabold text-xl text-white mb-8">Hourly Entrance Distribution</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach($hourly_flow as $hf): ?>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 flex flex-col items-center group hover:bg-white/10 transition-all">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2"><?= sprintf("%02d:00", $hf['hour']) ?></span>
                <span class="text-2xl font-manrope font-black text-white"><?= $hf['entries'] ?></span>
                <div class="w-full bg-white/5 h-1 rounded-full mt-4 overflow-hidden">
                    <?php $max_entries = max(array_column($hourly_flow, 'entries')) ?: 1; ?>
                    <div class="bg-emerald-400 h-full" style="width: <?= ($hf['entries'] / $max_entries * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
