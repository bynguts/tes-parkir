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
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 rounded-[2rem] icon-container flex items-center justify-center shadow-2xl shrink-0">
                <i class="fa-solid fa-users-gear text-3xl"></i>
            </div>
            <div>
                <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Personnel Management</h2>
                <p class="text-tertiary mt-1 text-sm font-medium">Manage gate personnel and administrative staff rosters.</p>
            </div>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="flex items-center gap-3 bg-brand hover:brightness-110 text-white text-[11px] font-black uppercase tracking-[0.2em] px-8 py-4 rounded-2xl shadow-xl shadow-brand/20 transition-all active:scale-[0.98]">
            <i class="fa-solid fa-plus text-sm"></i>
            Add New Personnel
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="flex items-center gap-4 status-badge-paid rounded-2xl px-6 py-5 border shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="w-10 h-10 rounded-xl bg-status-available-text/10 flex items-center justify-center">
            <i class="fa-solid fa-circle-check text-xl"></i>
        </div>
        <p class="text-sm font-manrope font-bold tracking-tight"><?= $msg ?></p>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="flex items-center gap-4 status-badge-lost rounded-2xl px-6 py-5 border shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="w-10 h-10 rounded-xl bg-status-lost-text/10 flex items-center justify-center">
            <i class="fa-solid fa-circle-exclamation text-xl"></i>
        </div>
        <p class="text-sm font-manrope font-bold tracking-tight"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- MAIN CARD -->
    <div class="bento-card bg-surface border-color rounded-[2.5rem] shadow-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-color">
                        <th class="text-left px-10 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">Personnel Name</th>
                        <th class="text-left px-6 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">Access Level</th>
                        <th class="text-left px-6 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">Active Shift</th>
                        <th class="text-left px-6 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">Contact</th>
                        <th class="text-center px-6 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">TRX Logs</th>
                        <th class="text-right px-10 py-8 text-[10px] font-black uppercase tracking-[0.3em] text-tertiary">Action</th>
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
                    <tr class="bg-surface-alt/30">
                        <td colspan="6" class="px-10 py-4">
                            <span class="text-[9px] font-black uppercase tracking-[0.25em] text-brand opacity-60"><?= $group_label ?></span>
                        </td>
                    </tr>
                    <?php endif;
                    $shiftMap = [
                        '06:00 - 12:00'           => ['status-badge-maintenance', 'Shift 1 (06-12)'],
                        '12:00 - 18:00'           => ['status-badge-parked',      'Shift 2 (12-18)'],
                        '18:00 - 00:00'           => ['status-badge-reserved',    'Shift 3 (18-00)'],
                        '00:00 - 06:00'           => ['status-badge-departed',    'Shift 4 (00-06)'],
                        '06:00 - 18:00 (Day)'     => ['status-badge-available',   'Day (12h)'],
                        '18:00 - 06:00 (Night)'   => ['status-badge-reserved',    'Night (12h)'],
                    ];
                    [$sCls, $sLabel] = $shiftMap[$op['shift']] ?? ['status-badge-departed', $op['shift']];
                    ?>
                    <tr class="hover:bg-surface-alt/50 transition-colors group">
                        <td class="px-10 py-6">
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-surface-alt border border-color flex items-center justify-center font-manrope font-black text-lg text-primary group-hover:bg-brand group-hover:text-white group-hover:border-brand transition-all shadow-sm">
                                    <?= strtoupper(substr($op['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-manrope font-bold text-primary tracking-tight"><?= htmlspecialchars($op['full_name']) ?></p>
                                    <p class="text-[10px] text-tertiary font-bold uppercase tracking-wider mt-0.5">ID #<?= str_pad($op['operator_id'], 4, '0', STR_PAD_LEFT) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6">
                            <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border font-inter <?= $op['staff_type'] === 'admin' ? 'status-badge-parked' : 'status-badge-maintenance' ?>"><?= $op['staff_type'] ?></span>
                        </td>
                        <td class="px-6 py-6">
                            <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border font-inter <?= $sCls ?>"><?= $sLabel ?></span>
                        </td>
                        <td class="px-6 py-6 font-mono text-[11px] text-tertiary tracking-tight"><?= htmlspecialchars($op['phone'] ?? '—') ?></td>
                        <td class="px-6 py-6 text-center">
                            <span class="font-manrope font-black text-lg text-primary"><?= number_format($op['total_trx']) ?></span>
                        </td>
                        <td class="px-10 py-6 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= $op['staff_type'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')"
                                        class="w-10 h-10 flex items-center justify-center text-tertiary hover:text-brand bg-surface-alt hover:bg-brand/10 border border-color rounded-2xl transition-all shadow-sm">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Terminate this operator from the system?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                                    <button class="w-10 h-10 flex items-center justify-center text-status-over-text bg-status-lost-bg hover:brightness-95 border border-status-lost-border rounded-2xl transition-all shadow-sm">
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
$inputCls = "w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all placeholder:text-tertiary/20";
$selectCls = "w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all appearance-none";
?>

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-xl bg-black/20 flex items-center justify-center p-4">
    <div class="modal-surface bento-card rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-10 py-8 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-user-plus text-xl"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-black text-2xl text-primary tracking-tight">Recruit Personnel</h2>
                    <p class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-0.5">Parking HR Registration</p>
                </div>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center text-tertiary hover:text-primary transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-10 space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Full Name</label>
                <input type="text" name="full_name" required placeholder="e.g. John Doe" class="<?= $inputCls ?>">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Shift Selection</label>
                    <select name="shift" required class="<?= $selectCls ?>"><?= $shiftOptions() ?></select>
                </div>
                <div class="space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Access Level</label>
                    <select name="staff_type" class="<?= $selectCls ?>">
                        <option value="operator">Operator</option>
                        <?php if ($_SESSION['role']==='superadmin'): ?><option value="admin">Admin Staff</option><?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Contact Number</label>
                <input type="tel" name="phone" placeholder="08xxxxxxxxxx" class="<?= $inputCls ?>">
            </div>

            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" 
                        class="flex-1 bg-surface-alt hover:bg-border-color/50 text-secondary font-black font-inter text-[11px] uppercase tracking-widest rounded-2xl py-5 transition-all">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-[0.25em] rounded-2xl py-5 shadow-xl shadow-brand/20 transition-all active:scale-[0.98]">
                    Register Personnel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="hidden fixed inset-0 z-50 backdrop-blur-xl bg-black/20 flex items-center justify-center p-4">
    <div class="modal-surface bento-card rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-10 py-8 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand">
                    <i class="fa-solid fa-user-gear text-xl"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-black text-2xl text-primary tracking-tight">Modify Profile</h2>
                    <p class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-0.5">Database Record Update</p>
                </div>
            </div>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center text-tertiary hover:text-primary transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-10 space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="operator_id" id="edit_id">
            
            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Full Name</label>
                <input type="text" name="full_name" id="edit_name" required class="<?= $inputCls ?>">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <?php if ($_SESSION['role']==='superadmin'): ?>
                <div class="space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Access Level</label>
                    <select name="staff_type" id="edit_type" class="<?= $selectCls ?>">
                        <option value="operator">Operator</option>
                        <option value="admin">Admin Staff</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="<?= $_SESSION['role']==='superadmin' ? '' : 'col-span-2' ?> space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Shift Assignment</label>
                    <select name="shift" id="edit_shift" class="<?= $selectCls ?>"><?= $shiftOptions() ?></select>
                </div>
            </div>

            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Emergency Contact</label>
                <input type="tel" name="phone" id="edit_phone" class="<?= $inputCls ?>">
            </div>

            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="flex-1 bg-surface-alt hover:bg-border-color/50 text-secondary font-black font-inter text-[11px] uppercase tracking-widest rounded-2xl py-5 transition-all">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-[0.25em] rounded-2xl py-5 shadow-xl shadow-brand/20 transition-all active:scale-[0.98]">
                    Apply Changes
                </button>
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
