<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $num      = strtoupper(trim($_POST['slot_number'] ?? ''));
        $type     = $_POST['slot_type'] ?? '';
        $floor_id = (int)($_POST['floor_id'] ?? 0);

        if (!$num || !in_array($type, ['car','motorcycle']) || $floor_id <= 0) {
            $error = 'Slot configuration data is incomplete.';
        } else {
            $fcheck = $pdo->prepare("SELECT floor_id FROM floor WHERE floor_id = ?");
            $fcheck->execute([$floor_id]);
            if (!$fcheck->fetch()) {
                $error = 'Invalid floor reference in the system.';
            } else {
                try {
                    $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id) VALUES (?,?,?)")
                        ->execute([$num, $type, $floor_id]);
                    $msg = "Slot <strong>{$num}</strong> successfully initialized in the database.";
                } catch (PDOException $e) {
                    $error = 'Slot number is already registered for this floor.';
                }
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved','maintenance'])) {
            $error = 'Invalid state value.';
        } else {
            $pdo->prepare("UPDATE parking_slot SET status=? WHERE slot_id=?")->execute([$status, $id]);
            $msg = 'Slot state successfully synchronized.';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['slot_id'];
        $occupied = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE slot_id=? AND payment_status='unpaid'");
        $occupied->execute([$id]);
        if ($occupied->fetchColumn() > 0) {
            $error = 'Constraint Violation: Active slot is tied to an ongoing transaction session.';
        } else {
            $pdo->prepare("DELETE FROM parking_slot WHERE slot_id=?")->execute([$id]);
            $msg = 'Slot permanently deleted.';
        }
    }
}

$slots = $pdo->query("
    SELECT ps.*, f.floor_code, f.floor_name
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    ORDER BY f.floor_code, ps.slot_type, ps.slot_number
")->fetchAll();

$floors_list = $pdo->query("SELECT floor_id, floor_code, floor_name FROM floor ORDER BY floor_code")->fetchAll();

$page_title = 'Manage Slot Inventory';
$page_subtitle = 'Configure capacity, location, and state of slots in the parking area.';
$page_actions = '
<button onclick="document.getElementById(\'addModal\').classList.remove(\'hidden\')"
        class="flex items-center gap-2 bg-slate-900 hover:bg-slate-900/90 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-lg transition-all">
    <i class="fa-solid fa-circle-plus text-sm"></i>
    Add Slot
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
            <div class="overflow-auto max-h-[72vh] no-scrollbar">
                <table class="w-full">
                    <thead class="sticky top-0 bg-white z-10">
                        <tr class="border-b border-slate-900/5">
                            <th class="text-left px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Slot Label</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Type</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Floor</th>
                            <th class="text-center px-4 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Current State</th>
                            <th class="text-right px-6 py-4 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Operational Commands</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php if (empty($slots)): ?>
                    <tr><td colspan="5" class="text-center py-20">
                        <i class="fa-solid fa-square-p text-6xl text-slate-900/5 block mb-4"></i>
                        <p class="text-slate-900/30 text-[11px] font-extrabold uppercase tracking-widest font-inter">No slots registered yet.</p>
                    </td></tr>
                    <?php else: foreach ($slots as $s):
                        $stMap = [
                            'available'   => ['bg-emerald-50/10 text-emerald-700 border-emerald-500/10',  'Available'],
                            'occupied'    => ['bg-red-50/10 text-red-700 border-red-500/10',          'Occupied'],
                            'reserved'    => ['bg-amber-50/10 text-amber-700 border-amber-500/10',      'Reserved'],
                            'maintenance' => ['bg-slate-900/5 text-slate-900/60 border-slate-900/10',     'Maintenance'],
                        ];
                        [$stCls, $stLabel] = $stMap[$s['status']] ?? ['bg-slate-900/5 text-slate-900/40', $s['status']];
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-manrope font-extrabold text-slate-900"><?= htmlspecialchars($s['slot_number']) ?></div>
                            <div class="text-slate-900/40 text-[10px] font-extrabold uppercase tracking-widest font-inter mt-1"><?= htmlspecialchars($s['floor_code']) ?> — <?= htmlspecialchars($s['floor_name']) ?></div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2 text-slate-600 text-sm font-inter">
                                <i class="fa-solid <?= $s['slot_type'] === 'car' ? 'fa-car text-blue-500' : 'fa-motorcycle text-emerald-500' ?> text-sm"></i>
                                <?= $s['slot_type'] === 'car' ? 'Car' : 'Motorcycle' ?>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="text-[9px] font-extrabold uppercase tracking-widest px-3 py-1.5 rounded-lg border font-inter <?= $stCls ?>"><?= $stLabel ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Status change -->
                                <form method="POST" class="flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <select name="status" onchange="this.form.submit()"
                                            class="bg-slate-50 ring-1 ring-slate-900/5 border-none rounded-lg px-3 py-1.5 text-[10px] font-bold font-inter uppercase tracking-widest text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all appearance-none cursor-pointer">
                                        <?php foreach (['available','occupied','reserved','maintenance'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>

                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Permanently delete slot <?= htmlspecialchars($s['slot_number'], ENT_QUOTES) ?>?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <button class="flex items-center gap-1 text-red-600 bg-red-50 hover:bg-slate-50 text-xs font-bold font-inter px-3 py-2 rounded-lg transition-all">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_30px_60px_-12px_rgba(15,23,42,0.15)] w-full max-w-sm mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-900/5">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-square-plus text-slate-900"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Initialize New Slot</h2>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-900/20 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Slot Number</label>
                <input type="text" name="slot_number" required placeholder="Example: A-01"
                       class="w-full bg-slate-900/5 ring-1 ring-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-extrabold font-manrope text-slate-900 uppercase focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all"
                       oninput="this.value=this.value.toUpperCase()">
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Vehicle Type</label>
                <select name="slot_type" class="w-full bg-slate-900/5 ring-1 ring-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-extrabold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all appearance-none">
                    <option value="car">🚗 Car</option>
                    <option value="motorcycle">🏍 Motorcycle</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter mb-2.5 ml-1">Floor</label>
                <select name="floor_id" class="w-full bg-slate-900/5 ring-1 ring-slate-900/5 border-none rounded-xl px-5 py-3.5 text-sm font-extrabold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all appearance-none" required>
                    <?php foreach ($floors_list as $f): ?>
                    <option value="<?= $f['floor_id'] ?>"><?= htmlspecialchars($f['floor_code']) ?> — <?= htmlspecialchars($f['floor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="flex-1 bg-slate-50 text-slate-900 font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all ring-1 ring-slate-900/5">Cancel</button>
                <button type="submit"
                        class="flex-1 bg-slate-900 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all shadow-lg shadow-slate-900/10">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php include '../../includes/footer.php'; ?>
