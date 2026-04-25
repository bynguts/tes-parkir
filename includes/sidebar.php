<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'operator';

function is_active(string $target): string {
    global $current_page;
    return $current_page === basename($target) ? 'nav-active' : '';
}
?>
<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen w-64 flex flex-col z-40 sidebar-main">

    <!-- Brand -->
    <div class="px-6 h-20 flex items-center justify-between gap-3 sidebar-brand-box">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-brand-icon">
                <i class="fa-solid fa-square-p text-base"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="font-inter text-base leading-tight sidebar-brand-text-main">Smart</span><span class="font-inter text-base leading-tight sidebar-brand-text-sub">Parking</span>
                <div class="text-[10px] font-inter font-normal uppercase tracking-widest leading-tight sidebar-brand-text-sub">Enterprise</div>
            </div>
        </div>
        <button id="sidebar-toggle" class="w-9 h-9 rounded-lg hover:bg-white/5 flex items-center justify-center transition-all shrink-0" title="Toggle sidebar">
            <i class="fa-solid fa-bars text-lg sidebar-brand-text-sub"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">

        <!-- OPERATIONS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest font-inter sidebar-label-text">Operations</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>index.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('index.php') ?>">
                        <i class="fa-solid fa-house text-sm"></i>
                        <span class="sidebar-label">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/gate_simulator.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('gate_simulator.php') ?>">
                        <i class="fa-solid fa-door-open text-sm"></i>
                        <span class="sidebar-label">Smart Gate</span>
                    </a>
                </li>
                <li>
                    <?php 
                    $badge_active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn(); 
                    ?>
                    <a href="<?= BASE_URL ?>modules/operations/active_vehicles.php"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('active_vehicles.php') ?>">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-car text-sm"></i>
                            <span class="sidebar-label">Active Vehicles</span>
                        </div>
                        <?php if ($badge_active > 0): ?>
                        <span class="sidebar-badge bg-white/10 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-lg border border-white/10"><?= $badge_active ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <?php 
                    $badge_res = $pdo->query("SELECT COUNT(*) FROM reservation WHERE status IN ('pending','confirmed')")->fetchColumn(); 
                    ?>
                    <a href="<?= BASE_URL ?>modules/operations/reservation.php"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('reservation.php') ?>">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-calendar-check text-sm"></i>
                            <span class="sidebar-label">Reservations</span>
                        </div>
                        <?php if ($badge_res > 0): ?>
                        <span class="sidebar-badge bg-white/10 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-lg border border-white/10"><?= $badge_res ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/scan_log.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('scan_log.php') ?>">
                        <i class="fa-solid fa-file-invoice-dollar text-sm"></i>
                        <span class="sidebar-label">Scan Log</span>
                    </a>
                </li>

            </ul>
        </div>

        <!-- REPORTS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest font-inter sidebar-label-text">Reports</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/revenue.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('revenue.php') ?>">
                        <i class="fa-solid fa-chart-column text-sm"></i>
                        <span class="sidebar-label">Revenue</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/parking_slots.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('parking_slots.php') ?>">
                        <i class="fa-solid fa-table-cells text-sm"></i>
                        <span class="sidebar-label">Parking Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/analytics.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('analytics.php') ?>">
                        <i class="fa-solid fa-chart-pie text-sm"></i>
                        <span class="sidebar-label">Analytics</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ADMINISTRATION -->
        <?php if (in_array($role, ['superadmin', 'admin'])): ?>
        <div>
            <div class="px-3 mb-2 text-[9px] font-normal uppercase tracking-widest font-inter sidebar-label-text">Administration</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/rates.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('rates.php') ?>">
                        <i class="fa-solid fa-money-check-dollar text-sm"></i>
                        <span class="sidebar-label">Manage Rates</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/operators.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('operators.php') ?>">
                        <i class="fa-solid fa-headset text-sm"></i>
                        <span class="sidebar-label">Operators</span>
                    </a>
                </li>
                <?php if ($role === 'superadmin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/users.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-normal sidebar-link <?= is_active('users.php') ?>">
                        <i class="fa-solid fa-users text-sm"></i>
                        <span class="sidebar-label">Users</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Bottom: user info + logout -->
    <div class="px-4 py-4 sidebar-footer">
        <div class="flex items-center gap-3 mb-3">
            <div class="relative flex-shrink-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center sidebar-user-avatar">
                    <span class="text-white text-xs font-inter font-normal"><?= strtoupper(substr($username, 0, 1)) ?></span>
                </div>
                <!-- Status Indicator -->
                <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 status-dot-available status-dot-ring rounded-full"></div>
            </div>
            <div class="min-w-0 sidebar-label">
                <div class="text-sm font-inter font-normal truncate sidebar-user-name"><?= $username ?></div>
                <div class="text-[10px] font-normal uppercase tracking-wider font-inter sidebar-user-role"><?= $role ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php"
           class="flex items-center justify-center gap-2 w-full text-xs font-normal font-inter uppercase tracking-widest rounded-lg py-2 transition-all sidebar-logout-btn">
            <i class="fa-solid fa-power-off text-xs"></i>
            <span class="sidebar-label">Logout</span>
        </a>
    </div>
</aside>
