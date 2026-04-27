<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

$summary   = get_slot_summary($pdo);
$car_avail  = $summary['car']['avail'] ?? 0;
$car_total  = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

$car_pct  = $car_total  > 0 ? round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? round(($moto_avail / $moto_total) * 100) : 100;

$active    = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$today_rev = $pdo->query("SELECT COALESCE(SUM(CEIL(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) / 60) * applied_rate), 0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();

$page_title = 'Operational Intelligence';
$page_subtitle = date('l, d F Y — H:i');

include '../../includes/header.php';
?>



<div class="px-10 py-10 max-w-[1750px] mx-auto space-y-10">
    
    <!-- ALERTS -->
    <?php if (($car_pct <= 20 && $car_total > 0) || ($moto_pct <= 20 && $moto_total > 0)): ?>
    <div class="space-y-4">
        <?php if ($car_pct <= 20 && $car_total > 0): ?>
        <div class="flex items-center gap-6 bg-status-lost-bg border border-status-lost-border rounded-3xl px-8 py-6 shadow-xl animate-in slide-in-from-top-4">
            <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-triangle-exclamation text-status-lost-text text-xl"></i>
            </div>
            <div>
                <p class="font-manrope font-black text-status-lost-text text-base uppercase tracking-tight">Car Capacity Critical</p>
                <p class="text-status-lost-text/70 text-xs font-bold uppercase tracking-widest mt-1">Only <?= $car_avail ?> of <?= $car_total ?> slots remaining in inventory.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
        <div class="flex items-center gap-6 bg-status-parked-bg border border-status-parked-border rounded-3xl px-8 py-6 shadow-xl animate-in slide-in-from-top-4">
            <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-triangle-exclamation text-status-parked-text text-xl"></i>
            </div>
            <div>
                <p class="font-manrope font-black text-status-parked-text text-base uppercase tracking-tight">Motorcycle Capacity Low</p>
                <p class="text-status-parked-text/70 text-xs font-bold uppercase tracking-widest mt-1">Only <?= $moto_avail ?> of <?= $moto_total ?> slots remaining in inventory.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- BENTO CORE -->
    <div class="grid grid-cols-12 gap-8 items-stretch">
        
        <!-- REVENUE HERO -->
        <div class="col-span-12 lg:col-span-5 bento-card bg-surface border-color rounded-[3rem] p-12 flex flex-col justify-between min-h-[280px] shadow-2xl relative overflow-hidden group">
            <div class="absolute -right-24 -top-24 w-48 h-48 bg-brand/10 rounded-full blur-3xl group-hover:bg-brand/20 transition-all duration-700"></div>
            
            <div class="relative z-10">
                <div class="flex items-baseline gap-3 whitespace-nowrap drop-shadow-2xl">
                    <span class="text-2xl font-black text-tertiary">Rp</span>
                    <div class="font-manrope font-black text-5xl text-primary tracking-tighter"><?= number_format((float)$today_rev, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="pt-8 border-t border-color flex items-center justify-between relative z-10">
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-brand/20 text-brand border border-brand/20 text-[9px] font-black rounded-full flex items-center gap-2 shadow-sm backdrop-blur-md">
                        <span class="w-1.5 h-1.5 bg-brand rounded-full animate-pulse shadow-[0_0_8px_rgba(99,102,241,0.8)]"></span>
                        LIVE SYNC
                    </span>
                </div>
                <p class="text-tertiary text-[9px] font-black uppercase tracking-widest"><?= date('l, d M Y') ?></p>
            </div>
        </div>

        <!-- ACTIVE VEHICLES -->
        <div class="col-span-6 lg:col-span-3 bento-card p-12 border-color shadow-xl flex flex-col justify-between group">
            <div class="flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-surface-alt border border-color flex items-center justify-center group-hover:bg-brand group-hover:text-white transition-all duration-500 shadow-sm group-hover:shadow-brand/20">
                    <i class="fa-solid fa-gauge-high text-2xl group-hover:scale-110 transition-transform"></i>
                </div>
                <div>
                    <h3 class="font-manrope font-black text-xl text-primary tracking-tight">Active Load</h3>
                </div>
            </div>
            <div class="flex items-baseline gap-3 mt-10">
                <span class="font-manrope font-black text-7xl text-primary tracking-tighter"><?= $active ?></span>
                <span class="text-tertiary text-[11px] font-black uppercase tracking-[0.2em]">Units</span>
            </div>
            <p class="text-tertiary text-[10px] font-black uppercase tracking-[0.2em] mt-8">Occupying parking zones</p>
        </div>

        <!-- SLOT STATS -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-8">
            <div class="bento-card p-8 border-color shadow-xl flex-1 group">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-surface-alt border border-color flex items-center justify-center text-brand">
                            <i class="fa-solid fa-car text-lg"></i>
                        </div>
                        <h3 class="font-manrope font-black text-base text-primary tracking-tight">Car Fleet</h3>
                    </div>
                    <span class="text-base font-black text-primary"><?= $car_avail ?> <span class="text-tertiary text-[10px] uppercase ml-1">/ <?= $car_total ?></span></span>
                </div>
                <div class="w-full bg-surface-alt rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="h-full rounded-full transition-all duration-1000" style="width:<?= $car_pct ?>%; background: <?= $car_pct > 20 ? 'var(--brand)' : 'var(--status-lost-text)' ?>;"></div>
                </div>
                <div class="flex justify-between items-center mt-5">
                    <p class="text-tertiary text-[9px] font-black uppercase tracking-widest"><?= $car_pct ?>% Available Inventory</p>
                </div>
            </div>

            <div class="bento-card p-8 border-color shadow-xl flex-1 group">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-surface-alt border border-color flex items-center justify-center text-brand">
                            <i class="fa-solid fa-motorcycle text-lg"></i>
                        </div>
                        <h3 class="font-manrope font-black text-base text-primary tracking-tight">Moto Fleet</h3>
                    </div>
                    <span class="text-base font-black text-primary"><?= $moto_avail ?> <span class="text-tertiary text-[10px] uppercase ml-1">/ <?= $moto_total ?></span></span>
                </div>
                <div class="w-full bg-surface-alt rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="h-full rounded-full transition-all duration-1000" style="width:<?= $moto_pct ?>%; background: <?= $moto_pct > 20 ? 'var(--brand)' : 'var(--status-lost-text)' ?>;"></div>
                </div>
                <div class="flex justify-between items-center mt-5">
                    <p class="text-tertiary text-[9px] font-black uppercase tracking-widest"><?= $moto_pct ?>% Available Inventory</p>
                </div>
            </div>
        </div>

        <!-- SYSTEM NAVIGATION -->
        <div class="col-span-12 bento-card p-12 border-color shadow-xl">
            <div class="flex items-center justify-between mb-12">
                <div>
                    <h3 class="font-manrope font-black text-2xl text-primary tracking-tight">System Navigation</h3>
                    <p class="text-tertiary text-[11px] font-black uppercase tracking-widest mt-1">Operational Command Centers</p>
                </div>
                <div class="hidden lg:block h-px flex-grow mx-12 bg-border-color opacity-50"></div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                <?php $quick = [
                    ['../operations/gate_simulator.php', 'fa-solid fa-door-open',      'Smart Gate',     'Simulate'],
                    ['../operations/reservation.php',    'fa-solid fa-calendar-check',   'Booking',        'Queue'],
                    ['slot_map.php',                     'fa-solid fa-layer-group',         'Spatial Map',    'Inventory'],
                    ['../operations/active_vehicles.php','fa-solid fa-car-side',    'Live Monitor',   'Operations'],
                    ['revenue.php',                      'fa-solid fa-chart-pie',         'Audit Log',      'Financial'],
                    ['../operations/scan_log.php',       'fa-solid fa-fingerprint',      'Security',       'Records'],
                ];
                foreach ($quick as $q): ?>
                <a href="<?= $q[0] ?>" class="flex flex-col gap-6 bg-surface-alt/50 border border-color hover:bg-primary hover:border-primary rounded-3xl p-8 transition-all duration-300 group shadow-sm hover:shadow-2xl hover:-translate-y-2">
                    <div class="w-12 h-12 rounded-2xl bg-surface flex items-center justify-center shadow-md group-hover:bg-surface/10 transition-colors">
                        <i class="<?= $q[1] ?> text-primary group-hover:text-surface text-xl transition-colors"></i>
                    </div>
                    <div>
                        <div class="font-manrope font-black text-base text-primary group-hover:text-surface transition-colors tracking-tight"><?= $q[2] ?></div>
                        <div class="font-manrope font-black text-[9px] text-tertiary group-hover:text-surface/40 uppercase tracking-widest mt-1 transition-colors"><?= $q[3] ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
