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
        if ($uid === (int)$_SESSION['user_id']) {
            $error = 'Illegal Operation: Cannot modify your own active session ID.';
        } else {
            $pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE user_id=?")->execute([$uid]);
            $msg = 'User Access Control List has been updated.';
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
}

$users = $pdo->query("SELECT user_id, username, role, full_name, last_login, is_active, created_at FROM admin_users ORDER BY role, username")->fetchAll();

$page_title = 'Admin Users & Roles';
$page_subtitle = 'Identity provisioning and system operational access control.';

include '../../includes/header.php';
?>

    <div class="p-6">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-user-shield text-emerald-600"></i>
            <p class="text-emerald-700 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-shield-xmark text-red-600"></i>
            <p class="text-red-700 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-[380px_1fr] gap-6">

            <!-- PROVISION FORM -->
            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] overflow-hidden self-start sticky top-24">
                <div class="px-6 py-5 border-b border-slate-900/5 flex items-center gap-3">
                    <i class="fa-solid fa-user-gear text-slate-900 text-xl"></i>
                    <h2 class="font-manrope font-bold text-lg text-slate-900">Provision New Account</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-4" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">

                        <div>
                            <label class="block text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter mb-2.5 ml-1">Username (Global ID)</label>
                            <input type="text" name="username" required autocomplete="off"
                                   class="w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-bold font-manrope text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all placeholder-slate-900/20">
                        </div>

                        <div>
                            <label class="block text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter mb-2.5 ml-1">Full Name</label>
                            <input type="text" name="full_name" placeholder="Optional"
                                   class="w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all placeholder-slate-900/20">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Authorization Role</label>
                            <select name="role" class="w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-bold font-manrope text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all appearance-none cursor-pointer">
                                <option value="operator">🖥 Operator</option>
                                <option value="admin">⚒ Admin</option>
                                <option value="superadmin">🛡 Superadmin</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Password (min 8 char)</label>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all">
                        </div>

                        <button type="submit"
                                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4.5 transition-all flex items-center justify-center gap-2 mt-2 shadow-xl shadow-slate-900/10">
                            <i class="fa-solid fa-user-plus text-sm"></i>
                            Provision Account
                        </button>
                    </form>
                </div>
            </div>

            <!-- USER TABLE -->
            <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-900/5 flex items-center gap-3">
                    <i class="fa-solid fa-users text-slate-900/40 text-lg"></i>
                    <h2 class="font-manrope font-extrabold text-xl text-slate-900 tracking-tight">System Identities (<?= count($users) ?>)</h2>
                </div>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-900/10">
                            <th class="text-left px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter">Identity</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter">Role</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter">Last Login</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter">Status</th>
                            <th class="text-right px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-900/[0.03]">
                        <?php foreach ($users as $u):
                            $isSelf = $u['user_id'] === (int)$_SESSION['user_id'];
                            $rColors = [
                                'superadmin' => 'bg-red-50/10 text-red-700 border border-red-500/10',
                                'admin'      => 'bg-amber-50/10 text-amber-700 border border-amber-500/10',
                                'operator'   => 'bg-blue-50/10 text-blue-700 border border-blue-500/10'
                            ];
                            $rClass = $rColors[$u['role']] ?? 'bg-slate-900/5 text-slate-900 border border-slate-900/10';
                        ?>
                        <tr class="hover:bg-slate-900/[0.01] transition-colors <?= !$u['is_active'] ? 'opacity-50' : '' ?> <?= $isSelf ? 'bg-emerald-50/[0.03]' : '' ?>">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center font-manrope font-bold text-white text-sm shadow-lg shadow-slate-900/10">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-inter font-bold text-sm text-slate-900">
                                            <?= htmlspecialchars($u['username']) ?>
                                            <?php if ($isSelf): ?><span class="text-emerald-600 text-[10px] font-bold uppercase tracking-widest ml-1 bg-emerald-50/10 px-1.5 py-0.5 rounded-md border border-emerald-500/10">Active Session</span><?php endif; ?>
                                        </div>
                                        <div class="text-slate-900/40 text-[11px] font-inter mt-0.5"><?= htmlspecialchars($u['full_name'] ?? 'No display name') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-5">
                                <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg font-inter <?= $rClass ?>"><?= $u['role'] ?></span>
                            </td>
                            <td class="px-4 py-5 text-slate-900/40 text-xs font-mono">
                                <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never active' ?>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <?php if ($u['is_active']): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-50/10 text-emerald-700 text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg font-inter border border-emerald-500/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 bg-red-50/10 text-red-700 text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg font-inter border border-red-500/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Disabled
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (!$isSelf): ?>
                                    <form method="POST" onsubmit="return confirm('Toggle account status for <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <button class="w-9 h-9 flex items-center justify-center <?= $u['is_active'] ? 'text-amber-600 bg-amber-50/10 hover:bg-amber-50/20' : 'text-emerald-600 bg-emerald-50/10 hover:bg-emerald-50/20' ?> rounded-xl transition-all border border-transparent hover:border-current">
                                            <i class="fa-solid <?= $u['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?> text-xs"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <button onclick="openReset(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                            class="w-9 h-9 flex items-center justify-center text-slate-900/40 hover:text-slate-900 bg-slate-900/5 hover:bg-slate-900/10 rounded-xl transition-all border border-transparent hover:border-slate-900/10">
                                        <i class="fa-solid fa-key text-xs"></i>
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

<!-- Reset Password Modal -->
<div id="modalReset" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_30px_60px_-12px_rgba(15,23,42,0.15)] w-full max-w-sm mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-900/5">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-key text-slate-900"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Force Reset Password</h2>
            </div>
            <button onclick="document.getElementById('modalReset').classList.add('hidden')" class="text-slate-900/20 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <p class="text-slate-900/40 text-xs font-inter uppercase tracking-wide">Reset password for account: <strong id="resetUsername" class="text-slate-900"></strong></p>
            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter mb-2.5 ml-1">New Password (min 8 char)</label>
                <input type="password" name="new_password" id="resetPass" required minlength="8" autocomplete="new-password"
                       class="w-full bg-slate-900/5 ring-1 ring-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('modalReset').classList.add('hidden')"
                        class="flex-1 bg-slate-900/5 text-slate-900/60 font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all hover:bg-slate-900/10">Cancel</button>
                <button type="submit"
                        class="flex-1 bg-slate-900 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all shadow-xl shadow-slate-900/10">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReset(id, uname) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').textContent = uname;
    document.getElementById('resetPass').value = '';
    document.getElementById('modalReset').classList.remove('hidden');
}
document.getElementById('modalReset').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php include '../../includes/footer.php'; ?>
