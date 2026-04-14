<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$msg   = '';
$error = '';

// Auto-expire reservations that are past reserved_until
$pdo->exec("UPDATE reservation SET status='expired' WHERE status IN ('pending','confirmed') AND reserved_until < NOW()");

// ── Handle form submissions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $plate       = strtoupper(trim($_POST['plate_number'] ?? ''));
        $vtype       = $_POST['vehicle_type'] ?? '';
        $owner       = trim($_POST['owner_name'] ?? 'Guest');
        $phone       = trim($_POST['owner_phone'] ?? '');
        $date_from   = $_POST['reserved_from'] ?? '';
        $date_until  = $_POST['reserved_until'] ?? '';

        if (!$plate || !in_array($vtype, ['car', 'motorcycle']) || !$date_from || !$date_until) {
            $error = 'Semua field wajib diisi.';
        } elseif (strtotime($date_until) <= strtotime($date_from)) {
            $error = 'Waktu selesai harus setelah waktu mulai.';
        } elseif (strtotime($date_from) < time() - 300) {
            $error = 'Waktu mulai tidak boleh di masa lalu.';
        } else {
            // Find available slot for this type
            // Slot available = not occupied AND not reserved during overlapping period
            $stmt = $pdo->prepare("
                SELECT ps.slot_id FROM parking_slot ps
                WHERE ps.slot_type = ?
                  AND ps.status = 'available'
                  AND ps.slot_id NOT IN (
                    SELECT slot_id FROM reservation
                    WHERE status IN ('pending','confirmed')
                      AND NOT (reserved_until <= ? OR reserved_from >= ?)
                  )
                ORDER BY ps.floor, ps.slot_number LIMIT 1
            ");
            $stmt->execute([$vtype, $date_from, $date_until]);
            $slot = $stmt->fetch();

            if (!$slot) {
                $error = 'Tidak ada slot tersedia untuk periode tersebut.';
            } else {
                // Upsert vehicle
                $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name, owner_phone)
                                VALUES (?,?,?,?)
                                ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), owner_name=VALUES(owner_name), owner_phone=VALUES(owner_phone)")
                    ->execute([$plate, $vtype, $owner ?: 'Guest', $phone ?: null]);

                $vid  = $pdo->query("SELECT vehicle_id FROM vehicle WHERE plate_number='".addslashes($plate)."'")->fetchColumn();
                $code = generate_reservation_code($pdo);

                $pdo->prepare("INSERT INTO reservation (vehicle_id, slot_id, reservation_code, reserved_from, reserved_until, status)
                                VALUES (?,?,?,?,?,'confirmed')")
                    ->execute([$vid, $slot['slot_id'], $code, $date_from, $date_until]);

                $msg = "Reservasi berhasil! Kode: <strong>{$code}</strong>";
            }
        }
    }

    if ($action === 'cancel') {
        $res_id = (int)($_POST['reservation_id'] ?? 0);
        $pdo->prepare("UPDATE reservation SET status='cancelled' WHERE reservation_id=? AND status IN ('pending','confirmed')")
            ->execute([$res_id]);
        $msg = 'Reservasi dibatalkan.';
    }
}

// Fetch active reservations
$reservations = $pdo->query("
    SELECT r.reservation_id, r.reservation_code, r.reserved_from, r.reserved_until, r.status,
           v.plate_number, v.vehicle_type, v.owner_name,
           ps.slot_number, ps.floor
    FROM reservation r
    JOIN vehicle v      ON r.vehicle_id = v.vehicle_id
    JOIN parking_slot ps ON r.slot_id   = ps.slot_id
    WHERE r.status IN ('pending','confirmed')
    ORDER BY r.reserved_from
")->fetchAll();

$min_datetime = date('Y-m-d\TH:i', strtotime('+5 minutes'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservasi — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; padding-top: 70px; }
        .card { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .type-btn { border: 2px solid #dee2e6; border-radius: 8px; padding: 10px 16px; cursor: pointer; background: #fff; width: 100%; font-weight: 600; transition: all .15s; }
        .type-btn.active { border-color: #0d6efd; background: #e8f0fe; color: #0d6efd; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">📅 Reservasi Slot</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Form -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fas fa-plus me-2"></i>Buat Reservasi Baru
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="vehicle_type" id="vtype_hidden" value="car">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jenis Kendaraan</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" class="type-btn active" id="btnCar" onclick="setType('car')">🚗 Mobil</button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="type-btn" id="btnMoto" onclick="setType('motorcycle')">🏍️ Motor</button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Plat Nomor <span class="text-danger">*</span></label>
                            <input type="text" name="plate_number" class="form-control text-uppercase fw-bold"
                                   placeholder="B 1234 AB" required oninput="this.value=this.value.toUpperCase()">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Pemilik</label>
                            <input type="text" name="owner_name" class="form-control" placeholder="Nama pemilik kendaraan">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">No. Telepon</label>
                            <input type="tel" name="owner_phone" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="reserved_from" class="form-control"
                                   min="<?= $min_datetime ?>" id="from_dt" required
                                   onchange="setMinUntil(this.value)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="reserved_until" class="form-control"
                                   min="<?= $min_datetime ?>" id="until_dt" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-calendar-plus me-2"></i>Buat Reservasi
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Active reservations -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fas fa-list me-2"></i>Reservasi Aktif (<?= count($reservations) ?>)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reservations)): ?>
                    <div class="text-center text-muted py-5">Tidak ada reservasi aktif.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Kendaraan</th>
                                <th>Slot</th>
                                <th>Waktu</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($r['reservation_code']) ?></code></td>
                            <td>
                                <?= $r['vehicle_type'] === 'car' ? '🚗' : '🏍️' ?>
                                <strong><?= htmlspecialchars($r['plate_number']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($r['owner_name']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($r['slot_number']) ?> / <?= $r['floor'] ?></td>
                            <td>
                                <small><?= date('d M H:i', strtotime($r['reserved_from'])) ?></small><br>
                                <small class="text-muted">s/d <?= date('d M H:i', strtotime($r['reserved_until'])) ?></small>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Batalkan reservasi ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm">Batal</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setType(t) {
    document.getElementById('vtype_hidden').value = t;
    document.getElementById('btnCar').classList.toggle('active', t === 'car');
    document.getElementById('btnMoto').classList.toggle('active', t === 'motorcycle');
}
function setMinUntil(val) {
    if (!val) return;
    const d = new Date(val);
    d.setHours(d.getHours() + 1);
    document.getElementById('until_dt').min = d.toISOString().slice(0,16);
    if (!document.getElementById('until_dt').value) {
        document.getElementById('until_dt').value = d.toISOString().slice(0,16);
    }
}
</script>
</body>
</html>
