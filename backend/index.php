<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_once 'includes/functions.php';

$summary   = get_slot_summary($pdo);
$car_avail = $summary['car']['avail'] ?? 0;
$car_total = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

$car_pct  = $car_total  > 0 ? round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? round(($moto_avail / $moto_total) * 100) : 100;

$active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();

$page_title    = 'Dashboard';
$page_subtitle = date('l, d F Y');

$on_duty = is_on_duty();

include 'includes/header.php';
?>

    <div class="p-10 max-w-[1440px] mx-auto">

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

        <div class="grid grid-cols-12 gap-4 mb-8">

            <div class="col-span-12 lg:col-span-5 bg-slate-900 rounded-2xl p-8 flex flex-col justify-between min-h-[180px]">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Pendapatan Hari Ini</p>
                    <span class="material-symbols-outlined text-slate-600 text-xl">payments</span>
                </div>
                <div>
                    <div class="font-manrope font-extrabold text-4xl text-white leading-none"><?= fmt_idr((float)$today_rev) ?></div>
                    <p class="text-slate-500 text-xs font-inter mt-2"><?= date('d M Y') ?></p>
                </div>
            </div>

            <div class="col-span-6 lg:col-span-3 bg-white rounded-2xl p-6 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Kendaraan Aktif</p>
                    <span class="material-symbols-outlined text-slate-300">timer</span>
                </div>
                <div class="font-manrope font-extrabold text-5xl text-slate-900"><?= $active ?></div>
                <p class="text-slate-400 text-xs font-inter mt-2">Sedang parkir saat ini</p>
            </div>

            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Mobil</p>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-xl">directions_car</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $car_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $car_total ?> tersedia</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $car_pct > 50 ? 'bg-emerald-500' : ($car_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $car_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $car_pct ?>% tersedia</p>
            </div>

            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Motor</p>
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-violet-600 text-xl">two_wheeler</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $moto_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $moto_total ?> tersedia</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $moto_pct > 50 ? 'bg-emerald-500' : ($moto_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $moto_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $moto_pct ?>% tersedia</p>
            </div>

        </div>
    </div>

<?php include 'includes/footer.php'; ?>
