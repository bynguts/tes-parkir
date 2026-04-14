<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'operator';
?>
<div class="sidebar">
    <div class="brand">
        <h5><i class="fas fa-parking text-success"></i> <span>Smart</span>Parking</h5>
        <small>Enterprise Management</small>
    </div>

    <div class="section-label">Operations</div>
    <nav>
        <div class="nav-item">
            <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="gate_simulator.php" class="<?= $current_page == 'gate_simulator.php' ? 'active' : '' ?>">
                <i class="fas fa-door-open"></i> Smart Gate
            </a>
        </div>
        <div class="nav-item">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-car"></i> Active Vehicles
            </a>
        </div>
        <div class="nav-item">
            <a href="reservation.php" class="<?= $current_page == 'reservation.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> Reservations
            </a>
        </div>
        <div class="nav-item">
            <a href="scan_log.php" class="<?= $current_page == 'scan_log.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i> Scan Log
            </a>
        </div>
    </nav>

    <div class="section-label">Reports</div>
    <nav>
        <div class="nav-item">
            <a href="dashboard_revenue.php" class="<?= $current_page == 'dashboard_revenue.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Revenue
            </a>
        </div>
        <div class="nav-item">
            <a href="slot_map.php" class="<?= $current_page == 'slot_map.php' ? 'active' : '' ?>">
                <i class="fas fa-map"></i> Slot Map
            </a>
        </div>
    </nav>

    <?php if (in_array($role, ['superadmin', 'admin'])): ?>
    <div class="section-label">Administration</div>
    <nav>
        <div class="nav-item">
            <a href="admin_slots.php" class="<?= $current_page == 'admin_slots.php' ? 'active' : '' ?>">
                <i class="fas fa-parking"></i> Manage Slots
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_rates.php" class="<?= $current_page == 'admin_rates.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> Manage Rates
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_operators.php" class="<?= $current_page == 'admin_operators.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i> Operators
            </a>
        </div>
        <?php if ($role === 'superadmin'): ?>
        <div class="nav-item">
            <a href="admin_users.php" class="<?= $current_page == 'admin_users.php' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i> Users
            </a>
        </div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <div style="margin-top: auto; padding: 24px;">
        <a href="logout.php" class="btn btn-outline-danger w-100" style="border-radius: 8px;">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>
