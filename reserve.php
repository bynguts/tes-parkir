<?php
/**
 * reserve.php — Public Reservation Page
 * High-fidelity booking interface for customers
 */
require_once 'config/connection.php';
require_once 'includes/functions.php';

// Fetch rates for display
$rates_stmt = $pdo->query("SELECT * FROM parking_rate");
$rates = [];
while ($r = $rates_stmt->fetch()) {
    $rates[$r['vehicle_type']] = $r;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve Your Spot — SmartParking</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['selector', '[data-theme="dark"]'],
            theme: {
                extend: {
                    fontFamily: {
                        'manrope': ['Manrope', 'sans-serif'],
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'brand': 'var(--brand)',
                        'surface': 'var(--surface)',
                        'surface-alt': 'var(--surface-alt)',
                        'bg-page': 'var(--bg-page)',
                        'primary': 'var(--text-primary)',
                        'secondary': 'var(--text-secondary)',
                        'border-color': 'var(--border-color)',
                    },
                }
            }
        }
    </script>

    <!-- Custom Theme -->
    <link rel="stylesheet" href="assets/css/theme.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .glass-panel {
            background: rgba(var(--surface-rgb, 30, 41, 59), 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
        }
        .form-input {
            background: rgba(var(--bg-page-rgb, 15, 23, 42), 0.5);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            color: var(--text-primary);
        }
        .form-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.2);
            outline: none;
        }
        .btn-primary {
            background: var(--brand);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(129, 140, 248, 0.2);
            transition: all 0.3s ease;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            filter: brightness(1.1);
            box-shadow: 0 8px 25px rgba(129, 140, 248, 0.3);
        }
        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }
    </style>
</head>
<body class="flex flex-col">
    <!-- Simple Navbar -->
    <nav class="h-20 flex items-center px-6 md:px-12 border-b border-white/5">
        <a href="home.php" class="flex items-center gap-3">
            <div class="w-8 h-8 bg-brand rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-parking text-white text-sm"></i>
            </div>
            <span class="text-lg font-manrope font-800 tracking-tight">Smart<span class="text-brand">Parking</span></span>
        </a>
    </nav>

    <main class="flex-grow flex items-center justify-center p-6 py-12">
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
            
            <!-- Left Info Column -->
            <div class="space-y-10">
                <div>
                    <h1 class="text-4xl md:text-5xl font-manrope font-800 mb-6 leading-tight">
                        Reserve Your <br>
                        <span class="text-brand">Premium Spot</span>
                    </h1>
                    <p class="text-slate-400 font-medium leading-relaxed">
                        Experience the ultimate convenience. Booking ahead ensures your spot is waiting for you, even during peak hours.
                    </p>
                </div>

                <!-- Venue Info Card -->
                <div class="glass-panel p-8 rounded-3xl space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand">
                            <i class="fa-solid fa-location-dot text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Location</div>
                            <div class="font-bold text-white">Berserk Mall</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 pt-4 border-t border-white/5">
                        <div>
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Car Rate</div>
                            <div class="text-lg font-manrope font-800 text-white"><?= fmt_idr($rates['car']['first_hour_rate']) ?><span class="text-xs text-slate-500 font-medium ml-1">/ hr</span></div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Motor Rate</div>
                            <div class="text-lg font-manrope font-800 text-white"><?= fmt_idr($rates['motorcycle']['first_hour_rate']) ?><span class="text-xs text-slate-500 font-medium ml-1">/ hr</span></div>
                        </div>
                    </div>
                </div>

                <!-- Benefits List -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-300">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>No waiting in queue</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-300">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>Secure Reservation Zones</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-300">
                        <i class="fa-solid fa-circle-check text-brand"></i>
                        <span>Digital Entry Code</span>
                    </div>
                </div>
            </div>

            <!-- Right Form Column -->
            <div class="glass-panel p-8 md:p-10 rounded-[2.5rem] shadow-2xl relative">
                <form id="reservation-form" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Vehicle Information</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="setVehicle('car')" class="v-type-btn p-4 rounded-2xl border-2 border-white/5 bg-white/5 flex flex-col items-center gap-2 transition-all group active" id="btn-car">
                                <i class="fa-solid fa-car text-2xl text-slate-500 group-hover:text-brand transition-colors"></i>
                                <span class="text-xs font-bold uppercase tracking-wider">Car</span>
                            </button>
                            <button type="button" onclick="setVehicle('motorcycle')" class="v-type-btn p-4 rounded-2xl border-2 border-white/5 bg-white/5 flex flex-col items-center gap-2 transition-all group" id="btn-motor">
                                <i class="fa-solid fa-motorcycle text-2xl text-slate-500 group-hover:text-brand transition-colors"></i>
                                <span class="text-xs font-bold uppercase tracking-wider">Motor</span>
                            </button>
                        </div>
                        <input type="hidden" name="vehicle_type" id="vehicle_type" value="car">
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">License Plate</label>
                        <input type="text" name="plate_number" required placeholder="B 1234 XYZ" class="form-input w-full h-14 px-6 rounded-2xl text-lg font-bold uppercase tracking-wider text-white">
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Reservation Time</label>
                        <div class="relative">
                            <i class="fa-solid fa-calendar-days absolute left-6 top-1/2 -translate-y-1/2 text-slate-500"></i>
                            <input type="text" id="time_range" name="time_range" required placeholder="Select Date & Time Range" class="form-input w-full h-14 pl-14 pr-6 rounded-2xl text-base font-semibold text-white">
                        </div>
                    </div>

                    <div id="booking-summary" class="hidden p-6 rounded-2xl bg-brand/5 border border-brand-500/10 space-y-3">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-400 font-medium">Estimated Duration</span>
                            <span id="summary-duration" class="text-white font-bold">-</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-400 font-medium">Base Rate</span>
                            <span id="summary-rate" class="text-white font-bold">-</span>
                        </div>
                    </div>

                    <button type="submit" id="submit-btn" class="btn-primary w-full h-16 rounded-2xl text-base font-bold text-white flex items-center justify-center gap-3">
                        <span>Confirm Reservation</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <!-- Success Overlay -->
                <div id="success-overlay" class="hidden absolute inset-0 bg-slate-950 rounded-[2.5rem] flex flex-col items-center justify-center p-10 text-center animate-fade-in z-20">
                    <div class="w-20 h-20 bg-brand/20 rounded-full flex items-center justify-center text-brand mb-6 scale-125">
                        <i class="fa-solid fa-check text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-manrope font-800 mb-2">Reservation Confirmed!</h2>
                    <p class="text-slate-400 text-sm mb-8 leading-relaxed">Your spot is secured. Show this code or your license plate at the entrance.</p>
                    
                    <div class="w-full p-6 rounded-3xl bg-white/5 border border-white/10 mb-8">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-2">Your Booking Code</div>
                        <div id="res-code" class="text-4xl font-manrope font-800 text-brand tracking-wider">RSV-XXXX</div>
                    </div>

                    <button onclick="location.reload()" class="text-sm font-bold text-slate-400 hover:text-white transition-colors">Make Another Booking</button>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Vehicle selection logic
        function setVehicle(type) {
            document.getElementById('vehicle_type').value = type;
            document.querySelectorAll('.v-type-btn').forEach(btn => btn.classList.remove('active', 'border-brand-500', 'bg-brand/10'));
            document.querySelectorAll('.v-type-btn i').forEach(i => i.classList.replace('text-brand', 'text-slate-500'));
            
            const activeBtn = document.getElementById(type === 'car' ? 'btn-car' : 'btn-motor');
            activeBtn.classList.add('active', 'border-brand-500', 'bg-brand/10');
            activeBtn.querySelector('i').classList.replace('text-slate-500', 'text-brand');
            
            updateSummary();
        }

        // Initialize Flatpickr
        const fp = flatpickr("#time_range", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            mode: "range",
            time_24hr: true,
            onChange: function(selectedDates, dateStr, instance) {
                updateSummary();
            }
        });

        const rates = <?= json_encode($rates) ?>;

        function updateSummary() {
            const range = fp.selectedDates;
            const summary = document.getElementById('booking-summary');
            if (range.length === 2) {
                summary.classList.remove('hidden');
                const diffMs = range[1] - range[0];
                const diffHrs = Math.ceil(diffMs / (1000 * 60 * 60));
                const vType = document.getElementById('vehicle_type').value;
                const rate = rates[vType];
                
                document.getElementById('summary-duration').textContent = diffHrs + ' Hours';
                document.getElementById('summary-rate').textContent = 'Rp ' + Number(rate.first_hour_rate).toLocaleString();
            } else {
                summary.classList.add('hidden');
            }
        }

        // Form submission
        document.getElementById('reservation-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            const range = fp.selectedDates;
            
            if (range.length < 2) {
                pushNotify('Validation Error', 'Please select a complete time range', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> Processing...';

            const formData = new FormData(e.target);
            formData.append('from', range[0].toISOString());
            formData.append('until', range[1].toISOString());

            try {
                const response = await fetch('api/public_reserve.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('res-code').textContent = result.reservation_code;
                    document.getElementById('success-overlay').classList.remove('hidden');
                } else {
                    pushNotify('Error', result.error || 'Reservation failed. Please try again.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<span>Confirm Reservation</span> <i class="fa-solid fa-arrow-right"></i>';
                }
            } catch (err) {
                pushNotify('Connection Error', 'An error occurred. Please check your connection.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<span>Confirm Reservation</span> <i class="fa-solid fa-arrow-right"></i>';
            }
        });
    </script>
    <?php include 'includes/ai_assistant.php'; ?>
</body>
</html>
