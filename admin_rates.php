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
            $error = 'Tarif harus lebih dari 0.';
        } else {
            $pdo->prepare("UPDATE parking_rate SET first_hour_rate=?, next_hour_rate=?, daily_max_rate=? WHERE rate_id=?")
                ->execute([$first_rate, $next_rate, $max_rate, $rate_id]);
            $msg = 'Tarif berhasil diperbarui.';
        }
    }
}

$rates = $pdo->query("SELECT * FROM parking_rate ORDER BY vehicle_type")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tarif — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; padding-top: 70px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .rate-preview { background: #f8f9fa; border-radius: 8px; padding: 14px; font-size: 13px; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">💳 Kelola Tarif Parkir</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <?php foreach ($rates as $r): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold">
                    <?= $r['vehicle_type'] === 'car' ? '🚗 Tarif Mobil' : '🏍️ Tarif Motor' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rate_id" value="<?= $r['rate_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tarif Jam Pertama (Rp)</label>
                            <input type="number" name="first_hour_rate" class="form-control"
                                   value="<?= (int)$r['first_hour_rate'] ?>" min="0" step="500" required
                                   id="first_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tarif Per Jam Berikutnya (Rp)</label>
                            <input type="number" name="next_hour_rate" class="form-control"
                                   value="<?= (int)$r['next_hour_rate'] ?>" min="0" step="500" required
                                   id="next_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tarif Maksimal Harian (Rp)</label>
                            <input type="number" name="daily_max_rate" class="form-control"
                                   value="<?= (int)$r['daily_max_rate'] ?>" min="0" step="1000" required
                                   id="max_<?= $r['rate_id'] ?>" oninput="updatePreview(<?= $r['rate_id'] ?>)">
                        </div>

                        <div class="rate-preview mb-3" id="preview_<?= $r['rate_id'] ?>">
                            <!-- JS will fill this -->
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Tarif
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updatePreview(id) {
    const first = parseFloat(document.getElementById('first_'+id).value) || 0;
    const next  = parseFloat(document.getElementById('next_'+id).value) || 0;
    const max   = parseFloat(document.getElementById('max_'+id).value) || 0;
    const fmt   = v => 'Rp ' + v.toLocaleString('id-ID');
    let html = '<strong>Simulasi biaya:</strong><br>';
    [1,2,3,6,12,24].forEach(h => {
        let fee = h <= 1 ? first : first + (h-1)*next;
        fee = Math.min(fee, max);
        html += `${h} jam → <strong>${fmt(fee)}</strong><br>`;
    });
    document.getElementById('preview_'+id).innerHTML = html;
}
<?php foreach ($rates as $r): ?>
updatePreview(<?= $r['rate_id'] ?>);
<?php endforeach; ?>
</script>
</body>
</html>
