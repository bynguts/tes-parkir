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
            $error = 'Integritas entitas gagal: Semua field esensial wajib diisi.';
        } elseif (strlen($pass) < 8) {
            $error = 'Standar keamanan gagal: Password hash parameter terlalu pendek (min 8 char).';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name) VALUES (?,?,?,?)")
                    ->execute([$uname, $hash, $role, $fullname ?: $uname]);
                $msg = "Identitas '{$uname}' berhasil di-provision dan mendapatkan autorisasi.";
            } catch (PDOException $e) {
                $error = 'Username yang anda coba registrasikan telah direservasi oleh sistem (Duplicate Key).';
            }
        }
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $error = 'Operasi ilegal: Tidak dapat memodifikasi state pada active session ID milik sendiri.';
        } else {
            $pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE user_id=?")->execute([$uid]);
            $msg = 'Access Control List user telah diperbarui dari database.';
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 8) {
            $error = 'Standar keamanan gagal: Password hash parameter terlalu pendek (min 8 char).';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE user_id=?")->execute([$hash, $uid]);
            $msg = 'Pembaruan force cryptographic hash password akun berhasil.';
        }
    }
}

$users = $pdo->query("SELECT user_id, username, role, full_name, last_login, is_active, created_at FROM admin_users ORDER BY role, username")->fetchAll();

$page_title = 'User & Role Administrator';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Management & Auth Access</h4>
            <small class="text-muted">Akses tingkat Root (Superadmin Only) untuk mengontrol akun sistem manajemen.</small>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success glass-panel mb-4 p-3 border border-success border-opacity-50 d-flex align-items-center">
        <i class="fas fa-shield-check fs-4 text-success me-3"></i>
        <div class="text-white"><?= $msg ?></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger glass-panel mb-4 p-3 border border-danger border-opacity-50 d-flex align-items-center">
        <i class="fas fa-sensor-alert fs-4 text-danger me-3"></i>
        <div class="text-white"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add user -->
        <div class="col-xl-4">
            <div class="glass-panel sticky-top" style="top: 100px; border-top: 4px solid var(--danger);">
                <div class="p-4 border-bottom" style="border-color: var(--border-glass) !important;">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-user-shield text-danger me-2"></i>Provision Akun Auth Baru</h5>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Username (Global ID)</label>
                            <input type="text" name="username" class="form-control form-control-lg bg-dark text-white border-secondary font-monospace" required autocomplete="off">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Display Name (Nama Profil)</label>
                            <input type="text" name="full_name" class="form-control form-control-lg bg-dark text-white border-secondary">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Cryptography Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-key"></i></span>
                                <input type="password" name="password" class="form-control form-control-lg bg-dark text-white border-secondary font-monospace" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                            </div>
                            <div class="form-text text-muted mt-1"><i class="fas fa-info-circle me-1"></i>Kekuatan hash standard 12 rounds BCRYPT, min 8 char.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Access Control List (Role)</label>
                            <select name="role" class="form-select form-control-lg bg-dark text-white border-secondary" required>
                                <option value="operator">🔵 Tier 1: Operator (Frontend Only)</option>
                                <option value="admin">🟡 Tier 2: Admin (Data Management)</option>
                                <option value="superadmin" class="text-danger">🔴 Tier 3: Superadmin (Root Access)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-danger w-100 py-3 fw-bold mt-2 shadow" style="border-radius: 12px; letter-spacing: 1px;">
                            EKSEKUSI PROVISIONING AKUN
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- User list -->
        <div class="col-xl-8">
            <div class="glass-panel overflow-hidden">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--border-glass) !important;">
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><i class="fas fa-server text-info me-2"></i>Auth Tables (<?= count($users) ?> nodes)</h5>
                    </div>
                </div>
                
                <div class="table-responsive" style="border: none;">
                    <table class="table table-glass table-hover mb-0">
                        <thead style="background: rgba(0,0,0,0.2);">
                            <tr>
                                <th class="ps-4">Identity / Profil</th>
                                <th>Role Level</th>
                                <th>Last Pulse (Login)</th>
                                <th>Status Node</th>
                                <th class="text-end pe-4">Command</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u):
                            $role_badge = [
                                'superadmin' => ['danger', '🛡️ Root'],
                                'admin' => ['warning', '⚙️ Admin'],
                                'operator' => ['info', '🖥️ Operator']
                            ][$u['role']];
                            $is_self = $u['user_id'] == $_SESSION['user_id'];
                        ?>
                        <tr class="<?= $u['is_active'] ? '' : 'opacity-50' ?>">
                            <td class="ps-4 align-middle">
                                <div class="fw-bold font-monospace text-white fs-6 d-flex align-items-center">
                                    <?= htmlspecialchars($u['username']) ?>
                                    <?php if ($is_self): ?>
                                        <span class="badge bg-primary ms-2 border border-primary px-2" style="font-size: 10px;">THIS SESSION</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($u['full_name'] ?? '-') ?></div>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $role_badge[0] ?> bg-opacity-25 text-<?= $role_badge[0] ?> border border-<?= $role_badge[0] ?> px-3 py-1 rounded">
                                    <?= $role_badge[1] ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <div class="small <?= $u['last_login'] ? 'text-white' : 'text-muted' ?>">
                                    <i class="far fa-clock me-1 text-muted"></i>
                                    <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Unknown Pulse' ?>
                                </div>
                            </td>
                            <td class="align-middle">
                                <?php if ($u['is_active']): ?>
                                    <div class="d-flex align-items-center text-success small fw-bold">
                                        <div class="spinner-grow spinner-grow-sm text-success me-2" style="width: 10px; height: 10px;" role="status"></div> ACTIVE
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center text-secondary small fw-bold">
                                        <i class="fas fa-power-off me-2"></i> DISABLED
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 align-middle">
                                <div class="d-flex gap-2 justify-content-end">
                                    <?php if (!$is_self): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <?php if ($u['is_active']): ?>
                                            <button class="btn btn-outline-warning btn-sm rounded" title="Disable node access">
                                                <i class="fas fa-ban"></i> Block
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-success btn-sm rounded" title="Enable node access">
                                                <i class="fas fa-check"></i> Restore
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-info btn-sm rounded" data-bs-toggle="modal"
                                            data-bs-target="#resetModal" title="Force Hash Reset"
                                            onclick="setResetId(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-key"></i> Key
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
        <div class="modal-content glass-card border-0" style="background: #1e1e2d; box-shadow: 0 20px 50px rgba(0,0,0,0.5); border-top: 4px solid var(--info) !important;">
            <div class="modal-header border-bottom border-secondary border-opacity-50">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-key me-2 text-info"></i> Force Key Reset Protocol</h5>
                <button type="button" class="btn-close btn-close-white opacity-50" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_uid">
                <div class="modal-body p-4">
                    <div class="alert alert-warning bg-warning bg-opacity-10 border border-warning text-warning d-flex align-items-center mb-4 p-3 rounded">
                        <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                        <small>Memaksa penggantian payload cryptographic account. Membutuhkan hak otorisasi superadmin penuh.</small>
                    </div>
                    <div class="mb-2 text-muted fw-bold text-uppercase small">Target Identity ID:</div>
                    <div id="reset_uname" class="font-monospace text-info fs-5 mb-4 p-2 bg-dark rounded border border-info border-opacity-25 text-center"></div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">New Password Hash Signature</label>
                        <input type="password" name="new_password" class="form-control bg-dark border-secondary text-white font-monospace fs-5 text-center" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-50">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">ABORT</button>
                    <button type="submit" class="btn btn-info px-4 fw-bold shadow">EXECUTE OVERRIDE</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setResetId(id, uname) {
    document.getElementById('reset_uid').value = id;
    document.getElementById('reset_uname').textContent = uname;
}
</script>

<?php include 'includes/footer.php'; ?>
