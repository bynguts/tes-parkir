<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$username  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']);
$role      = $_SESSION['role'] ?? 'operator';
$summary   = get_slot_summary($pdo);
$car_avail = $summary['car']['avail'] ?? 0;
$car_total = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

// Alert: slot below 20%
$car_pct  = $car_total  > 0 ? ($car_avail  / $car_total)  * 100 : 100;
$moto_pct = $moto_total > 0 ? ($moto_avail / $moto_total) * 100 : 100;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 240px; min-height: 100vh; background: #1a1a2e; position: fixed; top: 0; left: 0; z-index: 100; padding-top: 20px; }
        .sidebar .brand { padding: 20px 24px 30px; border-bottom: 1px solid rgba(255,255,255,.08); }
        .sidebar .brand h5 { color: #fff; font-weight: 700; margin: 0; font-size: 18px; }
        .sidebar .brand small { color: #888; font-size: 11px; }
        .sidebar .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: #aaa; text-decoration: none; font-size: 14px; transition: all .15s; }
        .sidebar .nav-item a:hover, .sidebar .nav-item a.active { background: rgba(255,255,255,.06); color: #fff; border-left: 3px solid #f5c518; }
        .sidebar .nav-item a i { width: 18px; text-align: center; }
        .sidebar .section-label { font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: .1em; padding: 20px 24px 6px; }
        .main-content { margin-left: 240px; padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .stat-card .icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .slot-bar { height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; margin-top: 8px; }
        .slot-bar-fill { height: 100%; border-radius: 3px; transition: width .3s; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-top: 8px; }
        .menu-card { background: #fff; border-radius: 12px; padding: 24px 20px; text-decoration: none; color: inherit; transition: transform .15s, box-shadow .15s; display: flex; flex-direction: column; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border: 1px solid #eee; }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); color: inherit; }
        .menu-card .icon { font-size: 28px; }
        .menu-card h6 { font-weight: 700; margin: 0; font-size: 15px; }
        .menu-card p { font-size: 12px; color: #888; margin: 0; }
        .alert-slot { border-radius: 10px; font-size: 13px; }
        .badge-role { font-size: 11px; padding: 4px 10px; border-radius: 20px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h5>🅿 SmartParking</h5>
        <small>Management System v2</small>
    </div>

    <div class="section-label">Operations</div>
    <nav>
        <div class="nav-item"><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="gate_simulator.php"><i class="fas fa-door-open"></i> Smart Gate</a></div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-car"></i> Active Vehicles</a></div>
        <div class="nav-item"><a href="reservation.php"><i class="fas fa-calendar-check"></i> Reservations</a></div>
        <div class="nav-item"><a href="scan_log.php"><i class="fas fa-history"></i> Scan Log</a></div>
    </nav>

    <div class="section-label">Reports</div>
    <nav>
        <div class="nav-item"><a href="dashboard_revenue.php"><i class="fas fa-chart-bar"></i> Revenue</a></div>
        <div class="nav-item"><a href="slot_map.php"><i class="fas fa-map"></i> Slot Map</a></div>
    </nav>

    <?php if (in_array($role, ['superadmin', 'admin'])): ?>
    <div class="section-label">Admin</div>
    <nav>
        <div class="nav-item"><a href="admin_slots.php"><i class="fas fa-parking"></i> Manage Slots</a></div>
        <div class="nav-item"><a href="admin_rates.php"><i class="fas fa-tags"></i> Manage Rates</a></div>
        <div class="nav-item"><a href="admin_operators.php"><i class="fas fa-users"></i> Operators</a></div>
        <?php if ($role === 'superadmin'): ?>
        <div class="nav-item"><a href="admin_users.php"><i class="fas fa-user-shield"></i> Users</a></div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <div style="position:absolute; bottom:20px; left:0; right:0; padding:0 24px;">
        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>

<!-- Main -->
<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard</h4>
            <small class="text-muted"><?= date('l, d F Y H:i') ?></small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge-role bg-warning text-dark badge"><?= strtoupper($role) ?></span>
            <span class="fw-semibold"><?= $username ?></span>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($car_pct <= 20 && $car_total > 0): ?>
    <div class="alert alert-danger alert-slot mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Peringatan!</strong> Slot mobil hampir penuh — hanya <?= $car_avail ?> dari <?= $car_total ?> slot tersedia.
    </div>
    <?php endif; ?>
    <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
    <div class="alert alert-warning alert-slot mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Peringatan!</strong> Slot motor hampir penuh — hanya <?= $moto_avail ?> dari <?= $moto_total ?> slot tersedia.
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Slot Mobil</div>
                        <div class="fs-3 fw-bold text-primary"><?= $car_avail ?><span class="fs-6 text-muted fw-normal">/<?= $car_total ?></span></div>
                    </div>
                    <div class="icon bg-primary bg-opacity-10 text-primary">🚗</div>
                </div>
                <div class="slot-bar"><div class="slot-bar-fill bg-primary" style="width:<?= $car_total > 0 ? ($car_avail/$car_total*100) : 0 ?>%"></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Slot Motor</div>
                        <div class="fs-3 fw-bold text-success"><?= $moto_avail ?><span class="fs-6 text-muted fw-normal">/<?= $moto_total ?></span></div>
                    </div>
                    <div class="icon bg-success bg-opacity-10 text-success">🏍️</div>
                </div>
                <div class="slot-bar"><div class="slot-bar-fill bg-success" style="width:<?= $moto_total > 0 ? ($moto_avail/$moto_total*100) : 0 ?>%"></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <?php
            $active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Kendaraan Parkir</div>
                        <div class="fs-3 fw-bold text-warning"><?= $active ?></div>
                    </div>
                    <div class="icon bg-warning bg-opacity-10 text-warning">⏱️</div>
                </div>
                <div class="text-muted small mt-1">sedang parkir aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <?php
            $today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Pendapatan Hari Ini</div>
                        <div class="fs-5 fw-bold text-info"><?= fmt_idr((float)$today_rev) ?></div>
                    </div>
                    <div class="icon bg-info bg-opacity-10 text-info">💰</div>
                </div>
                <div class="text-muted small mt-1"><?= date('d M Y') ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Menu -->
    <h6 class="text-muted fw-semibold mb-3 text-uppercase" style="font-size:11px; letter-spacing:.08em;">Menu Utama</h6>
    <div class="menu-grid">
        <a href="gate_simulator.php" class="menu-card">
            <div class="icon">🚦</div>
            <h6>Smart Gate</h6>
            <p>Entry & exit simulator dengan QR scan</p>
        </a>
        <a href="reservation.php" class="menu-card">
            <div class="icon">📅</div>
            <h6>Reservasi</h6>
            <p>Booking slot parkir di muka</p>
        </a>
        <a href="slot_map.php" class="menu-card">
            <div class="icon">🗺️</div>
            <h6>Peta Slot</h6>
            <p>Visualisasi slot per lantai real-time</p>
        </a>
        <a href="dashboard.php" class="menu-card">
            <div class="icon">🚗</div>
            <h6>Kendaraan Aktif</h6>
            <p>Semua kendaraan yang belum checkout</p>
        </a>
        <a href="dashboard_revenue.php" class="menu-card">
            <div class="icon">📊</div>
            <h6>Revenue</h6>
            <p>Laporan pendapatan harian & total</p>
        </a>
        <a href="scan_log.php" class="menu-card">
            <div class="icon">📹</div>
            <h6>Scan Log</h6>
            <p>Riwayat aktivitas gate masuk & keluar</p>
        </a>
        <?php if (in_array($role, ['superadmin', 'admin'])): ?>
        <a href="admin_slots.php" class="menu-card">
            <div class="icon">🅿️</div>
            <h6>Kelola Slot</h6>
            <p>Tambah, edit status, dan kelola lantai</p>
        </a>
        <a href="admin_rates.php" class="menu-card">
            <div class="icon">💳</div>
            <h6>Tarif Parkir</h6>
            <p>Atur tarif per jam & maksimal harian</p>
        </a>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
