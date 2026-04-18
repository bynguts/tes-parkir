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
            '06:00 - 12:00', '12:00 - 18:00', '18:00 - 00:00', '00:00 - 06:00',
            '06:00 - 18:00 (Day)', '18:00 - 06:00 (Night)'
        ];
        if (!$name || !in_array($shift, $valid_shifts)) {
            $error = 'Name and shift are required.';
        } else {
            $pdo->prepare("INSERT INTO operator (full_name, shift, staff_type, phone) VALUES (?,?,?,?)")
                ->execute([$name, $shift, $type, $phone ?: null]);
            $msg = "Profile {$name} successfully registered in the parking HR system.";
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
        $msg = 'Personnel profile database successfully updated.';
    }

    if ($action === 'delete') {
        $id = (int)$_POST['operator_id'];
        $used = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE operator_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $error = 'Constraint Violation: This operator holds active transaction logs.';
        } else {
            $pdo->prepare("DELETE FROM operator WHERE operator_id=?")->execute([$id]);
            $msg = 'Operator profile removed from the system.';
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

$page_title = 'Manage Operators';
$page_subtitle = 'Manage gate personnel data on duty for each shift.';
$page_actions = '
<button onclick="document.getElementById(\'addModal\').classList.remove(\'hidden\')"
        class="flex items-center gap-2 bg-slate-900 hover:bg-slate-900/90 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-lg transition-all">
    <i class="fa-solid fa-user-plus text-sm"></i>
    Add Operator
</button>';

include '../../includes/header.php';
?>

    <div class="p-6">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <p class="text-emerald-700 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-2xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i>
            <p class="text-red-700 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-900/10">
                        <th class="text-left px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Personnel Name</th>
                        <th class="text-left px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Role</th>
                        <th class="text-left px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Shift</th>
                        <th class="text-left px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Contact</th>
                        <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Trx</th>
                        <th class="text-right px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-900/[0.03]">
                    <?php
                    $current_type = '';
                    foreach ($operators as $op):
                        if ($current_type !== $op['staff_type']):
                            $current_type = $op['staff_type'];
                            $group_label  = ($current_type === 'admin') ? 'Management' : 'Field Staff';
                    ?>
                    <tr class="bg-slate-900/[0.02]">
                        <td colspan="6" class="px-6 py-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter"><?= $group_label ?></span>
                        </td>
                    </tr>
                    <?php endif;
                    $shiftMap = [
                        '06:00 - 12:00'           => ['bg-blue-50/10 text-blue-700 border-blue-500/10',    'Shift 1 (06-12)'],
                        '12:00 - 18:00'           => ['bg-amber-50/10 text-amber-700 border-amber-500/10',  'Shift 2 (12-18)'],
                        '18:00 - 00:00'           => ['bg-purple-50/10 text-purple-700 border-purple-500/10','Shift 3 (18-00)'],
                        '00:00 - 06:00'           => ['bg-slate-900/5 text-slate-600 border-slate-900/5', 'Shift 4 (00-06)'],
                        '06:00 - 18:00 (Day)'     => ['bg-emerald-50/10 text-emerald-700 border-emerald-500/10','Day (12h)'],
                        '18:00 - 06:00 (Night)'   => ['bg-indigo-50/10 text-indigo-700 border-indigo-500/10', 'Night (12h)'],
                    ];
                    [$sCls, $sLabel] = $shiftMap[$op['shift']] ?? ['bg-slate-900/5 text-slate-500', $op['shift']];
                    ?>
                    <tr class="hover:bg-slate-900/[0.01] transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center font-manrope font-bold text-sm text-slate-900 group-hover:bg-slate-900 group-hover:text-white transition-all">
                                    <?= strtoupper(substr($op['full_name'], 0, 1)) ?>
                                </div>
                                <span class="font-inter font-bold text-sm text-slate-900"><?= htmlspecialchars($op['full_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-lg border border-transparent font-inter <?= $op['staff_type'] === 'admin' ? 'bg-amber-50/10 text-amber-700 border-amber-500/10' : 'bg-blue-50/10 text-blue-700 border-blue-500/10' ?>"><?= $op['staff_type'] ?></span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-lg border font-inter <?= $sCls ?>"><?= $sLabel ?></span>
                        </td>
                        <td class="px-4 py-4 text-slate-900/40 text-xs font-mono tracking-tight"><?= htmlspecialchars($op['phone'] ?? '—') ?></td>
                        <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($op['total_trx']) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= $op['staff_type'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')"
                                        class="w-9 h-9 flex items-center justify-center text-slate-900/40 hover:text-slate-900 bg-slate-900/5 hover:bg-slate-900/10 rounded-xl transition-all">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Terminate this operator from the system?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                                    <button class="w-9 h-9 flex items-center justify-center text-red-500 bg-red-500/5 hover:bg-red-500/10 rounded-xl transition-all">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
// Shared shift options HTML
$shiftOptions = function($selected = '') {
    $session = $_SESSION['role'] ?? '';
    $opts = ['06:00 - 12:00'=>'Shift 1 (06-12)','12:00 - 18:00'=>'Shift 2 (12-18)','18:00 - 00:00'=>'Shift 3 (18-00)','00:00 - 06:00'=>'Shift 4 (00-06)'];
    $adminOpts = ['06:00 - 18:00 (Day)'=>'Day (06-18)','18:00 - 06:00 (Night)'=>'Night (18-06)'];
    $out = '<optgroup label="Operator (6h)">';
    foreach ($opts as $v=>$l) $out .= "<option value='$v'" . ($selected===$v?' selected':'') . ">$l</option>";
    $out .= '</optgroup>';
    if ($session === 'superadmin') {
        $out .= '<optgroup label="Admin (12h)">';
        foreach ($adminOpts as $v=>$l) $out .= "<option value='$v'" . ($selected===$v?' selected':'') . ">$l</option>";
        $out .= '</optgroup>';
    }
    return $out;
};
$inputCls = "w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all placeholder-slate-900/20";
$selectCls = "w-full bg-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all appearance-none";
?>

<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_30px_60px_-12px_rgba(15,23,42,0.15)] w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-900/10">
            <div class="flex items-center gap-3"><i class="fa-solid fa-headset text-slate-900/40 text-xl"></i>
                <h2 class="font-manrope font-extrabold text-xl text-slate-900 tracking-tight">Recruit New Operator</h2></div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-900/20 hover:text-slate-900 transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Full Name</label>
                <input type="text" name="full_name" required placeholder="e.g. John Doe" class="<?= $inputCls ?>"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Shift</label>
                    <select name="shift" required class="<?= $selectCls ?>"><?= $shiftOptions() ?></select></div>
                <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Role</label>
                    <select name="staff_type" class="<?= $selectCls ?>">
                        <option value="operator">Operator</option>
                        <?php if ($_SESSION['role']==='superadmin'): ?><option value="admin">Admin Staff</option><?php endif; ?>
                    </select></div>
            </div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Phone Number</label>
                <input type="tel" name="phone" placeholder="08xxxxxxxxxx" class="<?= $inputCls ?>"></div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 bg-slate-900/5 text-slate-900/60 font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all hover:bg-slate-900/10">Cancel</button>
                <button type="submit" class="flex-1 bg-slate-900 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 shadow-xl shadow-slate-900/10 hover:bg-slate-800 transition-all">Save Profile</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_30px_60px_-12px_rgba(15,23,42,0.15)] w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-900/10">
            <div class="flex items-center gap-3"><i class="fa-solid fa-user-gear text-slate-900/40 text-xl"></i>
                <h2 class="font-manrope font-extrabold text-xl text-slate-900 tracking-tight">Update Profile</h2></div>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-900/20 hover:text-slate-900 transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="POST" class="p-8 space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="operator_id" id="edit_id">
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Name</label>
                <input type="text" name="full_name" id="edit_name" required class="<?= $inputCls ?>"></div>
            <div class="grid grid-cols-2 gap-4">
                <?php if ($_SESSION['role']==='superadmin'): ?>
                <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Role</label>
                    <select name="staff_type" id="edit_type" class="<?= $selectCls ?>">
                        <option value="operator">Operator</option>
                        <option value="admin">Admin Staff</option>
                    </select></div>
                <?php endif; ?>
                <div class="<?= $_SESSION['role']==='superadmin' ? '' : 'col-span-2' ?>"><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Shift</label>
                    <select name="shift" id="edit_shift" class="<?= $selectCls ?>"><?= $shiftOptions() ?></select></div>
            </div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Emergency Contact</label>
                <input type="tel" name="phone" id="edit_phone" class="<?= $inputCls ?>"></div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-slate-900/5 text-slate-900/60 font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all hover:bg-slate-900/10">Cancel</button>
                <button type="submit" class="flex-1 bg-slate-900 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 shadow-xl shadow-slate-900/10 hover:bg-slate-800 transition-all">Update Database</button>
            </div>
        </form>
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
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
