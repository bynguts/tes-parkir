<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $rate_id    = (int)$_POST['rate_id'];
        $first_rate = (float)$_POST['first_hour_rate'];
        $next_rate  = (float)$_POST['next_hour_rate'];
        $max_rate   = (float)$_POST['daily_max_rate'];

        if ($first_rate <= 0 || $next_rate <= 0 || $max_rate <= 0) {
            $error = 'Nilai penyesuaian tarif harus di atas Rp 0.';
        } else {
            $pdo->prepare("UPDATE parking_rate SET first_hour_rate=?, next_hour_rate=?, daily_max_rate=? WHERE rate_id=?")
                ->execute([$first_rate, $next_rate, $max_rate, $rate_id]);
            $msg = 'Konfigurasi tarif parkir berhasil diperbarui ke database.';
        }
    }
}

$rates = $pdo->query("SELECT * FROM parking_rate ORDER BY vehicle_type")->fetchAll();

$page_title = 'Kelola Tarif Parkir';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Konfigurasi Tarif</h4>
            <small class="text-muted">Akses pengaturan parameter finansial untuk sistem auto-billing parkir.</small>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success glass-panel mb-4 p-3 border border-success border-opacity-50 d-flex align-items-center">
        <i class="fas fa-check-circle fs-4 text-success me-3"></i>
        <div class="text-white"><?= $msg ?></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger glass-panel mb-4 p-3 border border-danger border-opacity-50 d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fs-4 text-danger me-3"></i>
        <div class="text-white"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($rates as $r): ?>
        <div class="col-md-6">
            <div class="glass-panel overflow-hidden" style="height: 100%;">
                <div class="p-4 border-bottom d-flex align-items-center" style="border-color: var(--border-glass) !important; background: rgba(0,0,0,0.2);">
                    <div class="icon-circle bg-<?= $r['vehicle_type'] === 'car' ? 'info' : 'success' ?> bg-opacity-10 text-<?= $r['vehicle_type'] === 'car' ? 'info' : 'success' ?> me-3 border border-<?= $r['vehicle_type'] === 'car' ? 'info' : 'success' ?> border-opacity-25" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 24px;">
                        <i class="fas fa-<?= $r['vehicle_type'] === 'car' ? 'car-side' : 'motorcycle' ?>"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><?= $r['vehicle_type'] === 'car' ? 'Kelas Mobil (Tipe 1)' : 'Kelas Motor (Tipe 2)' ?></h5>
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px;">Rate Configuration</small>
                    </div>
                </div>
                
                <div class="p-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rate_id" value="<?= $r['rate_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Tarif Jam Masuk (1 Jam Pertama)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted fw-bold">Rp</span>
                                <input type="number" name="first_hour_rate" class="form-control bg-dark text-white border-secondary fw-bold fs-5"
                                       value="<?= (int)$r['first_hour_rate'] ?>" min="0" step="500" required
                                       id="first_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Interval Tarif (Per Jam Berikutnya)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted fw-bold">Rp</span>
                                <input type="number" name="next_hour_rate" class="form-control bg-dark text-white border-secondary fw-bold fs-5"
                                       value="<?= (int)$r['next_hour_rate'] ?>" min="0" step="500" required
                                       id="next_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Batas Maksimum Harian (24 Jam)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted fw-bold">Rp</span>
                                <input type="number" name="daily_max_rate" class="form-control bg-dark text-white border-secondary fw-bold fs-5"
                                       value="<?= (int)$r['daily_max_rate'] ?>" min="0" step="1000" required
                                       id="max_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                            </div>
                        </div>

                        <!-- Preview Box -->
                        <div class="p-3 mb-4 rounded-3 border border-secondary" style="background: rgba(0,0,0,0.3);">
                            <div class="text-info fw-bold mb-2 small text-uppercase" style="letter-spacing: 1px;"><i class="fas fa-calculator me-2"></i>Prediksi Sistem Billing</div>
                            <div id="preview_<?= $r['rate_id'] ?>" class="text-muted small font-monospace" style="line-height: 1.6;"></div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold mt-auto" style="border-radius: 12px; letter-spacing: 1px;">
                            <i class="fas fa-save me-2"></i> UPDATE PARAMETER TARIF
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function updatePreview(id) {
    const first = parseFloat(document.getElementById('first_'+id).value) || 0;
    const next  = parseFloat(document.getElementById('next_'+id).value) || 0;
    const max   = parseFloat(document.getElementById('max_'+id).value) || 0;
    const fmt   = v => 'Rp ' + v.toLocaleString('id-ID');
    
    let html = '';
    [1,2,3,6,12,24].forEach(h => {
        let fee = h <= 1 ? first : first + (h-1)*next;
        fee = Math.min(fee, max);
        html += `<div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1 mb-1">
                    <span>Durasi ${h} jam</span> 
                    <strong class="text-white">${fmt(fee)}</strong>
                 </div>`;
    });
    document.getElementById('preview_'+id).innerHTML = html;
}
<?php foreach ($rates as $r): ?>
updatePreview(<?= $r['rate_id'] ?>);
<?php endforeach; ?>
</script>

<?php include 'includes/footer.php'; ?>
