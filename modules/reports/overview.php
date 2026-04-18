<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$summary   = get_slot_summary($pdo);
$car_avail  = $summary['car']['avail'] ?? 0;
$car_total  = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

$car_pct  = $car_total  > 0 ? round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? round(($moto_avail / $moto_total) * 100) : 100;

$active    = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();

$page_title = 'Dashboard Overview';
$page_subtitle = date('l, d F Y — H:i');
include '../../includes/header.php';

function vite_widget_tags(string $entry): string {
    $manifest_path = __DIR__ . '/assets/home/.vite/manifest.json';
    if (!is_file($manifest_path)) return '';
    $json = file_get_contents($manifest_path);
    if ($json === false) return '';
    $manifest = json_decode($json, true);
    if (!is_array($manifest) || empty($manifest[$entry]['file'])) return '';
    $base = 'assets/home/';
    $file = $manifest[$entry]['file'];
    return '<script type="module" src="' . htmlspecialchars($base . $file) . '"></script>';
}
?>

<div class="p-8">

    <!-- Alerts -->
    <?php if ($car_pct <= 20 && $car_total > 0): ?>
    <div class="flex items-center gap-4 bg-red-50/10 border border-red-500/20 rounded-2xl px-6 py-5 mb-6 shadow-sm">
        <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
        <div>
            <p class="font-inter font-extrabold text-red-700 text-sm uppercase tracking-wider">Car Capacity Critical</p>
            <p class="font-inter text-red-500 text-xs font-semibold">Only <?= $car_avail ?> of <?= $car_total ?> slots remaining.</p>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
    <div class="flex items-center gap-4 bg-amber-50/10 border border-amber-500/20 rounded-2xl px-6 py-5 mb-6 shadow-sm">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
        <div>
            <p class="font-inter font-extrabold text-amber-700 text-sm uppercase tracking-wider">Motorcycle Capacity Low</p>
            <p class="font-inter text-amber-500 text-xs font-semibold">Only <?= $moto_avail ?> of <?= $moto_total ?> slots remaining.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bento Grid -->
    <div class="grid grid-cols-12 gap-6 mb-8 items-stretch">
        
        <!-- Revenue Hero -->
        <div class="col-span-12 lg:col-span-5 bg-slate-900 rounded-3xl p-8 flex flex-col justify-between min-h-[220px] ring-1 ring-white/10 shadow-2xl shadow-slate-900/20 relative overflow-hidden group">
            <div class="absolute -right-20 -top-20 w-40 h-40 bg-white/5 rounded-full blur-3xl group-hover:bg-white/10 transition-all duration-700"></div>
            
            <div class="flex items-center justify-between relative z-10">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-white/40 font-inter">Live Revenue Flow</p>
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10 group-hover:bg-white/20 transition-all">
                    <i class="fa-solid fa-money-bill-trend-up text-white text-lg"></i>
                </div>
            </div>
            
            <div id="today-revenue-text" class="relative z-10 flex flex-col items-center justify-center flex-grow text-center">
                <div class="font-manrope font-extrabold text-5xl text-white leading-none tracking-tighter mb-4 drop-shadow-2xl"><?= fmt_idr((float)$today_rev) ?></div>
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 bg-emerald-50/20 text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold rounded-lg flex items-center gap-1.5 shadow-sm backdrop-blur-md">
                        <span class="w-1.5 h-1.5 bg-emerald-50 rounded-full animate-pulse"></span>
                        LIVE STREAMING
                    </span>
                </div>
            </div>

            <div class="pt-6 border-t border-white/10 flex items-center justify-between relative z-10 mt-2">
                <p class="text-white/40 text-[10px] font-extrabold uppercase tracking-[0.2em]"><?= date('l, d M Y') ?></p>
            </div>
        </div>

        <!-- Active Vehicles -->
        <div class="col-span-6 lg:col-span-3 bg-white rounded-3xl p-8 flex flex-col justify-between ring-1 ring-slate-900/5 shadow-xl shadow-slate-900/[0.03] hover:shadow-2xl transition-all duration-500 group">
            <div class="flex items-center justify-between mb-6 -mt-2">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/30 font-inter">Current Load</p>
                <div class="w-10 h-10 rounded-xl bg-blue-50/10 flex items-center justify-center border border-blue-500/20 group-hover:bg-slate-50 transition-all duration-500">
                    <i class="fa-solid fa-gauge-high text-blue-600 text-lg"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-manrope font-extrabold text-6xl text-slate-900 tracking-tighter"><?= $active ?></span>
                <span class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-widest">Units</span>
            </div>
            <p class="text-slate-900/30 text-[10px] font-extrabold uppercase tracking-widest mt-6">Occupying parking zones</p>
        </div>

        <!-- Slot Stats Stack -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
            <!-- Car Slots -->
            <div class="bg-white rounded-3xl p-6 ring-1 ring-slate-900/5 shadow-xl shadow-slate-900/[0.03] group flex-1">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50/10 flex items-center justify-center text-indigo-600">
                            <i class="fa-solid fa-car text-sm"></i>
                        </div>
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-slate-900/40 font-inter">Car Network</p>
                    </div>
                    <span class="text-[13px] font-extrabold text-slate-900"><?= $car_avail ?> <span class="text-slate-900/40 text-[10px] uppercase font-bold tracking-tighter">/ <?= $car_total ?></span></span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 <?= $car_pct > 50 ? 'bg-indigo-50' : ($car_pct > 20 ? 'bg-amber-400' : 'bg-red-50') ?>" style="width:<?= $car_pct ?>%"></div>
                </div>
                <div class="flex justify-between items-center mt-3">
                    <p class="text-slate-900/30 text-[9px] font-extrabold uppercase tracking-widest"><?= $car_pct ?>% Available Space</p>
                </div>
            </div>

            <!-- Motorcycle Slots -->
            <div class="bg-white rounded-3xl p-6 ring-1 ring-slate-900/5 shadow-xl shadow-slate-900/[0.03] group flex-1">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50/10 flex items-center justify-center text-emerald-600">
                            <i class="fa-solid fa-motorcycle text-sm"></i>
                        </div>
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-slate-900/40 font-inter">Moto Network</p>
                    </div>
                    <span class="text-[13px] font-extrabold text-slate-900"><?= $moto_avail ?> <span class="text-slate-900/40 text-[10px] uppercase font-bold tracking-tighter">/ <?= $moto_total ?></span></span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 <?= $moto_pct > 50 ? 'bg-emerald-50' : ($moto_pct > 20 ? 'bg-amber-400' : 'bg-red-50') ?>" style="width:<?= $moto_pct ?>%"></div>
                </div>
                <div class="flex justify-between items-center mt-3">
                    <p class="text-slate-900/30 text-[9px] font-extrabold uppercase tracking-widest"><?= $moto_pct ?>% Available Space</p>
                </div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="col-span-12 bg-white rounded-3xl p-10 ring-1 ring-slate-900/5 shadow-xl shadow-slate-900/[0.03]">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="font-manrope font-extrabold text-xl text-slate-900 tracking-tight">System Navigation</h3>
                    <p class="text-slate-900/30 text-[11px] font-extrabold uppercase tracking-widest mt-1">Operational Entry Points</p>
                </div>
                <div class="h-px flex-grow mx-8 bg-slate-900/5"></div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php $quick = [
                    ['../operations/gate_simulator.php', 'fa-solid fa-door-open',      'Smart Gate',     'Sensors'],
                    ['../operations/reservation.php',    'fa-solid fa-calendar-check',   'Booking',        'Reservation'],
                    ['slot_map.php',                     'fa-solid fa-layer-group',         'Slot Map',       'Spatial'],
                    ['../operations/active_vehicles.php','fa-solid fa-car-side',    'Active List',    'Monitor'],
                    ['revenue.php',                      'fa-solid fa-chart-pie',         'Revenue',        'Financial'],
                    ['../operations/scan_log.php',       'fa-solid fa-fingerprint',      'Scan Log',       'Security'],
                ];
                foreach ($quick as $q): ?>
                <a href="<?= $q[0] ?>" class="flex flex-col gap-4 bg-slate-50 border border-slate-900/5 hover:bg-slate-900 hover:border-slate-900 rounded-2xl p-6 transition-all duration-300 group">
                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm group-hover:bg-white/10 transition-colors">
                        <i class="<?= $q[1] ?> text-slate-900 group-hover:text-white text-lg transition-colors"></i>
                    </div>
                    <div>
                        <div class="font-inter font-extrabold text-sm text-slate-900 group-hover:text-white transition-colors"><?= $q[2] ?></div>
                        <div class="font-inter font-bold text-[10px] text-slate-900/30 group-hover:text-white/40 uppercase tracking-widest mt-0.5 transition-colors"><?= $q[3] ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?= vite_widget_tags('src/today-revenue-widget.tsx') ?>
<?php include '../../includes/footer.php'; ?>
