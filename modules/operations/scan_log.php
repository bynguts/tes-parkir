<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

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
            COALESCE(s.slot_number, r_s_existing.slot_number) as slot_number,
            COALESCE(f.floor_code, r_f_existing.floor_code) as floor
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
            rs.slot_number,
            rf.floor_code as floor
        FROM `reservation` res
        JOIN vehicle rv ON res.vehicle_id = rv.vehicle_id
        JOIN parking_slot rs ON res.slot_id = rs.slot_id
        JOIN floor rf ON rs.floor_id = rf.floor_id
        WHERE res.reserved_from BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM plate_scan_log psl WHERE psl.ticket_code = res.reservation_code))
    ) combined
    ORDER BY scan_time DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$db_start, $db_end, $db_start, $db_end]);
$logs = $stmt->fetchAll();

// --- CHART DATA FETCHING ---
if ($range === 'today' || $range === '24h' || $range === 'hour') {
    $chart_query = "
        SELECT DATE_FORMAT(t.scan_time, '%h %p') as label, COUNT(*) as count
        FROM (
            SELECT scan_time FROM plate_scan_log WHERE scan_time BETWEEN ? AND ?
            UNION ALL
            SELECT reserved_from as scan_time FROM `reservation` 
            WHERE reserved_from BETWEEN ? AND ?
            AND NOT EXISTS (SELECT 1 FROM plate_scan_log psl WHERE psl.ticket_code = `reservation`.reservation_code)
        ) t
        GROUP BY DATE(t.scan_time), HOUR(t.scan_time)
        ORDER BY t.scan_time ASC
    ";
} else {
    $chart_query = "
        SELECT DATE_FORMAT(t.scan_time, '%d %b') as label, COUNT(*) as count
        FROM (
            SELECT scan_time FROM plate_scan_log WHERE scan_time BETWEEN ? AND ?
            UNION ALL
            SELECT reserved_from as scan_time FROM `reservation` 
            WHERE reserved_from BETWEEN ? AND ?
            AND NOT EXISTS (SELECT 1 FROM plate_scan_log psl WHERE psl.ticket_code = `reservation`.reservation_code)
        ) t
        GROUP BY DATE(t.scan_time)
        ORDER BY t.scan_time ASC
    ";
}

$chart_stmt = $pdo->prepare($chart_query);
$chart_stmt->execute([$db_start, $db_end, $db_start, $db_end]);
$chart_data = $chart_stmt->fetchAll();

$page_title = 'Security Scan Log';
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.filter-btn-log {
    transition: all 0.2s ease;
    cursor: pointer !important;
    border: none;
    outline: none;
}
.filter-btn-log:hover {
    background: rgba(99, 102, 241, 0.1);
}
.filter-btn-log.active {
    background: var(--brand) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}
.filter-container {
    position: relative;
    z-index: 50;
}
</style>

<div class="px-10 py-6 max-w-[1600px] mx-auto flex flex-col gap-5">

    <!-- CHART SECTION -->
    <div class="bg-surface rounded-[2.5rem] p-8 border border-color shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="card-title text-lg">Activity Analytics</h3>
                <p class="text-tertiary text-[10px] font-bold uppercase tracking-widest mt-1">Scan volume distribution</p>
            </div>
            
            <form id="filterForm" method="GET" class="flex items-center gap-3">
                <div class="relative">
                    <select name="range" onchange="this.form.submit()" class="appearance-none bg-surface-alt border border-color px-6 py-2.5 pr-12 rounded-2xl text-[10px] font-black uppercase tracking-widest text-primary focus:outline-none transition-all cursor-pointer">
                        <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>Past 24 Hours</option>
                        <option value="1week" <?= $range === '1week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="1month" <?= $range === '1month' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none text-[8px]"></i>
                </div>

                <?php if($range === 'custom'): ?>
                <div class="flex items-center gap-2 bg-surface-alt border border-color p-1 rounded-xl">
                    <input type="date" name="start_date" value="<?= $start_date ?>" class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest text-primary focus:ring-0 px-2 py-1">
                    <span class="text-tertiary font-bold">-</span>
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest text-primary focus:ring-0 px-2 py-1">
                    <button type="submit" class="bg-brand text-white w-7 h-7 rounded-lg flex items-center justify-center hover:bg-indigo-700 transition-all">
                        <i class="fa-solid fa-check text-[10px]"></i>
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="h-[180px] w-full">
            <canvas id="scanActivityChart"></canvas>
        </div>
    </div>
    
    <!-- Main Card with Table -->
    <div class="bento-card flex-1 flex flex-col overflow-hidden min-h-0">
        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-color shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Security Scan History</h3>
                    <p class="text-[11px] text-tertiary font-inter">Gate operational sensor events</p>
                </div>
            </div>

            <div class="flex items-center gap-3 filter-container">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-tertiary text-xs opacity-40"></i>
                    <input type="text" id="searchLog" placeholder="Search ticket or plate..."
                           oninput="currentLimit = 20; applyScanLogFilters()"
                           class="modal-input pl-10 pr-5 py-2.5 rounded-xl text-[12px] font-inter focus:outline-none w-64 transition-all border border-color">
                </div>

                <div class="flex items-center bg-surface-alt border border-color rounded-xl p-1 gap-1 shadow-sm">
                    <button type="button" onclick="setScanLogVehicleFilter('all')" id="btn-filter-all" 
                            class="filter-btn-log active px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider">ALL</button>
                    <button type="button" onclick="setScanLogVehicleFilter('car')" id="btn-filter-car" 
                            class="filter-btn-log px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider">
                        <i class="fa-solid fa-car text-xs pointer-events-none"></i>
                    </button>
                    <button type="button" onclick="setScanLogVehicleFilter('motorcycle')" id="btn-filter-motorcycle" 
                            class="filter-btn-log px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider">
                        <i class="fa-solid fa-motorcycle text-xs pointer-events-none"></i>
                    </button>
                </div>

                <div class="relative">
                    <select id="filterCategory" onchange="setScanLogTypeFilter(this.value)" 
                            class="appearance-none bg-surface-alt border border-color px-6 py-2.5 pr-12 rounded-xl text-[10px] font-black uppercase tracking-widest text-primary focus:outline-none transition-all cursor-pointer hover:bg-surface shadow-sm">
                        <option value="all">All Entries</option>
                        <option value="reservation">Reservations</option>
                        <option value="regular">Regular</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary pointer-events-none text-[8px]"></i>
                </div>

                <button type="button" onclick="document.getElementById('modalDelete').classList.remove('hidden'); loadDates();"
                        class="flex items-center gap-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white text-[10px] font-black font-inter uppercase tracking-widest px-5 py-3 rounded-xl transition-all border border-red-500/20 shadow-sm hover:shadow-lg hover:shadow-red-500/20">
                    <i class="fa-solid fa-broom text-sm"></i>
                    Purge Logs
                </button>
            </div>
        </div>

        <!-- Scrollable Table Body -->
        <div class="overflow-y-auto flex-1 no-scrollbar relative flex flex-col">
            <table class="w-full font-inter border-collapse table-fixed" id="logTable">
                <thead class="sticky top-0 bg-surface z-20">
                    <tr class="border-b border-color">
                        <th class="py-4 px-6 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left">Vehicle</th>
                        <th class="py-4 px-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Plate</th>
                        <th class="py-4 px-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Ticket</th>
                        <th class="py-4 px-4 w-[8%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Slot</th>
                        <th class="py-4 px-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Entry</th>
                        <th class="py-4 px-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Exit</th>
                        <th class="py-4 px-4 w-[10%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Duration</th>
                        <th class="py-4 px-4 w-[12%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center">Final Fee</th>
                        <th class="py-4 px-6 w-[14%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color" id="logTableBody">
                    <!-- Standard empty state (shown/hidden by JS) -->
                    <tr id="noDataRow" class="<?= !empty($logs) ? 'hidden' : '' ?>">
                        <td colspan="9" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <i class="fa-solid fa-clock-rotate-left text-5xl mb-4"></i>
                                <p class="text-secondary font-inter font-medium">No operational logs found in the security index.</p>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($logs)): 
                        $reg_counter = 1;
                        $res_counter = 1;
                        foreach ($logs as $row): 
                        $hours_val = (float)($row['duration_hours'] ?? 0);
                        $h = floor($hours_val);
                        $m = round(($hours_val - $h) * 60);
                        $dur = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                        
                        $is_res = !empty($row['reservation_id']);
                        $is_pending = empty($row['scan_id']);
                        $is_departed = !$is_pending && !empty($row['time_out']);
                        
                        if ($is_departed) {
                            $display_slot = '<span class="opacity-20 italic">---</span>';
                            $slot_label = 'RELEASED';
                        } else {
                            if ($is_res) {
                                $display_slot = "#RES " . $res_counter++;
                            } else {
                                $display_slot = "#" . $reg_counter++;
                            }
                            $slot_label = $is_res ? 'VIP AREA' : 'REGULAR';
                        }

                        if ($is_pending) $dur = '---';
                    ?>
                    <tr class="group hover:bg-surface-alt/50 transition-colors log-row" 
                        data-vehicle="<?= trim(strtolower($row['vehicle_type'] ?? '')) ?>"
                        data-category="<?= $is_res ? 'reservation' : 'regular' ?>">
                        <td class="px-6 py-2 align-middle text-left">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all group-hover:scale-110">
                                <?php if (strtolower($row['vehicle_type'] ?? '') == 'motorcycle'): ?>
                                    <i class="fa-solid fa-motorcycle text-lg"></i>
                                <?php elseif (strtolower($row['vehicle_type'] ?? '') == 'car'): ?>
                                    <i class="fa-solid fa-car text-lg"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-shield-halved text-lg"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <span class="text-[13px] font-manrope font-bold text-primary uppercase tracking-widest leading-none">
                                <?= !empty($row['plate_number']) ? htmlspecialchars($row['plate_number']) : '<span class="opacity-20">------</span>' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1 uppercase tracking-widest">
                                    <?= htmlspecialchars($row['ticket_code']) ?>
                                </span>
                                <span class="text-[9px] font-inter text-tertiary leading-none uppercase"><?= $is_pending ? 'EXPECTED' : 'LOG ENTRY' ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1">
                                    <?= $display_slot ?>
                                </span>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase">
                                    <?= $slot_label ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1">
                                    <?= date('H:i:s', strtotime($row['time_in'])) ?>
                                </span>
                                <span class="text-[10px] font-inter text-tertiary leading-none uppercase">
                                    <?= date('d M Y', strtotime($row['time_in'])) ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <div class="flex flex-col items-center justify-center">
                                <?php if ($row['time_out']): ?>
                                    <span class="text-[13px] font-manrope font-bold text-primary leading-none mb-1">
                                        <?= date('H:i:s', strtotime($row['time_out'])) ?>
                                    </span>
                                    <span class="text-[10px] font-inter text-tertiary leading-none uppercase">
                                        <?= date('d M Y', strtotime($row['time_out'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[11px] font-inter text-tertiary opacity-40 tracking-widest italic">---:---:---</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <span class="text-[12px] font-inter font-bold text-primary">
                                <?= $dur ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 align-middle text-center">
                            <span class="text-[13px] font-manrope font-bold text-primary">
                                Rp <?= number_format($row['final_fee'] ?? 0, 0, ',', '.') ?>
                            </span>
                        </td>
                        <td class="px-6 py-2 align-middle text-right">
                            <div class="flex justify-end gap-1.5 flex-wrap">
                                <?php 
                                    $pay_status = strtolower($row['payment_status'] ?? '');
                                    $is_lost = (int)($row['is_lost_ticket'] ?? 0);
                                    $is_force = (int)($row['is_force_checkout'] ?? 0);
                                ?>
                                <?php if ($is_pending): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-reserved">PENDING</span>
                                <?php elseif ($is_departed): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-departed">DEPARTED</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-parked">ACTIVE</span>
                                <?php endif; ?>
                                <?php if ($is_lost): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-lost">LOST TICKET</span>
                                <?php endif; ?>
                                <?php if ($is_force): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-force">FORCE EXIT</span>
                                <?php endif; ?>
                                <?php if ($pay_status === 'paid'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium font-inter status-badge-paid">PAID</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- NEW: Standalone Load More Card -->
    <div id="loadMoreContainer" class="hidden animate-in fade-in slide-in-from-bottom-4 duration-500">
        <div class="bento-card py-6 flex flex-col items-center justify-center group cursor-pointer hover:border-brand/30 transition-all" onclick="loadMoreLogs()">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-surface-alt border border-color flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-plus text-brand"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-primary">Load More Activity</span>
                    <span id="showingCount" class="text-[9px] text-tertiary font-bold uppercase tracking-widest mt-0.5">Showing 0 of 0 entries</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purge Confirmation Modal -->
<div id="modalDelete" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-sidebar/60 backdrop-blur-sm"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <div class="bento-card overflow-hidden">
            <div class="px-6 py-4 border-b border-color flex justify-between items-center">
                <h3 class="card-title">Purge Operational Logs</h3>
                <button onclick="document.getElementById('modalDelete').classList.add('hidden')" class="text-tertiary hover:text-primary transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="flex gap-2 mb-6">
                    <button id="tabBtnDate" onclick="switchTab('date')"
                            class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-brand text-white transition-all shadow-lg shadow-brand/20">By Date</button>
                    <button id="tabBtnAll" onclick="switchTab('all')"
                            class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-surface-alt text-red-500 transition-all border border-color">Wipe All</button>
                </div>
                <div id="tabDate">
                    <div id="dateList" class="max-h-52 overflow-y-auto no-scrollbar rounded-2xl bg-surface-alt p-2 mb-6 space-y-1 border border-color"></div>
                    <button id="btnDeleteDate" disabled onclick="deleteLog('by_date')"
                            class="btn-primary w-full py-4 rounded-xl text-[11px] font-black uppercase tracking-widest disabled:opacity-30 disabled:grayscale transition-all flex items-center justify-center gap-3">
                        <i class="fa-solid fa-calendar-xmark text-sm"></i> Purge Selected Date
                    </button>
                </div>
                <div id="tabAll" class="hidden">
                    <div class="bg-red-500/5 rounded-2xl p-6 mb-6 text-center border border-red-500/10">
                        <i class="fa-solid fa-triangle-exclamation text-red-500 text-4xl block mb-3"></i>
                        <p class="text-red-500 font-black text-sm font-inter uppercase tracking-widest">Full System Purge</p>
                        <p class="text-tertiary text-[11px] font-inter mt-2">This action is irreversible and will wipe all recorded security sensor history.</p>
                    </div>
                    <button onclick="deleteLog('all')"
                            class="btn-primary !bg-red-500 w-full py-4 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-3">
                        <i class="fa-solid fa-fire text-sm"></i> Execute Wipe
                    </button>
                </div>
                <div id="deleteResult" class="mt-4 hidden text-[11px] font-inter font-bold uppercase tracking-widest text-center py-3 rounded-xl"></div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
let currentLogVehicleFilter = 'all';
let currentLogCategoryFilter = 'all';
let selectedDate = null;
let currentLimit = 20;

// --- CHART INITIALIZATION ---
const ctx = document.getElementById('scanActivityChart').getContext('2d');
const chartData = <?= json_encode($chart_data) ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [{
            label: 'Scans',
            data: chartData.map(d => d.count),
            backgroundColor: '#6366f1',
            borderRadius: 8,
            barThickness: 30,
            hoverBackgroundColor: '#4f46e5'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 12, displayColors: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false }, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 10, weight: 'bold' } } },
            x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10, weight: 'bold' } } }
        }
    }
});

function switchTab(tab) {
    document.getElementById('tabDate').classList.toggle('hidden', tab !== 'date');
    document.getElementById('tabAll').classList.toggle('hidden', tab !== 'all');
    const dateBtn = document.getElementById('tabBtnDate');
    const allBtn = document.getElementById('tabBtnAll');
    if (tab === 'date') {
        dateBtn.className = "flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-brand text-white transition-all shadow-lg shadow-brand/20";
        allBtn.className = "flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-surface-alt text-red-500 transition-all border border-color";
    } else {
        dateBtn.className = "flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-surface-alt text-tertiary transition-all border border-color";
        allBtn.className = "flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-red-500 text-white transition-all shadow-lg shadow-red-500/20";
    }
}

window.onclick = function(e) { if (e.target === document.getElementById('modalDelete')) document.getElementById('modalDelete').classList.add('hidden'); };

function loadDates() {
    const c = document.getElementById('dateList');
    c.innerHTML = '<div class="text-center py-8 text-tertiary text-[11px] font-black uppercase animate-pulse">Scanning sensor index...</div>';
    fetch('get_log_dates.php').then(r => r.json()).then(data => {
        if (!data.length) { 
            c.innerHTML = '<div class="text-center py-12 text-tertiary text-[11px] font-black uppercase tracking-widest opacity-40">Index Empty</div>'; 
            return; 
        }
        let html = '';
        data.forEach(d => {
            html += `
            <div class="date-item flex justify-between items-center px-5 py-4 rounded-xl cursor-pointer hover:bg-white transition-all border border-transparent hover:border-brand/20 hover:shadow-md" 
                 data-date="${d.date}" onclick="selectDate(this,'${d.date}')">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-surface flex items-center justify-center border border-color">
                        <i class="fa-solid fa-calendar-day text-brand text-sm"></i>
                    </div>
                    <div>
                        <div class="font-manrope font-black text-[13px] text-primary tracking-widest uppercase">${d.date}</div>
                        <div class="text-tertiary text-[10px] font-inter mt-0.5 uppercase font-bold">${d.day}</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="bg-brand/10 text-brand px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-tighter">${d.scan_count} EVENTS</span>
                </div>
            </div>`;
        });
        c.innerHTML = html;
    });
}

function selectDate(el, date) {
    document.querySelectorAll('.date-item').forEach(d => d.classList.remove('bg-white', 'border-brand/30', 'shadow-lg'));
    el.classList.add('bg-white', 'border-brand/30', 'shadow-lg');
    selectedDate = date;
    document.getElementById('btnDeleteDate').disabled = false;
}

function deleteLog(mode) {
    const box = document.getElementById('deleteResult');
    if (!confirm(mode === 'by_date' ? `Purge logs for ${selectedDate}?` : 'WIPE ALL SENSOR HISTORY?')) return;
    const body = mode === 'by_date' ? `mode=by_date&date=${selectedDate}&csrf_token=${encodeURIComponent(CSRF)}` : `mode=all&csrf_token=${encodeURIComponent(CSRF)}`;
    fetch('delete_logs.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
    .then(r => r.json()).then(data => {
        box.classList.remove('hidden');
        if (data.success) { box.className = 'mt-4 py-3 rounded-xl bg-emerald-500/10 text-emerald-600 text-[11px] font-black uppercase text-center'; box.innerHTML = 'Records Purged'; setTimeout(() => location.reload(), 1500); }
    });
}

function setScanLogVehicleFilter(type) {
    currentLogVehicleFilter = type;
    document.querySelectorAll('.filter-btn-log').forEach(btn => btn.classList.remove('active'));
    document.getElementById('btn-filter-' + type).classList.add('active');
    currentLimit = 20; // Reset limit on filter change
    applyScanLogFilters();
}

function setScanLogTypeFilter(category) {
    currentLogCategoryFilter = category;
    currentLimit = 20; // Reset limit on filter change
    applyScanLogFilters();
}

function loadMoreLogs() {
    currentLimit += 20;
    applyScanLogFilters();
}

function applyScanLogFilters() {
    const searchInput = document.getElementById('searchLog');
    if (!searchInput) return;
    
    const q = searchInput.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.log-row');
    
    let filteredCount = 0;
    let displayedCount = 0;

    rows.forEach(tr => {
        const text = tr.textContent.toLowerCase();
        const vehicle = (tr.getAttribute('data-vehicle') || '').trim();
        const category = (tr.getAttribute('data-category') || '').trim();
        
        const matchSearch = q === '' || text.includes(q);
        const matchVehicle = currentLogVehicleFilter === 'all' || vehicle === currentLogVehicleFilter;
        const matchCategory = currentLogCategoryFilter === 'all' || category === currentLogCategoryFilter;
        
        if (matchSearch && matchVehicle && matchCategory) {
            filteredCount++;
            if (displayedCount < currentLimit) {
                tr.style.display = '';
                displayedCount++;
            } else {
                tr.style.display = 'none';
            }
        } else {
            tr.style.display = 'none';
        }
    });

    // Handle "Load More" button visibility
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const showingCount = document.getElementById('showingCount');
    
    if (filteredCount > currentLimit) {
        loadMoreContainer.classList.remove('hidden');
    } else {
        loadMoreContainer.classList.add('hidden');
    }

    if (showingCount) {
        showingCount.textContent = `Showing ${displayedCount} of ${filteredCount} entries`;
    }

    // Handle No Data Row
    const noData = document.getElementById('noDataRow');
    if (noData) {
        noData.style.display = (filteredCount === 0) ? '' : 'none';
    }
}

// Initial call
document.addEventListener('DOMContentLoaded', applyScanLogFilters);
</script>

<?php include '../../includes/footer.php'; ?>
