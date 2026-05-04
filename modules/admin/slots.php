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
        $is_res   = (int)($_POST['is_reservation_only'] ?? 0);

        // Auto-resolve floor_id (required by DB schema, but hidden from user)
        $floor_stmt = $pdo->query("SELECT floor_id FROM floor ORDER BY floor_id LIMIT 1");
        $floor_id = $floor_stmt->fetchColumn();

        if (!$num || !in_array($type, ['car','motorcycle']) || !$floor_id) {
            $error = 'Slot configuration data is incomplete.';
        } else {
            try {
                $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id, is_reservation_only) VALUES (?,?,?,?)")
                    ->execute([$num, $type, $floor_id, $is_res]);
                $msg = "Slot <strong>{$num}</strong> successfully initialized in the system.";
            } catch (PDOException $e) {
                $error = 'Slot number is already registered.';
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

    <div class="p-8">
        <!-- Message Handlers -->
        <?php if ($msg): ?>
        <div class="flex items-center gap-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl px-6 py-5 mb-8 animate-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-500">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <p class="text-emerald-700 dark:text-emerald-400 text-sm font-bold font-inter tracking-tight"><?= $msg ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="flex items-center gap-4 bg-red-500/10 border border-red-500/20 rounded-2xl px-6 py-5 mb-8 animate-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center text-red-500">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <p class="text-red-700 dark:text-red-400 text-sm font-bold font-inter tracking-tight"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <!-- Inventory Control Card -->
        <div class="bento-card rounded-[2.5rem] shadow-2xl overflow-hidden border border-color">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-color bg-surface">
                            <th class="px-8 py-6 text-[11px] font-black uppercase tracking-[0.25em] text-tertiary font-inter">Slot Identity</th>
                            <th class="px-8 py-6 text-[11px] font-black uppercase tracking-[0.25em] text-tertiary font-inter text-center">Classification</th>
                            <th class="px-8 py-6 text-[11px] font-black uppercase tracking-[0.25em] text-tertiary font-inter text-center">Floor Index</th>
                            <th class="px-8 py-6 text-[11px] font-black uppercase tracking-[0.25em] text-tertiary font-inter text-center">Status Badge</th>
                            <th class="px-8 py-6 text-[11px] font-black uppercase tracking-[0.25em] text-tertiary font-inter text-right">Operational Deck</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-color">
                    <?php if (empty($slots)): ?>
                    <tr>
                        <td colspan="5" class="py-32 text-center">
                            <div class="w-20 h-20 rounded-[2rem] bg-surface-alt flex items-center justify-center mx-auto mb-6 overflow-hidden p-4">
                                <img src="../../assets/img/logo_p.png" alt="Logo" class="w-full h-full object-contain opacity-20">
                            </div>
                            <h3 class="font-manrope font-black text-xl text-primary mb-1">Zero Assets Detected</h3>
                            <p class="text-tertiary text-sm font-inter">Initialize your first parking slot to begin management.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($slots as $s):
                        $stMap = [
                            'available'   => ['status-badge-available', 'AVAILABLE'],
                            'occupied'    => ['status-badge-parked',    'PARKED'],
                            'reserved'    => ['status-badge-reserved',  'RESERVED'],
                            'maintenance' => ['status-badge-maintenance','MAINTENANCE'],
                        ];
                        [$stCls, $stLabel] = $stMap[$s['status']] ?? ['status-badge-maintenance', strtoupper($s['status'])];
                    ?>
                    <tr class="group hover:bg-surface-alt/50 transition-all duration-300">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-surface-alt flex items-center justify-center text-primary border border-color group-hover:border-brand/30 transition-colors">
                                    <span class="font-manrope font-black text-lg"><?= substr($s['slot_number'], 0, 1) ?></span>
                                </div>
                                <div>
                                    <div class="font-manrope font-black text-lg text-primary tracking-tight"><?= htmlspecialchars($s['slot_number']) ?></div>
                                    <div class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-0.5"><?= (int)$s['is_reservation_only'] === 1 ? 'Reservation Only Zone' : 'Standard Regular Area' ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex flex-col items-center gap-1.5">
                                <i class="fa-solid <?= $s['slot_type'] === 'car' ? 'fa-car text-blue-500' : 'fa-motorcycle text-emerald-500' ?> text-lg"></i>
                                <span class="text-[9px] font-black uppercase tracking-widest text-secondary"><?= $s['slot_type'] ?></span>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-surface-alt border border-color">
                                <span class="text-[11px] font-black text-primary font-manrope"><?= htmlspecialchars($s['floor_code']) ?></span>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="status-badge <?= $stCls ?>"><?= $stLabel ?></span>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex items-center justify-end gap-3">
                                <!-- Command Switch -->
                                <form method="POST" class="relative">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <select name="status" onchange="this.form.submit()"
                                            class="bg-surface-alt border border-color rounded-xl pl-4 pr-10 py-2.5 text-[10px] font-black font-inter uppercase tracking-[0.1em] text-primary focus:outline-none focus:ring-4 focus:ring-brand/10 transition-all appearance-none cursor-pointer hover:bg-surface-alt/80">
                                        <?php foreach (['available','occupied','reserved','maintenance'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= strtoupper($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[8px] text-tertiary pointer-events-none"></i>
                                </form>

                                <!-- Erase Command -->
                                <form method="POST" onsubmit="return confirm('DANGER: Permanently decommission slot <?= htmlspecialchars($s['slot_number'], ENT_QUOTES) ?>?')" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <button class="w-10 h-10 flex items-center justify-center text-red-500/40 hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
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

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-xl bg-black/20 flex items-center justify-center p-4">
    <div class="modal-surface bento-card rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-10 py-8 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand overflow-hidden p-2">
                    <img src="../../assets/img/logo_p.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <h2 class="font-manrope font-black text-2xl text-primary tracking-tight">Initialize Slot</h2>
                    <p class="text-[10px] font-bold text-tertiary uppercase tracking-widest mt-0.5">Asset Inventory Expansion</p>
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
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Slot Label</label>
                <input type="text" name="slot_number" required placeholder="Example: A-01"
                       class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-black font-manrope text-primary uppercase focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all placeholder:text-tertiary/20"
                       oninput="this.value=this.value.toUpperCase()">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Classification</label>
                    <select name="slot_type" class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-black font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all appearance-none">
                        <option value="car">🚗 Car</option>
                        <option value="motorcycle">🏍 Motorcycle</option>
                    </select>
                </div>
                <div class="space-y-3">
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Area Category</label>
                    <select name="is_reservation_only" class="w-full modal-input border border-color rounded-2xl px-6 py-4 text-sm font-black font-inter text-primary focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition-all appearance-none">
                        <option value="0">Standard Regular Area</option>
                        <option value="1">Reservation Only Zone</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="flex-1 bg-surface-alt hover:bg-border-color/50 text-secondary font-black font-inter text-[11px] uppercase tracking-widest rounded-2xl py-5 transition-all">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 bg-brand hover:brightness-110 text-white font-black font-inter text-[11px] uppercase tracking-[0.25em] rounded-2xl py-5 shadow-xl shadow-brand/20 transition-all active:scale-[0.98]">
                    Save Slot
                </button>
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
