<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$summary   = get_slot_summary($pdo);
$car_avail = $summary['car']['avail'] ?? 0;
$car_total = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

$car_pct  = $car_total  > 0 ? ($car_avail  / $car_total)  * 100 : 100;
$moto_pct = $moto_total > 0 ? ($moto_avail / $moto_total) * 100 : 100;

$page_title = 'Dashboard';
include 'includes/header.php';

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

<!-- Main -->
<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard Overview</h4>
            <small class="text-muted"><?= date('l, d F Y H:i') ?></small>
        </div>
        <div class="d-flex align-items-center gap-3 glass-card px-4 py-2" style="border-radius: 50px;">
            <span class="badge bg-success bg-opacity-25 text-success border border-success"><?= strtoupper($role) ?></span>
            <span class="fw-semibold"><i class="fas fa-user-circle me-2 text-muted"></i><?= $username ?></span>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($car_pct <= 20 && $car_total > 0): ?>
    <div class="alert alert-danger glass-panel mb-4 p-3 d-flex align-items-center" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
        <i class="fas fa-exclamation-triangle fs-4 text-danger me-3"></i>
        <div>
            <strong class="text-danger d-block">Peringatan Kapasitas Mobil!</strong>
            <span class="text-white small">Slot mobil hampir penuh — hanya <?= $car_avail ?> dari <?= $car_total ?> slot tersedia.</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
    <div class="alert alert-warning glass-panel mb-4 p-3 d-flex align-items-center" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3);">
        <i class="fas fa-exclamation-triangle fs-4 text-warning me-3"></i>
        <div>
            <strong class="text-warning d-block">Peringatan Kapasitas Motor!</strong>
            <span class="text-white small">Slot motor hampir penuh — hanya <?= $moto_avail ?> dari <?= $moto_total ?> slot tersedia.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Slot Mobil</div>
                        <div class="fs-2 fw-bold text-primary-glow mb-1">
                            <?= $car_avail ?><span class="fs-5 text-muted fw-normal ms-1">/<?= $car_total ?></span>
                        </div>
                    </div>
                    <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: var(--primary);">
                        <i class="fas fa-car-side"></i>
                    </div>
                </div>
                <div class="slot-bar">
                    <div class="slot-bar-fill bg-primary" style="width:<?= $car_total > 0 ? ($car_avail/$car_total*100) : 0 ?>%; box-shadow: 0 0 10px var(--primary);"></div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Slot Motor</div>
                        <div class="fs-2 fw-bold text-success-glow mb-1">
                            <?= $moto_avail ?><span class="fs-5 text-muted fw-normal ms-1">/<?= $moto_total ?></span>
                        </div>
                    </div>
                    <div class="icon-box" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                </div>
                <div class="slot-bar">
                    <div class="slot-bar-fill bg-success" style="width:<?= $moto_total > 0 ? ($moto_avail/$moto_total*100) : 0 ?>%; box-shadow: 0 0 10px var(--success);"></div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <?php
            $active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
            ?>
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Kendaraan Aktif</div>
                        <div class="fs-2 fw-bold text-warning mb-1" style="text-shadow: 0 0 10px rgba(245, 158, 11, 0.4);"><?= $active ?></div>
                    </div>
                    <div class="icon-box" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="text-muted small mt-3"><i class="fas fa-circle text-warning me-1" style="font-size:8px;"></i>Sedang parkir saat ini</div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <?php
            $today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();
            ?>
            <div class="glass-card stat-card position-relative overflow-hidden">
                <div id="today-revenue-widget-root"
                     class="position-absolute top-0 start-0 w-100 h-100"
                     style="z-index: 0; pointer-events: none;"
                     data-kpi="<?= (int)$today_rev ?>"></div>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Pendapatan Hari Ini</div>
                        <div id="today-revenue-text" class="fs-4 fw-bold text-info mb-1 position-relative" style="z-index: 1; text-shadow: 0 0 10px rgba(6, 182, 212, 0.4);"><?= fmt_idr((float)$today_rev) ?></div>
                    </div>
                    <div class="icon-box position-relative" style="z-index: 1; background: rgba(6, 182, 212, 0.1); color: #06B6D4;">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="text-muted small mt-3"><i class="fas fa-calendar-day me-1"></i><?= date('d M Y') ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Menu -->
    <h6 class="text-muted fw-semibold mb-3 text-uppercase" style="font-size:12px; letter-spacing:.1em;"><i class="fas fa-layer-group me-2"></i>Akses Cepat</h6>
    <div class="menu-grid">
        <a href="gate_simulator.php" class="glass-card menu-card">
            <div class="icon text-primary"><i class="fas fa-door-open"></i></div>
            <h6>Smart Gate</h6>
            <p>Entry & exit simulator dengan engine pembaca barcodde & QR scan</p>
        </a>
        <a href="reservation.php" class="glass-card menu-card">
            <div class="icon text-success"><i class="fas fa-calendar-check"></i></div>
            <h6>Reservasi</h6>
            <p>Kelola pre-booking dan alokasi slot parkir premium di muka.</p>
        </a>
        <a href="slot_map.php" class="glass-card menu-card">
            <div class="icon text-warning"><i class="fas fa-map-marked-alt"></i></div>
            <h6>Peta Slot</h6>
            <p>Visualisasi pemetaan slot kendaraan per lantai secara real-time.</p>
        </a>
        <a href="dashboard.php" class="glass-card menu-card">
            <div class="icon text-info"><i class="fas fa-car"></i></div>
            <h6>Kendaraan Aktif</h6>
            <p>Monitor seluruh kendaraan yang terparkir dan durasi kunjungannya.</p>
        </a>
        <a href="dashboard_revenue.php" class="glass-card menu-card">
            <div class="icon text-danger"><i class="fas fa-chart-line"></i></div>
            <h6>Laporan Revenue</h6>
            <p>Analisis tren pendapatan harian dan agregat finansial.</p>
        </a>
        <a href="scan_log.php" class="glass-card menu-card">
            <div class="icon" style="color:#A855F7;"><i class="fas fa-history"></i></div>
            <h6>Scan Log</h6>
            <p>Log forensik aktivitas sensor gate masuk dan keluar secara mendetail.</p>
        </a>
    </div>
</div>

<?= vite_widget_tags('src/today-revenue-widget.tsx') ?>

<?php include 'includes/footer.php'; ?>

