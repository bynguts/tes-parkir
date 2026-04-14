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
        $plate      = strtoupper(trim($_POST['plate_number'] ?? ''));
        $vtype      = $_POST['vehicle_type'] ?? '';
        $owner      = trim($_POST['owner_name'] ?? 'Guest');
        $phone      = trim($_POST['owner_phone'] ?? '');
        $date_from  = $_POST['reserved_from'] ?? '';
        $date_until = $_POST['reserved_until'] ?? '';

        if (!$plate || !in_array($vtype, ['car', 'motorcycle']) || !$date_from || !$date_until) {
            $error = 'Semua field wajib diisi.';
        } elseif (strtotime($date_until) <= strtotime($date_from)) {
            $error = 'Waktu selesai harus setelah waktu mulai.';
        } elseif (strtotime($date_from) < time() - 300) {
            $error = 'Waktu mulai tidak boleh di masa lalu.';
        } else {
            $stmt = $pdo->prepare("
                SELECT ps.slot_id FROM parking_slot ps
                JOIN floor f ON ps.floor_id = f.floor_id
                WHERE ps.slot_type = ?
                  AND ps.status = 'available'
                  AND ps.slot_id NOT IN (
                    SELECT slot_id FROM reservation
                    WHERE status IN ('pending','confirmed')
                      AND NOT (reserved_until <= ? OR reserved_from >= ?)
                  )
                ORDER BY f.floor_code, ps.slot_number LIMIT 1
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

$reservations = $pdo->query("
    SELECT r.reservation_id, r.reservation_code, r.reserved_from, r.reserved_until, r.status,
           v.plate_number, v.vehicle_type, v.owner_name,
           ps.slot_number, f.floor_code AS floor
    FROM reservation r
    JOIN vehicle v       ON r.vehicle_id  = v.vehicle_id
    JOIN parking_slot ps ON r.slot_id     = ps.slot_id
    JOIN floor f         ON ps.floor_id   = f.floor_id
    WHERE r.status IN ('pending','confirmed')
    ORDER BY r.reserved_from
")->fetchAll();

$min_datetime = date('Y-m-d\TH:i', strtotime('+5 minutes'));

$page_title = 'Reservasi Slot';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Manajemen Reservasi</h4>
            <small class="text-muted">Kelola pre-booking dan alokasi prioritas.</small>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success glass-panel mb-4 p-3 border border-success d-flex align-items-center" style="background: rgba(34, 197, 94, 0.1);">
        <i class="fas fa-check-circle fs-4 text-success me-3"></i>
        <div class="text-white"><?= $msg ?></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger glass-panel mb-4 p-3 border border-danger d-flex align-items-center" style="background: rgba(239, 68, 68, 0.1);">
        <i class="fas fa-exclamation-circle fs-4 text-danger me-3"></i>
        <div class="text-white"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Form -->
        <div class="col-12 col-xl-5">
            <div class="glass-panel">
                <div class="p-4 border-bottom" style="border-color: var(--border-glass) !important;">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-calendar-plus me-2 text-primary"></i>Buat Reservasi Baru</h5>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="vehicle_type" id="vtype_hidden" value="car">

                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Tipe Kendaraan</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-fill vtype-btn active" id="btnCar" onclick="setType('car')" style="border-color: var(--border-glass); background:rgba(59,130,246,0.1); color: var(--primary);">
                                    <i class="fas fa-car-side fs-4 mb-1 d-block mt-2"></i> Mobil
                                </button>
                                <button type="button" class="btn btn-outline-secondary flex-fill vtype-btn" id="btnMoto" onclick="setType('motorcycle')" style="border-color: var(--border-glass);">
                                    <i class="fas fa-motorcycle fs-4 mb-1 d-block mt-2"></i> Motor
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Plat Nomor <span class="text-danger">*</span></label>
                            <input type="text" name="plate_number" class="form-control form-control-lg text-uppercase fw-bold text-center"
                                   placeholder="B 1234 AB" required oninput="this.value=this.value.toUpperCase()" style="letter-spacing: 2px;">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Nama Pemilik</label>
                                <input type="text" name="owner_name" class="form-control" placeholder="Opsional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">No. Telepon</label>
                                <input type="tel" name="owner_phone" class="form-control" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Waktu Mulai <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="reserved_from" class="form-control"
                                       min="<?= $min_datetime ?>" id="from_dt" required
                                       onchange="setMinUntil(this.value)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Waktu Selesai <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="reserved_until" class="form-control"
                                       min="<?= $min_datetime ?>" id="until_dt" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-6" style="border-radius: 12px;">
                            PROSES RESERVASI <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Active reservations -->
        <div class="col-12 col-xl-7">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-glass) !important;">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-info"></i>Reservasi Aktif</h5>
                    <span class="badge bg-info bg-opacity-25 text-info px-3 py-2 border border-info rounded-pill"><?= count($reservations) ?> Antrean</span>
                </div>
                <div class="p-0">
                    <?php if (empty($reservations)): ?>
                    <div class="text-center text-muted py-5 my-5">
                        <i class="far fa-calendar-times fs-1 mb-3 d-block" style="opacity: 0.2;"></i>
                        <p>Belum ada reservasi aktif terjadwal.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" style="border: none; max-height: 600px;">
                    <table class="table table-glass table-hover mb-0">
                        <thead style="position: sticky; top:0; background: var(--card-bg); z-index: 10;">
                            <tr>
                                <th class="ps-4">Kode Validasi</th>
                                <th>Klien / Kendaraan</th>
                                <th>Alokasi Slot</th>
                                <th>Periode Reservasi</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td class="ps-4">
                                <code class="fs-5 fw-bold text-success-glow" style="letter-spacing: 1px; font-family: 'Courier New', Courier, monospace; background:rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px;"><?= htmlspecialchars($r['reservation_code']) ?></code>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center;">
                                        <?= $r['vehicle_type'] === 'car' ? '<i class="fas fa-car text-primary fs-5"></i>' : '<i class="fas fa-motorcycle text-success fs-5"></i>' ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($r['plate_number']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($r['owner_name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary text-light">Str. <?= htmlspecialchars($r['floor']) ?></span>
                                <span class="ms-1 fw-bold fs-5"><?= htmlspecialchars($r['slot_number']) ?></span>
                            </td>
                            <td>
                                <div class="small text-white"><i class="far fa-clock text-muted me-1"></i><?= date('d M H:i', strtotime($r['reserved_from'])) ?></div>
                                <div class="small text-muted ms-3">s/d <?= date('H:i', strtotime($r['reserved_until'])) ?></div>
                            </td>
                            <td class="pe-4 text-end">
                                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin secara permanen membatalkan reservasi ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
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

<script>
function setType(t) {
    document.getElementById('vtype_hidden').value = t;
    const btnCar = document.getElementById('btnCar');
    const btnMoto = document.getElementById('btnMoto');
    
    if (t === 'car') {
        btnCar.classList.add('active');
        btnCar.style.background = 'rgba(59,130,246,0.1)';
        btnCar.style.color = 'var(--primary)';
        btnMoto.classList.remove('active');
        btnMoto.style.background = 'transparent';
        btnMoto.style.color = 'var(--text-main)';
    } else {
        btnMoto.classList.add('active');
        btnMoto.style.background = 'rgba(34,197,94,0.1)';
        btnMoto.style.color = 'var(--success)';
        btnCar.classList.remove('active');
        btnCar.style.background = 'transparent';
        btnCar.style.color = 'var(--text-main)';
    }
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
<?php include 'includes/footer.php'; ?>