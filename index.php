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

// Attendance logic
$on_duty = is_on_duty();
$staff_list = [];
if (!$on_duty) {
    $search_type = ($_SESSION['role'] === 'admin') ? 'admin' : 'operator';
    $stmt = $pdo->prepare("SELECT operator_id, full_name, shift FROM operator WHERE staff_type = ? ORDER BY full_name");
    $stmt->execute([$search_type]);
    $staff_list = $stmt->fetchAll();
}

include 'includes/header.php';
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
            <span class="fw-semibold">
                <i class="fas fa-user-circle me-2 text-muted"></i>
                <?= $username ?>
                <?php if (!empty($_SESSION['staff_name'])): ?>
                    <span class="text-muted ms-1 small">•</span>
                    <span class="text-primary ms-1 small fw-bold"><?= htmlspecialchars($_SESSION['staff_name']) ?></span>
                <?php endif; ?>
            </span>
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

    <?php if ($role === 'superadmin' || $role === 'admin'): 
        // Admin only sees field staff (operators), Superadmin sees all
        $filter = ($role === 'admin') ? 'operator' : null;
        $active_staff = get_active_attendance($pdo, $filter);
    ?>
    <div class="glass-card mb-5 p-4 border border-info border-opacity-25">
        <h6 class="text-white fw-bold mb-3 d-flex align-items-center">
            <i class="fas fa-users-viewfinder text-info me-2 fs-5"></i>
            Petugas Aktif Saat Ini (Absensi)
        </h6>
        <?php if (empty($active_staff)): ?>
            <div class="text-muted small py-2"><i class="fas fa-info-circle me-1"></i> Belum ada petugas yang melakukan absensi pada sesi ini.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($active_staff as $st): ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="p-3 rounded-4" style="background: rgba(0, 255, 255, 0.05); border: 1px solid rgba(0, 255, 255, 0.1);">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar bg-info bg-opacity-20 text-info rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; border: 1px solid var(--info);">
                                    <?= strtoupper(substr($st['full_name'], 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="text-white fw-bold text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($st['full_name']) ?></div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-<?= $st['staff_type'] === 'admin' ? 'warning' : 'info' ?> bg-opacity-10 text-<?= $st['staff_type'] === 'admin' ? 'warning' : 'info' ?> p-0 small" style="font-size: 0.7rem;">
                                            <?= strtoupper($st['staff_type']) ?>
                                        </span>
                                        <span class="text-muted small" style="font-size: 0.7rem;">Shift: <?= ucfirst($st['shift']) ?></span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.65rem;"><i class="fas fa-clock me-1"></i>In: <?= date('H:i', strtotime($st['check_in_time'])) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Pendapatan Hari Ini</div>
                        <div class="fs-4 fw-bold text-info mb-1" style="text-shadow: 0 0 10px rgba(6, 182, 212, 0.4);"><?= fmt_idr((float)$today_rev) ?></div>
                    </div>
                    <div class="icon-box" style="background: rgba(6, 182, 212, 0.1); color: #06B6D4;">
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

<?php if (!$on_duty): ?>
<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0" style="background: rgba(30, 30, 45, 0.95); backdrop-filter: blur(20px); box-shadow: 0 0 50px rgba(0,0,0,0.8);">
            <div class="modal-body p-5 text-center">
                <div class="mb-4">
                    <div class="icon-circle bg-primary bg-opacity-25 text-primary mx-auto mb-3" style="width: 80px; height: 80px; border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3 class="fw-bold text-white mb-2">Konfirmasi Kehadiran</h3>
                    <p class="text-muted">Halo <span class="text-primary fw-bold"><?= strtoupper($role) ?></span>, harap pilih identitas petugas yang bertanggung jawab pada sesi ini.</p>
                </div>

                <form id="attendanceForm">
                    <?= csrf_field() ?>
                    <div class="mb-4 text-start">
                        <label class="form-label text-muted small fw-bold text-uppercase">Pilih Nama Petugas</label>
                        <select name="staff_id" class="form-select form-control-lg bg-dark text-white border-secondary" required style="height: 60px;">
                            <option value="">-- Pilih Nama Anda --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?= $s['operator_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['shift'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-lg" style="border-radius: 15px; letter-spacing: 1px;">
                        MULAI BERTUGAS <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var myModal = new bootstrap.Modal(document.getElementById('attendanceModal'));
    myModal.show();

    document.getElementById('attendanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const btn = this.querySelector('button');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

        fetch('api/submit_attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Selamat Bertugas!',
                    text: data.message,
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message,
                    background: '#1e1e2d',
                    color: '#fff'
                });
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

