<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'operator';

function is_active(string $target): string {
    global $current_page;
    return $current_page === basename($target) ? 'nav-active' : '';
}
?>
<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen w-64 bg-white flex flex-col z-40 shadow-sm">

    <!-- Brand -->
    <div class="px-6 h-20 border-b border-slate-900/10 flex items-center gap-3">
        <div class="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center">
            <i class="fa-solid fa-square-p text-white text-base"></i>
        </div>
        <div>
            <span class="font-inter text-slate-900 text-base leading-tight">Smart</span><span class="font-inter text-slate-900/40 text-base leading-tight">Parking</span>
            <div class="text-[10px] text-slate-900/40 font-inter font-normal uppercase tracking-widest leading-tight">Enterprise</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">

        <!-- OPERATIONS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest text-slate-900/40 font-inter">Operations</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>index.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('index.php') ?>">
                        <i class="fa-solid fa-house text-slate-900/40 text-sm"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/gate_simulator.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('gate_simulator.php') ?>">
                        <i class="fa-solid fa-door-open text-slate-900/40 text-sm"></i>
                        Smart Gate
                    </a>
                </li>
                <li>
                    <?php 
                    $badge_active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn(); 
                    ?>
                    <a href="<?= BASE_URL ?>modules/operations/active_vehicles.php"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('active_vehicles.php') ?>">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-car text-slate-900/40 text-sm"></i>
                            Active Vehicles
                        </div>
                        <?php if ($badge_active > 0): ?>
                        <span class="bg-amber-50/10 text-amber-700 text-[10px] font-bold px-2.5 py-0.5 rounded-lg border border-amber-500/10"><?= $badge_active ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <?php 
                    $badge_res = $pdo->query("SELECT COUNT(*) FROM reservation WHERE status IN ('pending','confirmed')")->fetchColumn(); 
                    ?>
                    <a href="<?= BASE_URL ?>modules/operations/reservation.php"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('reservation.php') ?>">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-calendar-check text-slate-900/40 text-sm"></i>
                            Reservations
                        </div>
                        <?php if ($badge_res > 0): ?>
                        <span class="bg-blue-50/10 text-blue-700 text-[10px] font-bold px-2.5 py-0.5 rounded-lg border border-blue-500/10"><?= $badge_res ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/scan_log.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('scan_log.php') ?>">
                        <i class="fa-solid fa-file-invoice-dollar text-slate-900/40 text-sm"></i>
                        Scan Log
                    </a>
                </li>

            </ul>
        </div>

        <!-- REPORTS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest text-slate-900/40 font-inter">Reports</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/revenue.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('revenue.php') ?>">
                        <i class="fa-solid fa-chart-column text-slate-900/40 text-sm"></i>
                        Revenue
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/slot_map.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('slot_map.php') ?>">
                        <i class="fa-solid fa-map-location-dot text-slate-900/40 text-sm"></i>
                        Slot Map
                    </a>
                </li>
            </ul>
        </div>

        <!-- ANALYTICS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest text-slate-900/40 font-inter">Intelligence & Analytics</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/analytics.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= strpos($_SERVER['PHP_SELF'], 'analytics.php') !== false ? 'nav-active' : '' ?>">
                        <i class="fa-solid fa-chart-pie text-slate-900/40 text-[13px] w-4"></i>
                        Intelligence Dashboard
                    </a>
                </li>
            </ul>
        </div>

        <!-- ADMINISTRATION -->
        <?php if (in_array($role, ['superadmin', 'admin'])): ?>
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest text-slate-900/40 font-inter">Administration</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/slots.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('slots.php') ?>">
                        <i class="fa-solid fa-table-cells-large text-slate-900/40 text-sm"></i>
                        Manage Slots
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/rates.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('rates.php') ?>">
                        <i class="fa-solid fa-money-check-dollar text-slate-900/40 text-sm"></i>
                        Manage Rates
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/operators.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('operators.php') ?>">
                        <i class="fa-solid fa-headset text-slate-900/40 text-sm"></i>
                        Operators
                    </a>
                </li>
                <?php if ($role === 'superadmin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/users.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal text-slate-900/60 hover:bg-slate-900/5 hover:text-slate-900 transition-all <?= is_active('users.php') ?>">
                        <i class="fa-solid fa-users text-slate-900/40 text-sm"></i>
                        Users
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Bottom: user info + logout -->
    <div class="px-4 py-4 border-t border-slate-900/5">
        <div class="flex items-center gap-3 mb-3">
            <div class="relative flex-shrink-0">
                <div class="w-8 h-8 rounded-full bg-slate-900 flex items-center justify-center">
                    <span class="text-white text-xs font-inter font-normal"><?= strtoupper(substr($username, 0, 1)) ?></span>
                </div>
                <!-- Status Indicator -->
                <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-inter font-normal text-slate-900 truncate"><?= $username ?></div>
                <div class="text-[10px] text-slate-900/40 font-normal uppercase tracking-wider font-inter"><?= $role ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php"
           class="flex items-center justify-center gap-2 w-full bg-slate-900/5 hover:bg-slate-900/10 text-slate-900/60 text-xs font-normal font-inter uppercase tracking-widest rounded-lg py-2 transition-all">
            <i class="fa-solid fa-power-off text-xs"></i>
            Logout
        </a>
    </div>
</aside>
