<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        if (!$name || !in_array($shift, ['morning','afternoon','night'])) {
            $error = 'Nama dan shift wajib diisi.';
        } else {
            $pdo->prepare("INSERT INTO operator (full_name, shift, phone) VALUES (?,?,?)")
                ->execute([$name, $shift, $phone ?: null]);
            $msg = "Operator {$name} berhasil ditambahkan.";
        }
    }
    if ($action === 'edit') {
        $id    = (int)$_POST['operator_id'];
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $pdo->prepare("UPDATE operator SET full_name=?, shift=?, phone=? WHERE operator_id=?")
            ->execute([$name, $shift, $phone ?: null, $id]);
        $msg = 'Operator diperbarui.';
    }
    if ($action === 'delete') {
        $id = (int)$_POST['operator_id'];
        // Check if in use
        $used = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE operator_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $error = 'Operator sedang memiliki transaksi, tidak bisa dihapus.';
        } else {
            $pdo->prepare("DELETE FROM operator WHERE operator_id=?")->execute([$id]);
            $msg = 'Operator dihapus.';
        }
    }
}

$operators = $pdo->query("SELECT o.*, COUNT(t.transaction_id) AS total_trx
    FROM operator o
    LEFT JOIN `transaction` t ON t.operator_id = o.operator_id
    GROUP BY o.operator_id ORDER BY o.full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Operator — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#f0f2f5;padding-top:70px}.card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06)}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">👥 Kelola Operator</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>
<div class="container mt-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Operator</div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3"><label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Shift</label>
                            <select name="shift" class="form-select" required>
                                <option value="morning">Pagi (Morning)</option>
                                <option value="afternoon">Siang (Afternoon)</option>
                                <option value="night">Malam (Night)</option>
                            </select></div>
                        <div class="mb-3"><label class="form-label fw-semibold">No. Telepon</label>
                            <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxxxx"></div>
                        <button type="submit" class="btn btn-primary w-100">Tambah Operator</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-users me-2"></i>Daftar Operator (<?= count($operators) ?>)</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Nama</th><th>Shift</th><th>Telepon</th><th>Trx</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($operators as $op):
                            $shift_badge = ['morning'=>'bg-info text-dark','afternoon'=>'bg-warning text-dark','night'=>'bg-dark'][$op['shift']];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($op['full_name']) ?></td>
                            <td><span class="badge <?= $shift_badge ?>"><?= ucfirst($op['shift']) ?></span></td>
                            <td><?= htmlspecialchars($op['phone'] ?? '-') ?></td>
                            <td><?= $op['total_trx'] ?></td>
                            <td>
                                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus operator ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Edit Operator</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="operator_id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="full_name" id="edit_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Shift</label>
                        <select name="shift" id="edit_shift" class="form-select">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Telepon</label><input type="tel" name="phone" id="edit_phone" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fillEdit(id, name, shift, phone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_shift').value = shift;
    document.getElementById('edit_phone').value = phone;
}
</script>
</body>
</html>
