<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

// Check if user has management permissions
$can_manage = in_array($_SESSION['role'] ?? 'operator', ['superadmin', 'admin']);
$view = $_GET['view'] ?? 'map'; // 'map' or 'list'
$msg = $error = '';

// --- POST ACTIONS (Management) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $num      = strtoupper(trim($_POST['slot_number'] ?? ''));
        $type     = $_POST['slot_type'] ?? '';
        $is_res   = (int)($_POST['is_reservation_only'] ?? 0);

        // Auto-resolve floor_id (default to the first available floor)
        $floor_stmt = $pdo->query("SELECT floor_id FROM floor ORDER BY floor_id LIMIT 1");
        $floor_id = $floor_stmt->fetchColumn();

        if (!$num || !in_array($type, ['car','motorcycle']) || !$floor_id) {
            $error = 'Slot configuration data is incomplete.';
        } else {
            // Strict Pattern Validation (Prepend # as it's now locked in UI)
            $num = "#" . $num;
            
            $isValidPattern = false;
            if (strpos($num, '#RES') === 0) {
                // User typed RES...
                if ($is_res !== 1) {
                    $error = 'Format <strong>#RES</strong> detected. Please change the Fleet Category to <strong>Reservation Only Zone</strong>.';
                } else if (preg_match('/^#RES[0-9]+$/', $num)) {
                    $isValidPattern = true;
                } else {
                    $error = 'Invalid format. Reservation slots must be <strong>RES</strong> followed by numbers (e.g., RES1).';
                }
            } else {
                // User typed regular number...
                if ($is_res === 1) {
                    $error = 'Regular number detected. Please change the Fleet Category to <strong>Standard Regular</strong> or add <strong>RES</strong> prefix.';
                } else if (preg_match('/^#[0-9]+$/', $num)) {
                    $isValidPattern = true;
                } else {
                    $error = 'Invalid format. Regular slots must be numbers (e.g., 1).';
                }
            }

            if ($isValidPattern) {
                try {
                    $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id, is_reservation_only) VALUES (?,?,?,?)")
                        ->execute([$num, $type, $floor_id, $is_res]);
                    $msg = "Slot <strong>{$num}</strong> successfully initialized.";
                } catch (PDOException $e) {
                    $error = 'Slot number is already registered.';
                }
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved'])) {
            $error = 'Invalid state value.';
        } else {
            $pdo->prepare("UPDATE parking_slot SET status=? WHERE slot_id=?")->execute([$status, $id]);
            $msg = 'Slot state successfully synchronized.';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['slot_id'];
        $occupied = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE slot_id=? AND payment_status='unpaid'");
        $occupied->execute([$id]);
        if ($occupied->fetchColumn() > 0) {
            $error = 'Constraint Violation: Active slot is tied to an ongoing transaction session.';
        } else {
            $pdo->prepare("DELETE FROM parking_slot WHERE slot_id=?")->execute([$id]);
            $msg = 'Slot permanently deleted.';
        }
    }
}

// --- DATA FETCHING ---
sync_slot_statuses($pdo);

$stmt = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.status, ps.floor_id, ps.is_reservation_only,
           COALESCE(f.floor_code, 'N/A') AS floor_code, COALESCE(f.floor_name, 'Unknown') AS floor_name,
           t.check_in_time, t.ticket_code, t.reservation_id AS trans_res_id,
           v.plate_number, v.owner_name,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM parking_slot ps
    LEFT JOIN floor f ON ps.floor_id = f.floor_id
    LEFT JOIN `transaction` t ON t.slot_id = ps.slot_id AND t.payment_status = 'unpaid'
    LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    ORDER BY ps.is_reservation_only, COALESCE(f.floor_code, 'ZZZ'), ps.slot_type, LENGTH(ps.slot_number), ps.slot_number
");
$all_slots = $stmt->fetchAll();

// --- STATS LOGIC ---
$total_stats = ['total' => 0, 'avail' => 0];
$types_map = ['car' => [], 'motorcycle' => []];
$reg_counter = 1; $res_counter = 1;

foreach ($all_slots as &$s) {
    $eff_status = $s['status'];
    $s['eff_status'] = $eff_status;

    $is_res_area = (int)$s['is_reservation_only'] === 1;
    if ($is_res_area) {
        $s['display_label'] = "#RES" . $res_counter++;
        $s['display_category'] = "RSV ZONE";
    } else {
        $s['display_label'] = "#" . $reg_counter++;
        $s['display_category'] = "REGULAR";
    }

    $total_stats['total']++;
    if ($eff_status === 'available') $total_stats['avail']++;
    $types_map[$s['slot_type']][] = $s;
}
unset($s);

$floors_list = $pdo->query("SELECT floor_id, floor_code FROM floor ORDER BY floor_code")->fetchAll();

// --- NEXT SEQUENCE LOGIC ---
$max_reg = $pdo->query("SELECT MAX(CAST(REPLACE(slot_number, '#', '') AS UNSIGNED)) FROM parking_slot WHERE is_reservation_only = 0 AND slot_number REGEXP '^#[0-9]+$'")->fetchColumn() ?: 0;
$max_res = $pdo->query("SELECT MAX(CAST(REPLACE(slot_number, '#RES', '') AS UNSIGNED)) FROM parking_slot WHERE is_reservation_only = 1 AND slot_number REGEXP '^#RES[0-9]+$'")->fetchColumn() ?: 0;

$next_reg_id = "#" . ($max_reg + 1);
$next_res_id = "#RES" . ($max_res + 1);

$page_title = 'Parking Inventory';
$page_subtitle = 'Visual slot mapping and inventory management system.';

include '../../includes/header.php';
?>



<style>
/* Visual Map Styles */
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 24px; }
.slot-box {
    border-radius: 1.5rem;
    height: 125px; 
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px;
    cursor: pointer;
    transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    background: var(--surface);
    border: 2px solid var(--border-color);
    border-left-width: 6px;
}
.slot-box:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 20px 40px -10px var(--shadow-color); 
    border-color: var(--brand); 
}

.slot-box.available   { border-left-color: var(--status-available-text); background: var(--status-available-bg); }
.slot-box.occupied    { border-left-color: var(--status-parked-text); background: var(--status-parked-bg); }
.slot-box.reserved    { border-left-color: var(--status-reserved-text); background: var(--status-reserved-bg); }

.slot-num  { font-weight: 800; font-size: 16px; font-family: 'Manrope', sans-serif; color: var(--text-primary); margin-bottom: 2px; }
.slot-icon { font-size: 22px; color: var(--brand); opacity: 0.3; margin-bottom: 8px; transition: all 0.3s ease; }
.slot-box:hover .slot-icon { opacity: 0.8; transform: scale(1.1); }

.slot-plate { 
    font-size: 11px; 
    color: var(--text-primary); 
    margin-top: 10px; 
    background: var(--surface); 
    border: 1px solid var(--border-color);
    border-radius: 12px; 
    padding: 6px 12px; 
    font-family: 'Manrope', sans-serif; 
    font-weight: 800; 
    width: 100%; 
    text-align: center; 
    box-shadow: 0 2px 12px var(--shadow-color);
}
.slot-duration { font-size: 10px; color: var(--text-secondary); margin-top: 6px; font-family: 'Inter', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="fixed top-24 right-10 z-[100] flex flex-col gap-3">
        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-surface border border-status-available-border rounded-2xl px-6 py-4 shadow-2xl animate-in slide-in-from-right-10 duration-500">
            <div class="w-8 h-8 rounded-full bg-status-available-bg flex items-center justify-center text-status-available-text">
                <i class="fa-solid fa-check"></i>
            </div>
            <p class="text-primary text-sm font-manrope font-bold"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-surface border border-status-lost-border rounded-2xl px-6 py-4 shadow-2xl animate-in slide-in-from-right-10 duration-500">
            <div class="w-8 h-8 rounded-full bg-status-lost-bg flex items-center justify-center text-status-lost-text">
                <i class="fa-solid fa-exclamation"></i>
            </div>
            <p class="text-primary text-sm font-manrope font-bold"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
    </div>

<div class="px-10 py-10">

    <!-- PAGE HEADER -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
            <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
        </div>
    </div>

    <!-- Main Inventory Card -->
    <div class="bento-card flex flex-col overflow-hidden min-h-[600px]">
        <!-- Card Header (Dashboard Style) -->
        <div class="flex items-start justify-between py-5 px-4 border-b border-color">
            <div class="flex flex-col gap-2.5">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-car-side text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Full Fleet Inventory</h3>
                        <p class="text-[11px] text-tertiary font-medium uppercase tracking-wider">Visual slot mapping and inventory management system.</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 ml-[3.5rem]">
                    <p class="text-[11px] text-tertiary font-inter">Total Capacity: <span class="text-primary font-bold"><?= $total_stats['total'] ?></span></p>
                    <div class="w-1 h-1 rounded-full bg-tertiary/30"></div>
                    <p class="text-[11px] text-tertiary font-inter">Available: <span class="text-status-available-text font-bold"><?= $total_stats['avail'] ?></span></p>
                    <div class="w-1 h-1 rounded-full bg-tertiary/30"></div>
                    <p class="text-[11px] text-tertiary font-inter">Occupancy: <span class="text-brand font-bold"><?= round(($total_stats['total'] - $total_stats['avail']) / ($total_stats['total'] ?: 1) * 100) ?>%</span></p>
                </div>
            </div>

            <!-- Controls (Dashboard Style) -->
            <div class="flex items-center gap-4">
                <!-- View Toggle -->
                <div class="flex items-center bg-surface-alt border border-color rounded-xl p-1 gap-1 h-[38px]">
                    <a href="?view=map" class="w-11 h-[30px] flex items-center justify-center rounded-lg text-[11px] font-inter font-medium tracking-wider transition-all <?= $view === 'map' ? 'bg-brand text-white shadow-sm' : 'text-tertiary hover:text-brand' ?>">MAP</a>
                    <a href="?view=list" class="w-11 h-[30px] flex items-center justify-center rounded-lg text-[11px] font-inter font-medium tracking-wider transition-all <?= $view === 'list' ? 'bg-brand text-white shadow-sm' : 'text-tertiary hover:text-brand' ?>">LIST</a>
                </div>

                <!-- Search Input -->
                <div class="relative group">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-tertiary text-sm transition-colors group-focus-within:text-brand"></i>
                    <input type="text" id="slotSearch" placeholder="Search slot identifier..." 
                           oninput="this.value = this.value.toUpperCase()"
                           class="w-48 bg-surface-alt border border-color rounded-xl h-[38px] pl-10 pr-4 text-[11px] font-inter text-primary focus:outline-none focus:border-brand/20 focus:bg-surface transition-all">
                </div>

                <!-- Vehicle Type Dropdown -->
                <div class="relative">
                    <button onclick="toggleDropdown(event, 'vehicleDropdown')" class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                        <span id="activeVehicleLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary">All Types</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-tertiary group-hover:text-brand transition-colors"></i>
                    </button>
                    <div id="vehicleDropdown" class="hidden dropdown-menu absolute left-0 top-12 w-48 bg-surface border border-color rounded-xl shadow-xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                            <button onclick="setVehicleFilter('all', 'All Types')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">All Types</button>
                            <button onclick="setVehicleFilter('car', 'Automobiles')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Cars</button>
                            <button onclick="setVehicleFilter('motorcycle', 'Two-Wheelers')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Motorcycles</button>
                        </div>
                    </div>

                <!-- Fleet Category Dropdown -->
                <div class="relative">
                    <button onclick="toggleDropdown(event, 'categoryDropdown')" class="flex items-center gap-2 bg-surface-alt border border-color rounded-xl px-4 h-[38px] hover:border-brand/20 transition-all group">
                        <span id="activeCategoryLabel" class="text-[11px] font-inter font-medium tracking-wider text-primary">All Categories</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-tertiary group-hover:text-brand transition-colors"></i>
                    </button>
                    <div id="categoryDropdown" class="hidden dropdown-menu absolute left-0 top-12 w-56 bg-surface border border-color rounded-xl shadow-xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                        <button onclick="setCategoryFilter('all', 'All Categories')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">All Categories</button>
                        <button onclick="setCategoryFilter('REGULAR', 'Standard Regular')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Standard Regular</button>
                        <button onclick="setCategoryFilter('RSV ZONE', 'Reservation Only Zone')" class="w-full px-4 py-2.5 text-left text-[11px] font-inter font-medium tracking-wider text-primary hover:bg-surface-alt hover:text-brand transition-all">Reservation Only Zone</button>
                    </div>
                </div>

                <?php if ($can_manage): ?>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="flex items-center gap-2 bg-brand text-white text-[11px] font-inter font-medium tracking-wider px-4 h-[38px] rounded-xl transition-all shadow-lg shadow-brand/20 hover:brightness-110">
                    <i class="fa-solid fa-plus-circle text-sm"></i> ADD SLOT
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card Body -->
        <div class="flex-1 <?= $view === 'list' ? 'overflow-x-auto custom-scrollbar min-h-[350px]' : 'overflow-y-auto p-8 no-scrollbar bg-page/10' ?>">
            <?php if ($view === 'map'): ?>
                <?php foreach ($types_map as $type => $slots): ?>
                <div class="mb-12 last:mb-0 section-container" data-type="<?= $type ?>">
                    <div class="flex items-center gap-5 mb-8">
                        <div class="w-10 h-10 rounded-2xl bg-surface border border-color flex items-center justify-center shadow-sm">
                            <i class="fa-solid <?= $type === 'car' ? 'fa-car text-brand' : 'fa-motorcycle text-status-available-text' ?> text-lg"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-[0.2em] text-tertiary"><?= $type === 'car' ? 'Cars Section' : 'Motorcycles Section' ?></span>
                            <div class="text-[9px] text-tertiary/50 uppercase font-bold tracking-widest mt-0.5"><?= count($slots) ?> total slots</div>
                        </div>
                        <div class="flex-1 h-px bg-gradient-to-r from-border-color to-transparent"></div>
                    </div>
                    <div class="slot-grid">
                        <?php foreach ($slots as $s): ?>
                        <div class="slot-box <?= $s['eff_status'] ?> slot-item" data-id="<?= $s['slot_id'] ?>" data-type="<?= $s['slot_type'] ?>" data-category="<?= $s['display_category'] ?>" data-number="<?= $s['slot_number'] ?>">
                            <span class="slot-icon"><i class="fa-solid <?= $type === 'car' ? 'fa-car' : 'fa-motorcycle' ?>"></i></span>
                            <div class="slot-num"><?= htmlspecialchars($s['display_label']) ?></div>
                            <div class="text-[10px] font-bold text-tertiary uppercase tracking-wider opacity-60"><?= $s['display_category'] ?></div>
                            <?php if ($s['status'] === 'occupied' && $s['plate_number']): ?>
                            <div class="slot-plate"><?= htmlspecialchars($s['plate_number']) ?></div>
                            <div class="slot-duration"><?= (int)$s['minutes_parked'] >= 60 ? floor($s['minutes_parked']/60).'h '.($s['minutes_parked']%60).'m' : $s['minutes_parked'].'m' ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="overflow-x-auto custom-scrollbar flex-grow no-scrollbar">
                    <table class="w-full font-inter border-collapse table-fixed activity-table" id="slotTable">
                        <thead>
                            <tr class="border-b border-color">
                                <th class="py-3 w-[25%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-left pl-4">Slot Index</th>
                                <th class="py-3 w-[20%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Category</th>
                                <th class="py-3 w-[20%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Type</th>
                                <th class="py-3 w-[20%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-center px-4">Status</th>
                                <?php if ($can_manage): ?>
                                <th class="py-3 w-[15%] text-[11px] font-inter text-tertiary font-medium uppercase tracking-wider text-right pr-4">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="slotBody" class="divide-y divide-color">
                            <?php foreach ($all_slots as $s): ?>
                             <tr class="group hover:bg-surface-alt/50 transition-colors fleet-row slot-row" 
                                 data-type="<?= $s['slot_type'] ?>" 
                                 data-category="<?= $s['display_category'] ?>" 
                                 data-number="<?= $s['slot_number'] ?>"
                                 data-timestamp="<?= $s['slot_id'] ?>">
                                <td class="py-2 pl-4 pr-4 align-middle text-left">
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-manrope font-semibold text-primary leading-none"><?= htmlspecialchars($s['display_label']) ?></span>
                                            <span class="text-[9px] font-inter text-tertiary mt-1 uppercase tracking-widest opacity-60"><?= htmlspecialchars($s['slot_number']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 px-4 align-middle text-center">
                                    <div class="flex items-center justify-center">
                                        <span class="text-[10px] font-inter font-medium uppercase tracking-widest text-tertiary"><?= $s['display_category'] ?></span>
                                    </div>
                                </td>
                                <td class="py-2 px-4 align-middle text-center">
                                    <div class="flex items-center gap-3 w-32 mx-auto">
                                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0 transition-all">
                                            <i class="fa-solid <?= $s['slot_type'] === 'car' ? 'fa-car text-brand' : 'fa-motorcycle text-status-available-text' ?> text-lg"></i>
                                        </div>
                                        <span class="text-[11px] font-manrope font-semibold text-secondary uppercase tracking-wider"><?= ucfirst($s['slot_type']) ?></span>
                                    </div>
                                </td>
                                <td class="py-2 px-4 align-middle text-center">
                                    <div class="flex items-center justify-center">
                                        <?php $st = $s['eff_status'] === 'occupied' ? 'parked' : $s['eff_status']; ?>
                                        <div class="status-badge status-badge-<?= $st ?>">
                                            <?= ucfirst($st) ?>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($can_manage): ?>
                                <td class="py-2 pr-4 pl-4 align-middle text-right">
                                    <div class="flex items-center justify-end">
                                        <div class="relative action-menu-container">
                                            <button onclick="toggleDropdown(event, 'action-<?= $s['slot_id'] ?>')" class="w-9 h-9 rounded-xl bg-surface border border-color text-tertiary hover:text-brand hover:border-brand/30 hover:shadow-lg transition-all flex items-center justify-center shadow-sm">
                                                <i class="fa-solid fa-ellipsis-vertical text-sm"></i>
                                            </button>
                                            
                                            <!-- Action Dropdown -->
                                            <div id="action-<?= $s['slot_id'] ?>" class="hidden dropdown-menu absolute right-0 top-11 w-48 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                                                <div class="px-4 py-2 border-b border-color bg-surface-alt/30">
                                                    <span class="text-[9px] font-black uppercase tracking-widest text-tertiary">Quick Status Sync</span>
                                                </div>
                                                <?php foreach (['available','occupied','reserved'] as $st): 
                                                    $vis_st = ($st === 'occupied') ? 'parked' : $st;
                                                ?>
                                                <form method="POST" class="w-full">
                                                    <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>"><input type="hidden" name="status" value="<?= $st ?>">
                                                    <button type="submit" class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-status-<?= $vis_st ?>-text/5 transition-all group/item">
                                                        <div class="w-2 h-2 rounded-full status-dot-<?= $vis_st ?> shadow-[0_0_8px_var(--status-<?= $vis_st ?>-text)]"></div>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider text-status-<?= $vis_st ?>-text"><?= ucfirst($vis_st) ?></span>
                                                        <?php if ($s['status'] === $st): ?>
                                                        <i class="fa-solid fa-check text-[9px] ml-auto text-status-<?= $vis_st ?>-text"></i>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                                <?php endforeach; ?>
                                                <div class="h-px bg-color my-1"></div>
                                                <form method="POST" onsubmit="return confirm('Permanently delete slot <?= htmlspecialchars($s['slot_number']) ?>?')" class="w-full">
                                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                                    <button type="submit" class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-red-500/5 text-red-500 transition-all group/item">
                                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider">Delete Slot</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Stats -->
        <div class="px-8 py-4 border-t border-color bg-surface-alt/20 flex justify-between items-center shrink-0">
            <p id="showingCount" class="text-[10px] font-black uppercase tracking-widest text-tertiary">Showing <?= count($all_slots) ?> indexed slots</p>
            <div class="flex gap-4">
                <div class="status-badge status-badge-available">Available</div>
                <div class="status-badge status-badge-parked">Parked</div>
                <div class="status-badge status-badge-reserved">Reserved</div>
                <div class="status-badge status-badge-departed">Departed</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Standardized -->
<?php if ($can_manage): ?>
<div id="addModal" class="hidden fixed inset-0 z-[110] backdrop-blur-xl bg-slate-950/40 flex items-center justify-center p-4">
    <div class="modal-surface rounded-[2.5rem] border-2 border-white/10 shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="flex items-center justify-between px-10 py-8 border-b border-color bg-surface-alt/50">
            <div class="flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-brand/10 text-brand flex items-center justify-center shadow-inner"><i class="fa-solid fa-plus-square text-2xl"></i></div>
                <div><h2 class="font-manrope font-extrabold text-2xl text-primary tracking-tight">Register New Slot</h2><p class="text-[11px] text-tertiary uppercase font-bold tracking-widest mt-1">Initialize hardware identifier</p></div>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="w-10 h-10 rounded-full flex items-center justify-center text-tertiary hover:bg-surface-alt hover:text-primary transition-all"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="POST" class="p-10 space-y-8 bg-surface">
            <?= csrf_field() ?><input type="hidden" name="action" value="add">
            <div class="space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Slot Identifier <span class="text-brand">*</span></label>
                <div class="flex items-center">
                    <div class="h-16 px-6 flex items-center justify-center bg-surface-alt border-2 border-r-0 border-color rounded-l-2xl text-xl font-black font-manrope text-tertiary">#</div>
                    <input type="text" name="slot_number" id="slot_number_input" required 
                           value="<?= str_replace('#', '', $next_reg_id) ?>" 
                           pattern="^(RES)?[0-9]+$" 
                           title="Enter number or RES followed by number"
                           placeholder="E.G. 1 or RES1" 
                           class="modal-input flex-1 border-2 border-color rounded-r-2xl px-6 h-16 text-base font-black font-manrope text-primary uppercase focus:outline-none focus:border-brand transition-all shadow-sm" 
                           oninput="this.value=this.value.toUpperCase(); detectCategory(this.value)">
                </div>
            </div>
            <div class="space-y-3"><label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Vehicle Class</label><div class="relative"><select name="slot_type" class="appearance-none h-14 w-full modal-input border-2 border-color rounded-2xl px-6 text-[11px] font-black uppercase tracking-widest text-primary focus:outline-none focus:border-brand transition-all cursor-pointer shadow-sm"><option value="car">Cars</option><option value="motorcycle">Motorcycles</option></select><i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary/50 pointer-events-none text-[9px]"></i></div></div>
            <div class="space-y-3"><label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Fleet Category</label><div class="relative"><select name="is_reservation_only" id="category_select" onchange="updateIdentifierSuggestion()" class="appearance-none h-14 w-full modal-input border-2 border-color rounded-2xl px-6 text-[11px] font-black uppercase tracking-widest text-primary focus:outline-none focus:border-brand transition-all cursor-pointer shadow-sm"><option value="0">Standard Regular</option><option value="1">Reservation Only Zone</option></select><i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary/50 pointer-events-none text-[9px]"></i></div></div>
            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 h-14 bg-surface-alt text-primary font-black text-[11px] uppercase tracking-widest rounded-2xl transition-all border-2 border-color hover:bg-surface shadow-sm">Discard</button>
                <button type="submit" class="flex-1 h-14 bg-brand text-white font-black text-[11px] uppercase tracking-widest rounded-2xl transition-all shadow-2xl shadow-brand/40 hover:brightness-110 active:scale-95">Complete Sync</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
let currentVehicleFilter = 'all';
let currentCategoryFilter = 'all';

function toggleDropdown(e, id) { 
    e.stopPropagation(); 
    const dropdown = document.getElementById(id);
    document.querySelectorAll('.dropdown-menu').forEach(el => {
        if (el.id !== id) el.classList.add('hidden');
    });
    dropdown.classList.toggle('hidden'); 
}

function setCategoryFilter(val, label) { 
    currentCategoryFilter = val; 
    document.getElementById('activeCategoryLabel').textContent = label; 
    document.getElementById('categoryDropdown').classList.add('hidden'); 
    applyFilters(); 
}

function setVehicleFilter(type, label) {
    currentVehicleFilter = type;
    document.getElementById('activeVehicleLabel').textContent = label;
    document.getElementById('vehicleDropdown').classList.add('hidden');
    applyFilters();
}

function applyFilters() {
    const searchInput = document.getElementById('slotSearch');
    const search = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const slots = document.querySelectorAll('.slot-item');
    const sections = document.querySelectorAll('.section-container');
    const rows = document.querySelectorAll('.slot-row');
    let totalVisible = 0;

    // Map View Logic
    if (slots.length > 0) {
        slots.forEach(slot => {
            const matchesSearch = search === '' || 
                                 (slot.dataset.number && slot.dataset.number.toLowerCase().includes(search)) || 
                                 slot.textContent.toLowerCase().includes(search);
            const matchesVehicle = currentVehicleFilter === 'all' || slot.dataset.type === currentVehicleFilter;
            const matchesCategory = currentCategoryFilter === 'all' || slot.dataset.category === currentCategoryFilter;
            
            if (matchesSearch && matchesVehicle && matchesCategory) {
                slot.style.display = '';
                totalVisible++;
            } else {
                slot.style.display = 'none';
            }
        });

        // Toggle Section Headers based on visibility
        sections.forEach(section => {
            const matchesVehicle = currentVehicleFilter === 'all' || section.dataset.type === currentVehicleFilter;
            let visibleInSec = 0;
            section.querySelectorAll('.slot-item').forEach(s => {
                if (s.style.display !== 'none') visibleInSec++;
            });
            section.style.display = (matchesVehicle && visibleInSec > 0) ? '' : 'none';
        });
    }

    // List View Logic
    if (rows.length > 0) {
        rows.forEach(row => {
            const matchesSearch = search === '' || 
                                 (row.dataset.number && row.dataset.number.toLowerCase().includes(search)) || 
                                 row.textContent.toLowerCase().includes(search);
            const matchesVehicle = currentVehicleFilter === 'all' || row.dataset.type === currentVehicleFilter;
            const matchesCategory = currentCategoryFilter === 'all' || row.dataset.category === currentCategoryFilter;
            
            if (matchesSearch && matchesVehicle && matchesCategory) {
                row.style.display = '';
                totalVisible++;
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Sync Footer Count
    const showingCount = document.getElementById('showingCount');
    if (showingCount) {
        showingCount.textContent = `Showing ${totalVisible} indexed slots`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const slotSearch = document.getElementById('slotSearch');
    if (slotSearch) slotSearch.addEventListener('input', applyFilters);
    document.addEventListener('click', () => { 
        document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.add('hidden'));
    });
    setTimeout(() => { document.querySelectorAll('#toastContainer > div').forEach(t => { t.classList.add('fade-out', 'translate-x-10'); setTimeout(() => t.remove(), 500); }); }, 5000);
});

<?php if ($view === 'map'): ?>
let countdown = 60; setInterval(() => { countdown--; if (countdown <= 0) location.reload(); }, 1000);
<?php endif; ?>

const addModal = document.getElementById('addModal');
if (addModal) { addModal.addEventListener('click', function(e) { if (e.target === this) this.classList.add('hidden'); }); }

function updateIdentifierSuggestion() {
    const isRes = document.getElementById('category_select').value === '1';
    const input = document.getElementById('slot_number_input');
    const nextReg = '<?= str_replace('#', '', $next_reg_id) ?>';
    const nextRes = '<?= str_replace('#', '', $next_res_id) ?>';
    
    input.value = isRes ? nextRes : nextReg;
    updatePattern(isRes);
}

function updatePattern(isRes) {
    const input = document.getElementById('slot_number_input');
    input.placeholder = isRes ? 'E.G. RES1' : 'E.G. 1';
    input.pattern = isRes ? '^RES[0-9]+$' : '^[0-9]+$';
    input.title = isRes ? 'Must be RES followed by numbers (e.g., RES1)' : 'Must be numbers (e.g., 1)';
}

function detectCategory(val) {
    const select = document.getElementById('category_select');
    if (val.startsWith('RES')) {
        if (select.value !== '1') {
            select.value = '1';
            updatePattern(true);
        }
    } else if (/^[0-9]/.test(val)) {
        if (select.value !== '0') {
            select.value = '0';
            updatePattern(false);
        }
    }
}
</script>

<?php if ($can_manage): ?>
    <!-- Context Menu for Map Mode -->
    <div id="slotContextMenu" class="hidden fixed bg-surface border border-color rounded-xl shadow-2xl z-[200] py-2 w-56 overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-4 py-2 border-b border-color bg-surface-alt/50 flex items-center justify-between">
            <span class="text-[9px] font-black uppercase tracking-[0.15em] text-tertiary/50">Quick Status Sync</span>
            <span id="ctxSlotLabel" class="text-[9px] font-bold text-brand"></span>
        </div>
        
        <?php foreach (['available','occupied','reserved'] as $st): 
            $vis_st = ($st === 'occupied') ? 'parked' : $st;
        ?>
        <form method="POST" class="w-full status-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="slot_id" class="ctx-slot-id" value="">
            <input type="hidden" name="status" value="<?= $st ?>">
            <button type="submit" class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-status-<?= $vis_st ?>-text/5 transition-all group/item">
                <div class="w-2 h-2 rounded-full status-dot-<?= $vis_st ?> shadow-[0_0_8px_var(--status-<?= $vis_st ?>-text)]"></div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-status-<?= $vis_st ?>-text"><?= ucfirst($vis_st) ?></span>
                <i class="fa-solid fa-check text-[9px] ml-auto text-status-<?= $vis_st ?>-text hidden check-<?= $st ?>"></i>
            </button>
        </form>
        <?php endforeach; ?>

        <div class="px-4 py-2 border-t border-color bg-surface-alt/30">
            <span class="text-[9px] font-black uppercase tracking-[0.15em] text-tertiary/50">Settings</span>
        </div>
        
        <form method="POST" class="w-full" onsubmit="return confirm('Archive this slot? This will permanently remove its tracking history.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="slot_id" class="ctx-slot-id" value="">
            <button type="submit" class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-status-lost-text/5 text-status-lost-text transition-all group/item">
                <i class="fa-solid fa-trash-alt text-[10px] opacity-70"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider">Archive Slot</span>
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('contextmenu', function(e) {
        const slot = e.target.closest('.slot-box');
        if (slot) {
            e.preventDefault();
            const slotId = slot.dataset.id;
            const slotNum = slot.dataset.number;
            const currentStatus = slot.classList.contains('available') ? 'available' : 
                                 slot.classList.contains('occupied') ? 'occupied' : 
                                 slot.classList.contains('reserved') ? 'reserved' : 'maintenance';
            
            showSlotContextMenu(e.clientX, e.clientY, slotId, slotNum, currentStatus);
        } else {
            hideSlotContextMenu();
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#slotContextMenu')) hideSlotContextMenu();
    });

    function showSlotContextMenu(x, y, id, num, status) {
        const menu = document.getElementById('slotContextMenu');
        if (!menu) return;
        
        document.getElementById('ctxSlotLabel').textContent = num;
        menu.querySelectorAll('.ctx-slot-id').forEach(input => input.value = id);
        menu.querySelectorAll('.fa-check').forEach(check => check.classList.add('hidden'));
        const activeCheck = menu.querySelector('.check-' + status);
        if (activeCheck) activeCheck.classList.remove('hidden');

        menu.classList.remove('hidden');
        
        const menuWidth = menu.offsetWidth || 224;
        const menuHeight = menu.offsetHeight || 280;
        
        let posX = x;
        let posY = y;
        
        if (x + menuWidth > window.innerWidth) posX = x - menuWidth;
        if (y + menuHeight > window.innerHeight) posY = y - menuHeight;
        
        menu.style.left = posX + 'px';
        menu.style.top = posY + 'px';
    }

    function hideSlotContextMenu() {
        const menu = document.getElementById('slotContextMenu');
        if (menu) menu.classList.add('hidden');
    }
    </script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
