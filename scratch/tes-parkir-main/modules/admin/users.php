<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $uname    = trim($_POST['username'] ?? '');
        $pass     = $_POST['password'] ?? '';
        $urole    = $_POST['role'] ?? '';
        $fullname = trim($_POST['full_name'] ?? '');

        if (!$uname || !$pass || !in_array($urole, ['superadmin','admin','operator'])) {
            $error = 'Entity Integrity Failed: All essential fields are required.';
        } elseif (strlen($pass) < 8) {
            $error = 'Security Standard Failed: Password is too short (min 8 characters).';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name) VALUES (?,?,?,?)")
                    ->execute([$uname, $hash, $urole, $fullname ?: $uname]);
                $msg = "Identity '{$uname}' successfully provisioned with role authorization: {$urole}.";
            } catch (PDOException $e) {
                $error = 'Username is already reserved by the system (Duplicate Key).';
            }
        }
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $currId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($uid === $currId) {
            $error = 'Security Violation: Manual revocation of your own authority is prohibited.';
        } else {
            $pdo->prepare("UPDATE admin_users SET is_active = IF(is_active=1, 0, 1) WHERE user_id=?")->execute([$uid]);
            header("Location: users.php?msg=" . urlencode("Security protocol updated: Access level modified for entity ID #$uid."));
            exit;
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 8) {
            $error = 'New password is too short (min 8 characters).';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE user_id=?")->execute([$hash, $uid]);
            $msg = 'Cryptographic password hash successfully updated.';
        }
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $currId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($uid === $currId) {
            $error = 'Security Violation: Self-termination of the active administrative session is prohibited.';
        } else {
            $pdo->prepare("DELETE FROM admin_users WHERE user_id=?")->execute([$uid]);
            header("Location: users.php?msg=" . urlencode("Entity purged: Administrative record for ID #$uid has been permanently removed."));
            exit;
        }
    }
}

$users = $pdo->query("SELECT user_id, username, role, full_name, last_login, is_active, created_at FROM admin_users ORDER BY role, username")->fetchAll();

$page_title = 'System Identity Control';
$page_subtitle = 'Manage administrative identities and operational access levels.';

include '../../includes/header.php';

// Get messages from session OR URL
$displayMsg = $msg ?: ($_GET['msg'] ?? '');
$displayError = $error ?: ($_GET['error'] ?? '');
?>

<link rel="stylesheet" href="../../assets/css/theme.css">

<div class="px-10 py-10">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($displayMsg): ?>
    <div class="flex items-center gap-4 bg-emerald-50 border border-emerald-100 rounded-2xl px-6 py-4 animate-in fade-in slide-in-from-top-2 duration-500">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <p class="text-emerald-700 text-sm font-bold font-inter"><?= htmlspecialchars($displayMsg) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($displayError): ?>
    <div class="flex items-center gap-4 bg-rose-50 border border-rose-100 rounded-2xl px-6 py-4 animate-in fade-in slide-in-from-top-2 duration-500">
        <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <p class="text-rose-700 text-sm font-bold font-inter"><?= htmlspecialchars($displayError) ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-[400px_1fr] gap-8">

        <!-- Provisioning Panel -->
        <div class="bento-card p-8 self-start sticky top-32">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-user-plus text-sm"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-bold text-primary text-base">Provision Identity</h2>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Operational access control</p>
                </div>
            </div>

            <form method="POST" class="space-y-6" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">

                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Username</label>
                    <input type="text" name="username" required autocomplete="off" placeholder="e.g. system_admin"
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all placeholder:text-slate-300">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                    <input type="text" name="full_name" placeholder="Optional display name"
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all placeholder:text-slate-300">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Access Role</label>
                    <div class="relative">
                        <select name="role" class="w-full appearance-none bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all cursor-pointer">
                            <option value="operator">System Operator</option>
                            <option value="admin">Administrator</option>
                            <option value="superadmin">Super Administrator</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Initial Password</label>
                    <div class="relative group">
                        <input type="password" name="password" id="provision_password" required minlength="8" autocomplete="new-password"
                               class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 pr-12 text-sm font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all">
                        <button type="button" onclick="togglePass('provision_password', this)" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-slate-300 hover:text-slate-500 transition-colors">
                            <i class="fa-solid fa-eye-slash text-xs"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-brand hover:brightness-110 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 shadow-lg shadow-brand/20 transition-all active:scale-[0.98] flex items-center justify-center gap-3 mt-4">
                    <i class="fa-solid fa-shield-check text-sm"></i>
                    Provision Identity
                </button>
            </form>
        </div>

        <!-- Identity List -->
        <div class="bento-card p-4 overflow-hidden">
            <div class="px-8 py-6 border-b border-color flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-surface-alt border border-color flex items-center justify-center text-tertiary">
                        <i class="fa-solid fa-users-viewfinder text-lg"></i>
                    </div>
                    <div>
                        <h2 class="font-manrope font-bold text-primary text-base">Identity Registry</h2>
                        <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Management of operational entities (<?= count($users) ?>)</p>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="premium-thead">
                        <tr>
                            <th>Identity Profile</th>
                            <th class="text-center">Authorization</th>
                            <th class="text-center">Last Session</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Commands</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-color">
                        <?php foreach ($users as $u):
                            $isSelf = $u['user_id'] === (int)$_SESSION['user_id'];
                            $roleKey = strtolower(trim($u['role']));
                            
                            $roleBadge = 'badge-soft-slate';
                            if ($roleKey === 'superadmin') $roleBadge = 'badge-soft-rose';
                            elseif ($roleKey === 'admin') $roleBadge = 'badge-soft-emerald';
                            elseif ($roleKey === 'operator') $roleBadge = 'badge-soft-indigo';
                        ?>
                        <tr class="group hover:bg-surface-alt/40 transition-all duration-300 <?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center font-manrope font-extrabold text-white text-base shadow-sm group-hover:scale-105 transition-transform">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-manrope font-bold text-[14px] text-primary flex items-center gap-2">
                                            <?= htmlspecialchars($u['username']) ?>
                                            <?php if ($isSelf): ?>
                                            <span class="badge-soft badge-soft-indigo !px-1.5 !py-0 !text-[9px]">YOU</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] font-medium text-tertiary leading-none mt-1"><?= htmlspecialchars($u['full_name'] ?: 'External Provisional Entity') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="badge-soft <?= $roleBadge ?>">
                                    <?= strtoupper($roleKey) ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <div class="text-[12px] font-inter font-medium text-secondary">
                                    <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : '—' ?>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php if ($u['is_active']): ?>
                                    <span class="badge-soft badge-soft-indigo">AUTHORIZED</span>
                                <?php else: ?>
                                    <span class="badge-soft badge-soft-slate">REVOKED</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (!$isSelf): ?>
                                    <button type="button" 
                                            onclick="confirmToggle(<?= $u['user_id'] ?>)"
                                            class="btn-ghost !w-9 !h-9 <?= $u['is_active'] ? '!text-amber-500 hover:!bg-amber-50' : '!text-emerald-500 hover:!bg-emerald-50' ?>"
                                            title="<?= $u['is_active'] ? 'Revoke Access' : 'Restore Access' ?>">
                                        <i class="fa-solid <?= $u['is_active'] ? 'fa-shield-halved' : 'fa-shield-check' ?> text-xs"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button type="button" onclick="openReset(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                            class="btn-ghost !w-9 !h-9 !text-slate-400 hover:!text-brand hover:!bg-indigo-50"
                                            title="Security Override">
                                        <i class="fa-solid fa-key text-xs"></i>
                                    </button>

                                    <?php if (!$isSelf): ?>
                                    <button type="button" 
                                            onclick="confirmDelete(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                            class="btn-ghost !w-9 !h-9 !text-rose-400 hover:!text-rose-500 hover:!bg-rose-50"
                                            title="Purge Identity">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Password Reset Modal -->
<div id="modalReset" class="hidden fixed inset-0 z-50 backdrop-blur-sm bg-slate-900/40 flex items-center justify-center p-4">
    <div class="bento-card w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-8 py-5 border-b border-color bg-surface">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-brand/5 border border-brand/10 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-shield-keyhole"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-bold text-primary text-base">Security Override</h2>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Credential rotation protocol</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalReset').classList.add('hidden')" class="btn-ghost">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" class="p-8 space-y-6 bg-surface">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            
            <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Target Identity</div>
                <div class="font-manrope font-extrabold text-lg text-primary" id="resetUsername"></div>
            </div>

            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">New Access Key</label>
                <div class="relative group">
                    <input type="password" name="new_password" id="resetPass" required minlength="8" autocomplete="new-password"
                           class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 pr-12 text-sm font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all">
                    <button type="button" onclick="togglePass('resetPass', this)" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-slate-300 hover:text-slate-500 transition-colors">
                        <i class="fa-solid fa-eye-slash text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-4 pt-2">
                <button type="button" onclick="document.getElementById('modalReset').classList.add('hidden')"
                        class="flex-1 py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-[11px] uppercase tracking-widest rounded-xl transition-all">
                    Dismiss
                </button>
                <button type="submit"
                        class="flex-1 py-4 bg-brand hover:brightness-110 text-white font-bold text-[11px] uppercase tracking-widest rounded-xl shadow-lg shadow-brand/20 transition-all active:scale-[0.98]">
                    Rotate Key
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Universal Confirmation Modal -->
<div id="modalConfirm" class="hidden fixed inset-0 z-[100] backdrop-blur-sm bg-slate-900/60 flex items-center justify-center p-4">
    <div class="bento-card w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-300 bg-surface">
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-500 mx-auto mb-6">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
            </div>
            <h3 class="font-manrope font-extrabold text-xl text-primary mb-2">Security Protocol</h3>
            <p id="confirmMessage" class="text-[13px] text-slate-500 font-inter leading-relaxed mb-10 px-4"></p>
            
            <div class="flex gap-4">
                <button onclick="closeConfirm(false)" class="flex-1 py-4 bg-slate-50 hover:bg-slate-100 text-slate-500 font-bold text-[11px] uppercase tracking-widest rounded-xl transition-all">Cancel</button>
                <button id="confirmBtn" class="flex-1 py-4 bg-brand hover:brightness-110 text-white font-bold text-[11px] uppercase tracking-widest rounded-xl shadow-lg shadow-brand/20 transition-all">Execute</button>
            </div>
        </div>
    </div>
</div>

<script>
let confirmCallback = null;
function showConfirm(msg, callback) {
    document.getElementById('confirmMessage').textContent = msg;
    confirmCallback = callback;
    document.getElementById('modalConfirm').classList.remove('hidden');
}
function closeConfirm(result) {
    document.getElementById('modalConfirm').classList.add('hidden');
    if (result && confirmCallback) confirmCallback();
    confirmCallback = null;
}
document.getElementById('confirmBtn').onclick = () => closeConfirm(true);

function confirmToggle(uid) {
    showConfirm('CRITICAL: Are you sure you want to modify authorization for this entity?', () => {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'users.php';
        f.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="${uid}">
        `;
        document.body.appendChild(f);
        f.submit();
    });
}

function confirmDelete(uid, uname) {
    showConfirm(`DANGER: Permanent removal of entity '${uname}'. This protocol is irreversible. Proceed?`, () => {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'users.php';
        f.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${uid}">
        `;
        document.body.appendChild(f);
        f.submit();
    });
}

function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    }
}

function openReset(id, uname) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').textContent = uname;
    document.getElementById('resetPass').value = '';
    document.getElementById('modalReset').classList.remove('hidden');
}

window.onclick = (e) => {
    if (e.target.id === 'modalReset') document.getElementById('modalReset').classList.add('hidden');
};
</script>

<?php include '../../includes/footer.php'; ?>

<script>
let confirmCallback = null;
function showConfirm(msg, callback) {
    document.getElementById('confirmMessage').textContent = msg;
    confirmCallback = callback;
    document.getElementById('modalConfirm').classList.remove('hidden');
}
function closeConfirm(result) {
    document.getElementById('modalConfirm').classList.add('hidden');
    if (result && confirmCallback) confirmCallback();
    confirmCallback = null;
}
document.getElementById('confirmBtn').onclick = () => closeConfirm(true);

function confirmToggle(uid) {
    showConfirm('DANGER: Modify authorization protocol for this entity?', () => {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'users.php';
        f.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="${uid}">
        `;
        document.body.appendChild(f);
        f.submit();
    });
}
function confirmDelete(uid, uname) {
    showConfirm(`DANGER: Are you sure you want to PERMANENTLY PURGE entity '${uname}'? This action is irreversible.`, () => {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'users.php';
        f.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${uid}">
        `;
        document.body.appendChild(f);
        f.submit();
    });
}
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}
function openReset(id, uname) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').textContent = uname;
    document.getElementById('resetPass').value = '';
    document.getElementById('resetPass').type = 'password';
    const btn = document.querySelector('#modalReset button[onclick*="togglePass"] i');
    if(btn) {
        btn.classList.remove('fa-eye');
        btn.classList.add('fa-eye-slash');
    }
    document.getElementById('modalReset').classList.remove('hidden');
}
document.getElementById('modalReset').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php include '../../includes/footer.php'; ?>
