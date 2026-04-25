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

$page_title = 'Admin Users & Roles';
$page_subtitle = 'Identity provisioning and system operational access control.';

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">
<?php
// Get messages from session OR URL
$displayMsg = $msg ?: ($_GET['msg'] ?? '');
$displayError = $error ?: ($_GET['error'] ?? '');
?>

    <div class="p-8">
        <!-- Message Handlers -->
        <?php if ($displayMsg): ?>
        <div class="flex items-center gap-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl px-6 py-5 mb-8 animate-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-500">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <p class="text-emerald-700 dark:text-emerald-400 text-sm font-bold font-inter tracking-tight"><?= htmlspecialchars($displayMsg) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($displayError): ?>
        <div class="flex items-center gap-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl px-6 py-5 mb-8 animate-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-500">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <p class="text-rose-700 dark:text-rose-400 text-sm font-bold font-inter tracking-tight"><?= htmlspecialchars($displayError) ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-[400px_1fr] gap-10">

            <!-- PROVISIONING COMMAND CENTER -->
            <div class="bento-card rounded-[2.5rem] shadow-2xl overflow-hidden border border-color self-start sticky top-32">
                <div class="px-8 py-6 border-b border-color flex items-center gap-4 bg-surface-alt/30">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand border border-brand/20">
                        <i class="fa-solid fa-user-gear text-sm"></i>
                    </div>
                    <div>
                        <h2 class="font-manrope font-black text-lg text-primary tracking-tight">Provision Identity</h2>
                        <p class="text-[10px] font-bold text-tertiary tracking-tight mt-0.5">Define access authority</p>
                    </div>
                </div>
                <div class="p-8">
                    <form method="POST" class="space-y-6" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">

                        <div class="space-y-2.5">
                            <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-tertiary ml-1 opacity-60">Global Username</label>
                            <input type="text" name="username" required autocomplete="off" placeholder="e.g. jsmith_op"
                                   class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-black font-manrope text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all placeholder:text-tertiary/20">
                        </div>

                        <div class="space-y-2.5">
                            <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-tertiary ml-1 opacity-60">Display Name</label>
                            <input type="text" name="full_name" placeholder="Optional"
                                   class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all placeholder:text-tertiary/20">
                        </div>

                        <div class="space-y-2.5">
                            <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-tertiary ml-1 opacity-60">Access Role</label>
                            <div class="relative">
                                <select name="role" class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-black font-manrope text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all appearance-none cursor-pointer">
                                    <option value="operator">Operator Access</option>
                                    <option value="admin">Administrator Access</option>
                                    <option value="superadmin">Superadmin Authority</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-[10px] text-tertiary pointer-events-none opacity-40"></i>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-tertiary ml-1 opacity-60">Access Key</label>
                            <div class="relative group">
                                <input type="password" name="password" id="provision_password" required minlength="8" autocomplete="new-password"
                                       class="w-full modal-input border border-color rounded-2xl px-6 py-4 pr-14 text-sm font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all">
                                <button type="button" onclick="togglePass('provision_password', this)" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-tertiary hover:text-primary transition-colors opacity-40 group-focus-within:opacity-100">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-[0.2em] rounded-2xl py-5 shadow-xl shadow-brand/20 transition-all active:scale-[0.98] flex items-center justify-center gap-3 mt-2">
                            <i class="fa-solid fa-shield-check text-sm"></i>
                            Provision Account
                        </button>
                    </form>
                </div>
            </div>

            <!-- IDENTITY REGISTRY -->
            <div class="bento-card rounded-[2.5rem] shadow-2xl overflow-hidden border border-color">
                <div class="px-10 py-8 border-b border-color flex items-center justify-between bg-surface-alt/30">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-surface-alt flex items-center justify-center text-tertiary border border-color">
                            <i class="fa-solid fa-users-viewfinder text-lg"></i>
                        </div>
                        <div>
                            <h2 class="font-manrope font-black text-xl text-primary tracking-tight">Identity Registry</h2>
                            <p class="text-[10px] font-bold text-tertiary tracking-tight mt-0.5">Management of operational entities (<?= count($users) ?>)</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto no-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-color">
                                <th class="px-10 py-6 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left">Identity Profile</th>
                                <th class="px-6 py-6 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Authorization</th>
                                <th class="px-6 py-6 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Last Session</th>
                                <th class="px-6 py-6 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Status</th>
                                <th class="px-10 py-6 text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right">Commands</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-color">
                            <?php foreach ($users as $u):
                                $isSelf = $u['user_id'] === (int)$_SESSION['user_id'];
                                $roleKey = strtolower(trim($u['role']));
                                $rColors = [
                                    'superadmin' => 'status-badge-reserved',
                                    'admin'      => 'status-badge-paid',
                                    'operator'   => 'status-badge-parked'
                                ];
                                $rClass = $rColors[$roleKey] ?? 'status-badge-maintenance';
                            ?>
                            <tr class="group hover:bg-surface-alt/50 transition-all duration-300 <?= !$u['is_active'] ? 'opacity-40 grayscale' : '' ?> <?= $isSelf ? 'bg-brand/[0.02]' : '' ?>">
                                <td class="px-10 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center font-manrope font-black text-surface text-base shadow-xl shadow-primary/10 group-hover:scale-105 transition-transform">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-manrope font-black text-sm text-primary flex items-center gap-2">
                                                <?= htmlspecialchars($u['username']) ?>
                                                <?php if ($isSelf): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold font-inter text-brand bg-brand/10 border border-brand/20">SELF</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[10px] font-bold text-tertiary tracking-tight mt-0.5 opacity-60"><?= htmlspecialchars($u['full_name'] ? ucwords(strtolower($u['full_name'])) : 'Provisional account') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium font-inter <?= $rClass ?>">
                                        <?= ucfirst($roleKey) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="text-[11px] font-medium text-secondary font-inter">
                                        <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : '—' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium font-inter status-badge-parked">Authorized</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium font-inter status-badge-maintenance">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-10 py-5 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <?php if (!$isSelf): ?>
                                        <?php 
                                            $tColor = $u['is_active'] ? 'text-amber-500 bg-amber-500/5 border-amber-500/10 hover:bg-amber-500/10' : 'text-emerald-500 bg-emerald-500/5 border-emerald-500/10 hover:bg-emerald-500/10';
                                            $tIcon = $u['is_active'] ? 'fa-shield-halved' : 'fa-shield-check';
                                        ?>
                                        <button type="button" 
                                                title="Toggle Authorization Protocol"
                                                onclick="confirmToggle(<?= $u['user_id'] ?>)"
                                                class="w-10 h-10 flex items-center justify-center rounded-2xl transition-all shadow-sm border cursor-pointer <?= $tColor ?>">
                                            <i class="fa-solid <?= $tIcon ?> text-xs"></i>
                                        </button>
                                        <?php endif; ?>

                                        <button type="button" onclick="openReset(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                                class="w-10 h-10 flex items-center justify-center text-tertiary hover:text-brand bg-surface-alt hover:bg-brand/10 border border-color rounded-2xl transition-all shadow-sm">
                                            <i class="fa-solid fa-key text-xs"></i>
                                        </button>

                                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                        <button type="button" 
                                                title="Purge Entity Record"
                                                onclick="confirmDelete(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                                class="w-10 h-10 flex items-center justify-center text-rose-500 hover:text-white bg-rose-500/5 hover:bg-rose-500 border border-rose-500/10 rounded-2xl transition-all shadow-sm">
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
</div>

<!-- SECURITY: RESET PROTOCOL MODAL -->
<div id="modalReset" class="hidden fixed inset-0 z-50 backdrop-blur-xl bg-black/20 flex items-center justify-center p-4">
    <div class="modal-surface bento-card rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-10 py-8 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand border border-brand/20">
                    <i class="fa-solid fa-shield-keyhole text-lg"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-black text-xl text-primary tracking-tight">Security Override</h2>
                    <p class="text-[10px] font-bold text-tertiary tracking-tight mt-0.5">Credential rotation protocol</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalReset').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center text-tertiary hover:text-primary transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-10 space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            
            <div class="p-6 rounded-[2rem] bg-surface-alt/50 border border-color">
                <div class="text-[10px] font-black text-tertiary uppercase tracking-widest mb-1">Target Identity</div>
                <div class="font-manrope font-black text-lg text-primary" id="resetUsername"></div>
            </div>

            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">New Access Key</label>
                <div class="relative group">
                    <input type="password" name="new_password" id="resetPass" required minlength="8" autocomplete="new-password"
                           class="w-full modal-input border border-color rounded-2xl px-6 py-4 pr-14 text-sm font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all">
                    <button type="button" onclick="togglePass('resetPass', this)" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-tertiary hover:text-primary transition-colors opacity-40 group-focus-within:opacity-100">
                        <i class="fa-solid fa-eye-slash text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="button" onclick="document.getElementById('modalReset').classList.add('hidden')"
                        class="flex-1 bg-surface-alt hover:bg-border-color/50 text-secondary font-black font-inter text-[11px] uppercase tracking-widest rounded-2xl py-5 transition-all">
                    Dismiss
                </button>
                <button type="submit"
                        class="flex-1 bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-[0.25em] rounded-2xl py-5 shadow-xl shadow-brand/20 transition-all active:scale-[0.98]">
                    Rotate Key
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- SECURITY: CONFIRMATION PROTOCOL -->
<div id="modalConfirm" class="hidden fixed inset-0 z-[100] backdrop-blur-xl bg-black/40 flex items-center justify-center p-4">
    <div class="modal-surface bento-card rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-300 border border-color">
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500 mx-auto mb-6 border border-amber-500/20">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
            </div>
            <h3 class="font-manrope font-black text-xl text-primary mb-2">Security Protocol</h3>
            <p id="confirmMessage" class="text-sm text-tertiary font-inter leading-relaxed mb-10">Are you sure you want to proceed with this action?</p>
            
            <div class="flex gap-4">
                <button type="button" onclick="closeConfirm(false)" class="flex-1 px-6 py-4 rounded-xl bg-surface-alt hover:bg-brand/5 border border-color text-tertiary hover:text-brand font-black font-inter text-[11px] uppercase tracking-widest transition-all">Cancel</button>
                <button type="button" id="confirmBtn" class="flex-1 px-6 py-4 rounded-xl bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-widest shadow-lg shadow-brand/20 transition-all">Proceed</button>
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
