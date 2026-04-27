<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$msg   = '';
$error = '';

// Auto-expire reservations that are past reserved_until
$pdo->exec("UPDATE reservation SET status='expired' WHERE status IN ('pending','confirmed') AND reserved_until < NOW()");

// ── Handle form submissions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';


    if ($action === 'cancel') {
        $res_id = (int)($_POST['reservation_id'] ?? 0);
        $pdo->prepare("UPDATE reservation SET status='cancelled' WHERE reservation_id=? AND status IN ('pending','confirmed')")
            ->execute([$res_id]);
        $msg = 'Reservation successfully cancelled.';
    }

    if ($action === 'edit') {
        $res_id     = (int)($_POST['reservation_id'] ?? 0);
        $plate      = strtoupper(trim($_POST['plate_number'] ?? ''));
        $vtype      = $_POST['vehicle_type'] ?? '';
        $owner      = trim($_POST['owner_name'] ?? 'Guest');
        $phone      = trim($_POST['owner_phone'] ?? '');
        $date_from  = $_POST['reserved_from'] ?? '';
        $date_until = $_POST['reserved_until'] ?? '';
        if (!$date_until && $date_from) {
            $date_until = date('Y-m-d H:i:s', strtotime($date_from . ' + 30 days'));
        }

        if (!$res_id || !$plate || !in_array($vtype, ['car', 'motorcycle']) || !$date_from) {
            $error = 'All fields are required.';
        } else {
            try {
                $pdo->beginTransaction();

                // Get current reservation state
                $stmt = $pdo->prepare("SELECT slot_id, reserved_from, vehicle_id FROM reservation WHERE reservation_id = ?");
                $stmt->execute([$res_id]);
                $current = $stmt->fetch();

                if (!$current) {
                    throw new Exception("Reservation not found.");
                }

                // Update vehicle info
                $pdo->prepare("UPDATE vehicle SET plate_number=?, vehicle_type=?, owner_name=?, owner_phone=? WHERE vehicle_id=?")
                    ->execute([$plate, $vtype, $owner ?: 'Guest', $phone ?: null, $current['vehicle_id']]);

                // Check if slot needs to be reassigned (type or time change)
                $needs_new_slot = false;
                $old_vtype = $pdo->query("SELECT vehicle_type FROM vehicle WHERE vehicle_id=".$current['vehicle_id'])->fetchColumn();
                
                if ($vtype !== $old_vtype || $date_from !== $current['reserved_from']) {
                    $needs_new_slot = true;
                }

                $slot_id = $current['slot_id'];
                if ($needs_new_slot) {
                    $stmt = $pdo->prepare("
                        SELECT ps.slot_id FROM parking_slot ps
                        JOIN floor f ON ps.floor_id = f.floor_id
                        WHERE ps.slot_type = ?
                          AND ps.status = 'available'
                          AND ps.slot_id NOT IN (
                            SELECT slot_id FROM reservation
                            WHERE status IN ('pending','confirmed')
                              AND reservation_id != ?
                              AND NOT (reserved_until <= ? OR reserved_from >= ?)
                          )
                        ORDER BY f.floor_code, ps.slot_number LIMIT 1
                    ");
                    $stmt->execute([$vtype, $res_id, $date_from, $date_until]);
                    $new_slot = $stmt->fetch();

                    if (!$new_slot) {
                        throw new Exception("No slots available for the new configuration.");
                    }
                    $slot_id = $new_slot['slot_id'];
                }

                $pdo->prepare("UPDATE reservation SET plate_number=?, slot_id=?, reserved_from=?, reserved_until=? WHERE reservation_id=?")
                    ->execute([$plate, $slot_id, $date_from, $date_until, $res_id]);

                $pdo->commit();
                $msg = "Reservation updated successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$reservations = $pdo->query("
    SELECT r.reservation_id, r.reservation_code, r.reserved_from, r.reserved_until, r.status, r.slot_id, r.is_public,
           v.plate_number, v.vehicle_type, v.owner_name, v.owner_phone,
           ps.slot_number, f.floor_code AS floor
    FROM reservation r
    JOIN vehicle v       ON r.vehicle_id  = v.vehicle_id
    JOIN parking_slot ps ON r.slot_id     = ps.slot_id
    JOIN floor f         ON ps.floor_id   = f.floor_id
    WHERE r.status IN ('pending','confirmed','used')
    ORDER BY FIELD(r.status, 'confirmed', 'pending', 'used'), r.reserved_from
")->fetchAll();

$rates_data = $pdo->query("SELECT * FROM parking_rate")->fetchAll();
$rates = [];
foreach ($rates_data as $r) {
    $rates[$r['vehicle_type']] = $r;
}

function calculateEstimation($from, $until, $rate) {
    if (!$from || !$until || !$rate) return 0;
    $duration = strtotime($until) - strtotime($from);
    if ($duration <= 0) return 0;
    
    $hours = ceil($duration / 3600);
    $fee = $rate['first_hour_rate'];
    if ($hours > 1) {
        $fee += ($hours - 1) * $rate['next_hour_rate'];
    }
    
    return min($fee, $rate['daily_max_rate']);
}


$page_title = 'Reservation Management';
$page_subtitle = 'Manage pre-booking and priority parking slot allocation.';
$page_actions = '
<div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-surface-alt border border-color shadow-sm transition-all hover:border-brand/30">
    <div class="w-2 h-2 rounded-full bg-brand animate-pulse"></div>
    <span class="text-[10px] font-black uppercase tracking-widest text-primary">
        ' . count($reservations) . ' Active Reservations
    </span>
</div>';

include '../../includes/header.php';
?>


<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* Vtype Selector */

/* Vtype Selector */
.vtype-btn {
    border: 2px solid var(--border-color);
    background: var(--surface-alt);
    color: var(--text-secondary);
    cursor: pointer;
}
.vtype-btn.active {
    border-color: var(--brand);
    background: var(--brand);
    color: var(--surface);
}
</style>

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <?php if ($msg || $error): ?>
    <div class="flex-shrink-0">
        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-500/5 rounded-2xl px-5 py-4 border border-emerald-500/20">
            <i class="fa-solid fa-circle-check text-emerald-500"></i>
            <p class="text-emerald-500/80 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/5 rounded-2xl px-5 py-4 border border-red-500/20">
            <i class="fa-solid fa-circle-exclamation text-red-500"></i>
            <p class="text-red-500/80 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-5 overflow-hidden flex-1 min-h-0">

        <div class="bento-card overflow-hidden">
            <div class="py-5 px-4 border-b border-color flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-calendar-check text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Active Reservation Queue</h3>
                        <p class="text-tertiary text-[11px] font-medium flex items-center gap-2">
                            Scheduled Parking Sessions
                        </p>
                    </div>
                </div>

            </div>

            <div class="overflow-x-auto min-h-[350px]">
                <div class="inline-block min-w-full align-middle">
                    <table class="w-full font-inter border-collapse table-fixed activity-table">
                        <thead>
                            <tr class="border-b border-color">
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left pl-6">Vehicle</th>
                                <th class="py-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Plate Number</th>
                                <th class="py-4 w-[14%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left px-4">Client</th>
                                <th class="py-4 w-[14%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Ticket Code</th>
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Slot</th>
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Entry</th>
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Exit</th>
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Est. Fee</th>
                                <th class="py-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Status</th>
                                <th class="py-4 w-[6%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-color">
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="10" class="px-4 py-24 text-center">
                                        <div class="flex flex-col items-center opacity-40">
                                            <i class="fa-solid fa-calendar-xmark text-5xl mb-4 text-slate-300"></i>
                                            <p class="text-slate-500 font-inter font-medium text-sm">No active reservations found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php 
                            $res_counter = 1;
                            foreach ($reservations as $r): 
                            ?>
                            <tr class="group hover:bg-surface-alt/50 transition-colors fleet-row">
                                <td class="pl-6 pr-4 py-4 align-middle">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                            <i class="fa-solid fa-<?= $r['vehicle_type'] === 'car' ? 'car' : 'motorcycle' ?> text-lg"></i>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <span class="plate-number text-sm font-manrope font-semibold text-primary leading-none uppercase tracking-wider"><?= htmlspecialchars($r['plate_number']) ?></span>
                                </td>
                                <td class="px-4 py-4 text-left align-middle">
                                    <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= htmlspecialchars($r['owner_name']) ?></span>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="flex flex-col gap-1 items-center">
                                        <span class="text-sm font-manrope font-semibold text-primary leading-none uppercase"><?= htmlspecialchars($r['reservation_code']) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <?php 
                                            $display_slot = "#RES " . $res_counter++;
                                        ?>
                                        <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= $display_slot ?></span>
                                        <span class="text-[10px] font-inter text-tertiary leading-none uppercase tracking-wider">RSV ZONE</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('H:i', strtotime($r['reserved_from'])) ?></span>
                                        <span class="text-[10px] font-inter text-tertiary leading-none"><?= date('d M Y', strtotime($r['reserved_from'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('H:i', strtotime($r['reserved_until'])) ?></span>
                                        <span class="text-[10px] font-inter text-tertiary leading-none"><?= date('d M Y', strtotime($r['reserved_until'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <?php 
                                    $est_fee = calculateEstimation($r['reserved_from'], $r['reserved_until'], $rates[$r['vehicle_type']] ?? null);
                                    ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-sm font-manrope font-semibold text-primary leading-none">Rp <?= number_format($est_fee, 0, ',', '.') ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="flex justify-center">
                                        <?php if ($r['status'] === 'used'): ?>
                                            <div class="status-badge status-badge-online !bg-indigo-500/10 !text-indigo-400 !border-indigo-500/20">
                                                <span class="status-dot-online !bg-indigo-500"></span>
                                                INSIDE
                                            </div>
                                        <?php else: ?>
                                            <div class="status-badge status-badge-awaiting uppercase">
                                                <span class="status-dot-awaiting"></span>
                                                WAITING
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 pr-6 pl-4 text-right align-middle relative">
                                    <div class="flex justify-end">
                                        <div class="relative action-menu-container">
                                            <button onclick="toggleActionMenu(this, event)" class="btn-ghost">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            
                                            <!-- Dropdown Menu -->
                                            <div class="action-dropdown hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                                                <?php if ($r['status'] !== 'used'): ?>
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                                    'id' => $r['reservation_id'],
                                                    'plate' => $r['plate_number'],
                                                    'type' => $r['vehicle_type'],
                                                    'owner' => $r['owner_name'],
                                                    'phone' => $r['owner_phone'],
                                                    'from' => $r['reserved_from'],
                                                    'until' => $r['reserved_until']
                                                ])) ?>)" class="w-full px-4 py-3 text-left flex items-center gap-3 hover:bg-brand/[0.03] transition-all group/item">
                                                    <div class="w-8 h-8 rounded-lg icon-container flex items-center justify-center shrink-0 !text-brand !bg-brand/5 transition-all group-hover/item:scale-110">
                                                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] font-black uppercase tracking-widest text-brand leading-tight">Edit</span>
                                                        <span class="text-[9px] text-tertiary font-medium">Modify Data</span>
                                                    </div>
                                                </button>

                                                <div class="h-px bg-color mx-4 my-1"></div>

                                                <form method="POST" onsubmit="return confirm('Cancel this reservation?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                                                    <button type="submit" class="w-full px-4 py-3 text-left flex items-center gap-3 hover:bg-red-500/[0.03] transition-all group/item">
                                                        <div class="w-8 h-8 rounded-lg icon-container flex items-center justify-center shrink-0 !text-red-500 !bg-red-500/5 transition-all group-hover/item:scale-110">
                                                            <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-[10px] font-black uppercase tracking-widest text-red-500 leading-tight">Cancel</span>
                                                            <span class="text-[9px] text-tertiary font-medium">Stop Allocation</span>
                                                        </div>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <div class="px-4 py-3 text-center">
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-tertiary">Session Locked</span>
                                                    <p class="text-[9px] text-tertiary/60 leading-tight mt-1">Vehicle is currently parked</p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>


        </div>
    </div>


    <!-- EDIT RESERVATION MODAL -->
    <div id="editResModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[1000] hidden items-center justify-center p-6">
        <div class="bg-surface border border-color w-full max-w-xl rounded-3xl overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-300">
            <div class="py-5 px-4 border-b border-color flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl icon-container flex items-center justify-center bg-brand/10 text-brand">
                        <i class="fa-solid fa-pen-to-square text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black font-inter text-primary uppercase tracking-tight">Edit Reservation</h2>
                        <p class="text-xs text-tertiary font-inter">Update allocation parameters for active schedule</p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="w-10 h-10 rounded-xl hover:bg-surface-alt flex items-center justify-center transition-all text-tertiary hover:text-primary">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" class="p-8">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="reservation_id" id="edit_res_id">
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-3">Vehicle Identifier</label>
                        <input type="text" name="plate_number" id="edit_plate"
                               class="modal-input w-full border-2 border-transparent focus:border-brand rounded-2xl px-5 py-4 text-lg font-black font-manrope text-primary focus:outline-none text-center uppercase tracking-widest transition-all placeholder:opacity-20 shadow-inner"
                               placeholder="B 1234 AB" required oninput="this.value=this.value.toUpperCase()">
                    </div>

                    <div class="grid grid-cols-3 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Vehicle Category</label>
                            <select name="vehicle_type" id="edit_vtype"
                                    class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3.5 text-[13px] font-bold font-manrope text-primary focus:outline-none transition-all bg-surface">
                                <option value="car">Car / Four-Wheeler</option>
                                <option value="motorcycle">Motorcycle / Two-Wheeler</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Start Schedule</label>
                            <div class="relative">
                                <input type="text" name="reserved_from" id="edit_from_dt" required
                                       class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3.5 text-[12px] font-bold font-manrope text-primary focus:outline-none transition-all cursor-pointer">
                                <i class="fa-solid fa-clock absolute right-4 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">End Schedule</label>
                            <div class="relative">
                                <input type="text" name="reserved_until" id="edit_until_dt"
                                       class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3.5 text-[12px] font-bold font-manrope text-primary focus:outline-none transition-all cursor-pointer"
                                       placeholder="Optional">
                                <i class="fa-solid fa-clock absolute right-4 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Owner Identity</label>
                            <input type="text" name="owner_name" id="edit_owner"
                                   class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3.5 text-[13px] font-bold font-manrope text-primary focus:outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Contact Info</label>
                            <input type="tel" name="owner_phone" id="edit_phone"
                                   class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3.5 text-[13px] font-bold font-manrope text-primary focus:outline-none transition-all">
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-color flex gap-4">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 px-6 py-4 rounded-xl font-bold text-[11px] uppercase tracking-widest text-tertiary hover:bg-surface-alt transition-all">
                        Discard Changes
                    </button>
                    <button type="submit"
                            class="flex-2 px-8 py-4 rounded-xl font-black text-[11px] uppercase tracking-widest bg-brand text-white hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        Apply Updates
                    </button>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    function toggleModal(id) {
        const modal = document.getElementById(id);
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } else {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function toggleActionMenu(button, event) {
        event.stopPropagation();
        const container = button.closest('.action-menu-container');
        const dropdown = container.querySelector('.action-dropdown');
        document.querySelectorAll('.action-dropdown').forEach(d => {
            if (d !== dropdown) d.classList.add('hidden');
        });
        dropdown.classList.toggle('hidden');
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.action-menu-container')) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
        }
    });

    var MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];

    function makeSelect(options, currentVal, onChange, extraStyle) {
        var sel = document.createElement('select');
        sel.className = 'fp-inject';
        if (extraStyle) sel.style.cssText += extraStyle;
        options.forEach(function(item) {
            var o = document.createElement('option');
            o.value = item.v; o.textContent = item.l;
            if (String(item.v) === String(currentVal)) o.selected = true;
            sel.appendChild(o);
        });
        sel.addEventListener('change', function () { onChange(parseInt(sel.value), sel); });
        return sel;
    }

    function buildDropdowns(instance) {
        var cal = instance.calendarContainer;
        // We now use native monthSelectorType: "dropdown"
        // but keep the hour/minute overrides below
        var hourInput = cal.querySelector('input.flatpickr-hour');
        if (hourInput) {
            var hw = hourInput.closest('.numInputWrapper');
            if (hw) {
                var hourOpts = [];
                for (var h = 0; h < 24; h++) hourOpts.push({v: h, l: String(h).padStart(2,'0')});
                var hourSel = makeSelect(hourOpts, parseInt(hourInput.value) || 0, function(val) {
                    hourInput.value = String(val).padStart(2,'0');
                    hourInput.dispatchEvent(new Event('input', {bubbles: true}));
                    hourInput.dispatchEvent(new Event('change', {bubbles: true}));
                }, 'font-size:20px;min-width:76px;text-align:center;');
                hw.parentNode.insertBefore(hourSel, hw);
            }
        }
        var minInput = cal.querySelector('input.flatpickr-minute');
        if (minInput) {
            var mw = minInput.closest('.numInputWrapper');
            if (mw) {
                var curMin = Math.round((parseInt(minInput.value) || 0) / 15) * 15 % 60;
                var minSel = makeSelect([{v:0,l:'00'},{v:15,l:'15'},{v:30,l:'30'},{v:45,l:'45'}], curMin, function(val) {
                    minInput.value = String(val).padStart(2,'0');
                    minInput.dispatchEvent(new Event('input', {bubbles: true}));
                    minInput.dispatchEvent(new Event('change', {bubbles: true}));
                }, 'font-size:20px;min-width:76px;text-align:center;');
                mw.parentNode.insertBefore(minSel, mw);
            }
        }
    }

    function addButtons(instance) {
        var d = document.createElement('div');
        d.className = 'flatpickr-custom-btn';
        ['Clear','Today','OK'].forEach(function(lbl) {
            var b = document.createElement('button');
            b.type = 'button'; b.innerText = lbl;
            if (lbl === 'OK') b.className = 'ok';
            b.onclick = function() {
                if (lbl === 'Clear') instance.clear();
                else if (lbl === 'Today') instance.setDate(new Date());
                else instance.close();
            };
            d.appendChild(b);
        });
        instance.calendarContainer.appendChild(d);
    }

    function onReady(_, __, instance) {
        addButtons(instance);
        buildDropdowns(instance);
    }


    var editPicker = flatpickr("#edit_from_dt", {
        enableTime: true, 
        dateFormat: "Y-m-d\\TH:i",
        monthSelectorType: "dropdown",
        time_24hr: true, 
        minuteIncrement: 15,
        onReady: onReady
    });

    var editUntilPicker = flatpickr("#edit_until_dt", {
        enableTime: true, 
        dateFormat: "Y-m-d\\TH:i",
        monthSelectorType: "dropdown",
        time_24hr: true, 
        minuteIncrement: 15,
        onReady: onReady
    });

    function openEditModal(data) {
        document.getElementById('edit_res_id').value = data.id;
        document.getElementById('edit_plate').value = data.plate;
        document.getElementById('edit_vtype').value = data.type;
        document.getElementById('edit_owner').value = data.owner;
        document.getElementById('edit_phone').value = data.phone || '';
        
        // Convert SQL format (YYYY-MM-DD HH:MM:SS) to ISO format (YYYY-MM-DDTHH:MM) for picker
        const isoDateFrom = data.from.replace(' ', 'T').substring(0, 16);
        editPicker.setDate(isoDateFrom);

        if (data.until) {
            const isoDateUntil = data.until.replace(' ', 'T').substring(0, 16);
            editUntilPicker.setDate(isoDateUntil);
        } else {
            editUntilPicker.clear();
        }

        document.getElementById('editResModal').classList.remove('hidden');
        document.getElementById('editResModal').classList.add('flex');
    }

    function closeEditModal() {
        document.getElementById('editResModal').classList.add('hidden');
        document.getElementById('editResModal').classList.remove('flex');
    }
</script>

<?php include '../../includes/footer.php'; ?>
