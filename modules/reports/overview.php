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

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <header class="flex justify-between items-center px-8 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Dashboard Overview</h1>
            <p class="text-slate-400 text-xs font-inter mt-0.5"><?= date('l, d F Y H:i') ?></p>
        </div>
        <div class="flex items-center gap-2 bg-slate-100 rounded-full px-4 py-2">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
            <span class="text-xs font-inter font-semibold text-slate-700 uppercase tracking-wider"><?= $role ?></span>
            <span class="text-slate-300">|</span>
            <span class="text-sm font-inter text-slate-700"><?= $username ?></span>
        </div>
    </header>

    <div class="p-8 max-w-[1440px] mx-auto">

        <!-- Alerts -->
        <?php if ($car_pct <= 20 && $car_total > 0): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-4">
            <span class="material-symbols-outlined text-red-500">warning</span>
            <div>
                <p class="font-inter font-semibold text-red-700 text-sm">Kapasitas Mobil Hampir Penuh!</p>
                <p class="font-inter text-red-500 text-xs">Hanya <?= $car_avail ?> dari <?= $car_total ?> slot tersedia.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
        <div class="flex items-center gap-3 bg-amber-50 rounded-xl px-5 py-4 mb-4">
            <span class="material-symbols-outlined text-amber-500">warning</span>
            <div>
                <p class="font-inter font-semibold text-amber-700 text-sm">Kapasitas Motor Hampir Penuh!</p>
                <p class="font-inter text-amber-500 text-xs">Hanya <?= $moto_avail ?> dari <?= $moto_total ?> slot tersedia.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bento Grid -->
        <div class="grid grid-cols-12 gap-4 mb-8">
            <!-- Revenue hero -->
            <div class="col-span-12 lg:col-span-5 bg-slate-900 rounded-2xl p-8 flex flex-col justify-between min-h-[180px]">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Pendapatan Hari Ini</p>
                    <span class="material-symbols-outlined text-slate-600">account_balance_wallet</span>
                </div>
                <div id="today-revenue-text">
                    <div class="font-manrope font-extrabold text-4xl text-white leading-none"><?= fmt_idr((float)$today_rev) ?></div>
                    <p class="text-slate-500 text-xs font-inter mt-2"><?= date('d M Y') ?></p>
                </div>
            </div>

            <!-- Active vehicles -->
            <div class="col-span-6 lg:col-span-3 bg-white rounded-2xl p-6 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Kendaraan Aktif</p>
                    <span class="material-symbols-outlined text-slate-300">timer</span>
                </div>
                <div class="font-manrope font-extrabold text-5xl text-slate-900"><?= $active ?></div>
                <p class="text-slate-400 text-xs font-inter mt-2">Sedang parkir saat ini</p>
            </div>

            <!-- Slot Mobil -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Mobil</p>
                    <span class="material-symbols-outlined text-slate-300 text-xl">directions_car</span>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $car_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $car_total ?></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all <?= $car_pct > 50 ? 'bg-emerald-500' : ($car_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>" style="width:<?= $car_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $car_pct ?>% tersedia</p>
            </div>

            <!-- Slot Motor -->
            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Motor</p>
                    <span class="material-symbols-outlined text-slate-300 text-xl">two_wheeler</span>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $moto_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $moto_total ?></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all <?= $moto_pct > 50 ? 'bg-emerald-500' : ($moto_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>" style="width:<?= $moto_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $moto_pct ?>% tersedia</p>
            </div>

            <!-- Quick Access -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-5">Akses Cepat</p>
                <div class="grid grid-cols-3 gap-3">
                    <?php $quick = [
                        ['../operations/gate_simulator.php', 'door_sensor',      'Smart Gate',     'Entry & exit gate'],
                        ['../operations/reservation.php',    'event_available',   'Reservasi',      'Pre-booking slot'],
                        ['slot_map.php',                     'grid_view',         'Peta Slot',      'Real-time slot map'],
                        ['../operations/active_vehicles.php','directions_car',    'Kendaraan Aktif','Monitor kendaraan'],
                        ['revenue.php',                      'bar_chart_4_bars',  'Revenue',        'Laporan finansial'],
                        ['../operations/scan_log.php',       'receipt_long',      'Scan Log',       'Log sensor gate'],
                    ];
                    foreach ($quick as $q): ?>
                    <a href="<?= $q[0] ?>" class="flex flex-col gap-2 bg-slate-50 hover:bg-slate-100 rounded-xl p-4 transition-all group">
                        <span class="material-symbols-outlined text-slate-400 group-hover:text-slate-700 text-2xl transition-colors"><?= $q[1] ?></span>
                        <div>
                            <div class="font-inter font-semibold text-sm text-slate-800"><?= $q[2] ?></div>
                            <div class="font-inter text-xs text-slate-400 mt-0.5"><?= $q[3] ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?= vite_widget_tags('src/today-revenue-widget.tsx') ?>
<?php include '../../includes/footer.php'; ?>
