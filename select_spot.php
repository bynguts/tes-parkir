<?php
/**
 * select_spot.php — Step 2: Spot Selection
 * High-fidelity map interface for choosing parking spots
 */
require_once 'config/connection.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['entry_time']) || empty($_POST['exit_time'])) {
    header("Location: reserve.php");
    exit;
}

$entry_time = $_POST['entry_time'];
$exit_time = $_POST['exit_time'];
$vehicle_type = $_POST['vehicle_type'] ?? 'car';
$client_name = $_POST['client_name'] ?? '';
$plate_number = $_POST['plate_number'] ?? '';
$client_phone = $_POST['client_phone'] ?? '';

// Fetch rates for summary
$rates_stmt = $pdo->prepare("SELECT * FROM parking_rate WHERE vehicle_type = ?");
$rates_stmt->execute([$vehicle_type]);
$rate = $rates_stmt->fetch(PDO::FETCH_ASSOC);

$diffHrs = 0;
$entry_dt = strtotime($entry_time);
$exit_dt = strtotime($exit_time);
if ($entry_dt && $exit_dt && $exit_dt > $entry_dt) {
    $diffHrs = ceil(($exit_dt - $entry_dt) / 3600);
}
$baseRate = $diffHrs > 0 && $rate ? $rate['first_hour_rate'] : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Spot — Parkhere</title>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>
    <style>
        body { background-color: var(--bg-page); color: var(--text-primary); }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col items-center justify-center p-4">

    <!-- Top Navigation -->
    <nav class="fixed top-0 w-full bg-surface border-b border-color z-40">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center text-white shadow-lg shadow-brand/20">
                    <i class="fa-solid fa-square-parking text-xl"></i>
                </div>
                <span class="text-xl font-manrope font-800 tracking-tight text-primary">Park<span class="text-brand">here</span></span>
            </div>
            <a href="reserve.php" class="text-sm font-bold text-secondary hover:text-brand transition-colors"><i class="fa-solid fa-arrow-left mr-2"></i>Back to Form</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="w-full max-w-4xl mt-24 mb-12">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-manrope font-800 text-primary mb-2">Select Your Premium Spot</h1>
            <p class="text-secondary font-medium">Choose your preferred parking location from the map below.</p>
        </div>

        <div class="bento-card bg-surface rounded-3xl p-6 md:p-8">
            <!-- Hidden form data to pass along via JS -->
            <input type="hidden" id="client_name" value="<?= htmlspecialchars($client_name) ?>">
            <input type="hidden" id="plate_number" value="<?= htmlspecialchars($plate_number) ?>">
            <input type="hidden" id="client_phone" value="<?= htmlspecialchars($client_phone) ?>">
            <input type="hidden" id="entry_time" value="<?= htmlspecialchars($entry_time) ?>">
            <input type="hidden" id="exit_time" value="<?= htmlspecialchars($exit_time) ?>">
            <input type="hidden" id="vehicle_type" value="<?= htmlspecialchars($vehicle_type) ?>">
            <input type="hidden" id="selected_slot_id" value="">

            <div class="space-y-6">
                <!-- Legend and Status -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-surface-alt p-4 rounded-2xl border border-color">
                    <span id="selected-slot-display" class="text-sm font-bold text-brand uppercase bg-brand/10 px-4 py-2 rounded-xl border border-brand/20">No Spot Selected</span>
                    <div class="flex flex-wrap gap-3">
                        <div class="px-4 py-1.5 rounded-full text-[10px] font-800 uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">Available</div>
                        <div class="px-4 py-1.5 rounded-full text-[10px] font-800 uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">Occupied</div>
                        <div class="px-4 py-1.5 rounded-full text-[10px] font-800 uppercase tracking-wider bg-violet-500/10 text-violet-500 border border-violet-500/20">Reserved</div>
                    </div>
                </div>

                <!-- Grid Map -->
                <div id="parking-map" class="parking-grid bg-surface-alt rounded-2xl border border-color min-h-[150px]">
                    <div class="col-span-full py-10 flex flex-col items-center gap-3"><i class="fa-solid fa-circle-notch fa-spin text-brand text-2xl"></i><span class="text-[10px] font-bold text-secondary uppercase tracking-widest">Scanning Floor...</span></div>
                </div>

                <!-- Footer Summary -->
                <div class="pt-6 border-t border-color flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-6">
                        <div class="text-sm">
                            <span class="text-secondary font-medium">Duration:</span>
                            <span class="text-primary font-bold ml-1"><?= $diffHrs ?> Hours</span>
                        </div>
                        <div class="text-sm border-l border-color pl-6">
                            <span class="text-secondary font-medium">Base Rate:</span>
                            <span class="text-primary font-bold ml-1">Rp <?= number_format($baseRate) ?></span>
                        </div>
                    </div>
                    <button type="button" id="confirm-booking-btn" onclick="submitReservation()" disabled class="btn-primary w-full md:w-auto px-8 h-14 rounded-2xl text-base font-bold text-white flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>Confirm Booking</span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Overlay -->
    <div id="success-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-xl bg-slate-900/60 transition-all duration-500">
        <div class="glass-panel w-full max-w-md bg-surface border border-color rounded-[2.5rem] shadow-2xl p-8 transform scale-95 opacity-0 transition-all duration-500 flex flex-col items-center text-center relative overflow-hidden">
            <!-- Background Decoration -->
            <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-brand/20 to-transparent"></div>
            
            <div class="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mb-6 relative z-10 border border-emerald-500/20">
                <i class="fa-solid fa-check text-4xl text-emerald-500"></i>
            </div>
            
            <h2 class="text-2xl font-manrope font-800 mb-1 text-primary relative z-10">Booking Confirmed!</h2>
            <p class="text-secondary text-[11px] mb-6 uppercase tracking-widest font-bold relative z-10">Your Digital Parking Receipt</p>
            
            <!-- Receipt Content -->
            <div class="w-full bg-surface-alt border border-color rounded-3xl overflow-hidden mb-6 relative z-10">
                <!-- Top: Code & Slot -->
                <div class="p-6 border-b border-color" style="background-color: color-mix(in srgb, var(--brand) 5%, transparent);">
                    <div class="text-[10px] font-bold text-brand uppercase tracking-[0.2em] mb-2">Reservation Code</div>
                    <div id="res-code" class="text-3xl font-manrope font-800 text-primary tracking-wider mb-4">RSV-XXXX</div>
                    
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-color">
                        <div class="text-left">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Assigned Slot</div>
                            <div id="receipt-slot" class="text-lg font-manrope font-800 text-primary">-</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Vehicle</div>
                            <div id="receipt-vehicle" class="text-lg font-manrope font-800 text-primary"><?= strtoupper(htmlspecialchars($plate_number)) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Middle: Details -->
                <div class="p-6 space-y-4 text-left">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Client Name</div>
                            <div id="receipt-name" class="text-xs font-bold text-primary"><?= htmlspecialchars($client_name) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Phone</div>
                            <div id="receipt-phone" class="text-xs font-bold text-primary"><?= htmlspecialchars($client_phone) ?></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 py-4 border-y border-color">
                        <div>
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Entry Schedule</div>
                            <div id="receipt-entry" class="text-[11px] font-bold text-primary"><?= date('d M, H:i', strtotime($entry_time)) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] font-bold text-secondary uppercase tracking-widest mb-1">Exit Schedule</div>
                            <div id="receipt-exit" class="text-[11px] font-bold text-primary"><?= date('d M, H:i', strtotime($exit_time)) ?></div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-secondary">Security Checkpoint</span>
                        <span class="text-[10px] font-mono text-brand font-bold uppercase">Authorized Access Only</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4 w-full relative z-10">
                <?php if (!isset($_SESSION['customer_id'])): ?>
                <!-- Hybrid Guest CTA -->
                <div class="p-4 rounded-2xl bg-brand/5 border border-brand/20 text-left relative overflow-hidden group">
                    <div class="relative z-10">
                        <h4 class="text-[12px] font-bold text-primary mb-1">Want faster reservations?</h4>
                        <p class="text-[10px] text-secondary mb-2 leading-relaxed">Become a part of our community to save your details for one-click bookings next time.</p>
                        <a href="#" id="cta-register-link" class="inline-flex items-center gap-2 text-[11px] font-bold text-brand hover:brightness-110 transition-all">
                            Join Now & Save Details <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <button onclick="window.print()" class="w-full h-12 rounded-xl bg-surface border border-color hover:border-brand text-primary text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-sm">
                    <i class="fa-solid fa-print"></i>
                    Print Receipt
                </button>
                <a href="reserve.php" class="text-[11px] font-bold text-secondary hover:text-primary transition-colors py-2">Make Another Booking</a>
            </div>
        </div>
    </div>

    <script>
        const vehicleType = document.getElementById('vehicle_type').value;
        const entryTime = document.getElementById('entry_time').value;
        const exitTime = document.getElementById('exit_time').value;

        async function loadParkingMap() {
            const mapGrid = document.getElementById('parking-map');
            const ts = new Date().getTime();

            try {
                const res = await fetch(`api/get_slots_availability.php?from=${encodeURIComponent(entryTime)}&until=${encodeURIComponent(exitTime)}&vehicle_type=${vehicleType}&_t=${ts}`);
                const data = await res.json();

                if (data.success) {
                    mapGrid.innerHTML = '';
                    if (data.slots && data.slots.length > 0) {
                        data.slots.forEach(slot => {
                            const node = document.createElement('div');
                            node.className = `slot-node ${slot.is_available ? 'available' : 'booked'} ${slot.is_reservation_only ? 'is-res-only' : ''}`;
                            node.innerHTML = `<i class="fa-solid fa-${vehicleType === 'car' ? 'car' : 'motorcycle'} mb-1 text-lg"></i><span class="font-bold">#RES${slot.slot_number}</span>`;
                            
                            if (slot.is_available) {
                                node.onclick = () => selectSlot(slot.slot_id, slot.slot_number);
                            }
                            
                            mapGrid.appendChild(node);
                        });
                    } else {
                        mapGrid.innerHTML = '<div class="col-span-full text-center text-sm font-bold text-secondary py-10">No premium slots found for this vehicle type.</div>';
                    }
                } else {
                    mapGrid.innerHTML = `<div class="col-span-full text-center text-sm font-bold text-red-500 py-10">${data.error || 'Failed to load map data'}</div>`;
                }
            } catch (err) {
                console.error('Failed to load map:', err);
                mapGrid.innerHTML = '<div class="col-span-full text-center text-sm font-bold text-red-500 py-10">Network error. Failed to load map.</div>';
            }
        }

        function selectSlot(id, number) {
            document.getElementById('selected_slot_id').value = id;
            document.getElementById('selected-slot-display').textContent = `Spot #RES${number} Selected`;
            document.getElementById('confirm-booking-btn').disabled = false;
            
            document.querySelectorAll('#parking-map .slot-node').forEach(node => {
                node.classList.remove('selected');
                if (node.textContent.includes('#RES' + number)) node.classList.add('selected');
            });
        }

        async function submitReservation() {
            const btn = document.getElementById('confirm-booking-btn');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> Processing...';

            const formData = new FormData();
            formData.append('from', entryTime);
            formData.append('until', exitTime);
            formData.append('vehicle_type', vehicleType);
            formData.append('client_name', document.getElementById('client_name').value);
            formData.append('plate_number', document.getElementById('plate_number').value);
            formData.append('client_phone', document.getElementById('client_phone').value);
            formData.append('slot_id', document.getElementById('selected_slot_id').value);

            try {
                const response = await fetch('api/public_reserve.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    // Populate Receipt
                    document.getElementById('res-code').textContent = result.reservation_code;
                    document.getElementById('receipt-slot').textContent = '#RES' + result.slot_number;
                    document.getElementById('receipt-vehicle').textContent = result.plate_number.toUpperCase();
                    
                    // Update CTA link if it exists
                    const ctaLink = document.getElementById('cta-register-link');
                    if (ctaLink) {
                        const name = encodeURIComponent(document.getElementById('receipt-name').textContent);
                        const phone = encodeURIComponent(document.getElementById('receipt-phone').textContent);
                        const plate = encodeURIComponent(document.getElementById('receipt-vehicle').textContent);
                        ctaLink.href = `auth.php?action=register&name=${name}&phone=${phone}&plate=${plate}`;
                    }

                    // Show Overlay
                    const overlay = document.getElementById('success-overlay');
                    overlay.classList.remove('hidden');
                    setTimeout(() => {
                        overlay.classList.remove('opacity-0');
                        overlay.querySelector('div').classList.remove('scale-95', 'translate-y-8', 'opacity-0');
                    }, 10);
                } else {
                    if (result.error && result.error.includes('duplicate')) {
                        alert('Warning: This license plate already has an active reservation for this time period.');
                    } else {
                        alert(result.error || 'Reservation failed. Please try again.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<span>Confirm Booking</span> <i class="fa-solid fa-check"></i>';
                }
            } catch (err) {
                alert('An error occurred. Please check your connection.');
                btn.disabled = false;
                btn.innerHTML = '<span>Confirm Booking</span> <i class="fa-solid fa-check"></i>';
            }
        }

        // Initialize map on load
        document.addEventListener('DOMContentLoaded', loadParkingMap);
    </script>
</body>
</html>
