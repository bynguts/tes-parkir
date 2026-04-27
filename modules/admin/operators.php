<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $type  = $_POST['staff_type'] ?? 'operator';
        $valid_shifts = [
            '07:00 - 10:45', '10:45 - 14:30', '14:30 - 18:15', '18:15 - 22:00',
            '07:00 - 14:30', '14:30 - 22:00'
        ];
        if (!$name || !in_array($shift, $valid_shifts)) {
            $error = 'Name and shift are required.';
        } else {
            $pdo->prepare("INSERT INTO operator (full_name, shift, staff_type, phone) VALUES (?,?,?,?)")
                ->execute([$name, $shift, $type, $phone ?: null]);
            $msg = "Profile {$name} successfully registered in the personnel database.";
        }
    }

    if ($action === 'edit') {
        $id    = (int)$_POST['operator_id'];
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $type  = $_POST['staff_type'] ?? 'operator';
        $pdo->prepare("UPDATE operator SET full_name=?, shift=?, staff_type=?, phone=? WHERE operator_id=?")
            ->execute([$name, $shift, $type, $phone ?: null, $id]);
        $msg = 'Personnel profile successfully updated.';
    }

    if ($action === 'delete') {
        $id = (int)$_POST['operator_id'];
        $used = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE operator_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $error = 'Constraint Violation: This operator holds active transaction records.';
        } else {
            $pdo->prepare("DELETE FROM operator WHERE operator_id=?")->execute([$id]);
            $msg = 'Operator profile successfully removed from the system.';
        }
    }
}

$is_admin_only = ($_SESSION['role'] === 'admin');
$role_filter   = $is_admin_only ? "WHERE o.staff_type = 'operator'" : "";
$operators = $pdo->query("SELECT o.*, COUNT(t.transaction_id) AS total_trx
    FROM operator o
    LEFT JOIN `transaction` t ON t.operator_id = o.operator_id
    $role_filter
    GROUP BY o.operator_id
    ORDER BY o.staff_type ASC, o.full_name ASC")->fetchAll();

$page_title = 'Personnel Roster';
$page_subtitle = 'Management of gate operators and administrative staff profiles.';

include '../../includes/header.php';
?>



<div class="px-10 py-10">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="bg-brand hover:brightness-110 text-white font-bold font-inter text-[11px] uppercase tracking-widest px-8 py-4 rounded-xl shadow-lg shadow-brand/20 transition-all active:scale-[0.98] flex items-center gap-3">
            <i class="fa-solid fa-user-plus text-sm"></i>
            Register Personnel
        </button>
    </div>

    <!-- Alerts -->
    <?php if ($msg): ?>
    <div class="flex items-center gap-4 bg-emerald-50 border border-emerald-100 rounded-2xl px-6 py-4 animate-in fade-in slide-in-from-top-2 duration-500">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <p class="text-emerald-700 text-sm font-bold font-inter"><?= htmlspecialchars($msg) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="flex items-center gap-4 bg-rose-50 border border-rose-100 rounded-2xl px-6 py-4 animate-in fade-in slide-in-from-top-2 duration-500">
        <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <p class="text-rose-700 text-sm font-bold font-inter"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- Personnel Registry -->
    <div class="bento-card p-4 overflow-hidden">
        <div class="px-8 py-6 border-b border-color flex items-center justify-between bg-surface">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-users-gear text-lg"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-bold text-primary text-base">Personnel Registry</h2>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Active roster management (<?= count($operators) ?> profiles)</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="premium-thead">
                    <tr>
                        <th>Personnel Profile</th>
                        <th class="text-center">Access Level</th>
                        <th class="text-center">Active Shift</th>
                        <th class="text-center">Contact</th>
                        <th class="text-center">TRX Activity</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
                    <?php
                    $current_type = '';
                    foreach ($operators as $op):
                        if ($current_type !== $op['staff_type']):
                            $current_type = $op['staff_type'];
                            $group_label  = ($current_type === 'admin') ? 'Executive Management' : 'Field Operations Staff';
                    ?>
                    <tr class="bg-surface-alt/50">
                        <td colspan="6" class="px-8 py-2.5">
                            <span class="text-[9px] font-extrabold uppercase tracking-[0.2em] text-brand/60"><?= $group_label ?></span>
                        </td>
                    </tr>
                    <?php endif;
                    
                    $shiftMap = [
                        '06:00 - 12:00'           => ['badge-soft-emerald', 'Shift 1 (06-12)'],
                        '12:00 - 18:00'           => ['badge-soft-indigo',  'Shift 2 (12-18)'],
                        '18:00 - 00:00'           => ['badge-soft-rose',    'Shift 3 (18-00)'],
                        '00:00 - 06:00'           => ['badge-soft-slate',   'Shift 4 (00-06)'],
                        '06:00 - 18:00 (Day)'     => ['badge-soft-emerald', 'Day (06-18)'],
                        '18:00 - 06:00 (Night)'   => ['badge-soft-rose',    'Night (18-06)'],
                    ];
                    [$sBadge, $sLabel] = $shiftMap[$op['shift']] ?? ['badge-soft-slate', $op['shift']];
                    ?>
                    <tr class="group hover:bg-surface-alt/40 transition-all duration-300">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-brand flex items-center justify-center font-manrope font-extrabold text-white text-base shadow-lg shadow-brand/20 group-hover:scale-105 transition-all duration-300">
                                    <?= strtoupper(substr($op['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-manrope font-bold text-[14px] text-primary tracking-tight"><?= htmlspecialchars($op['full_name']) ?></p>
                                    <p class="text-[10px] font-bold text-tertiary uppercase tracking-wider leading-none mt-1">PRO-ID #<?= str_pad($op['operator_id'], 4, '0', STR_PAD_LEFT) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="badge-soft <?= $op['staff_type'] === 'admin' ? 'badge-soft-indigo' : 'badge-soft-slate' ?>">
                                <?= strtoupper($op['staff_type']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="badge-soft <?= $sBadge ?>">
                                <?= $sLabel ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <div class="text-[12px] font-inter font-medium text-secondary tracking-tight">
                                <?= htmlspecialchars($op['phone'] ?: '—') ?>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <div class="text-[14px] font-manrope font-extrabold text-primary">
                                <?= number_format($op['total_trx']) ?>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= $op['staff_type'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')"
                                        class="btn-ghost !w-9 !h-9 !text-slate-400 hover:!text-brand hover:!bg-indigo-50"
                                        title="Modify Profile">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>')"
                                        class="btn-ghost !w-9 !h-9 !text-rose-400 hover:!text-rose-500 hover:!bg-rose-50"
                                        title="Terminate Record">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
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

<?php
// Shared shift options logic
$shiftOptions = function($selected = '') {
    $role = $_SESSION['role'] ?? '';
    $opts = [
        '07:00 - 10:45' => 'Shift 1 (07:00 - 10:45)',
        '10:45 - 14:30' => 'Shift 2 (10:45 - 14:30)',
        '14:30 - 18:15' => 'Shift 3 (14:30 - 18:15)',
        '18:15 - 22:00' => 'Shift 4 (18:15 - 22:00)'
    ];
    $adminOpts = [
        '07:00 - 14:30' => 'Admin Shift 1 (07:00 - 14:30)',
        '14:30 - 22:00' => 'Admin Shift 2 (14:30 - 22:00)'
    ];
    
    $out = '<optgroup label="OPERATIONAL ROTATION (07-22)">';
    foreach ($opts as $v => $l) {
        $out .= "<option value='$v'" . ($selected === $v ? ' selected' : '') . ">$l</option>";
    }
    $out .= '</optgroup>';
    
    if ($role === 'superadmin') {
        $out .= '<optgroup label="ADMINISTRATIVE ROTATION (07-22)">';
        foreach ($adminOpts as $v => $l) {
            $out .= "<option value='$v'" . ($selected === $v ? ' selected' : '') . ">$l</option>";
        }
        $out .= '</optgroup>';
    }
    return $out;
};
?>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-sm bg-slate-900/40 flex items-center justify-center p-4">
    <div class="bento-card w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-8 py-5 border-b border-color bg-surface">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-brand/5 border border-brand/10 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-bold text-primary text-base">Recruit Personnel</h2>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Parking HR Registration</p>
                </div>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="btn-ghost">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" class="p-8 space-y-6 bg-surface">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                <input type="text" name="full_name" required placeholder="e.g. John Doe" 
                       class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all placeholder:text-slate-300">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Access Level</label>
                    <div class="relative">
                        <select name="staff_type" class="w-full appearance-none bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all cursor-pointer">
                            <option value="operator">Operator</option>
                            <?php if ($_SESSION['role']==='superadmin'): ?><option value="admin">Admin</option><?php endif; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Shift Assignment</label>
                    <div class="relative">
                        <select name="shift" required class="w-full appearance-none bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all cursor-pointer">
                            <?= $shiftOptions() ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Contact Number</label>
                <input type="tel" name="phone" placeholder="08xxxxxxxxxx" 
                       class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all placeholder:text-slate-300">
            </div>

            <div class="flex gap-4 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="flex-1 py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-[11px] uppercase tracking-widest rounded-xl transition-all">
                    Dismiss
                </button>
                <button type="submit"
                        class="flex-1 py-4 bg-brand hover:brightness-110 text-white font-bold text-[11px] uppercase tracking-widest rounded-xl shadow-lg shadow-brand/20 transition-all active:scale-[0.98]">
                    Register Personnel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 backdrop-blur-sm bg-slate-900/40 flex items-center justify-center p-4">
    <div class="bento-card w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-8 py-5 border-b border-color bg-surface">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-brand/5 border border-brand/10 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-bold text-primary text-base">Modify Profile</h2>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Database Record Update</p>
                </div>
            </div>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="btn-ghost">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" class="p-8 space-y-6 bg-surface">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="operator_id" id="edit_id">
            
            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                <input type="text" name="full_name" id="edit_name" required 
                       class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <?php if ($_SESSION['role']==='superadmin'): ?>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Access Level</label>
                    <div class="relative">
                        <select name="staff_type" id="edit_type" class="w-full appearance-none bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all cursor-pointer">
                            <option value="operator">Operator</option>
                            <option value="admin">Admin</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                    </div>
                </div>
                <?php endif; ?>
                <div class="<?= $_SESSION['role']==='superadmin' ? '' : 'col-span-2' ?> space-y-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Shift Assignment</label>
                    <div class="relative">
                        <select name="shift" id="edit_shift" class="w-full appearance-none bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-manrope font-bold text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all cursor-pointer">
                            <?= $shiftOptions() ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1">Emergency Contact</label>
                <input type="tel" name="phone" id="edit_phone" 
                       class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-white transition-all">
            </div>

            <div class="flex gap-4 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="flex-1 py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-[11px] uppercase tracking-widest rounded-xl transition-all">
                    Dismiss
                </button>
                <button type="submit"
                        class="flex-1 py-4 bg-brand hover:brightness-110 text-white font-bold text-[11px] uppercase tracking-widest rounded-xl shadow-lg shadow-brand/20 transition-all active:scale-[0.98]">
                    Apply Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="POST" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="operator_id" id="delete_id">
</form>

<!-- Universal Confirmation Modal -->
<div id="modalConfirm" class="hidden fixed inset-0 z-[100] backdrop-blur-sm bg-slate-900/60 flex items-center justify-center p-4">
    <div class="bento-card w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-300 bg-surface">
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-500 mx-auto mb-6">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
            </div>
            <h3 class="font-manrope font-extrabold text-xl text-primary mb-2">Personnel Protocol</h3>
            <p id="confirmMessage" class="text-[13px] text-slate-500 font-inter leading-relaxed mb-10 px-4"></p>
            
            <div class="flex gap-4">
                <button onclick="closeConfirm(false)" class="flex-1 py-4 bg-slate-50 hover:bg-slate-100 text-slate-500 font-bold text-[11px] uppercase tracking-widest rounded-xl transition-all">Cancel</button>
                <button id="confirmBtn" class="flex-1 py-4 bg-brand hover:brightness-110 text-white font-bold text-[11px] uppercase tracking-widest rounded-xl shadow-lg shadow-brand/20 transition-all">Execute</button>
            </div>
        </div>
    </div>
</div>

<script>
function fillEdit(id, name, shift, type, phone) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_name').value  = name;
    document.getElementById('edit_shift').value = shift;
    const et = document.getElementById('edit_type');
    if (et) et.value = type;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('editModal').classList.remove('hidden');
}

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

function confirmDelete(id, name) {
    showConfirm(`CRITICAL: You are about to terminate the profile for '${name}'. This action is irreversible. Proceed?`, () => {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    });
}

window.onclick = (e) => {
    if (e.target.id === 'addModal') document.getElementById('addModal').classList.add('hidden');
    if (e.target.id === 'editModal') document.getElementById('editModal').classList.add('hidden');
};
</script>

<?php include '../../includes/footer.php'; ?>
