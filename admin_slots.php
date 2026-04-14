<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $num      = strtoupper(trim($_POST['slot_number'] ?? ''));
        $type     = $_POST['slot_type'] ?? '';
        // [3NF FIX] Terima floor_id (INT FK) bukan string floor
        $floor_id = (int)($_POST['floor_id'] ?? 0);

        if (!$num || !in_array($type, ['car','motorcycle']) || $floor_id <= 0) {
            $error = 'Data tidak lengkap.';
        } else {
            // Validate floor_id exists
            $fcheck = $pdo->prepare("SELECT floor_id FROM floor WHERE floor_id = ?");
            $fcheck->execute([$floor_id]);
            if (!$fcheck->fetch()) {
                $error = 'Lantai tidak valid.';
            } else {
                try {
                    $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id) VALUES (?,?,?)")
                        ->execute([$num, $type, $floor_id]);
                    $msg = "Slot {$num} berhasil ditambahkan.";
                } catch (PDOException $e) {
                    $error = 'Nomor slot sudah ada.';
                }
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved','maintenance'])) {
            $error = 'Status tidak valid.';
        } else {
            $pdo->prepare("UPDATE parking_slot SET status=? WHERE slot_id=?")->execute([$status, $id]);
            $msg = 'Status slot diperbarui.';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['slot_id'];
        $occupied = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE slot_id=? AND payment_status='unpaid'");
        $occupied->execute([$id]);
        if ($occupied->fetchColumn() > 0) {
            $error = 'Slot sedang digunakan, tidak bisa dihapus.';
        } else {
            $pdo->prepare("DELETE FROM parking_slot WHERE slot_id=?")->execute([$id]);
            $msg = 'Slot berhasil dihapus.';
        }
    }
}

// [3NF FIX] JOIN floor untuk tampilkan floor_code dan floor_name
$slots = $pdo->query("
    SELECT ps.*, f.floor_code, f.floor_name
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    ORDER BY f.floor_code, ps.slot_type, ps.slot_number
")->fetchAll();

// [3NF FIX] Daftar lantai dari tabel floor (bukan DISTINCT floor varchar)
$floors_list = $pdo->query("SELECT floor_id, floor_code, floor_name FROM floor ORDER BY floor_code")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Slot — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; padding-top: 70px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .badge-available   { background: #d4edda; color: #155724; }
        .badge-occupied    { background: #f8d7da; color: #721c24; }
        .badge-reserved    { background: #fff3cd; color: #856404; }
        .badge-maintenance { background: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">🅿️ Kelola Slot Parkir</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Add slot form -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-plus me-2"></i>Tambah Slot Baru</div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nomor Slot</label>
                            <input type="text" name="slot_number" class="form-control text-uppercase"
                                   placeholder="Contoh: C-G11" required oninput="this.value=this.value.toUpperCase()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tipe</label>
                            <select name="slot_type" class="form-select" required>
                                <option value="car">🚗 Mobil</option>
                                <option value="motorcycle">🏍️ Motor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <!-- [3NF FIX] Pilih lantai via floor_id (FK), bukan ketik string bebas -->
                            <label class="form-label fw-semibold">Lantai</label>
                            <select name="floor_id" class="form-select" required>
                                <?php foreach ($floors_list as $fl): ?>
                                <option value="<?= $fl['floor_id'] ?>">
                                    <?= htmlspecialchars($fl['floor_code']) ?> — <?= htmlspecialchars($fl['floor_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Tambah Slot</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Slot list -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="fas fa-list me-2"></i>Daftar Slot (<?= count($slots) ?>)</span>
                    <input type="text" id="searchSlot" class="form-control form-control-sm w-auto bg-secondary text-white border-0"
                           placeholder="Cari slot..." oninput="filterSlots(this.value)">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0" id="slotTable">
                        <thead class="table-light">
                            <tr><th>Nomor</th><th>Tipe</th><th>Lantai</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($slots as $s): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($s['slot_number']) ?></td>
                            <td><?= $s['slot_type'] === 'car' ? '🚗 Mobil' : '🏍️ Motor' ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($s['floor_code']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($s['floor_name']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $s['status'] ?> px-2 py-1">
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Change status -->
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto"
                                                onchange="this.form.submit()">
                                            <?php foreach (['available','occupied','reserved','maintenance'] as $st): ?>
                                            <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>>
                                                <?= ucfirst($st) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" onsubmit="return confirm('Hapus slot ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterSlots(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#slotTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>