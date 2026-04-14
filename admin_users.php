<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_role('superadmin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $uname    = trim($_POST['username'] ?? '');
        $pass     = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $fullname = trim($_POST['full_name'] ?? '');

        if (!$uname || !$pass || !in_array($role, ['superadmin','admin','operator'])) {
            $error = 'Semua field wajib diisi.';
        } elseif (strlen($pass) < 8) {
            $error = 'Password minimal 8 karakter.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name) VALUES (?,?,?,?)")
                    ->execute([$uname, $hash, $role, $fullname ?: $uname]);
                $msg = "User '{$uname}' berhasil ditambahkan.";
            } catch (PDOException $e) {
                $error = 'Username sudah digunakan.';
            }
        }
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $error = 'Tidak bisa menonaktifkan akun sendiri.';
        } else {
            $pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE user_id=?")->execute([$uid]);
            $msg = 'Status user diperbarui.';
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 8) {
            $error = 'Password minimal 8 karakter.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE user_id=?")->execute([$hash, $uid]);
            $msg = 'Password berhasil direset.';
        }
    }
}

$users = $pdo->query("SELECT user_id, username, role, full_name, last_login, is_active, created_at FROM admin_users ORDER BY role, username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#f0f2f5;padding-top:70px}.card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06)}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">🛡️ Admin Users</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>
<div class="container mt-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Add user -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah User</div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3"><label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" required autocomplete="off"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimal 8 karakter.</div></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="operator">Operator</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Super Admin</option>
                            </select></div>
                        <button type="submit" class="btn btn-primary w-100">Tambah User</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- User list -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-users me-2"></i>Daftar User (<?= count($users) ?>)</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Username</th><th>Nama</th><th>Role</th><th>Last Login</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u):
                            $role_badge = ['superadmin'=>'bg-danger','admin'=>'bg-warning text-dark','operator'=>'bg-secondary'][$u['role']];
                            $is_self = $u['user_id'] == $_SESSION['user_id'];
                        ?>
                        <tr class="<?= $u['is_active'] ? '' : 'table-secondary text-muted' ?>">
                            <td class="fw-bold"><?= htmlspecialchars($u['username']) ?><?= $is_self ? ' <span class="badge bg-info text-dark">Kamu</span>' : '' ?></td>
                            <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                            <td><span class="badge <?= $role_badge ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><small><?= $u['last_login'] ? date('d M H:i', strtotime($u['last_login'])) : 'Belum pernah' ?></small></td>
                            <td><span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Toggle active -->
                                    <?php if (!$is_self): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <button class="btn btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?> btn-sm" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <!-- Reset password -->
                                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#resetModal"
                                            onclick="setResetId(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_uid">
                <div class="modal-body">
                    <p>Reset password untuk: <strong id="reset_uname"></strong></p>
                    <input type="password" name="new_password" class="form-control" placeholder="Password baru (min 8 karakter)" minlength="8" required autocomplete="new-password">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Reset</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setResetId(id, uname) {
    document.getElementById('reset_uid').value = id;
    document.getElementById('reset_uname').textContent = uname;
}
</script>
</body>
</html>
