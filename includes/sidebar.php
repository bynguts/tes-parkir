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
    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
        <div class="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-content center">
            <span class="material-symbols-outlined text-white text-base leading-none flex items-center justify-center w-full h-full">local_parking</span>
        </div>
        <div>
            <span class="font-manrope font-extrabold text-slate-900 text-base leading-tight">Smart</span><span class="font-manrope font-extrabold text-slate-400 text-base leading-tight">Parking</span>
            <div class="text-[10px] text-slate-400 font-inter uppercase tracking-widest leading-tight">Enterprise</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">

        <!-- OPERATIONS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest text-slate-400 font-inter">Operations</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>index.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('index.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">dashboard</span>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/gate_simulator.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('gate_simulator.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">door_sensor</span>
                        Smart Gate
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/active_vehicles.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('active_vehicles.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">directions_car</span>
                        Active Vehicles
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/reservation.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('reservation.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">event_available</span>
                        Reservations
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/scan_log.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('scan_log.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">receipt_long</span>
                        Scan Log
                    </a>
                </li>
            </ul>
        </div>

        <!-- REPORTS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest text-slate-400 font-inter">Reports</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/revenue.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('revenue.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">bar_chart_4_bars</span>
                        Revenue
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/slot_map.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('slot_map.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">grid_view</span>
                        Slot Map
                    </a>
                </li>
            </ul>
        </div>

        <!-- ADMINISTRATION -->
        <?php if (in_array($role, ['superadmin', 'admin'])): ?>
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest text-slate-400 font-inter">Administration</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/slots.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('slots.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">table_rows</span>
                        Manage Slots
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/rates.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('rates.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">payments</span>
                        Manage Rates
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/operators.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('operators.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">badge</span>
                        Operators
                    </a>
                </li>
                <?php if ($role === 'superadmin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/users.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all <?= is_active('users.php') ?>">
                        <span class="material-symbols-outlined text-slate-400 text-xl">manage_accounts</span>
                        Users
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Bottom: user info + logout -->
    <div class="px-4 py-4 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-8 h-8 rounded-full bg-slate-900 flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xs font-manrope font-bold"><?= strtoupper(substr($username, 0, 1)) ?></span>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-inter font-semibold text-slate-800 truncate"><?= $username ?></div>
                <div class="text-[10px] text-slate-400 uppercase tracking-wider font-inter"><?= $role ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php"
           class="flex items-center justify-center gap-2 w-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold font-inter uppercase tracking-widest rounded-lg py-2 transition-all">
            <span class="material-symbols-outlined text-base">logout</span>
            Logout
        </a>
    </div>
</aside>
