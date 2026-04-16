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
        $date_until = $_POST['reserved_until'] ?? '';

        if (!$plate || !in_array($vtype, ['car', 'motorcycle']) || !$date_from || !$date_until) {
            $error = 'Semua field wajib diisi.';
        } elseif (strtotime($date_until) <= strtotime($date_from)) {
            $error = 'Waktu selesai harus setelah waktu mulai.';
        } elseif (strtotime($date_from) < time() - 300) {
            $error = 'Waktu mulai tidak boleh di masa lalu.';
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
                $error = 'Tidak ada slot tersedia untuk periode tersebut.';
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

                $msg = "Reservasi berhasil! Kode: <strong class='font-mono'>{$code}</strong>";
            }
        }
    }

    if ($action === 'cancel') {
        $res_id = (int)($_POST['reservation_id'] ?? 0);
        $pdo->prepare("UPDATE reservation SET status='cancelled' WHERE reservation_id=? AND status IN ('pending','confirmed')")
            ->execute([$res_id]);
        $msg = 'Reservasi berhasil dibatalkan.';
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

$page_title = 'Manajemen Reservasi';
include '../../includes/header.php';
?>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* =====================================
   FLATPICKR DARK THEME — RESERVATION
   ===================================== */
.flatpickr-calendar {
    font-family: 'Inter', sans-serif !important;
    background: #0f172a !important;
    border-radius: 20px !important;
    border: 1px solid #334155 !important;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7) !important;
    padding: 12px !important;
    width: 388px !important;
    box-sizing: border-box !important;
}

/* Day grid sizing */
.flatpickr-innerContainer, .flatpickr-rContainer { width: 100% !important; }
.flatpickr-days { width: 364px !important; }
.dayContainer { width: 364px !important; min-width: 364px !important; max-width: 364px !important; }

/* Remove default max-width so cells fill the row equally (364px / 7 = 52px each) */
.flatpickr-day { max-width: 52px !important; width: 14.2857% !important; }

.flatpickr-day.selected  { background: #3b82f6 !important; border-color: #3b82f6 !important; font-weight: 700; }
.flatpickr-day.today     { border-color: #3b82f6 !important; }
.flatpickr-day:hover     { background: #1e293b !important; }
.flatpickr-day.flatpickr-disabled { color: #334155 !important; pointer-events: none; }

/* Show prev/next month day numbers — subtle so they don't confuse current month */
.flatpickr-day.nextMonthDay,
.flatpickr-day.prevMonthDay { color: #475569 !important; font-weight: 400 !important; }
.flatpickr-day.nextMonthDay:hover,
.flatpickr-day.prevMonthDay:hover { background: #1e293b !important; color: #94a3b8 !important; }

/* Global text */
.flatpickr-months, .flatpickr-weekday, .flatpickr-day {
    color: #ffffff !important; fill: #ffffff !important; font-weight: 600 !important;
}

/* Month header row */
.flatpickr-months { padding: 6px 0 4px !important; align-items: center !important; }
.flatpickr-current-month {
    display: flex !important; align-items: center !important;
    justify-content: center !important; gap: 10px !important;
    padding: 0 !important; font-size: 100% !important;
    position: static !important; width: auto !important;
}

/* Hide built-in month/year controls (replaced by JS selects) */
.flatpickr-monthDropdown-months, .numInputWrapper { display: none !important; }
.flatpickr-prev-month, .flatpickr-next-month      { display: none !important; }

/* Weekday header */
.flatpickr-weekdays                { background: transparent !important; }
.flatpickr-weekdaycontainer        { width: 100% !important; }
.flatpickr-weekday { color: #64748b !important; font-size: 11px !important; font-weight: 800 !important; text-transform: uppercase; }

/* Time picker row */
.flatpickr-time {
    border-top: 1px solid #334155 !important;
    background: #0f172a !important;
    height: auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    padding: 10px 0 !important;
    margin-top: 8px !important;
}
/* Hide built-in time inputs (replaced by JS selects) */
.flatpickr-time .numInputWrapper, .flatpickr-time input { display: none !important; }
.flatpickr-time-separator { color: #fff !important; font-size: 24px !important; font-weight: 800 !important; }

/* Injected custom selects style via JS */
.fp-inject {
    background: #1e293b; color: #fff; font-weight: 700; font-size: 15px;
    padding: 6px 10px; border-radius: 10px; border: 1px solid #475569;
    cursor: pointer; font-family: 'Inter', sans-serif; outline: none;
}

/* Custom bottom bar */
.flatpickr-custom-btn {
    display: flex; justify-content: space-between;
    padding: 14px 8px 4px; border-top: 1px solid #334155; margin-top: 8px;
}
.flatpickr-custom-btn button {
    background: transparent; border: none; color: #64748b;
    font-size: 11px; cursor: pointer; font-family: 'Inter', sans-serif;
    font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; transition: color .15s;
}
.flatpickr-custom-btn button:hover { color: #fff; }
.flatpickr-custom-btn button.ok   { color: #22c55e; }
</style>

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <header class="flex justify-between items-center px-8 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Manajemen Reservasi</h1>
            <p class="text-slate-400 text-xs font-inter mt-0.5">Kelola pre-booking dan alokasi prioritas slot parkir.</p>
        </div>
        <span class="bg-slate-100 text-slate-600 text-xs font-bold font-inter uppercase tracking-widest px-4 py-2 rounded-full">
            <?= count($reservations) ?> Aktif
        </span>
    </header>

    <div class="p-8 max-w-[1440px] mx-auto">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-xl px-5 py-4 mb-6">
            <span class="material-symbols-outlined text-emerald-600">check_circle</span>
            <p class="text-emerald-700 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-6">
            <span class="material-symbols-outlined text-red-600">error</span>
            <p class="text-red-700 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-[420px_1fr] gap-6">

            <!-- CREATE FORM -->
            <div class="bg-white rounded-2xl shadow-sm self-start" style="overflow: visible;">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-400 text-xl">calendar_add_on</span>
                    <h2 class="font-manrope font-bold text-lg text-slate-900">Buat Reservasi Baru</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="vehicle_type" id="vtype_hidden" value="car">

                        <!-- Vehicle type selector -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Tipe Kendaraan</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="btnCar" onclick="setType('car')"
                                        class="vtype-btn flex flex-col items-center gap-1.5 py-4 rounded-xl border-2 border-slate-900 bg-slate-900 text-white transition-all">
                                    <span class="material-symbols-outlined text-2xl">directions_car</span>
                                    <span class="text-xs font-bold font-inter">Mobil</span>
                                </button>
                                <button type="button" id="btnMoto" onclick="setType('motorcycle')"
                                        class="vtype-btn flex flex-col items-center gap-1.5 py-4 rounded-xl border-2 border-slate-200 bg-slate-50 text-slate-400 transition-all">
                                    <span class="material-symbols-outlined text-2xl">two_wheeler</span>
                                    <span class="text-xs font-bold font-inter">Motor</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Plat Nomor <span class="text-red-500">*</span></label>
                            <input type="text" name="plate_number"
                                   class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-bold font-manrope text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 text-center uppercase tracking-widest transition-all"
                                   placeholder="B 1234 AB" required oninput="this.value=this.value.toUpperCase()">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Nama Pemilik</label>
                                <input type="text" name="owner_name" placeholder="Opsional"
                                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">No. Telepon</label>
                                <input type="tel" name="owner_phone" placeholder="08xxxx"
                                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Waktu Mulai <span class="text-red-500">*</span></label>
                                <input type="text" name="reserved_from" id="from_dt" required placeholder="Pilih waktu..."
                                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Waktu Selesai <span class="text-red-500">*</span></label>
                                <input type="text" name="reserved_until" id="until_dt" required placeholder="Pilih waktu..."
                                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all cursor-pointer">
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3.5 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-base">event_available</span>
                            Proses Reservasi
                        </button>
                    </form>
                </div>
            </div>

            <!-- ACTIVE RESERVATIONS -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-400 text-xl">calendar_month</span>
                    <h2 class="font-manrope font-bold text-lg text-slate-900">Antrean Reservasi Aktif</h2>
                </div>
                <?php if (empty($reservations)): ?>
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <span class="material-symbols-outlined text-6xl text-slate-200 block mb-4">event_busy</span>
                    <p class="text-slate-400 text-sm font-inter">Belum ada reservasi aktif terjadwal.</p>
                </div>
                <?php else: ?>
                <div class="overflow-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Kode Validasi</th>
                                <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Klien / Kendaraan</th>
                                <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Alokasi Slot</th>
                                <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Periode</th>
                                <th class="text-right px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($reservations as $r): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <code class="font-mono text-sm text-slate-800 bg-slate-100 px-3 py-1.5 rounded-lg font-bold"><?= htmlspecialchars($r['reservation_code']) ?></code>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl <?= $r['vehicle_type'] === 'car' ? 'bg-blue-50' : 'bg-emerald-50' ?> flex items-center justify-center">
                                            <span class="material-symbols-outlined text-xl <?= $r['vehicle_type'] === 'car' ? 'text-blue-600' : 'text-emerald-600' ?>"><?= $r['vehicle_type'] === 'car' ? 'directions_car' : 'two_wheeler' ?></span>
                                        </div>
                                        <div>
                                            <div class="font-inter font-bold text-sm text-slate-800"><?= htmlspecialchars($r['plate_number']) ?></div>
                                            <div class="text-slate-400 text-xs font-inter"><?= htmlspecialchars($r['owner_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-manrope font-bold text-slate-900"><?= htmlspecialchars($r['slot_number']) ?></span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-1.5 text-slate-700 text-xs font-inter">
                                        <span class="material-symbols-outlined text-blue-400 text-sm">schedule</span>
                                        <?= date('d M H:i', strtotime($r['reserved_from'])) ?>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-400 text-xs font-inter mt-0.5">
                                        <span class="material-symbols-outlined text-slate-300 text-sm">arrow_forward</span>
                                        s/d <?= date('H:i', strtotime($r['reserved_until'])) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan reservasi ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                                        <button class="flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold font-inter px-4 py-2 rounded-xl transition-all ml-auto">
                                            <span class="material-symbols-outlined text-sm">close</span>
                                            Cancel
                                        </button>
                                    </form>
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
</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
function setType(t) {
    document.getElementById('vtype_hidden').value = t;
    var car  = document.getElementById('btnCar');
    var moto = document.getElementById('btnMoto');
    car.className  = 'vtype-btn flex flex-col items-center gap-1.5 py-4 rounded-xl border-2 transition-all ' + (t === 'car'        ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-400');
    moto.className = 'vtype-btn flex flex-col items-center gap-1.5 py-4 rounded-xl border-2 transition-all ' + (t === 'motorcycle' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-400');
}

document.addEventListener('DOMContentLoaded', function () {

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

    var untilPicker = flatpickr("#until_dt", {
        enableTime: true, dateFormat: "Y-m-d\\TH:i",
        minDate: minDate, time_24hr: true, minuteIncrement: 15,
        onReady: onReady
    });

    var fromPicker = flatpickr("#from_dt", {
        enableTime: true, dateFormat: "Y-m-d\\TH:i",
        minDate: minDate, time_24hr: true, minuteIncrement: 15,
        onReady: onReady,
        onChange: function(selectedDates) {
            if (selectedDates[0]) {
                var m = new Date(selectedDates[0]);
                m.setHours(m.getHours() + 1);
                untilPicker.set('minDate', m);
                if (!untilPicker.selectedDates.length || untilPicker.selectedDates[0] < m) {
                    untilPicker.setDate(m);
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
