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

    if ($action === 'create') {
        $plate      = strtoupper(trim($_POST['plate_number'] ?? ''));
        $vtype      = $_POST['vehicle_type'] ?? '';
        $owner      = trim($_POST['owner_name'] ?? 'Guest');
        $phone      = trim($_POST['owner_phone'] ?? '');
        $date_from  = $_POST['reserved_from'] ?? '';
        // Set a default indefinite end time (e.g., 30 days buffer) to avoid constraining the user's return
        $date_until = $date_from ? date('Y-m-d H:i:s', strtotime($date_from . ' + 30 days')) : '';

        if (!$plate || !in_array($vtype, ['car', 'motorcycle']) || !$date_from) {
            $error = 'All fields are required.';
        } elseif (strtotime($date_from) < time() - 300) {
            $error = 'Start time cannot be in the past.';
        } else {
            $stmt = $pdo->prepare("
                SELECT ps.slot_id FROM parking_slot ps
                JOIN floor f ON ps.floor_id = f.floor_id
                WHERE ps.slot_type = ?
                  AND ps.status = 'available'
                  AND ps.slot_id NOT IN (
                    SELECT slot_id FROM reservation
                    WHERE status IN ('pending','confirmed')
                      AND NOT (reserved_until <= ? OR reserved_from >= ?)
                  )
                ORDER BY f.floor_code, ps.slot_number LIMIT 1
            ");
            $stmt->execute([$vtype, $date_from, $date_until]);
            $slot = $stmt->fetch();

            if (!$slot) {
                $error = 'No slots available for this period.';
            } else {
                $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name, owner_phone)
                                VALUES (?,?,?,?)
                                ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), owner_name=VALUES(owner_name), owner_phone=VALUES(owner_phone)")
                    ->execute([$plate, $vtype, $owner ?: 'Guest', $phone ?: null]);

                $vid  = $pdo->query("SELECT vehicle_id FROM vehicle WHERE plate_number='".addslashes($plate)."'")->fetchColumn();
                $code = generate_reservation_code($pdo);

                $pdo->prepare("INSERT INTO reservation (vehicle_id, slot_id, reservation_code, reserved_from, reserved_until, status)
                                VALUES (?,?,?,?,?,'confirmed')")
                    ->execute([$vid, $slot['slot_id'], $code, $date_from, $date_until]);

                $msg = "Reservation successful! Code: <strong class='font-mono'>{$code}</strong>";
            }
        }
    }

    if ($action === 'cancel') {
        $res_id = (int)($_POST['reservation_id'] ?? 0);
        $pdo->prepare("UPDATE reservation SET status='cancelled' WHERE reservation_id=? AND status IN ('pending','confirmed')")
            ->execute([$res_id]);
        $msg = 'Reservation successfully cancelled.';
    }
}

$reservations = $pdo->query("
    SELECT r.reservation_id, r.reservation_code, r.reserved_from, r.reserved_until, r.status,
           v.plate_number, v.vehicle_type, v.owner_name,
           ps.slot_number, f.floor_code AS floor
    FROM reservation r
    JOIN vehicle v       ON r.vehicle_id  = v.vehicle_id
    JOIN parking_slot ps ON r.slot_id     = ps.slot_id
    JOIN floor f         ON ps.floor_id   = f.floor_id
    WHERE r.status IN ('pending','confirmed')
    ORDER BY r.reserved_from
")->fetchAll();

$min_datetime = date('Y-m-d\TH:i', strtotime('+5 minutes'));

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

<link rel="stylesheet" href="../../assets/css/theme.css">
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* =====================================
   FLATPICKR THEME — INDIGO NIGHT
   ===================================== */
.flatpickr-calendar {
    font-family: 'Inter', sans-serif !important;
    background: var(--surface) !important;
    border-radius: 24px !important;
    border: 1px solid var(--border-color) !important;
    box-shadow: 0 25px 50px -12px var(--shadow-color) !important;
    padding: 16px !important;
    width: 388px !important;
}

.flatpickr-day.selected { background: var(--brand) !important; border-color: var(--brand) !important; }
.flatpickr-day.today { border-color: var(--brand) !important; }
.flatpickr-day:hover { background: var(--surface-alt) !important; }
.flatpickr-day.flatpickr-disabled { opacity: 0.1 !important; pointer-events: none; }

.flatpickr-months, .flatpickr-weekday, .flatpickr-day {
    color: var(--text-primary) !important; fill: var(--text-primary) !important;
}

.flatpickr-weekday { color: var(--text-secondary) !important; opacity: 0.5; font-size: 10px !important; font-weight: 800 !important; text-transform: uppercase; }

.flatpickr-time {
    border-top: 1px solid var(--border-color) !important;
    background: var(--surface) !important;
}

.fp-inject {
    background: var(--surface-alt); color: var(--text-primary); font-weight: 700; font-size: 14px;
    padding: 8px 12px; border-radius: 12px; border: 1px solid var(--border-color);
    cursor: pointer; font-family: 'Inter', sans-serif; outline: none; transition: all 0.2s;
}
.fp-inject:hover { border-color: var(--brand); }

.flatpickr-custom-btn {
    display: flex; justify-content: space-between;
    padding: 16px 8px 4px; border-top: 1px solid var(--border-color); margin-top: 12px;
}
.flatpickr-custom-btn button {
    background: transparent; border: none; color: var(--text-secondary);
    font-size: 11px; cursor: pointer; font-family: 'Inter', sans-serif;
    font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; transition: color .15s;
}
.flatpickr-custom-btn button:hover { color: var(--brand); }
.flatpickr-custom-btn button.ok { color: var(--brand); }

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

<div class="px-6 py-6 h-[calc(100vh-100px)] max-w-[1600px] mx-auto flex flex-col gap-5 overflow-hidden">
    
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

    <div class="grid grid-cols-1 xl:grid-cols-[400px_1fr] gap-5 overflow-hidden flex-1 min-h-0">


            <!-- CREATE FORM -->
            <div class="bento-card flex flex-col overflow-hidden h-full">
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-color shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-calendar-plus text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Create Reservation</h3>
                            <p class="text-[11px] text-tertiary font-inter">Allocate priority parking slots</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 overflow-y-auto no-scrollbar flex-1">
                    <form method="POST" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <?php $vtype = $_POST['vehicle_type'] ?? 'car'; ?>
                        <input type="hidden" name="vehicle_type" id="vtype_hidden" value="<?= htmlspecialchars($vtype) ?>">

                        <!-- Vehicle type selector -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-3">Vehicle Category</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" id="btnCar" onclick="setType('car')"
                                        class="vtype-btn <?= $vtype === 'car' ? 'active' : '' ?> flex flex-col items-center gap-1.5 py-3 rounded-2xl transition-all">
                                    <i class="fa-solid fa-car text-2xl"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Car</span>
                                </button>
                                <button type="button" id="btnMoto" onclick="setType('motorcycle')"
                                        class="vtype-btn <?= $vtype === 'motorcycle' ? 'active' : '' ?> flex flex-col items-center gap-1.5 py-3 rounded-2xl transition-all">
                                    <i class="fa-solid fa-motorcycle text-2xl"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Motorcycle</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">License Plate</label>
                            <input type="text" name="plate_number"
                                   class="modal-input w-full border-2 border-transparent focus:border-brand rounded-2xl px-5 py-3.5 text-sm font-bold font-manrope text-primary focus:outline-none text-center uppercase tracking-widest transition-all placeholder:opacity-20"
                                   placeholder="B 1234 AB" required oninput="this.value=this.value.toUpperCase()">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Owner Name</label>
                                <input type="text" name="owner_name" placeholder="Guest"
                                       class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3 text-[13px] font-bold font-manrope text-primary focus:outline-none transition-all placeholder:opacity-20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Contact</label>
                                <input type="tel" name="owner_phone" placeholder="08xxxx"
                                       class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3 text-[13px] font-bold font-manrope text-primary focus:outline-none transition-all placeholder:opacity-20">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-tertiary font-inter mb-2">Start Time</label>
                            <div class="relative">
                                <input type="text" name="reserved_from" id="from_dt" required placeholder="Select date & time"
                                       class="modal-input w-full border-2 border-transparent focus:border-brand rounded-xl px-4 py-3 text-[12px] font-bold font-manrope text-primary focus:outline-none transition-all cursor-pointer placeholder:opacity-20">
                                <i class="fa-solid fa-clock absolute right-4 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none"></i>
                            </div>
                        </div>

                        <button type="submit"
                                class="btn-primary w-full font-black font-inter text-[10px] uppercase tracking-widest rounded-full py-4 transition-all flex items-center justify-center gap-3">
                            <i class="fa-solid fa-calendar-check text-base"></i>
                            Confirm Reservation
                        </button>
                    </form>
                </div>
            </div>


            <!-- ACTIVE RESERVATIONS -->
            <div class="bento-card flex flex-col overflow-hidden h-full">
                <div class="flex items-center justify-between px-6 py-4 border-b border-color shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-calendar-days text-lg"></i>
                        </div>
                        <div>
                            <h3 class="card-title leading-tight">Active Reservation Queue</h3>
                            <p class="text-[11px] text-tertiary font-inter">Live tracking of <?= count($reservations) ?> allocations</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($reservations)): ?>
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <i class="fa-solid fa-calendar-times text-6xl text-tertiary opacity-10 block mb-4"></i>
                    <p class="text-tertiary text-sm font-inter">No active reservations scheduled.</p>
                </div>
                <?php else: ?>
                <div class="overflow-y-auto no-scrollbar flex-1">
                    <table class="w-full font-inter border-collapse table-fixed">
                        <thead class="sticky top-0 bg-surface z-20">
                            <tr class="border-b border-color">
                                <th class="py-2.5 px-6 w-[18%] text-[10px] font-black uppercase tracking-widest text-tertiary text-left">Validation</th>
                                <th class="py-2.5 px-4 w-[28%] text-[10px] font-black uppercase tracking-widest text-tertiary text-left">Client / Vehicle</th>
                                <th class="py-2.5 px-4 w-[15%] text-[10px] font-black uppercase tracking-widest text-tertiary text-center">Slot</th>
                                <th class="py-2.5 px-4 w-[25%] text-[10px] font-black uppercase tracking-widest text-tertiary text-left">Schedule</th>
                                <th class="py-2.5 px-6 w-[14%] text-[10px] font-black uppercase tracking-widest text-tertiary text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-color">
                            <?php 
                            $res_counter = 1;
                            foreach ($reservations as $r): 
                            ?>
                            <tr class="group hover:bg-surface-alt/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-manrope font-black text-[13px] text-brand tracking-widest"><?= htmlspecialchars($r['reservation_code']) ?></span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-<?= $r['vehicle_type'] === 'car' ? 'car' : 'motorcycle' ?> text-lg"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[13px] font-manrope font-bold text-primary leading-tight"><?= htmlspecialchars($r['plate_number']) ?></span>
                                            <span class="text-[10px] text-tertiary font-medium"><?= htmlspecialchars($r['owner_name']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <?php 
                                            $display_slot = "#RES " . $res_counter++;
                                        ?>
                                        <span class="text-[13px] font-manrope font-black text-primary leading-none mb-1"><?= $display_slot ?></span>
                                        <span class="text-[10px] font-inter text-tertiary leading-none uppercase">VIP AREA</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-clock text-[10px] text-brand"></i>
                                            <span class="text-[12px] font-manrope font-bold text-primary"><?= date('H:i', strtotime($r['reserved_from'])) ?></span>
                                        </div>
                                        <span class="text-[10px] text-tertiary font-medium ml-4"><?= date('d M Y', strtotime($r['reserved_from'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right relative">
                                    <div class="flex justify-end">
                                        <div class="relative action-menu-container">
                                            <button onclick="toggleActionMenu(this, event)" 
                                                    class="w-10 h-10 rounded-xl bg-surface border border-color text-secondary hover:text-brand hover:border-brand/30 hover:shadow-lg transition-all flex items-center justify-center shadow-sm">
                                                <i class="fa-solid fa-ellipsis-vertical text-lg"></i>
                                            </button>
                                            
                                            <!-- Dropdown Menu -->
                                            <div class="action-dropdown hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    function setType(type) {
        const hiddenInput = document.getElementById('vtype_hidden');
        if (hiddenInput) hiddenInput.value = type;
        
        const carBtn = document.getElementById('btnCar');
        const motoBtn = document.getElementById('btnMoto');
        
        if (carBtn) carBtn.classList.toggle('active', type === 'car');
        if (motoBtn) motoBtn.classList.toggle('active', type === 'motorcycle');
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

    // Build a styled <select> element
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

    // Inject month, year, hour, minute dropdowns into the calendar
    function buildDropdowns(instance) {
        var cal = instance.calendarContainer;

        // Remove previously injected selects
        Array.prototype.forEach.call(cal.querySelectorAll('.fp-inject'), function(el) { el.remove(); });

        // == MONTH ==
        var builtinMonth = cal.querySelector('.flatpickr-monthDropdown-months');
        if (builtinMonth) {
            var monthSel = makeSelect(
                MONTHS.map(function(l, i) { return {v: i, l: l}; }),
                instance.currentMonth,
                function(val) { instance.changeMonth(val - instance.currentMonth, true); }
            );
            builtinMonth.parentNode.insertBefore(monthSel, builtinMonth.nextSibling);
        }

        // == YEAR ==
        var yearWrapper = cal.querySelector('.flatpickr-current-month .numInputWrapper');
        if (yearWrapper) {
            var curY = new Date().getFullYear();
            var yearOpts = [];
            for (var y = curY; y <= curY + 10; y++) yearOpts.push({v: y, l: y});
            var yearSel = makeSelect(yearOpts, instance.currentYear, function(val) { instance.changeYear(val); });
            yearWrapper.parentNode.insertBefore(yearSel, yearWrapper.nextSibling);
        }

        // == HOUR ==
        var hourInput = cal.querySelector('input.flatpickr-hour');
        if (hourInput) {
            var hw = hourInput.closest('.numInputWrapper');
            if (hw) {
                var hourOpts = [];
                for (var h = 0; h < 24; h++) hourOpts.push({v: h, l: String(h).padStart(2,'0')});
                var hourSel = makeSelect(
                    hourOpts,
                    parseInt(hourInput.value) || 0,
                    function(val) {
                        hourInput.value = String(val).padStart(2,'0');
                        hourInput.dispatchEvent(new Event('input',  {bubbles: true}));
                        hourInput.dispatchEvent(new Event('change', {bubbles: true}));
                    },
                    'font-size:20px;min-width:76px;text-align:center;'
                );
                hw.parentNode.insertBefore(hourSel, hw);
            }
        }

        // == MINUTE (15-min steps) ==
        var minInput = cal.querySelector('input.flatpickr-minute');
        if (minInput) {
            var mw = minInput.closest('.numInputWrapper');
            if (mw) {
                var curMin = Math.round((parseInt(minInput.value) || 0) / 15) * 15 % 60;
                var minSel = makeSelect(
                    [{v:0,l:'00'},{v:15,l:'15'},{v:30,l:'30'},{v:45,l:'45'}],
                    curMin,
                    function(val) {
                        minInput.value = String(val).padStart(2,'0');
                        minInput.dispatchEvent(new Event('input',  {bubbles: true}));
                        minInput.dispatchEvent(new Event('change', {bubbles: true}));
                    },
                    'font-size:20px;min-width:76px;text-align:center;'
                );
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

    var minDate = new Date("<?= date('Y-m-d\TH:i', strtotime('+5 minutes')) ?>");

    var fromPicker = flatpickr("#from_dt", {
        enableTime: true, dateFormat: "Y-m-d\\TH:i",
        minDate: minDate, time_24hr: true, minuteIncrement: 15,
        onReady: onReady
    });
</script>

<?php include '../../includes/footer.php'; ?>
