<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

$active = $pdo->query("
    (SELECT 
        t.transaction_id, 
        t.reservation_id, 
        t.ticket_code, 
        v.plate_number, 
        v.vehicle_type, 
        s.slot_number, 
        f.floor_code as floor,
        t.check_in_time, 
        NULL as exit_time,
        TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) as minutes_parked,
        r.first_hour_rate, 
        r.next_hour_rate, 
        r.daily_max_rate
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    JOIN parking_slot s ON t.slot_id = s.slot_id
    JOIN floor f ON s.floor_id = f.floor_id
    JOIN parking_rate r ON t.rate_id = r.rate_id
    WHERE t.payment_status = 'unpaid')
    
    UNION ALL
    
    (SELECT 
        NULL as transaction_id, 
        res.reservation_id, 
        res.reservation_code as ticket_code, 
        v.plate_number, 
        v.vehicle_type, 
        s.slot_number, 
        f.floor_code as floor,
        res.reserved_from as check_in_time, 
        res.reserved_until as exit_time,
        TIMESTAMPDIFF(MINUTE, res.reserved_from, NOW()) as minutes_parked,
        r.first_hour_rate, 
        r.next_hour_rate, 
        r.daily_max_rate
    FROM `reservation` res
    JOIN vehicle v ON res.vehicle_id = v.vehicle_id
    JOIN parking_slot s ON res.slot_id = s.slot_id
    JOIN floor f ON s.floor_id = f.floor_id
    LEFT JOIN parking_rate r ON r.vehicle_type = v.vehicle_type
    WHERE res.status = 'confirmed' 
      AND DATE(res.reserved_from) = CURDATE()
      AND NOT EXISTS (SELECT 1 FROM `transaction` t2 WHERE t2.reservation_id = res.reservation_id))
      
    ORDER BY check_in_time DESC
")->fetchAll();

$page_title = 'Live Fleet Status';
$page_subtitle = "Actively monitoring " . count($active) . " occupied zones.";
$page_actions = ""; // Moved into the bento-card header for better hierarchy

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">

    <div class="px-10 py-10 h-[calc(100vh-80px)] max-w-[1400px] mx-auto flex flex-col gap-6 overflow-hidden">
        
        <!-- Main Card with Table -->
        <div class="bento-card flex-1 flex flex-col overflow-hidden min-h-0">
            <!-- Card Header (Dashboard Style) -->
            <div class="flex items-center justify-between px-4 py-4 border-b border-color shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-car-side text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Live Fleet Status</h3>
                        <p class="text-[11px] text-tertiary font-inter">Actively monitoring <?= count($active) ?> occupied slots</p>
                    </div>
                </div>

            </div>
            <!-- Sticky Table Header Wrapper -->
            <div class="overflow-y-auto flex-1 no-scrollbar">
                <table class="w-full font-inter border-collapse table-fixed">
                    <thead class="sticky top-0 bg-surface z-20">
                        <tr class="border-b border-color">
                            <th class="py-3 px-4 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left">Vehicle</th>
                            <th class="py-3 px-4 w-[14%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Plate Number</th>
                            <th class="py-3 px-4 w-[14%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Ticket Code</th>
                            <th class="py-3 px-4 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Slot</th>
                            <th class="py-3 px-4 w-[11%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Entry</th>
                            <th class="py-3 px-4 w-[11%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Exit</th>
                            <th class="py-3 px-4 w-[11%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Duration</th>
                            <th class="py-3 px-4 w-[13%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Price</th>
                            <th class="py-3 px-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right">Action</th>
                        </tr>
                        <style>
        @keyframes blink-red {
            0%, 100% { opacity: 1; color: #ef4444; }
            50% { opacity: 0.5; color: #ef4444; }
        }
        .blink-red {
            animation: blink-red 1s infinite;
        }
    </style>
                    </thead>
                    <tbody class="divide-y divide-color">
                        <?php if (empty($active)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-20 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <i class="fa-solid fa-car-tunnel text-5xl mb-4"></i>
                                    <p class="text-secondary font-inter font-medium">No active vehicles currently detected in the facility.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: 
                            $reg_counter = 1;
                            $res_counter = 1;
                            foreach ($active as $index => $row): 
                                $is_res = !empty($row['reservation_id']);
                                $mins = (int)$row['minutes_parked'];
                                
                                if ($mins < 0) {
                                    $dur_text = "Scheduled";
                                    $is_long_stay = false;
                                    $est_fee = "Rp 0";
                                } else {
                                    $hours = floor($mins / 60);
                                    $remaining_mins = $mins % 60;
                                    $dur_text = $hours . "h " . $remaining_mins . "m";
                                    $is_long_stay = $mins > 1440;
                                    $calc = calculate_fee($mins, $row['first_hour_rate'], $row['next_hour_rate'], $row['daily_max_rate']);
                                    $est_fee = fmt_idr($calc['total_fee']);
                                }

                                // Slot display logic
                                if ($is_res) {
                                    $slot_num = intval(preg_replace('/[^0-9]/', '', $row['slot_number']));
                                    $display_slot = "#RES " . ($slot_num > 10 ? $slot_num % 10 : $slot_num);
                                    if ($display_slot == "#RES 0") $display_slot = "#RES 10";
                                } else {
                                    $display_slot = "#" . $reg_counter++;
                                }
                        ?>
                        <tr class="group hover:bg-surface-alt/50 transition-colors">
                            <!-- 1. Vehicle -->
                            <td class="px-4 py-2 align-middle text-left">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                        <?php if (strtolower($row['vehicle_type']) == 'motorcycle'): ?>
                                            <i class="fa-solid fa-motorcycle text-lg"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-car text-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Plate -->
                            <td class="px-4 py-2 align-middle text-center">
                                <span class="text-[13px] font-manrope font-bold text-primary leading-none">
                                    <?= !empty($row['plate_number']) ? htmlspecialchars($row['plate_number']) : '<span class="opacity-20">------</span>' ?>
                                </span>
                            </td>

                            <!-- 2.1 Ticket -->
                            <td class="px-4 py-2 align-middle text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1"><?= htmlspecialchars($row['ticket_code']) ?></span>
                                    <span class="text-[9px] font-inter text-tertiary leading-none uppercase"><?= $is_res ? 'RESERVATION' : 'REGULAR' ?></span>
                                </div>
                            </td>

                            <!-- 3. Slot -->
                            <td class="px-4 py-2 align-middle text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1"><?= $display_slot ?></span>
                                    <span class="text-[10px] font-inter text-tertiary leading-none uppercase"><?= $is_res ? 'VIP AREA' : 'REGULAR' ?></span>
                                </div>
                            </td>

                            <td class="px-4 py-2 align-middle text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1"><?= date('H:i', strtotime($row['check_in_time'])) ?></span>
                                    <span class="text-[10px] font-inter text-tertiary leading-none uppercase"><?= date('d M', strtotime($row['check_in_time'])) ?></span>
                                </div>
                            </td>

                            <td class="px-4 py-2 align-middle text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-[13px] font-manrope font-bold text-tertiary/20 leading-none">-- : --</span>
                                </div>
                            </td>

                            <td class="px-4 py-2 align-middle text-center">
                                <span class="text-[13px] font-manrope font-bold <?= $is_long_stay ? 'blink-red' : 'text-primary' ?>">
                                    <?= $dur_text ?>
                                </span>
                            </td>

                            <td class="px-4 py-2 align-middle text-center">
                                <span class="text-[13px] font-manrope font-bold text-primary"><?= $est_fee ?></span>
                            </td>

                             <!-- 7. Action -->
                            <!-- 7. Action (Dropdown Menu) -->
                            <td class="px-4 py-2 align-middle text-right relative">
                                <div class="flex justify-end">
                                    <div class="relative action-menu-container">
                                        <button onclick="toggleActionMenu(this, event)" 
                                                class="w-10 h-10 rounded-xl bg-surface border border-color text-secondary hover:text-brand hover:border-brand/30 hover:shadow-lg transition-all flex items-center justify-center shadow-sm">
                                            <i class="fa-solid fa-ellipsis-vertical text-lg"></i>
                                        </button>
                                        
                                        <!-- Dropdown Menu -->
                                        <div class="action-dropdown hidden absolute right-0 top-12 w-56 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-3 overflow-hidden animate-in fade-in zoom-in duration-200">
                                            <!-- Lost Ticket -->
                                            <button onclick="handleLostTicket('<?= $row['ticket_code'] ?>', '<?= $row['plate_number'] ?>', <?= $is_res ? 0 : $calc['total_fee'] ?>)"
                                                    class="w-full px-4 py-3 text-left flex items-center gap-4 hover:bg-brand/[0.03] transition-all group/item">
                                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all group-hover/item:scale-110">
                                                    <i class="fa-solid fa-print text-sm"></i>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-[11px] font-black uppercase tracking-widest text-primary leading-tight">Tiket Hilang</span>
                                                    <span class="text-[10px] text-tertiary font-medium">Print & Checkout</span>
                                                </div>
                                            </button>

                                            <!-- Force Delete -->
                                            <button onclick="handleForceDelete('<?= $row['ticket_code'] ?>', '<?= $row['plate_number'] ?>')"
                                                    class="w-full px-4 py-3 text-left flex items-center gap-4 hover:bg-brand/[0.03] transition-all group/item">
                                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 !text-red-500 !bg-red-500/5 transition-all group-hover/item:scale-110">
                                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-[11px] font-black uppercase tracking-widest text-red-500 leading-tight">Hapus Paksa</span>
                                                    <span class="text-[10px] text-tertiary font-medium">Manual Cleanup</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($active)): ?>
        <div class="bento-card p-4 relative overflow-hidden group shrink-0">
            <div class="absolute -right-12 -top-12 w-24 h-24 bg-accent-glow rounded-full blur-2xl group-hover:bg-accent-glow transition-all"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-10 h-10 rounded-xl status-badge-over flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-lg trend-down"></i>
                </div>
                <div>
                    <p class="font-manrope font-extrabold text-primary text-sm leading-tight">Extended Stay Protocol</p>
                    <p class="font-inter text-tertiary text-[11px] mt-0.5">Highlighting applied to units exceeding an 8-hour operational threshold.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>


<!-- Receipt Modal -->
<div id="receiptModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white p-8 w-full max-w-[320px] shadow-2xl relative animate-in fade-in zoom-in duration-300">
        <!-- Close Button -->
        <button onclick="closeReceipt()" class="absolute -top-12 right-0 text-white hover:text-brand transition-colors flex items-center gap-2 font-black text-[10px] uppercase tracking-widest">
            Close <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Thermal Receipt Content -->
        <div id="receiptContent" class="receipt-thermal text-black font-mono text-[12px] leading-tight">
            <div class="text-center mb-4">
                <p class="font-black text-sm uppercase">SmartParking V2</p>
                <p>Digital Operational Hub</p>
                <p>---------------------------</p>
                <p class="mt-2 font-bold uppercase">KUITANSI DENDA HILANG</p>
            </div>

            <div class="space-y-1 mb-4">
                <div class="flex justify-between"><span>Date:</span> <span id="r-date">23 Apr, 14:30</span></div>
                <div class="flex justify-between"><span>Ticket:</span> <span id="r-ticket">TCK-10293</span></div>
                <div class="flex justify-between"><span>Plate:</span> <span id="r-plate">B 1234 CD</span></div>
            </div>

            <p class="text-center mb-2">---------------------------</p>

            <div class="space-y-1 mb-4">
                <div class="flex justify-between font-bold">
                    <span>Parking Fee:</span>
                    <span id="r-fee">Rp 15.000</span>
                </div>
                <div class="flex justify-between text-red-600">
                    <span>Denda Hilang:</span>
                    <span>Rp 50.000</span>
                </div>
            </div>

            <p class="text-center mb-2">===========================</p>

            <div class="flex justify-between font-black text-base mb-6">
                <span>TOTAL:</span>
                <span id="r-total">Rp 65.000</span>
            </div>

            <div class="text-center opacity-70">
                <p>Thank you for your visit</p>
                <p>Drive Safely!</p>
            </div>
        </div>

        <!-- Print Action -->
        <button onclick="processCheckout()" class="w-full mt-8 py-3 bg-brand text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:brightness-110 transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-print"></i>
            PRINT KUITANSI
        </button>
    </div>
</div>

<style>
.receipt-thermal {
    color: #000;
    filter: contrast(1.2);
}
@media print {
    body * { visibility: hidden !important; }
    #receiptContent, #receiptContent * { visibility: visible !important; }
    #receiptModal { background: white !important; }
    #receiptContent {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        padding: 0;
        margin: 0;
    }
}
</style>

<script>
let currentTicket = '';
let currentPlate = '';
let isLostMode = false;

function handleLostTicket(ticket, plate, baseFee) {
    currentTicket = ticket;
    currentPlate = plate;
    isLostMode = true;

    const fine = 50000;
    const total = baseFee + fine;
    const fmt = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
    
    document.getElementById('r-date').innerText = new Date().toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    document.getElementById('r-ticket').innerText = ticket || 'N/A';
    document.getElementById('r-plate').innerText = plate || 'N/A';
    document.getElementById('r-fee').innerText = fmt(baseFee);
    document.getElementById('r-total').innerText = fmt(total);

    const modal = document.getElementById('receiptModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function handleForceDelete(ticket, plate) {
    if (confirm(`Yakin ingin hapus paksa kendaraan ${plate || ticket}?`)) {
        currentTicket = ticket;
        currentPlate = plate;
        isLostMode = false;
        processCheckout();
    }
}

function processCheckout() {
    const formData = new FormData();
    formData.append('ticket', currentTicket);
    formData.append('plate', currentPlate);
    formData.append('is_lost', isLostMode);

    fetch('../../api/force_checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (isLostMode) {
                window.print();
            }
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closeReceipt() {
    const modal = document.getElementById('receiptModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Action Menu Functions
function toggleActionMenu(btn, e) {
    e.stopPropagation();
    const allMenus = document.querySelectorAll('.action-dropdown');
    const currentMenu = btn.nextElementSibling;

    allMenus.forEach(m => {
        if (m !== currentMenu) m.classList.add('hidden');
    });

    currentMenu.classList.toggle('hidden');
}

// Close all menus when clicking outside
document.addEventListener('click', () => {
    document.querySelectorAll('.action-dropdown').forEach(m => m.classList.add('hidden'));
});
</script>

<?php include '../../includes/footer.php'; ?>
