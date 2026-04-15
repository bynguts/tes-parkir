<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'operator';

// Helper to determine active state correctly for moved pages
function is_active($target) {
    global $current_page;
    return $current_page == basename($target) ? 'active' : '';
}
?>
<div class="sidebar">
    <div class="brand">
        <h5><i class="fas fa-parking text-success"></i> <span>Smart</span>Parking</h5>
        <small>Enterprise Management</small>
    </div>

    <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding-bottom: 20px;" class="sidebar-scrollable-area">

    <div class="section-label">Operations</div>
    <nav>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>index.php" class="<?= is_active('index.php') ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/operations/gate_simulator.php" class="<?= is_active('gate_simulator.php') ?>">
                <i class="fas fa-door-open"></i> Smart Gate
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/operations/active_vehicles.php" class="<?= is_active('active_vehicles.php') ?>">
                <i class="fas fa-car"></i> Active Vehicles
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/operations/reservation.php" class="<?= is_active('reservation.php') ?>">
                <i class="fas fa-calendar-check"></i> Reservations
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/operations/scan_log.php" class="<?= is_active('scan_log.php') ?>">
                <i class="fas fa-history"></i> Scan Log
            </a>
        </div>
    </nav>

    <div class="section-label">Reports</div>
    <nav>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/reports/revenue.php" class="<?= is_active('revenue.php') ?>">
                <i class="fas fa-chart-line"></i> Revenue
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/reports/slot_map.php" class="<?= is_active('slot_map.php') ?>">
                <i class="fas fa-map"></i> Slot Map
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/reports/overview.php" class="<?= is_active('overview.php') ?>">
                <i class="fas fa-chart-bar"></i> Overview
            </a>
        </div>
    </nav>

    <?php if (in_array($role, ['superadmin', 'admin'])): ?>
    <div class="section-label">Administration</div>
    <nav>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/admin/slots.php" class="<?= is_active('slots.php') ?>">
                <i class="fas fa-parking"></i> Manage Slots
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/admin/rates.php" class="<?= is_active('rates.php') ?>">
                <i class="fas fa-tags"></i> Manage Rates
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/admin/operators.php" class="<?= is_active('operators.php') ?>">
                <i class="fas fa-users-cog"></i> Operators
            </a>
        </div>
        <?php if ($role === 'superadmin'): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>modules/admin/users.php" class="<?= is_active('users.php') ?>">
                <i class="fas fa-user-shield"></i> Users
            </a>
        </div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    </div>

    <div style="margin-top: auto; padding: 24px; border-top: 1px solid var(--border-glass);">
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger w-100" style="border-radius: 8px;">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>
