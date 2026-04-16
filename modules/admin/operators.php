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
        class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all">
    <i class="fa-solid fa-user-plus text-sm"></i>
    Add Operator
</button>';

include '../../includes/header.php';
?>

    <div class="p-8 max-w-[1440px] mx-auto">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <p class="text-emerald-700 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i>
            <p class="text-red-700 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Personnel Name</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Role</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Shift</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Contact</th>
                        <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Trx</th>
                        <th class="text-right px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php
                    $current_type = '';
                    foreach ($operators as $op):
                        if ($current_type !== $op['staff_type']):
                            $current_type = $op['staff_type'];
                            $group_label  = ($current_type === 'admin') ? 'Management' : 'Field Staff';
                    ?>
                    <tr class="bg-slate-50">
                        <td colspan="6" class="px-6 py-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter"><?= $group_label ?></span>
                        </td>
                    </tr>
                    <?php endif;
                    $shiftMap = [
                        '06:00 - 12:00'           => ['bg-blue-50 text-blue-700',    'Shift 1 (06-12)'],
                        '12:00 - 18:00'           => ['bg-amber-50 text-amber-700',  'Shift 2 (12-18)'],
                        '18:00 - 00:00'           => ['bg-purple-50 text-purple-700','Shift 3 (18-00)'],
                        '00:00 - 06:00'           => ['bg-slate-100 text-slate-600', 'Shift 4 (00-06)'],
                        '06:00 - 18:00 (Day)'     => ['bg-emerald-50 text-emerald-700','Day (12h)'],
                        '18:00 - 06:00 (Night)'   => ['bg-indigo-50 text-indigo-700', 'Night (12h)'],
                    ];
                    [$sCls, $sLabel] = $shiftMap[$op['shift']] ?? ['bg-slate-100 text-slate-500', $op['shift']];
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center font-manrope font-bold text-sm text-slate-600">
                                    <?= strtoupper(substr($op['full_name'], 0, 1)) ?>
                                </div>
                                <span class="font-inter font-semibold text-sm text-slate-800"><?= htmlspecialchars($op['full_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full font-inter <?= $op['staff_type'] === 'admin' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' ?>"><?= $op['staff_type'] ?></span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full font-inter <?= $sCls ?>"><?= $sLabel ?></span>
                        </td>
                        <td class="px-4 py-4 text-slate-400 text-sm font-mono"><?= htmlspecialchars($op['phone'] ?? '—') ?></td>
                        <td class="px-4 py-4 text-center font-manrope font-bold text-slate-900"><?= number_format($op['total_trx']) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= $op['staff_type'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')"
                                        class="flex items-center gap-1 text-slate-600 bg-slate-100 hover:bg-slate-200 text-xs font-bold font-inter px-3 py-2 rounded-xl transition-all">
                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Terminate this operator from the system?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                                    <button class="flex items-center gap-1 text-red-600 bg-red-50 hover:bg-red-100 text-xs font-bold font-inter px-3 py-2 rounded-xl transition-all">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
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
$inputCls = "w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all";
$selectCls = "w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all appearance-none";
?>

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3"><i class="fa-solid fa-headset text-slate-500"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Recruit New Operator</h2></div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Full Name</label>
                <input type="text" name="full_name" required placeholder="e.g. John Doe" class="<?= $inputCls ?>"></div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Shift</label>
                <select name="shift" required class="<?= $selectCls ?>"><?= $shiftOptions() ?></select></div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Role</label>
                <select name="staff_type" class="<?= $selectCls ?>">
                    <option value="operator">Operator</option>
                    <?php if ($_SESSION['role']==='superadmin'): ?><option value="admin">Admin Staff</option><?php endif; ?>
                </select></div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Phone Number</label>
                <input type="tel" name="phone" placeholder="08xxxxxxxxxx" class="<?= $inputCls ?>"></div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 bg-slate-100 text-slate-700 font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3">Cancel</button>
                <button type="submit" class="flex-1 bg-slate-900 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3"><i class="fa-solid fa-user-gear text-slate-500"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Update Operator Data</h2></div>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="operator_id" id="edit_id">
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Name</label>
                <input type="text" name="full_name" id="edit_name" required class="<?= $inputCls ?>"></div>
            <?php if ($_SESSION['role']==='superadmin'): ?>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Role</label>
                <select name="staff_type" id="edit_type" class="<?= $selectCls ?>">
                    <option value="operator">Operator</option>
                    <option value="admin">Admin Staff</option>
                </select></div>
            <?php endif; ?>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Shift</label>
                <select name="shift" id="edit_shift" class="<?= $selectCls ?>"><?= $shiftOptions() ?></select></div>
            <div><label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Emergency Contact</label>
                <input type="tel" name="phone" id="edit_phone" class="<?= $inputCls ?>"></div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-slate-100 text-slate-700 font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3">Cancel</button>
                <button type="submit" class="flex-1 bg-slate-900 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3">Save</button>
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
