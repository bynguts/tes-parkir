<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/connection.php';

// Redirect to auth if not logged in
if (empty($_SESSION['customer_id'])) {
    header('Location: auth.php?redirect=my_bookings');
    exit;
}

$customer_id = $_SESSION['customer_id'];
$current_page = 'bookings';

// Fetch latest active/pending reservation
$stmt = $pdo->prepare("
    SELECT r.*, ps.slot_number 
    FROM reservation r
    JOIN parking_slot ps ON r.slot_id = ps.slot_id
    WHERE r.customer_id = ? AND r.status IN ('pending', 'confirmed')
    ORDER BY r.reserved_from DESC
    LIMIT 1
");
$stmt->execute([$customer_id]);
$active_res = $stmt->fetch();

// Fetch history (last 5)
$stmt = $pdo->prepare("
    SELECT r.*, ps.slot_number 
    FROM reservation r
    JOIN parking_slot ps ON r.slot_id = ps.slot_id
    WHERE r.customer_id = ? AND r.status NOT IN ('pending', 'confirmed')
    ORDER BY r.reserved_from DESC
    LIMIT 5
");
$stmt->execute([$customer_id]);
$history = $stmt->fetchAll();

// Fetch real total spending
$stmt = $pdo->prepare("
    SELECT SUM(t.total_fee) as total 
    FROM transaction t 
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id 
    WHERE v.customer_id = ?
");
$stmt->execute([$customer_id]);
$spending = $stmt->fetch();
$total_spending = $spending['total'] ?? 0;

// Fetch registered vehicles
$stmt = $pdo->prepare("SELECT plate_number, vehicle_type FROM vehicle WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$my_vehicles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>My Bookings - Parkhere</title>

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Leaflet JS & CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
        }

        .booking-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .booking-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.04);
            border-color: var(--brand);
        }

        .status-badge {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 4px 12px;
            border-radius: 99px;
        }

        .active-glow {
            box-shadow: 0 0 0 2px var(--brand), 0 10px 25px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body class="bg-bg-page text-primary font-inter antialiased">
    <?php include 'includes/navbar.php'; ?>

    <main class="pt-32 pb-20 px-6 max-w-7xl mx-auto">
        <!-- Page Header & Filters -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-12">
            <div>
                <h1 class="text-4xl font-manrope font-800 text-primary mb-2">Booking History</h1>
                <p class="text-secondary font-medium">Manage your urban mobility and parking schedule.</p>
            </div>
            

        </div>

        <!-- Reservations Content -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Active/Featured Reservation -->
            <div class="lg:col-span-8 flex flex-col gap-8">
                <?php if ($active_res): ?>
                <div class="booking-card active-glow rounded-[2.5rem] p-8 md:p-10 relative overflow-hidden group">
                    <!-- Status Badge -->
                    <div class="absolute top-8 right-8 z-20">
                        <span class="bg-brand/10 text-brand status-badge"><?= ucfirst($active_res['status']) ?></span>
                    </div>

                    <!-- Locked Mini Map -->
                    <div id="mini-map" class="w-full h-48 rounded-[1.5rem] mb-8 z-0 border border-border-color overflow-hidden"></div>

                    <div class="flex flex-col md:flex-row gap-10 items-start relative z-10">
                        <div class="w-full md:w-56 h-56 rounded-[2rem] overflow-hidden shadow-xl shrink-0">
                            <img alt="Parking location" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="assets/img/lippo_cikarang.jpg"/>
                        </div>
                        
                        <div class="flex-1 space-y-8 w-full">
                            <div>
                                <h3 class="text-3xl font-manrope font-800 text-primary mb-2">Mall Lippo Cikarang</h3>
                                <p class="text-secondary font-semibold flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-brand"></i>
                                    Kawasan Lippo Cikarang CBD • Slot <?= htmlspecialchars(str_replace('#RES', 'RES ', $active_res['slot_number'])) ?>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                                <div class="space-y-1">
                                    <p class="text-[10px] font-800 text-secondary uppercase tracking-widest">Schedule</p>
                                    <p class="text-primary font-bold">
                                        <?= date('M d, H:i', strtotime($active_res['reserved_from'])) ?> - <?= date('H:i', strtotime($active_res['reserved_until'])) ?>
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] font-800 text-secondary uppercase tracking-widest">Vehicle</p>
                                    <div class="flex items-center gap-2 text-primary font-bold">
                                        <i class="fa-solid fa-car-side text-brand"></i>
                                        <span><?= htmlspecialchars($active_res['plate_number']) ?></span>
                                    </div>
                                </div>
                                <div class="space-y-1 col-span-2 md:col-span-1">
                                    <p class="text-[10px] font-800 text-secondary uppercase tracking-widest">Code</p>
                                    <p class="text-2xl font-manrope font-800 text-brand"><?= htmlspecialchars($active_res['reservation_code']) ?></p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-4 pt-4">
                                <button onclick="openTicketModal('<?= $active_res['reservation_code'] ?>', '<?= str_replace('#RES', 'RES ', $active_res['slot_number']) ?>', '<?= $active_res['plate_number'] ?>', '<?= date('M d, H:i', strtotime($active_res['reserved_from'])) ?>', '<?= date('M d, H:i', strtotime($active_res['reserved_until'])) ?>')" class="flex-1 bg-brand text-white h-14 rounded-2xl font-bold text-lg shadow-lg shadow-brand/20 hover:brightness-110 transition-all flex items-center justify-center gap-3">
                                    <i class="fa-solid fa-qrcode"></i>
                                    Open QR Ticket
                                </button>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=Mall+Lippo+Cikarang" target="_blank" class="px-8 h-14 rounded-2xl border border-border-color text-primary font-bold hover:bg-surface-alt transition-all flex items-center justify-center gap-3">
                                    <i class="fa-solid fa-map-location-dot text-brand"></i>
                                    Directions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="booking-card rounded-[2.5rem] p-12 text-center space-y-6">
                    <div class="w-20 h-20 bg-brand/10 text-brand rounded-full flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-calendar-day text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-manrope font-800 text-primary">No Active Reservations</h3>
                        <p class="text-secondary font-medium">You don't have any upcoming parking sessions scheduled.</p>
                    </div>
                    <a href="reserve.php" class="inline-flex bg-brand text-white px-8 py-4 rounded-2xl font-bold hover:brightness-110 transition-all shadow-lg shadow-brand/20">
                        Book a Spot Now
                    </a>
                </div>
                <?php endif; ?>

                <!-- Secondary Bookings Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($history as $item): ?>
                    <div class="booking-card rounded-[2rem] p-6 flex items-center gap-6 group">
                        <div class="w-20 h-20 rounded-2xl bg-brand/5 flex items-center justify-center shrink-0">
                            <?php if ($item['status'] === 'completed'): ?>
                                <i class="fa-solid fa-clock-rotate-left text-brand text-3xl"></i>
                            <?php elseif ($item['status'] === 'canceled'): ?>
                                <i class="fa-solid fa-calendar-xmark text-red-500 text-3xl"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-parking text-brand text-3xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-lg font-manrope font-800 text-primary truncate">Mall Lippo Cikarang</h4>
                            <p class="text-xs font-bold text-secondary mb-3">
                                <?= date('M d, H:i', strtotime($item['reserved_from'])) ?> • <?= htmlspecialchars($item['plate_number']) ?>
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-brand font-800">Slot <?= htmlspecialchars(str_replace('#RES', 'RES ', $item['slot_number'])) ?></span>
                                <span class="bg-slate-500/10 text-slate-600 status-badge"><?= ucfirst($item['status']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stats/Summary Sidebar -->
            <div class="lg:col-span-4 flex flex-col gap-8">
                <!-- Wallet/Spending Card -->
                <div class="bg-brand rounded-[2.5rem] p-8 text-white flex flex-col justify-between shadow-xl shadow-brand/20">
                    <div>
                        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                            <i class="fa-solid fa-wallet text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-manrope font-800 opacity-80">Spending Summary</h4>
                        <p class="text-4xl font-manrope font-800 mt-2">Rp <?= number_format($total_spending / 1000, 0) ?>k</p>
                        <p class="text-[10px] font-800 opacity-60 mt-2 uppercase tracking-widest">Calculated for this account</p>
                    </div>
                    
                    <div class="flex justify-between items-center mt-12">
                        <div class="flex -space-x-3">
                            <div class="w-10 h-10 rounded-full border-2 border-brand bg-white/10 flex items-center justify-center backdrop-blur-md">
                                <i class="fa-solid fa-car text-[10px]"></i>
                            </div>
                            <div class="w-10 h-10 rounded-full border-2 border-brand bg-white/10 flex items-center justify-center backdrop-blur-md">
                                <i class="fa-solid fa-motorcycle text-[10px]"></i>
                            </div>
                        </div>
                        <button class="bg-white text-brand px-6 py-2.5 rounded-xl text-xs font-800 hover:scale-105 transition-all">Details</button>
                    </div>
                </div>
                
                <!-- Registered Vehicles -->
                <div class="booking-card rounded-[2.5rem] p-8 flex-1">
                    <div class="flex items-center justify-between mb-8">
                        <h4 class="text-xl font-manrope font-800 text-primary">Your Vehicles</h4>
                        <button class="text-brand text-xs font-800 hover:underline">Manage</button>
                    </div>
                    <div class="space-y-6">
                        <?php if (empty($my_vehicles)): ?>
                            <p class="text-secondary text-sm font-medium italic">No vehicles registered.</p>
                        <?php else: ?>
                            <?php foreach ($my_vehicles as $v): ?>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-surface-alt flex items-center justify-center text-primary shadow-sm border border-border-color">
                                    <i class="fa-solid fa-<?= $v['vehicle_type'] === 'car' ? 'car' : 'motorcycle' ?> text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-primary font-bold tracking-wider uppercase"><?= htmlspecialchars($v['plate_number']) ?></p>
                                    <p class="text-[10px] font-bold text-secondary uppercase tracking-widest"><?= ucfirst($v['vehicle_type']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-12 px-6 text-center border-t border-border-color bg-surface/30">
        <p class="text-[10px] font-800 text-secondary uppercase tracking-[0.2em]">&copy; 2026 Parkhere Technologies Inc. All rights reserved.</p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mapElement = document.getElementById('mini-map');
            if (!mapElement) return;

            // Mall Lippo Cikarang Coordinates
            var lat = -6.334082;
            var lng = 107.136951;

            // Initialize map with ALL interactions locked
            var map = L.map('mini-map', {
                center: [lat, lng],
                zoom: 17,
                zoomControl: false,
                dragging: false,
                touchZoom: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false
            });

            // Light Mode Tile Layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(map);

            // Custom Marker
            var customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: "<div class='w-10 h-10 bg-brand rounded-full flex items-center justify-center shadow-[0_0_15px_rgba(99,102,241,0.5)] border-2 border-white'><i class='fa-solid fa-parking text-white text-lg'></i></div>",
                iconSize: [40, 40],
                iconAnchor: [20, 40]
            });

            L.marker([lat, lng], {icon: customIcon}).addTo(map);
        });
    </script>
    <!-- Ticket Modal Overlay -->
    <div id="ticket-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md" onclick="closeTicketModal()"></div>
        <div class="relative w-full max-w-md bg-surface border border-color rounded-[2.5rem] overflow-hidden shadow-2xl animate-in zoom-in-95 duration-300">
            <!-- Receipt Top Decor -->
            <div class="bg-brand p-8 text-center relative">
                <button onclick="closeTicketModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white hover:bg-white/30 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-qrcode text-3xl text-white"></i>
                </div>
                <h3 class="text-2xl font-manrope font-800 text-white">Digital Ticket</h3>
                <p class="text-white/80 text-sm mt-1">Mall Lippo Cikarang</p>
            </div>

            <div class="p-8 space-y-6">
                <!-- Reservation Code -->
                <div class="text-center">
                    <div class="text-[10px] font-bold text-secondary uppercase tracking-[0.2em] mb-2">Unique Access Code</div>
                    <div class="text-4xl font-manrope font-800 text-primary tracking-[0.1em]" id="modal-res-code">XXXXXX</div>
                </div>

                <!-- QR Code Placeholder -->
                <div class="flex justify-center py-2">
                    <div class="w-48 h-48 bg-white p-4 rounded-3xl border border-color shadow-inner flex items-center justify-center">
                        <i class="fa-solid fa-qrcode text-8xl text-slate-800"></i>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-2 gap-y-6 border-t border-color pt-6">
                    <div class="space-y-1">
                        <div class="text-[10px] font-bold text-secondary uppercase tracking-widest">Spot</div>
                        <div class="text-sm font-bold text-primary" id="modal-receipt-slot">-</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-[10px] font-bold text-secondary uppercase tracking-widest">Vehicle</div>
                        <div class="text-sm font-bold text-primary" id="modal-receipt-vehicle">-</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-[10px] font-bold text-secondary uppercase tracking-widest">Entry Time</div>
                        <div class="text-[11px] font-bold text-primary" id="modal-receipt-entry">-</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-[10px] font-bold text-secondary uppercase tracking-widest">Exit Time</div>
                        <div class="text-[11px] font-bold text-primary" id="modal-receipt-exit">-</div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 w-full">
                    <button onclick="window.print()" class="w-full h-12 rounded-xl bg-brand text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-print"></i>
                        Print Ticket
                    </button>
                    <button onclick="closeTicketModal()" class="w-full h-12 rounded-xl bg-surface-alt border border-color text-primary text-xs font-bold transition-all">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTicketModal(code, slot, vehicle, entry, exit) {
            document.getElementById('modal-res-code').textContent = code;
            document.getElementById('modal-receipt-slot').textContent = slot;
            document.getElementById('modal-receipt-vehicle').textContent = vehicle;
            document.getElementById('modal-receipt-entry').textContent = entry;
            document.getElementById('modal-receipt-exit').textContent = exit;
            
            document.getElementById('ticket-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeTicketModal() {
            document.getElementById('ticket-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
</body>
</html>
