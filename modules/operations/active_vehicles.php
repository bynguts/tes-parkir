<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

// --- GLOBAL SLOT MAPPING (Indigo Night Standard) ---
$all_slots_query = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.is_reservation_only, f.floor_code
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    ORDER BY ps.is_reservation_only ASC, f.floor_code ASC, ps.slot_type ASC, ps.slot_number ASC
");
$slot_mapping = [];
$reg_idx = 1; $res_idx = 1;
foreach ($all_slots_query as $s) {
    if ((int)$s['is_reservation_only'] === 1) {
        $slot_mapping[$s['slot_id']] = ["label" => "#RES " . $res_idx++, "category" => "RSV ZONE"];
    } else {
        $slot_mapping[$s['slot_id']] = ["label" => "#" . $reg_idx++, "category" => "REGULAR"];
    }
}

$active = $pdo->query("
    (SELECT 
        t.transaction_id, 
        t.reservation_id, 
        t.ticket_code, 
        v.plate_number, 
        v.vehicle_type, 
        s.slot_id, 
        t.check_in_time, 
        NULL as exit_time,
        TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) as minutes_parked,
        r.first_hour_rate, 
        r.next_hour_rate, 
        r.lost_ticket_fine,
        t.payment_status
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    JOIN parking_slot s ON t.slot_id = s.slot_id
    JOIN parking_rate r ON t.rate_id = r.rate_id
    WHERE t.check_out_time IS NULL)
    
    UNION ALL
    
    (SELECT 
        NULL as transaction_id, 
        res.reservation_id, 
        res.reservation_code as ticket_code, 
        v.plate_number, 
        v.vehicle_type, 
        s.slot_id, 
        res.reserved_from as check_in_time, 
        res.reserved_until as exit_time,
        TIMESTAMPDIFF(MINUTE, res.reserved_from, NOW()) as minutes_parked,
        r.first_hour_rate, 
        r.next_hour_rate, 
        r.lost_ticket_fine,
        'unpaid' as payment_status
    FROM `reservation` res
    JOIN vehicle v ON res.vehicle_id = v.vehicle_id
    JOIN parking_slot s ON res.slot_id = s.slot_id
    LEFT JOIN parking_rate r ON r.vehicle_type = v.vehicle_type
    WHERE res.status = 'confirmed' 
      AND DATE(res.reserved_from) = CURDATE()
      AND NOT EXISTS (SELECT 1 FROM `transaction` t2 WHERE t2.reservation_id = res.reservation_id))
      
    ORDER BY check_in_time DESC
")->fetchAll();

$page_title = 'Live Fleet Status';
$page_subtitle = "Actively monitoring " . count($active) . " occupied slots";

include '../../includes/header.php';
?>



<div class="px-10 py-10">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>
        <div class="flex items-center gap-3">
                <button type="button" onclick="forceCheckoutAll(this)"
                    class="btn-danger-soft h-[38px] gap-2">
                <i class="fa-solid fa-sign-out-alt"></i>
                Force Checkout All
            </button>
        </div>
    </div>

    <div class="bento-card overflow-hidden">
        <!-- Card Header with Filters -->
        <div class="flex items-center justify-between py-5 px-4 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-car-side text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Active Fleet</h3>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Real-time occupancy</p>
                </div>
            </div>

            <!-- Integrated Filters -->
            <div class="flex items-center gap-4">
                <!-- Sort -->
                <button onclick="toggleSort()" id="sortBtn" 
                        class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                    <i id="sortIcon" class="fa-solid fa-sort text-[12px] text-tertiary group-hover:text-brand"></i>
                    <span class="text-[11px] font-inter font-medium tracking-wider text-primary">Sort</span>
                </button>

                <!-- Search -->
                <div class="relative group">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-tertiary text-sm"></i>
                           <input type="text" id="logSearch" placeholder="Search plate or ticket..." 
                               class="w-44 bg-surface-alt border border-color rounded-xl h-[38px] pl-10 pr-4 text-[11px] font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-surface transition-all">
                </div>

                <!-- Vehicle Type Filter -->
                <div class="flex items-center bg-surface-alt border border-color rounded-xl p-1 gap-1 h-[38px]">
                    <button onclick="setVehicleFilter('all')" data-filter="all" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-[11px] font-inter font-medium tracking-wider transition-all bg-brand text-white shadow-sm leading-none">
                        All
                    </button>
                    <button onclick="setVehicleFilter('car')" data-filter="car" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-tertiary hover:text-brand transition-all leading-none">
                        <i class="fa-solid fa-car text-sm"></i>
                    </button>
                    <button onclick="setVehicleFilter('motorcycle')" data-filter="motorcycle" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-tertiary hover:text-brand transition-all leading-none">
                        <i class="fa-solid fa-motorcycle text-sm"></i>
                    </button>
                </div>

                <!-- Category Filter -->
                <div class="relative">
                    <button onclick="toggleCategoryDropdown(event)" 
                            class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                        <span id="activeCategoryLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary">All Entries</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-tertiary"></i>
                    </button>
                    
                    <div id="categoryDropdown" class="hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-xl shadow-xl z-50 py-2 overflow-hidden">
                        <button onclick="setCategoryFilter('all', 'All Entries')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">All Entries</button>
                        <button onclick="setCategoryFilter('reservation', 'Reservations')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Reservations</button>
                        <button onclick="setCategoryFilter('regular', 'Regular')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Regular</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="overflow-x-auto custom-scrollbar min-h-[350px]">
            <table class="w-full font-inter border-collapse table-fixed activity-table">
                <thead>
                    <tr class="border-b border-color">
                        <th class="py-3 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left pl-4">Vehicle</th>
                        <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Plate Number</th>
                        <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Ticket Code</th>
                        <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Slot</th>
                        <th class="py-3 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Entry</th>
                        <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Duration</th>
                        <th class="py-3 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Est. Fee</th>
                        <th class="py-3 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody id="activeFleetBody" class="divide-y divide-color">
                    <!-- Standard empty state -->
                    <tr id="noDataRow" class="<?= !empty($active) ? 'hidden' : '' ?>">
                        <td colspan="8" class="px-4 py-24 text-center">
                            <div class="flex flex-col items-center opacity-40">
                                <i class="fa-solid fa-car-tunnel text-5xl mb-4 text-slate-300"></i>
                                <p class="text-slate-500 font-inter font-medium text-sm">No active vehicles currently detected.</p>
                            </div>
                        </td>
                    </tr>

                    <?php foreach ($active as $row): 
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
                            $calc = calculate_fee($mins, $row['first_hour_rate'], $row['next_hour_rate']);
                            $est_fee = fmt_idr($calc['total_fee']);
                        }

                        $s_id = $row['slot_id'] ?? 0;
                        $display_slot = $slot_mapping[$s_id]['label'] ?? "#???";
                        $slot_label = $slot_mapping[$s_id]['category'] ?? "UNKNOWN";
                    ?>
                    <tr class="group hover:bg-surface-alt/50 transition-colors fleet-row" 
                        data-vehicle="<?= strtolower($row['vehicle_type']) ?>"
                        data-category="<?= $is_res ? 'reservation' : 'regular' ?>"
                        data-timestamp="<?= strtotime($row['check_in_time']) ?>">
                        <td class="py-2 pl-4 pr-4 text-left align-middle">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                    <i class="fa-solid fa-<?= strtolower($row['vehicle_type'] ?? '') == 'motorcycle' ? 'motorcycle' : 'car' ?> text-lg"></i>
                                </div>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <span class="plate-number text-sm font-manrope font-semibold text-primary leading-none">
                                    <?= !empty($row['plate_number']) ? htmlspecialchars($row['plate_number']) : '------' ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="ticket-code text-sm font-manrope font-semibold text-primary leading-none uppercase"><?= htmlspecialchars($row['ticket_code']) ?></span>
                                    <?php if ($row['payment_status'] === 'paid'): ?>
                                        <span class="badge-soft badge-soft-emerald text-[9px] leading-none">PAID</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase tracking-widest"><?= $is_res ? 'RESERVATION' : 'REGULAR' ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= $display_slot ?></span>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase tracking-wider"><?= $slot_label ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('H:i', strtotime($row['check_in_time'])) ?></span>
                                <span class="text-[10px] font-inter text-tertiary leading-none"><?= date('d M Y', strtotime($row['check_in_time'])) ?></span>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <span class="text-sm font-manrope font-semibold <?= $is_long_stay ? 'text-rose-500 animate-pulse' : 'text-primary' ?> leading-none">
                                    <?= $dur_text ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= $est_fee ?></span>
                            </div>
                        </td>
                        <td class="py-2 pr-4 pl-4 text-right align-middle relative">
                            <div class="flex justify-end items-center relative action-menu-container">
                                <button onclick="toggleActionMenu(this, event)" class="btn-ghost">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                
                                <div class="action-dropdown hidden absolute right-0 top-10 w-48 bg-surface border border-color rounded-xl shadow-xl z-50 py-2 overflow-hidden">
                                    <button onclick="handleLostTicket('<?= $row['ticket_code'] ?>', '<?= $row['plate_number'] ?>', <?= $is_res ? 0 : $calc['total_fee'] ?>, <?= $row['lost_ticket_fine'] ?? 50000 ?>)"
                                            class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-surface-alt transition-all group/item">
                                        <i class="fa-solid fa-print text-tertiary group-hover/item:text-brand text-xs"></i>
                                        <span class="text-[11px] font-bold text-primary uppercase tracking-wider">Lost Ticket</span>
                                    </button>
                                    <button onclick="handleForceDelete('<?= $row['ticket_code'] ?>', '<?= $row['plate_number'] ?>')"
                                            class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-rose-500/10 transition-all group/item">
                                        <i class="fa-solid fa-trash-can text-rose-500/40 group-hover/item:text-rose-500 text-xs"></i>
                                        <span class="text-[11px] font-bold text-rose-500 uppercase tracking-wider">Force Removal</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Protocol Info -->
    <?php if (!empty($active)): ?>
    <div class="bento-card p-5 mt-6 relative overflow-hidden">
        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-brand rounded-full"></div>
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-brand">
                <i class="fa-solid fa-circle-info text-lg"></i>
            </div>
            <div>
                <h4 class="font-manrope font-extrabold text-primary text-sm">Extended Stay Protocol</h4>
                <p class="font-inter text-tertiary text-[11px] mt-0.5">Units highlighted in red indicate parking duration exceeding the standard 24-hour operational window.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Receipt Modal (Aesthetic Update) -->
    <div id="receiptModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-surface p-8 w-full max-w-[340px] rounded-3xl shadow-2xl relative animate-in fade-in zoom-in duration-300 border border-color">
        <button onclick="closeReceipt()" class="absolute top-6 right-6 text-tertiary/40 hover:text-brand transition-all">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div id="receiptContent" class="receipt-thermal text-primary font-mono text-[12px] leading-tight">
            <div class="text-center mb-6">
                <h2 class="font-black text-lg uppercase tracking-tighter">Parkhere</h2>
                <p class="text-[10px] uppercase tracking-widest text-tertiary">Digital Receipt</p>
                <div class="border-b border-dashed border-color my-4 opacity-50"></div>
                <p class="font-bold uppercase">LOST TICKET FINE RECEIPT</p>
            </div>

            <div class="space-y-2 mb-6">
                <div class="flex justify-between"><span>DATE:</span> <span id="r-date" class="font-bold"></span></div>
                <div class="flex justify-between"><span>TICKET:</span> <span id="r-ticket" class="font-bold"></span></div>
                <div class="flex justify-between"><span>PLATE:</span> <span id="r-plate" class="font-bold"></span></div>
            </div>

            <div class="border-b border-dashed border-color my-4 opacity-50"></div>

            <div class="space-y-2 mb-6">
                <div class="flex justify-between">
                    <span>PARKING FEE:</span>
                    <span id="r-fee" class="font-bold"></span>
                </div>
                <div class="flex justify-between text-rose-500">
                    <span>LOST TICKET FINE:</span>
                    <span id="r-lost-fine" class="font-bold"></span>
                </div>
            </div>

            <div class="border-b border-double border-color my-4 opacity-50"></div>

            <div class="flex justify-between items-end mb-8">
                <span class="text-[10px] font-bold">TOTAL AMOUNT:</span>
                <span id="r-total" class="text-xl font-black tracking-tighter"></span>
            </div>

            <div class="text-center text-[10px] text-tertiary uppercase tracking-widest">
                <p>Thank you for your visit</p>
                <p>Drive Safely!</p>
            </div>
        </div>

        <button onclick="processCheckout()" class="w-full mt-8 py-4 bg-brand text-white rounded-2xl font-bold text-[11px] uppercase tracking-widest hover:shadow-xl hover:shadow-brand/20 transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-check-double"></i>
            COMPLETE TRANSACTION
        </button>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #receiptContent, #receiptContent * { visibility: visible !important; }
    #receiptModal { background: transparent !important; backdrop-filter: none !important; }
    #receiptContent {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
    }
}
</style>

<script>
let currentTicket = '';
let currentPlate = '';
let isLostMode = false;
let currentVehicleFilter = 'all';
let currentCategoryFilter = 'all';
let currentSortOrder = 'desc'; // default to newest first

function toggleSort() {
    currentSortOrder = currentSortOrder === 'desc' ? 'asc' : 'desc';
    const icon = document.getElementById('sortIcon');
    if (currentSortOrder === 'desc') {
        icon.className = 'fa-solid fa-sort-down text-[12px] text-brand';
    } else {
        icon.className = 'fa-solid fa-sort-up text-[12px] text-brand';
    }
    applyFilters();
}

function handleLostTicket(ticket, plate, baseFee, fineAmount) {
    currentTicket = ticket;
    currentPlate = plate;
    isLostMode = true;

    const fine = parseFloat(fineAmount) || 0;
    const total = baseFee + fine;
    const fmt = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
    
    document.getElementById('r-date').innerText = new Date().toLocaleString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    document.getElementById('r-ticket').innerText = ticket || 'N/A';
    document.getElementById('r-plate').innerText = plate || 'N/A';
    document.getElementById('r-fee').innerText = fmt(baseFee);
    document.getElementById('r-lost-fine').innerText = fmt(fine);
    document.getElementById('r-total').innerText = fmt(total);

    const modal = document.getElementById('receiptModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function handleForceDelete(ticket, plate) {
    if (confirm(`Warning: You are about to force removal of vehicle ${plate || ticket} from the system. Continue?`)) {
        currentTicket = ticket;
        currentPlate = plate;
        isLostMode = false;
        processCheckout();
    }
}

function forceCheckoutAll(btn) {
    if (!confirm('Critical Warning: This action will force checkout ALL vehicles. This cannot be undone. Continue?')) return;
    
    if (!btn) {
        btn = document.querySelector('button[onclick="forceCheckoutAll(this)"]');
    }
    if (!btn) return;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
    
    fetch('../../api/force_checkout_all.php', {
        method: 'POST'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            pushNotify('Error', data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
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
            location.reload();
        } else {
            pushNotify('Error', data.message, 'error');
        }
    });
}

function closeReceipt() {
    const modal = document.getElementById('receiptModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function toggleActionMenu(btn, e) {
    e.stopPropagation();
    const allMenus = document.querySelectorAll('.action-dropdown');
    const currentMenu = btn.nextElementSibling;

    allMenus.forEach(m => {
        if (m !== currentMenu) m.classList.add('hidden');
    });

    currentMenu.classList.toggle('hidden');
}

function applyFilters() {
    const searchInput = document.getElementById('logSearch');
    if (!searchInput) return;
    
    const search = searchInput.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.fleet-row');
    
    let filteredCount = 0;

    rows.forEach(row => {
        const plate = row.querySelector('.plate-number')?.textContent.toLowerCase() || '';
        const ticket = row.querySelector('.ticket-code')?.textContent.toLowerCase() || '';
        const vehicle = row.dataset.vehicle;
        const category = row.dataset.category;

        const matchesSearch = search === '' || plate.includes(search) || ticket.includes(search);
        const matchesVehicle = currentVehicleFilter === 'all' || vehicle === currentVehicleFilter;
        const matchesCategory = currentCategoryFilter === 'all' || category === currentCategoryFilter;

        if (matchesSearch && matchesVehicle && matchesCategory) {
            row.style.display = '';
            filteredCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noData = document.getElementById('noDataRow');
    if (noData) {
        if (filteredCount === 0) {
            noData.classList.remove('hidden');
        } else {
            noData.classList.add('hidden');
        }
    }

    // Sort the filtered rows
    const tbody = document.getElementById('activeFleetBody');
    const rowsArray = Array.from(document.querySelectorAll('.fleet-row'));
    
    rowsArray.sort((a, b) => {
        const timeA = parseInt(a.dataset.timestamp);
        const timeB = parseInt(b.dataset.timestamp);
        return currentSortOrder === 'desc' ? timeB - timeA : timeA - timeB;
    });

    // Re-append sorted rows
    rowsArray.forEach(row => tbody.appendChild(row));
}

function setVehicleFilter(type) {
    currentVehicleFilter = type;
    document.querySelectorAll('.vehicle-filter-btn').forEach(btn => {
        if (btn.dataset.filter === type) {
            btn.classList.add('bg-brand', 'text-white', 'shadow-sm');
            btn.classList.remove('text-tertiary');
        } else {
            btn.classList.remove('bg-brand', 'text-white', 'shadow-sm');
            btn.classList.add('text-tertiary');
        }
    });
    applyFilters();
}

function toggleCategoryDropdown(e) {
    e.stopPropagation();
    document.getElementById('categoryDropdown').classList.toggle('hidden');
}

function setCategoryFilter(val, label) {
    currentCategoryFilter = val;
    document.getElementById('activeCategoryLabel').textContent = label;
    document.getElementById('categoryDropdown').classList.add('hidden');
    applyFilters();
}

document.getElementById('logSearch').addEventListener('input', applyFilters);

document.addEventListener('click', () => {
    document.querySelectorAll('.action-dropdown').forEach(m => m.classList.add('hidden'));
    const catDropdown = document.getElementById('categoryDropdown');
    if (catDropdown) catDropdown.classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', applyFilters);
</script>

<?php include '../../includes/footer.php'; ?>
