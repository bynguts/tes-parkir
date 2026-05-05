<?php
/**
 * reserve.php — Public Reservation Page
 * High-fidelity booking interface for customers
 * Supports Guest-First (Hybrid) flow
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/connection.php';
require_once 'includes/functions.php';
$current_page = 'reserve';

// Fetch rates for display
$rates_stmt = $pdo->query("SELECT * FROM parking_rate");
$rates = [];
while ($r = $rates_stmt->fetch()) {
    $rates[$r['vehicle_type']] = $r;
}

// Check if logged in for pre-filling
$is_logged_in = !empty($_SESSION['customer_id']);
$customer_data = null;
if ($is_logged_in) {
    // Fetch customer details and their first registered vehicle
    $stmt = $pdo->prepare("SELECT c.full_name, c.email, c.phone, v.plate_number, v.vehicle_type 
                          FROM customers c 
                          LEFT JOIN vehicle v ON c.id = v.customer_id 
                          WHERE c.id = ? 
                          LIMIT 1");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer_data = $stmt->fetch();
}

$pre_v_type = $customer_data['vehicle_type'] ?? 'car';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve Your Spot — Parkhere</title>
    
    <!-- Fonts & Icons (Keep here if specific version needed) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>


    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .glass-panel {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
        }
        .form-input {
            background: var(--bg-page);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            color: var(--text-primary);
        }
        .form-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        .btn-primary {
            background: var(--brand);
            color: #ffffff !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
            transition: all 0.3s ease;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            filter: brightness(1.1);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        /* Flatpickr Custom */
        .flatpickr-calendar {
            background: var(--surface) !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
            border-radius: 1.5rem !important;
        }
        .flatpickr-day.selected {
            background: var(--brand) !important;
            border-color: var(--brand) !important;
        }
        .flatpickr-time {
            border-top: 1px solid var(--border-color) !important;
        }
    </style>
</head>
<body class="flex flex-col">
    <?php include 'includes/navbar.php'; ?>

    <main class="flex-grow flex items-center justify-center p-4 py-6 md:py-8">
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 items-center">
            
            <!-- Left Info Column -->
            <div class="space-y-6 md:space-y-8">
                <div>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-manrope font-800 mb-4 leading-tight text-primary">
                        Reserve Your <br>
                        <span class="text-brand">Premium Spot</span>
                    </h1>
                    <p class="text-sm md:text-base text-secondary font-medium leading-relaxed">
                        Experience the ultimate convenience. Booking ahead ensures your spot is waiting for you, even during peak hours.
                    </p>
                </div>

                <!-- Venue Info Card -->
                <div class="glass-panel p-6 rounded-[2rem] space-y-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                            <i class="fa-solid fa-location-dot text-lg"></i>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-secondary uppercase tracking-widest">Location</div>
                            <div class="text-sm font-bold text-primary">Mall Lippo Cikarang</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-color">
                        <div>
                            <div class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">Car Rate</div>
                            <div class="text-base font-manrope font-800 text-primary"><?= fmt_idr($rates['car']['first_hour_rate']) ?><span class="text-[10px] text-secondary font-medium ml-1">/ hr</span></div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">Motor Rate</div>
                            <div class="text-base font-manrope font-800 text-primary"><?= fmt_idr($rates['motorcycle']['first_hour_rate']) ?><span class="text-[10px] text-secondary font-medium ml-1">/ hr</span></div>
                        </div>
                    </div>
                </div>

                <!-- Benefits List -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-sm font-medium text-secondary">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>No waiting in queue</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm font-medium text-secondary">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>Secure Reservation Zones</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm font-medium text-secondary">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>Digital Entry Code</span>
                    </div>
                </div>
            </div>

            <!-- Right Form Column -->
            <div class="glass-panel p-6 md:p-8 rounded-[2rem] shadow-xl relative w-full max-w-md mx-auto">
                <!-- Progress Stepper -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div id="step-1-dot" class="w-8 h-8 rounded-lg bg-brand text-white flex items-center justify-center text-sm font-bold shadow-lg shadow-brand/20 transition-all">1</div>
                        <span class="text-[11px] font-bold text-primary uppercase tracking-widest hidden sm:block">Details</span>
                    </div>
                    <div class="flex-grow h-px bg-border-color"></div>
                    <div class="flex items-center gap-3">
                        <div id="step-2-dot" class="w-8 h-8 rounded-lg bg-surface border-2 border-border-color text-secondary flex items-center justify-center text-sm font-bold transition-all">2</div>
                        <span class="text-[11px] font-bold text-secondary uppercase tracking-widest hidden sm:block">Select Spot</span>
                    </div>
                </div>

                <form id="reservation-form" class="space-y-4">
                    <!-- STEP 1: PERSONAL & VEHICLE DETAILS -->
                    <div id="step-1-content" class="space-y-4 transition-all duration-300">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">Vehicle</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="setVehicle('car')" class="v-type-btn p-2 rounded-xl border-2 border-color bg-surface flex flex-col items-center justify-center gap-1 transition-all group" id="btn-car">
                                        <i class="fa-solid fa-car text-lg text-secondary group-hover:text-brand transition-colors"></i>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-secondary group-hover:text-primary">Car</span>
                                    </button>
                                    <button type="button" onclick="setVehicle('motorcycle')" class="v-type-btn p-2 rounded-xl border-2 border-color bg-surface flex flex-col items-center justify-center gap-1 transition-all group" id="btn-motor">
                                        <i class="fa-solid fa-motorcycle text-lg text-secondary group-hover:text-brand transition-colors"></i>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-secondary group-hover:text-primary">Motor</span>
                                    </button>
                                </div>
                                <input type="hidden" name="vehicle_type" id="vehicle_type" value="<?= htmlspecialchars($pre_v_type) ?>">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">License Plate</label>
                                <input type="text" name="plate_number" required 
                                       pattern="^[A-Za-z]{1,3}\s*\d{1,4}\s*[A-Za-z]{0,3}\s*$"
                                       title="Format: A 1234 BCD (Spaces optional)"
                                       placeholder="B 1234 XYZ" 
                                       value="<?= htmlspecialchars($customer_data['plate_number'] ?? '') ?>"
                                       class="form-input w-full h-12 px-4 rounded-xl text-sm font-bold uppercase tracking-wider mt-[6px]">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">Full Name</label>
                                <input type="text" name="client_name" required minlength="3"
                                       pattern="^[A-Za-z\s\']+$"
                                       title="Letters and spaces only, minimum 3 characters"
                                       placeholder="John Doe" 
                                       value="<?= htmlspecialchars($customer_data['full_name'] ?? '') ?>"
                                       class="form-input w-full h-12 px-4 rounded-xl text-sm font-semibold">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">Phone Number</label>
                                <input type="tel" name="client_phone" required 
                                       pattern="^(08|\+628)\d{8,12}$"
                                       title="Must be an Indonesian number starting with 08 or +628"
                                       placeholder="0812..." 
                                       value="<?= htmlspecialchars($customer_data['phone'] ?? '') ?>"
                                       class="form-input w-full h-12 px-4 rounded-xl text-sm font-semibold">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">Entry Time</label>
                                <input type="text" id="entry_time" name="entry_time" required class="form-input w-full h-12 px-4 rounded-xl text-sm font-medium cursor-pointer" placeholder="Select Time">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest px-1">Exit Time</label>
                                <input type="text" id="exit_time" name="exit_time" required class="form-input w-full h-12 px-4 rounded-xl text-sm font-medium cursor-pointer" placeholder="Select Time">
                            </div>
                        </div>

                        <!-- Summary Card -->
                        <div id="booking-summary" class="hidden bg-brand/5 border border-brand/20 rounded-xl p-4 animate-in fade-in slide-in-from-bottom-4">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-medium text-secondary">Estimated Duration</span>
                                <span class="text-xs font-bold text-primary" id="summary-duration">-</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-medium text-secondary">Rate Preview</span>
                                <span class="text-sm font-bold text-brand" id="summary-rate">-</span>
                            </div>
                        </div>

                        <button type="button" onclick="goToStep2()" id="next-btn" class="btn-primary w-full h-12 rounded-xl text-sm font-bold flex items-center justify-center gap-2 group mt-2">
                            <span>Continue to Select Spot</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>

                    <!-- STEP 2: SPOT SELECTION -->
                    <div id="step-2-content" class="hidden space-y-6 animate-in fade-in slide-in-from-right-4">
                        <div class="text-center space-y-1">
                            <h3 class="text-xl font-manrope font-800 text-primary uppercase tracking-tight">Select Your Spot</h3>
                            <p class="text-xs text-secondary font-medium">Reservation Zone (Priority Allocation)</p>
                        </div>

                        <!-- Spot Picker Legend -->
                        <div class="flex justify-center gap-4 pb-1">
                            <div class="flex items-center gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-sm bg-surface border border-border-color"></div>
                                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest">Available</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-sm bg-brand shadow-lg shadow-brand/20"></div>
                                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest">Selected</span>
                            </div>
                            <div class="flex items-center gap-1.5 opacity-50">
                                <div class="w-2.5 h-2.5 rounded-sm bg-secondary/20 border border-transparent"></div>
                                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest">Taken</span>
                            </div>
                        </div>

                        <!-- The Grid -->
                        <div class="bg-surface-alt/50 border border-border-color rounded-2xl p-6">
                            <div id="spot-grid" class="grid grid-cols-5 gap-3 max-w-[350px] mx-auto">
                                <!-- Spots will be injected here -->
                                <div class="col-span-5 flex flex-col items-center py-8 opacity-40">
                                    <i class="fa-solid fa-circle-notch animate-spin text-2xl mb-3"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-widest">Loading available spots...</p>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="slot_id" id="selected_slot_id">

                        <div class="flex flex-col gap-2 mt-2">
                            <button type="submit" id="submit-btn" disabled class="btn-primary w-full h-12 rounded-xl text-sm font-bold flex items-center justify-center gap-2 group opacity-50 cursor-not-allowed">
                                <span>Confirm Reservation</span>
                                <i class="fa-solid fa-check group-hover:scale-110 transition-transform"></i>
                            </button>
                            <button type="button" onclick="goToStep1()" class="text-[11px] font-bold text-secondary hover:text-primary transition-colors text-center mt-1">Back to Details</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Modal Overlay -->
        <div id="success-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md"></div>
            <div class="relative w-full max-w-[340px] bg-surface border border-color rounded-3xl overflow-hidden shadow-2xl animate-in zoom-in-95 duration-300">
                <!-- Receipt Top Decor -->
                <div class="bg-brand p-5 text-center">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fa-solid fa-check text-2xl text-white"></i>
                    </div>
                    <h3 class="text-lg font-manrope font-800 text-white">Reservation Confirmed!</h3>
                    <p class="text-white/80 text-xs mt-0.5">Your spot is secured.</p>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Reservation Code -->
                    <div class="text-center">
                        <div class="text-[9px] font-bold text-secondary uppercase tracking-[0.2em] mb-1">Unique Access Code</div>
                        <div class="text-2xl font-manrope font-800 text-primary tracking-[0.1em]" id="res-code">XXXXXX</div>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-color">
                        <div class="space-y-0.5">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest">Parking Slot</div>
                            <div class="text-xs font-bold text-primary" id="receipt-slot">-</div>
                        </div>
                        <div class="space-y-0.5 text-right">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest">Plate Number</div>
                            <div class="text-xs font-bold text-primary" id="receipt-vehicle">-</div>
                        </div>
                        <div class="space-y-0.5">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest">Entry</div>
                            <div class="text-xs font-bold text-primary" id="receipt-entry">-</div>
                        </div>
                        <div class="space-y-0.5 text-right">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest">Exit</div>
                            <div class="text-xs font-bold text-primary" id="receipt-exit">-</div>
                        </div>
                    </div>

                    <div class="pt-3 space-y-3">
                        <div class="p-3 rounded-xl bg-surface-alt/50 border border-color space-y-0.5">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest">Booked For</div>
                            <div class="text-xs font-bold text-primary" id="receipt-name">-</div>
                            <div class="text-[10px] text-secondary" id="receipt-phone">-</div>
                        </div>

                        <?php if (!$is_logged_in): ?>
                        <!-- Quick Account Promotion -->
                        <div class="relative mt-1">
                            <div class="p-4 rounded-2xl bg-brand/5 border border-brand/20 relative overflow-hidden group transition-all hover:bg-brand/10">
                                <div class="relative z-10 flex flex-col items-center text-center">
                                    <h4 class="text-xs font-bold text-primary mb-1">Want to book faster?</h4>
                                    <p class="text-[9px] text-secondary mb-2 leading-relaxed max-w-[200px]">Save your vehicle details now for next time.</p>
                                    <a href="auth.php?action=register" id="cta-register-link" class="w-full py-2 rounded-lg bg-brand text-white text-[10px] font-bold hover:brightness-110 transition-all shadow-md shadow-brand/20">
                                        Save & Create Account <i class="fa-solid fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="flex flex-col gap-2 w-full mt-1">
                            <button onclick="window.print()" class="w-full h-10 rounded-lg bg-surface-alt border border-color hover:bg-surface text-primary text-[11px] font-bold transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-print text-brand"></i>
                                Print Receipt
                            </button>
                            <button onclick="location.reload()" class="text-[10px] font-bold text-secondary hover:text-primary transition-colors">Make Another Booking</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Vehicle selection logic
        function setVehicle(type) {
            document.getElementById('vehicle_type').value = type;
            document.querySelectorAll('.v-type-btn').forEach(btn => btn.classList.remove('active', 'border-brand', 'bg-brand/5'));
            document.querySelectorAll('.v-type-btn i').forEach(i => i.classList.replace('text-brand', 'text-secondary'));
            
            const activeBtn = document.getElementById(type === 'car' ? 'btn-car' : 'btn-motor');
            activeBtn.classList.add('active', 'border-brand', 'bg-brand/5');
            activeBtn.querySelector('i').classList.replace('text-secondary', 'text-brand');
            
            updateSummary();
        }

        // Flatpickr Premium UI Helpers
        function makeSelect(options, currentVal, onChange, extraStyle) {
            var sel = document.createElement('select');
            sel.className = 'bg-surface text-primary rounded-lg px-2 py-1 mx-1 border border-color outline-none focus:border-brand text-sm';
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
                    });
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
                    });
                    mw.parentNode.insertBefore(minSel, mw);
                }
            }
        }

        function addButtons(instance) {
            var d = document.createElement('div');
            d.className = 'flex items-center justify-between p-2 border-t border-color';
            ['Clear','OK'].forEach(function(lbl) {
                var b = document.createElement('button');
                b.type = 'button'; b.innerText = lbl;
                b.className = lbl === 'OK' 
                    ? 'bg-brand text-white text-xs font-bold px-4 py-2 rounded-lg' 
                    : 'text-secondary text-xs font-bold px-4 py-2 hover:text-primary';
                b.onclick = function() {
                    if (lbl === 'Clear') instance.clear();
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

        // Initialize Flatpickr for Entry
        const fpEntry = flatpickr("#entry_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            monthSelectorType: "dropdown",
            onReady: onReady,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    fpExit.set("minDate", selectedDates[0]);
                }
                updateSummary();
            }
        });

        // Initialize Flatpickr for Exit
        const fpExit = flatpickr("#exit_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            monthSelectorType: "dropdown",
            onReady: onReady,
            onChange: function(selectedDates, dateStr, instance) {
                updateSummary();
            }
        });

        const rates = <?= json_encode($rates) ?>;

        function updateSummary() {
            const entry = fpEntry.selectedDates[0];
            const exit = fpExit.selectedDates[0];
            const summary = document.getElementById('booking-summary');

            if (entry && exit && exit > entry) {
                summary.classList.remove('hidden');
                const diffMs = exit - entry;
                const diffHrs = Math.ceil(diffMs / (1000 * 60 * 60));
                const vType = document.getElementById('vehicle_type').value;
                const rate = rates[vType];
                
                document.getElementById('summary-duration').textContent = diffHrs + ' Hours';
                document.getElementById('summary-rate').textContent = 'Rp ' + Number(rate.first_hour_rate).toLocaleString();
            } else {
                summary.classList.add('hidden');
            }
        }

        // Step Navigation
        function goToStep2() {
            const entry = fpEntry.selectedDates[0];
            const exit = fpExit.selectedDates[0];
            const plate = document.querySelector('[name="plate_number"]').value.trim();
            const name = document.querySelector('[name="client_name"]').value.trim();
            const phone = document.querySelector('[name="client_phone"]').value.trim();

            if (!entry || !exit || !plate || !name || !phone) {
                alert('Please fill all details before selecting a spot.');
                return;
            }

            if (exit <= entry) {
                alert('Exit time must be after entry time.');
                return;
            }

            // Update Stepper UI
            document.getElementById('step-1-dot').classList.replace('bg-brand', 'bg-emerald-500');
            document.getElementById('step-1-dot').innerHTML = '<i class="fa-solid fa-check"></i>';
            document.getElementById('step-2-dot').classList.replace('bg-surface', 'bg-brand');
            document.getElementById('step-2-dot').classList.replace('border-border-color', 'border-brand');
            document.getElementById('step-2-dot').classList.replace('text-secondary', 'text-white');

            // Switch Content
            document.getElementById('step-1-content').classList.add('hidden');
            document.getElementById('step-2-content').classList.remove('hidden');

            fetchAvailableSpots();
        }

        function goToStep1() {
            document.getElementById('step-1-dot').classList.replace('bg-emerald-500', 'bg-brand');
            document.getElementById('step-1-dot').innerHTML = '1';
            document.getElementById('step-2-dot').classList.replace('bg-brand', 'bg-surface');
            document.getElementById('step-2-dot').classList.replace('border-brand', 'border-border-color');
            document.getElementById('step-2-dot').classList.replace('text-white', 'text-secondary');

            document.getElementById('step-1-content').classList.remove('hidden');
            document.getElementById('step-2-content').classList.add('hidden');
        }

        async function fetchAvailableSpots() {
            const grid = document.getElementById('spot-grid');
            const vType = document.getElementById('vehicle_type').value;
            const from = fpEntry.formatDate(fpEntry.selectedDates[0], "Y-m-d H:i");
            const until = fpExit.formatDate(fpExit.selectedDates[0], "Y-m-d H:i");

            grid.innerHTML = `
                <div class="col-span-5 flex flex-col items-center py-10 opacity-40">
                    <i class="fa-solid fa-circle-notch animate-spin text-2xl mb-3"></i>
                    <p class="text-xs font-bold uppercase tracking-widest">Finding Best Spots...</p>
                </div>
            `;

            try {
                const response = await fetch(`api/get_slots_availability.php?from=${from}&until=${until}&vehicle_type=${vType}&floor_id=1`);
                const result = await response.json();

                if (result.success) {
                    grid.innerHTML = '';
                    // We only care about #RES 1 to #RES 10
                    const resSlots = result.slots.filter(s => s.slot_number.includes('#RES'));
                    
                    if (resSlots.length === 0) {
                        grid.innerHTML = '<p class="col-span-5 text-center text-xs font-bold text-secondary uppercase py-10">No priority spots available for this time.</p>';
                        return;
                    }

                    resSlots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = `spot-btn flex flex-col items-center justify-center gap-1 p-3 rounded-2xl border-2 transition-all ${slot.is_available ? 'bg-surface border-border-color hover:border-brand hover:scale-105' : 'bg-secondary/10 border-transparent opacity-40 cursor-not-allowed'}`;
                        btn.disabled = !slot.is_available;
                        
                        btn.innerHTML = `
                            <span class="text-[10px] font-black uppercase text-secondary group-hover:text-primary">${slot.slot_number.replace('#RES', 'RES ')}</span>
                            <i class="fa-solid fa-square-parking text-xl ${slot.is_available ? 'text-secondary' : 'text-secondary/30'}"></i>
                        `;

                        if (slot.is_available) {
                            btn.onclick = () => selectSlot(slot.slot_id, btn);
                        }

                        grid.appendChild(btn);
                    });
                }
            } catch (err) {
                grid.innerHTML = '<p class="col-span-5 text-center text-red-500 text-xs font-bold uppercase py-10">Failed to load spots.</p>';
            }
        }

        function selectSlot(id, btn) {
            document.getElementById('selected_slot_id').value = id;
            document.querySelectorAll('.spot-btn').forEach(b => {
                b.classList.remove('border-brand', 'bg-brand/10', 'scale-105');
                const span = b.querySelector('span');
                const i = b.querySelector('i');
                if (span) { span.classList.remove('text-brand'); span.classList.add('text-secondary'); }
                if (i) { i.classList.remove('text-brand'); i.classList.add('text-secondary'); }
            });
            
            btn.classList.add('border-brand', 'bg-brand/10', 'scale-105');
            const span = btn.querySelector('span');
            const i = btn.querySelector('i');
            if (span) { span.classList.remove('text-secondary'); span.classList.add('text-brand'); }
            if (i) { i.classList.remove('text-secondary'); i.classList.add('text-brand'); }

            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        // Form submission
        document.getElementById('reservation-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            const slotId = document.getElementById('selected_slot_id').value;

            if (!slotId) {
                alert('Please select a parking spot first.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> Confirming...';

            const formData = new FormData(e.target);
            formData.append('from', fpEntry.formatDate(fpEntry.selectedDates[0], "Y-m-d H:i"));
            formData.append('until', fpExit.formatDate(fpExit.selectedDates[0], "Y-m-d H:i"));

            try {
                const response = await fetch('api/public_reserve.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    // Populate Receipt
                    document.getElementById('res-code').textContent = result.reservation_code;
                    document.getElementById('receipt-slot').textContent = result.slot_number.replace('#RES', 'RES ');
                    document.getElementById('receipt-vehicle').textContent = result.plate_number;
                    document.getElementById('receipt-name').textContent = result.client_name;
                    document.getElementById('receipt-phone').textContent = result.client_phone;
                    
                    const options = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
                    document.getElementById('receipt-entry').textContent = new Date(result.from.replace(/-/g, "/")).toLocaleString('en-GB', options);
                    document.getElementById('receipt-exit').textContent = new Date(result.until.replace(/-/g, "/")).toLocaleString('en-GB', options);

                    // Update registration link for guests
                    const ctaLink = document.getElementById('cta-register-link');
                    if (ctaLink) {
                        const params = new URLSearchParams({
                            action: 'register',
                            fullname: result.client_name,
                            phone: result.client_phone,
                            plate: result.plate_number,
                            redirect: 'home'
                        });
                        ctaLink.href = `auth.php?${params.toString()}`;
                    }

                    document.getElementById('step-2-content').classList.add('hidden');
                    document.getElementById('success-overlay').classList.remove('hidden');
                } else {
                    alert(result.error || 'Reservation failed.');
                    btn.disabled = false;
                    btn.innerHTML = '<span>Confirm Reservation</span> <i class="fa-solid fa-check"></i>';
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred.');
                btn.disabled = false;
                btn.innerHTML = '<span>Confirm Reservation</span> <i class="fa-solid fa-check"></i>';
            }
        });

        // Initialize with pre-selected vehicle
        setVehicle('<?= $pre_v_type ?>');
    </script>

</body>
</html>
