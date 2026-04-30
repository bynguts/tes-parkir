<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

sync_slot_statuses($pdo);

// --- GLOBAL SLOT MAPPING (Indigo Night Standard) ---
$all_slots_query = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.is_reservation_only, f.floor_code
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    ORDER BY ps.is_reservation_only ASC, f.floor_code ASC, ps.slot_type ASC, ps.slot_number ASC
");
$slot_mapping = [];
foreach ($all_slots_query as $s) {
    $is_vip = (int)$s['is_reservation_only'] === 1;
    $slot_mapping[$s['slot_id']] = [
        "label"    => $s['slot_number'],
        "category" => $is_vip ? "RSV ZONE" : "REGULAR"
    ];
}

// --- DATE FILTER LOGIC ---
$range = $_GET['range'] ?? 'today';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if ($range !== 'custom') {
    $end_dt = new DateTime();
    $end_date = $end_dt->format('Y-m-d');
    
    switch ($range) {
        case 'hour': $start_date = date('Y-m-d H:i:s', strtotime('-1 hour')); break;
        case 'today': 
            $start_date = date('Y-m-d'); 
            break;
        case '24h': $start_date = date('Y-m-d H:i:s', strtotime('-24 hours')); break;
        case '1week': $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case '1month': $start_date = date('Y-m-d', strtotime('-30 days')); break;
        default: $start_date = date('Y-m-d'); break;
    }
}

$db_start = $start_date . (strlen($start_date) <= 10 ? ' 00:00:00' : '');
$db_end = $end_date . (strlen($end_date) <= 10 ? ' 23:59:59' : '');

// Fetch scan logs with transaction and vehicle details
$query = "
    SELECT * FROM (
        (SELECT 
            x.scan_id, 
            x.scan_time, 
            x.scan_type, 
            x.ticket_code, 
            x.gate_action,
            COALESCE(t.check_in_time, r_existing.reserved_from) as time_in,
            t.check_out_time as time_out,
            t.total_fee as final_fee,
            t.duration_hours,
            t.payment_status,
            t.is_lost_ticket,
            t.is_force_checkout,
            COALESCE(t.reservation_id, r_existing.reservation_id) as reservation_id,
            COALESCE(v.plate_number, r_v_existing.plate_number, x.plate_number) as plate_number,
            COALESCE(v.vehicle_type, r_v_existing.vehicle_type) as vehicle_type,
            COALESCE(s.slot_id, r_s_existing.slot_id) as slot_id
        FROM plate_scan_log x
        LEFT JOIN `transaction` t ON x.ticket_code = t.ticket_code
        LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN parking_slot s ON t.slot_id = s.slot_id
        LEFT JOIN floor f ON s.floor_id = f.floor_id
        LEFT JOIN `reservation` r_existing ON x.ticket_code = r_existing.reservation_code
        LEFT JOIN vehicle r_v_existing ON r_existing.vehicle_id = r_v_existing.vehicle_id
        LEFT JOIN parking_slot r_s_existing ON r_existing.slot_id = r_s_existing.slot_id
        LEFT JOIN floor r_f_existing ON r_s_existing.floor_id = r_f_existing.floor_id
        WHERE x.scan_time BETWEEN ? AND ?)
        
        UNION ALL
        
        (SELECT 
            NULL as scan_id, 
            res.reserved_from as scan_time, 
            'entry' as scan_type, 
            res.reservation_code as ticket_code, 
            'open' as gate_action,
            res.reserved_from as time_in,
            NULL as time_out,
            NULL as final_fee,
            NULL as duration_hours,
            'unpaid' as payment_status,
            0 as is_lost_ticket,
            0 as is_force_checkout,
            res.reservation_id,
            rv.plate_number,
            rv.vehicle_type,
            rs.slot_id
        FROM `reservation` res
        JOIN vehicle rv ON res.vehicle_id = rv.vehicle_id
        JOIN parking_slot rs ON res.slot_id = rs.slot_id
        JOIN floor rf ON rs.floor_id = rf.floor_id
        WHERE res.reserved_from BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM plate_scan_log psl WHERE psl.ticket_code = res.reservation_code)
          AND NOT EXISTS (SELECT 1 FROM `transaction` t WHERE t.reservation_id = res.reservation_id))
    ) combined
    ORDER BY scan_time DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$db_start, $db_end, $db_start, $db_end]);
$logs = $stmt->fetchAll();


$page_title = 'Security Scan Log';
$page_subtitle = "Viewing security sensor events from " . date('d M', strtotime($db_start)) . " to " . date('d M', strtotime($db_end));

include '../../includes/header.php';
?>



<div class="px-10 py-10">

    <!-- Page Header (Premium Style) -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>
        
        <div class="flex items-center gap-3">
            <form id="filterForm" method="GET" class="flex items-center gap-3 bg-surface border border-color p-1.5 rounded-xl shadow-sm">
                <div class="relative">
                    <select name="range" id="range-select"
                            class="appearance-none bg-surface-alt border-none px-5 py-2.5 pr-10 rounded-lg text-[10px] font-black uppercase tracking-widest text-primary focus:outline-none transition-all cursor-pointer">
                        <option value="today"  <?= $range === 'today'  ? 'selected' : '' ?>>Today</option>
                        <option value="24h"    <?= $range === '24h'    ? 'selected' : '' ?>>Past 24 Hours</option>
                        <option value="1week"  <?= $range === '1week'  ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="1month" <?= $range === '1month' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none text-[8px]"></i>
                </div>

                <!-- Hidden inputs for custom range -->
                <input type="hidden" name="start_date" id="start_date" value="<?= $start_date ?>">
                <input type="hidden" name="end_date"   id="end_date"   value="<?= $end_date ?>">
                <input type="text"   id="range-picker-trigger" class="absolute opacity-0 pointer-events-none w-0 h-0">

                <?php if($range === 'custom'): ?>
                <div class="flex items-center gap-2 px-3 border-l border-color">
                    <button type="button" id="change-range-btn" class="flex items-center gap-2 hover:text-brand transition-colors group">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary">
                            <?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?>
                        </span>
                        <i class="fa-solid fa-calendar-days text-tertiary group-hover:text-brand text-xs"></i>
                    </button>
                </div>
                <?php endif; ?>
            </form>

            <button type="button" onclick="openPurgeModal()" class="btn-danger-soft h-[38px]">
                <i class="fa-solid fa-broom text-[12px]"></i>
                <span>Purge Logs</span>
            </button>
        </div>
    </div>
    
    <!-- Table Content Card -->
    <div class="bento-card overflow-hidden mt-6">
        <!-- Filters Header -->
        <div class="flex items-center justify-between py-5 px-4 border-b border-color">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Operational Index</h3>
                    <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Gate sensor history</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <!-- Sort -->
                <button onclick="toggleLogSort()" id="sortLogBtn" 
                        class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                    <i id="sortLogIcon" class="fa-solid fa-sort text-[12px] text-tertiary group-hover:text-brand"></i>
                    <span class="text-[11px] font-inter font-medium tracking-wider text-primary">Sort</span>
                </button>

                <!-- Search -->
                <div class="relative group">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-tertiary text-sm"></i>
                    <input type="text" id="searchLog" placeholder="Search plate or ticket..."
                           oninput="applyScanLogFilters()"
                           class="w-44 bg-surface-alt border border-color rounded-xl h-[38px] pl-10 pr-4 text-[11px] font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-surface transition-all">
                </div>

                <!-- Vehicle Filter -->
                <div class="flex items-center bg-surface-alt border border-color rounded-xl p-1 gap-1 h-[38px]">
                    <button onclick="setScanLogVehicleFilter('all')" data-filter="all" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-[11px] font-inter font-medium tracking-wider transition-all bg-brand text-white shadow-sm leading-none">All</button>
                    <button onclick="setScanLogVehicleFilter('car')" data-filter="car" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-tertiary hover:text-brand transition-all leading-none">
                        <i class="fa-solid fa-car text-sm pointer-events-none"></i>
                    </button>
                    <button onclick="setScanLogVehicleFilter('motorcycle')" data-filter="motorcycle" 
                            class="vehicle-filter-btn w-11 h-[30px] flex items-center justify-center rounded-lg text-tertiary hover:text-brand transition-all leading-none">
                        <i class="fa-solid fa-motorcycle text-sm pointer-events-none"></i>
                    </button>
                </div>

                <!-- Type Filter -->
                <div class="relative">
                    <button onclick="toggleScanLogCategoryDropdown(event)" class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all">
                        <span id="scanLogCategoryLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary">All Entries</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-tertiary"></i>
                    </button>
                    <div id="scanLogCategoryDropdown" class="hidden absolute right-0 top-12 w-48 bg-surface border border-color rounded-xl shadow-xl z-50 py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                        <button onclick="setScanLogCategoryFilter('all', 'All Entries')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">All Entries</button>
                        <button onclick="setScanLogCategoryFilter('reservation', 'Reservations')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Reservations</button>
                        <button onclick="setScanLogCategoryFilter('regular', 'Regular')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Regular</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="overflow-x-auto custom-scrollbar min-h-[350px] pb-4">
            <table class="w-full min-w-[1600px] font-inter border-collapse table-auto activity-table" id="logTable">
                <thead>
                    <tr class="border-b border-color">
                        <th class="py-3 pl-4 text-left text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Vehicle</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Plate Number</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Ticket Code</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Slot</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Entry</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Exit</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Duration</th>
                        <th class="py-3 px-4 text-center text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider min-w-[150px]">Final Fee</th>
                        <th class="py-3 px-4 text-right text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider">Status</th>
                        <th class="py-3 pr-4 text-right text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody id="logTableBody" class="divide-y divide-color">
                    <tr id="noDataRow" class="<?= !empty($logs) ? 'hidden' : '' ?>">
                        <td colspan="10" class="px-6 py-24 text-center">
                            <div class="flex flex-col items-center opacity-40">
                                <i class="fa-solid fa-clock-rotate-left text-5xl mb-4 text-slate-300"></i>
                                <p class="text-secondary font-inter font-medium text-sm">No operational records match your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($logs)): 
                        foreach ($logs as $row): 
                        $hours_val = (float)($row['duration_hours'] ?? 0);
                        $h = floor($hours_val);
                        $m = round(($hours_val - $h) * 60);
                        $dur = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                        
                        $is_res = !empty($row['reservation_id']);
                        $is_pending = empty($row['scan_id']);
                        $is_departed = !$is_pending && !empty($row['time_out']);
                        
                        $s_id = $row['slot_id'] ?? 0;
                        if ($is_departed) {
                            $display_slot = '<span class="opacity-20 italic">---</span>';
                            $slot_label = 'RELEASED';
                        } elseif (isset($slot_mapping[$s_id])) {
                            $display_slot = $slot_mapping[$s_id]['label'];
                            $slot_label = $slot_mapping[$s_id]['category'];
                        } else {
                            $display_slot = '<span class="opacity-20">#???</span>';
                            $slot_label = 'UNKNOWN';
                        }

                        if ($is_pending) $dur = '---';
                    ?>
                    <tr class="group hover:bg-surface-alt/50 transition-colors fleet-row log-row" 
                        data-vehicle="<?= trim(strtolower($row['vehicle_type'] ?? '')) ?>"
                        data-category="<?= $is_res ? 'reservation' : 'regular' ?>"
                        data-timestamp="<?= strtotime($row['time_in']) ?>">
                        <!-- Vehicle -->
                        <td class="py-2 pl-4 pr-4 text-left align-middle">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                    <i class="fa-solid fa-<?= strtolower($row['vehicle_type'] ?? '') == 'motorcycle' ? 'motorcycle' : 'car' ?> text-lg"></i>
                                </div>
                            </div>
                        </td>

                        <!-- Plate Number -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <span class="plate-number text-sm font-manrope font-semibold text-primary leading-none">
                                    <?= !empty($row['plate_number']) ? htmlspecialchars($row['plate_number']) : '------' ?>
                                </span>
                            </div>
                        </td>

                        <!-- Ticket Code -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="ticket-code text-sm font-manrope font-semibold text-primary leading-none uppercase"><?= htmlspecialchars($row['ticket_code']) ?></span>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase tracking-widest"><?= $is_pending ? 'EXPECTED' : 'LOG ENTRY' ?></span>
                            </div>
                        </td>

                        <!-- Slot -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= $display_slot ?></span>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase tracking-wider"><?= $slot_label ?></span>
                            </div>
                        </td>

                        <!-- Entry -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('H:i', strtotime($row['time_in'])) ?></span>
                                <span class="text-[10px] font-inter text-tertiary leading-none"><?= date('d M Y', strtotime($row['time_in'])) ?></span>
                            </div>
                        </td>

                        <!-- Exit -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <?php if ($row['time_out']): ?>
                                    <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= date('H:i', strtotime($row['time_out'])) ?></span>
                                    <span class="text-[10px] font-inter text-tertiary leading-none"><?= date('d M Y', strtotime($row['time_out'])) ?></span>
                                <?php else: ?>
                                    <span class="text-[11px] font-inter text-slate-200 tracking-widest leading-none">---:---</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Duration -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <?php 
                                    $is_long_stay = ($hours_val >= 24); 
                                ?>
                                <span class="text-sm font-manrope font-semibold <?= $is_long_stay ? 'text-rose-500 animate-pulse' : 'text-primary' ?> leading-none">
                                    <?= $dur ?>
                                </span>
                            </div>
                        </td>

                        <!-- Final Fee -->
                        <td class="py-2 px-4 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <span class="text-sm font-manrope font-semibold text-primary leading-none">
                                    <?= $row['final_fee'] > 0 ? fmt_idr($row['final_fee']) : 'Rp 0' ?>
                                </span>
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="py-2 px-4 text-right align-middle">
                            <div class="flex justify-end gap-1.5 ml-auto flex-wrap">
                                <?php 
                                    $pay_status = strtolower($row['payment_status'] ?? '');
                                    $is_lost = (int)($row['is_lost_ticket'] ?? 0);
                                    $is_force = (int)($row['is_force_checkout'] ?? 0);
                                ?>
                                <?php if ($is_pending): ?>
                                    <span class="badge-soft badge-soft-amber">RESERVED</span>
                                <?php elseif ($is_departed): ?>
                                    <span class="badge-soft badge-soft-slate">DEPARTED</span>
                                <?php else: ?>
                                    <span class="badge-soft badge-soft-indigo">ACTIVE</span>
                                <?php endif; ?>
                                
                                <?php if ($is_lost): ?>
                                    <span class="badge-soft badge-soft-rose">LOST</span>
                                <?php endif; ?>
                                <?php if ($is_force): ?>
                                    <span class="badge-soft badge-soft-amber">FORCE</span>
                                <?php endif; ?>
                                <?php if ($pay_status === 'paid'): ?>
                                    <span class="badge-soft badge-soft-emerald">PAID</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Action -->
                        <td class="py-2 pr-4 pl-4 text-right align-middle relative">
                            <button type="button" 
                                    onclick="deleteSingleLog(this, '<?= $row['scan_id'] ?>', '<?= $row['reservation_id'] ?>', '<?= $row['ticket_code'] ?>')"
                                    class="w-8 h-8 rounded-lg border border-color hover:border-rose-500 hover:bg-rose-500/5 text-secondary hover:text-rose-500 transition-all group/btn">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purge Confirmation Modal -->
<div id="modalDelete" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <div class="bento-card overflow-hidden modal-surface">
            <div class="px-4 py-5 border-b border-color flex justify-between items-center">
                <h3 class="font-manrope font-extrabold text-primary text-base">Purge Operational Logs</h3>
                <button onclick="document.getElementById('modalDelete').classList.add('hidden')" class="btn-ghost">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-8">
                <div class="flex gap-2 mb-6 bg-surface-alt border border-color p-1 rounded-2xl">
                    <button id="tabBtnDate" onclick="switchTab('date')"
                            class="flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider bg-brand text-white shadow-lg transition-all">By Date</button>
                    <button id="tabBtnAll" onclick="switchTab('all')"
                            class="flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider text-rose-500 hover:bg-rose-500/10 transition-all">Wipe All</button>
                </div>

                <div id="tabDate">
                    <div id="dateList" class="max-h-52 overflow-y-auto no-scrollbar rounded-2xl bg-surface-alt p-2 mb-6 space-y-1 border border-color"></div>
                    <button id="btnDeleteDate" disabled onclick="deleteLog('by_date')"
                            class="w-full py-4 bg-brand text-white rounded-2xl font-bold text-[11px] uppercase tracking-widest disabled:opacity-30 disabled:grayscale transition-all flex items-center justify-center gap-3">
                        <i class="fa-solid fa-calendar-xmark"></i> Purge Selected Date
                    </button>
                </div>

                <div id="tabAll" class="hidden">
                    <div class="bg-rose-500/5 rounded-2xl p-6 mb-6 text-center border border-rose-500/20">
                        <i class="fa-solid fa-triangle-exclamation text-rose-500 text-3xl block mb-4"></i>
                        <p class="text-rose-500 font-extrabold text-sm font-manrope uppercase tracking-tight">Full System Purge</p>
                        <p class="text-secondary text-[11px] font-inter mt-3 leading-relaxed">This will permanently delete all gate sensor history. <span class="text-primary font-bold block mt-1">Transaction and financial records remain secure.</span></p>
                    </div>
                    <button onclick="confirmWipeAll()"
                            class="w-full py-4 bg-rose-500 text-white rounded-2xl font-bold text-[11px] uppercase tracking-widest hover:brightness-110 transition-all flex items-center justify-center gap-3 shadow-lg shadow-rose-500/20">
                        <i class="fa-solid fa-fire"></i> Execute Wipe
                    </button>
                </div>
                <div id="deleteResult" class="mt-4 hidden text-[11px] font-bold uppercase tracking-widest text-center py-3 rounded-xl"></div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
let currentLogVehicleFilter = 'all';
let currentLogCategoryFilter = 'all';
let currentLogSortOrder = 'desc'; // newest first

function toggleLogSort() {
    currentLogSortOrder = currentLogSortOrder === 'desc' ? 'asc' : 'desc';
    const icon = document.getElementById('sortLogIcon');
    if (currentLogSortOrder === 'desc') {
        icon.className = 'fa-solid fa-sort-down text-[12px] text-brand';
    } else {
        icon.className = 'fa-solid fa-sort-up text-[12px] text-brand';
    }
    applyScanLogFilters();
}
let selectedDate = null;


function switchTab(tab) {
    const tabDate = document.getElementById('tabDate');
    const tabAll = document.getElementById('tabAll');
    const btnDate = document.getElementById('tabBtnDate');
    const btnAll = document.getElementById('tabBtnAll');

    if (tab === 'date') {
        tabDate.classList.remove('hidden');
        tabAll.classList.add('hidden');
        btnDate.className = "flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider bg-brand text-white shadow-lg shadow-brand/20 transition-all";
        btnAll.className = "flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider text-rose-500 hover:bg-rose-500/10 transition-all";
    } else {
        tabDate.classList.add('hidden');
        tabAll.classList.remove('hidden');
        btnDate.className = "flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider text-secondary hover:bg-surface-alt transition-all";
        btnAll.className = "flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider bg-rose-500 text-white shadow-lg shadow-rose-500/20 transition-all";
    }
}

function openPurgeModal() {
    document.getElementById('modalDelete').classList.remove('hidden');
    document.getElementById('modalDelete').classList.add('flex');
    loadDates();
}

function loadDates() {
    // Show loading state
    const dateList = document.getElementById('dateList');
    dateList.innerHTML = '<div class="py-4 text-center text-secondary">Loading dates...</div>';

    // Fetch available dates with scan logs
    fetch('get_log_dates.php', { method: 'GET' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.dates && data.dates.length > 0) {
            dateList.innerHTML = '';
            data.dates.forEach(date => {
                const dateElement = document.createElement('div');
                dateElement.className = 'flex items-center justify-between px-3 py-2 rounded-lg hover:bg-surface-alt transition-colors cursor-pointer date-item';
                dateElement.dataset.date = date;
                dateElement.innerHTML = `
                    <span class="text-[11px] font-inter text-primary">${date}</span>
                    <i class="fa-solid fa-check text-[9px] text-brand hidden date-check"></i>
                `;
                dateElement.onclick = () => selectDate(date);
                dateList.appendChild(dateElement);
            });

            // Enable the purge button (it will be enabled when a date is selected)
            document.getElementById('btnDeleteDate').disabled = true;
        } else {
            dateList.innerHTML = '<div class="py-4 text-center text-secondary">No dates with logs found</div>';
            document.getElementById('btnDeleteDate').disabled = true;
        }
    })
    .catch(err => {
        console.error('Error loading dates:', err);
        dateList.innerHTML = '<div class="py-4 text-center text-secondary">Error loading dates</div>';
        document.getElementById('btnDeleteDate').disabled = true;
    });
}

function selectDate(date) {
    selectedDate = date;
    // Update UI to show selected date
    document.querySelectorAll('.date-item').forEach(item => {
        if (item.dataset.date === date) {
            item.classList.add('bg-brand');
            item.querySelector('.date-check').classList.remove('hidden');
        } else {
            item.classList.remove('bg-brand');
            item.querySelector('.date-check').classList.add('hidden');
        }
    });
    // Enable the purge button
    document.getElementById('btnDeleteDate').disabled = false;
}

function closePurgeModal() {
    document.getElementById('modalDelete').classList.add('hidden');
    document.getElementById('modalDelete').classList.remove('flex');
}

// Custom Confirm System
let confirmCallback = null;
function showConfirm(title, message, callback) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    confirmCallback = callback;
    const modal = document.getElementById('customConfirmModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeConfirm() {
    const modal = document.getElementById('customConfirmModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    confirmCallback = null;
}

function handleConfirm() {
    if (confirmCallback) confirmCallback();
    closeConfirm();
}

function confirmWipeAll() {
    showConfirm(
        'Critical Action',
        'CRITICAL WARNING: This will permanently delete ALL gate sensor history. This cannot be undone. Continue?',
        () => {
            deleteLog('all');
        }
    );
}

function deleteLog(mode) {
    const box = document.getElementById('deleteResult');
    const body = mode === 'by_date' ? `mode=by_date&date=${selectedDate}&csrf_token=${encodeURIComponent(CSRF)}` : `mode=all&csrf_token=${encodeURIComponent(CSRF)}`;
    
    fetch('delete_logs.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
    .then(r => r.json()).then(data => {
        box.classList.remove('hidden');
        if (data.success) { 
            box.className = 'mt-4 py-3 rounded-xl bg-emerald-50 text-emerald-600 text-[11px] font-bold uppercase tracking-widest text-center'; 
            box.innerHTML = 'Logs successfully purged'; 
            setTimeout(() => location.reload(), 1200); 
        } else {
            box.className = 'mt-4 py-3 rounded-xl bg-rose-50 text-rose-600 text-[11px] font-bold uppercase tracking-widest text-center';
            box.innerHTML = data.message || 'Error purging logs';
        }
    });
}

function deleteSingleLog(btn, scanId, resId, ticket) {
    showConfirm(
        'Delete Log Entry',
        `Permanently delete log entry for ticket ${ticket}? This action cannot be undone.`,
        () => {
            const row = btn.closest('tr');
            row.classList.add('opacity-40', 'pointer-events-none');
            
            const body = `mode=single&scan_id=${scanId}&reservation_id=${resId}&ticket=${ticket}&csrf_token=${encodeURIComponent(CSRF)}`;
            
            fetch('delete_logs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    row.classList.add('transition-all', 'duration-500', 'opacity-0', 'translate-x-4');
                    setTimeout(() => {
                        row.remove();
                        applyScanLogFilters();
                    }, 500);
                } else {
                    pushNotify('Error', data.message || 'Error deleting record', 'error');
                    row.classList.remove('opacity-40', 'pointer-events-none');
                }
            })
            .catch(err => {
                row.classList.remove('opacity-40', 'pointer-events-none');
            });
        }
    );
}

function setScanLogVehicleFilter(type) {
    currentLogVehicleFilter = type;
    document.querySelectorAll('.vehicle-filter-btn').forEach(btn => {
        btn.classList.remove('bg-brand', 'text-white', 'shadow-sm');
        btn.classList.add('text-tertiary');
    });
    const activeBtn = document.querySelector(`.vehicle-filter-btn[data-filter="${type}"]`);
    if (activeBtn) {
        activeBtn.classList.add('bg-brand', 'text-white', 'shadow-sm');
        activeBtn.classList.remove('text-tertiary');
    }
    applyScanLogFilters();
}

function toggleScanLogCategoryDropdown(e) {
    e.stopPropagation();
    const dd = document.getElementById('scanLogCategoryDropdown');
    if (dd) dd.classList.toggle('hidden');
}

function setScanLogCategoryFilter(val, label) {
    currentLogCategoryFilter = val;
    const labelEl = document.getElementById('scanLogCategoryLabel');
    if (labelEl) labelEl.textContent = label;
    const dd = document.getElementById('scanLogCategoryDropdown');
    if (dd) dd.classList.add('hidden');
    applyScanLogFilters();
}

function applyScanLogFilters() {
    const searchInput = document.getElementById('searchLog');
    if (!searchInput) return;
    
    const q = searchInput.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.log-row');
    
    let filteredCount = 0;

    rows.forEach(tr => {
        const text = tr.textContent.toLowerCase();
        const vehicle = (tr.getAttribute('data-vehicle') || '').trim();
        const category = (tr.getAttribute('data-category') || '').trim();
        
        const matchSearch = q === '' || text.includes(q);
        const matchVehicle = currentLogVehicleFilter === 'all' || vehicle === currentLogVehicleFilter;
        const matchCategory = currentLogCategoryFilter === 'all' || category === currentLogCategoryFilter;
        
        if (matchSearch && matchVehicle && matchCategory) {
            tr.style.display = '';
            filteredCount++;
        } else {
            tr.style.display = 'none';
        }
    });

    const noData = document.getElementById('noDataRow');
    if (noData) {
        noData.classList.toggle('hidden', filteredCount > 0);
    }

    // Sort the filtered rows
    const tbody = document.querySelector('#logTable tbody');
    const rowsArray = Array.from(document.querySelectorAll('.log-row'));
    
    rowsArray.sort((a, b) => {
        const timeA = parseInt(a.dataset.timestamp);
        const timeB = parseInt(b.dataset.timestamp);
        return currentLogSortOrder === 'desc' ? timeB - timeA : timeA - timeB;
    });

    // Re-append sorted rows
    rowsArray.forEach(row => tbody.appendChild(row));
}

document.addEventListener('DOMContentLoaded', function() {
    applyScanLogFilters();

    // --- FLATPICKR QUICK CALENDAR ---
    const rangeSelect = document.getElementById('range-select');
    const trigger     = document.getElementById('range-picker-trigger');
    const form        = document.getElementById('filterForm');

    if (rangeSelect && trigger && form) {
        const fp = flatpickr(trigger, {
            mode: 'range',
            monthSelectorType: 'dropdown',
            dateFormat: 'Y-m-d',
            defaultDate: ['<?= $start_date ?>', '<?= $end_date ?>'],
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    document.getElementById('start_date').value = instance.formatDate(selectedDates[0], 'Y-m-d');
                    document.getElementById('end_date').value   = instance.formatDate(selectedDates[1], 'Y-m-d');
                    form.submit();
                } else {
                    if (rangeSelect.value === 'custom' && '<?= $range ?>' !== 'custom') {
                        rangeSelect.value = '<?= $range ?>';
                    }
                }
            }
        });

        rangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                fp.open();
            } else {
                form.submit();
            }
        });

        const changeBtn = document.getElementById('change-range-btn');
        if (changeBtn) changeBtn.addEventListener('click', () => fp.open());
    }

    document.addEventListener('click', (e) => {
        const dd = document.getElementById('scanLogCategoryDropdown');
        if (dd && !e.target.closest('.relative')) {
            dd.classList.add('hidden');
        }
    });
});
</script>

<!-- Custom Confirm Modal -->
<div id="customConfirmModal" class="hidden fixed inset-0 z-[10000] items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeConfirm()"></div>
    <div class="bento-card relative w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-300 shadow-2xl border-color bg-surface">
        <div class="p-8 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-rose-500/10 flex items-center justify-center mb-6">
                <i class="fa-solid fa-triangle-exclamation text-2xl text-rose-500"></i>
            </div>
            <h3 id="confirmTitle" class="text-xl font-manrope font-extrabold text-primary mb-3">Confirmation Required</h3>
            <p id="confirmMessage" class="text-[13px] font-medium text-tertiary leading-relaxed mb-8">Are you sure you want to proceed with this action?</p>
            
            <div class="flex gap-3 w-full">
                <button onclick="closeConfirm()" class="flex-1 h-12 rounded-xl bg-surface-alt border border-color text-primary font-bold text-[11px] uppercase tracking-widest hover:bg-surface transition-all">
                    Cancel
                </button>
                <button onclick="handleConfirm()" class="flex-1 h-12 rounded-xl bg-rose-500 text-white font-bold text-[11px] uppercase tracking-widest hover:bg-rose-600 transition-all shadow-lg shadow-rose-500/20">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
