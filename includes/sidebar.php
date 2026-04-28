<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'operator';

function is_active(string $target): string {
    global $current_page;
    return $current_page === basename($target) ? 'nav-active' : '';
}
?>
<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen flex flex-col z-40 border-r border-color sidebar-main">

    <!-- Brand -->
    <div class="px-6 h-20 flex items-center justify-between gap-3 border-b border-color sidebar-brand-box">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center sidebar-brand-icon overflow-hidden">
                <img src="<?= BASE_URL ?>assets/img/logo_p.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div class="sidebar-brand-text">
                <span class="font-inter text-xl leading-tight sidebar-brand-text-main font-black">Park</span><span class="font-inter text-xl leading-tight sidebar-brand-text-sub font-black">here</span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav id="sidebar-nav" class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">

        <!-- OPERATIONS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest font-inter sidebar-label-text">Operations</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>index.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('index.php') ?>">
                        <i class="fa-solid fa-house text-sm"></i>
                        <span class="sidebar-label">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/gate_simulator.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('gate_simulator.php') ?>">
                        <i class="fa-solid fa-door-open text-sm"></i>
                        <span class="sidebar-label">Smart Gate</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/active_vehicles.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('active_vehicles.php') ?>">
                        <i class="fa-solid fa-car text-sm"></i>
                        <span class="sidebar-label">Active Vehicles</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/reservation.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('reservation.php') ?>">
                        <i class="fa-solid fa-calendar-check text-sm"></i>
                        <span class="sidebar-label">Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/operations/scan_log.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('scan_log.php') ?>">
                        <i class="fa-solid fa-file-invoice-dollar text-sm"></i>
                        <span class="sidebar-label">Scan Log</span>
                    </a>
                </li>

            </ul>
        </div>

        <!-- REPORTS -->
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest font-inter sidebar-label-text">Reports</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/revenue.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('revenue.php') ?>">
                        <i class="fa-solid fa-chart-column text-sm"></i>
                        <span class="sidebar-label">Revenue</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/parking_slots.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('parking_slots.php') ?>">
                        <i class="fa-solid fa-table-cells text-sm"></i>
                        <span class="sidebar-label">Parking Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/analytics.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('analytics.php') ?>">
                        <i class="fa-solid fa-chart-pie text-sm"></i>
                        <span class="sidebar-label">Analytics</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ADMINISTRATION -->
        <?php if (in_array($role, ['superadmin', 'admin'])): ?>
        <div>
            <div class="px-3 mb-2 text-[9px] font-bold uppercase tracking-widest font-inter sidebar-label-text">Administration</div>
            <ul class="space-y-0.5">
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/rates.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('rates.php') ?>">
                        <i class="fa-solid fa-money-check-dollar text-sm"></i>
                        <span class="sidebar-label">Manage Rates</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/operators.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('operators.php') ?>">
                        <i class="fa-solid fa-headset text-sm"></i>
                        <span class="sidebar-label">Operators</span>
                    </a>
                </li>
                <?php if ($role === 'superadmin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/admin/users.php"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-inter font-bold sidebar-link <?= is_active('users.php') ?>">
                        <i class="fa-solid fa-users text-sm"></i>
                        <span class="sidebar-label">Users Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    </nav>
</aside>

<script>
(function() {
    const nav = document.getElementById('sidebar-nav');
    if (!nav) return;

    // 1. Restore scroll position immediately
    const savedScroll = sessionStorage.getItem('sidebar-scroll');
    if (savedScroll) {
        nav.scrollTop = savedScroll;
    }

    // 2. Save scroll position on scroll
    nav.addEventListener('scroll', () => {
        sessionStorage.setItem('sidebar-scroll', nav.scrollTop);
    }, { passive: true });

    // 3. Ensure active link is visible (especially for items at the bottom)
    const activeLink = nav.querySelector('.nav-active');
    if (activeLink) {
        const rect = activeLink.getBoundingClientRect();
        const navRect = nav.getBoundingClientRect();
        // If the active link is outside the visible nav area, scroll it into view
        if (rect.top < navRect.top || rect.bottom > navRect.bottom) {
            activeLink.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    }
})();
</script>
