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
            try {
                $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id, is_reservation_only) VALUES (?,?,?,?)")
                    ->execute([$num, $type, $floor_id, $is_res]);
                $msg = "Slot <strong>{$num}</strong> successfully initialized.";
            } catch (PDOException $e) {
                $error = 'Slot number is already registered.';
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved','maintenance'])) {
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
$stmt = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.status, ps.floor_id, ps.is_reservation_only,
           f.floor_code, f.floor_name,
           t.check_in_time, tk.ticket_code, t.reservation_id AS trans_res_id,
           v.plate_number, v.owner_name,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked,
           res.reservation_id AS future_res_id
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    LEFT JOIN `transaction` t ON t.slot_id = ps.slot_id AND t.payment_status = 'unpaid'
    LEFT JOIN ticket tk ON t.transaction_id = tk.transaction_id
    LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    LEFT JOIN `reservation` res ON res.slot_id = ps.slot_id 
         AND res.status = 'confirmed' 
         AND DATE(res.reserved_from) = CURDATE()
         AND NOT EXISTS (SELECT 1 FROM `transaction` t2 WHERE t2.reservation_id = res.reservation_id)
    ORDER BY ps.is_reservation_only, f.floor_code, ps.slot_type, ps.slot_number
");
$all_slots = $stmt->fetchAll();

// --- STATS LOGIC ---
$total_stats = ['total' => 0, 'avail' => 0];
$types_map = ['car' => [], 'motorcycle' => []];
$reg_counter = 1; $res_counter = 1;

foreach ($all_slots as &$s) {
    $eff_status = $s['status'];
    if (!empty($s['ticket_code'])) $eff_status = 'occupied';
    elseif (!empty($s['future_res_id'])) $eff_status = 'reserved';
    $s['eff_status'] = $eff_status;

    $is_res_area = (int)$s['is_reservation_only'] === 1;
    if ($is_res_area) {
        $s['display_label'] = "#RES " . $res_counter++;
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
.slot-box.maintenance { border-left-color: var(--status-maintenance-text); background: var(--status-maintenance-bg); }

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

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
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

    <!-- Main Inventory Card -->
    <div class="bento-card flex flex-col overflow-hidden min-h-[600px]">
        <!-- Card Header (Dashboard Style) -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-color shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-car-side text-lg"></i>
                </div>
                <div>
                    <h3 class="card-title leading-tight">Full Fleet Inventory</h3>
                    <div class="flex items-center gap-4 mt-0.5">
                        <p class="text-[11px] text-tertiary font-inter">Total Capacity: <span class="text-primary font-bold"><?= $total_stats['total'] ?></span></p>
                        <div class="w-1 h-1 rounded-full bg-tertiary/30"></div>
                        <p class="text-[11px] text-tertiary font-inter">Available: <span class="text-status-available-text font-bold"><?= $total_stats['avail'] ?></span></p>
                        <div class="w-1 h-1 rounded-full bg-tertiary/30"></div>
                        <p class="text-[11px] text-tertiary font-inter">Occupancy: <span class="text-brand font-bold"><?= round(($total_stats['total'] - $total_stats['avail']) / ($total_stats['total'] ?: 1) * 100) ?>%</span></p>
                    </div>
                </div>
            </div>

            <!-- Controls (Dashboard Style) -->
            <div class="flex items-center gap-3">
                <!-- View Toggle -->
                <div class="flex items-center bg-surface-alt border border-color rounded-2xl p-1 gap-1 h-11">
                    <a href="?view=map" class="px-4 py-2 rounded-xl text-[10px] font-black tracking-widest transition-all <?= $view === 'map' ? 'bg-brand text-white shadow-lg' : 'text-tertiary hover:text-brand' ?>">MAP</a>
                    <a href="?view=list" class="px-4 py-2 rounded-xl text-[10px] font-black tracking-widest transition-all <?= $view === 'list' ? 'bg-brand text-white shadow-lg' : 'text-tertiary hover:text-brand' ?>">LIST</a>
                </div>

                <!-- Search Input -->
                <div class="relative group">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-tertiary text-xs transition-colors group-focus-within:text-brand"></i>
                    <input type="text" id="slotSearch" placeholder="Search slot identifier..." 
                           class="w-48 bg-surface border border-color rounded-2xl py-2.5 pl-10 pr-4 text-[11px] font-inter text-primary placeholder:text-tertiary focus:outline-none focus:border-brand/30 transition-all">
                </div>

                <!-- Dual Dropdowns: Vehicle Type & Fleet Category -->
                <div class="flex items-center gap-2">
                    <!-- Vehicle Type Dropdown -->
                    <div class="relative">
                        <button onclick="toggleDropdown(event, 'vehicleDropdown')" class="flex items-center gap-3 bg-surface border border-color rounded-2xl px-5 h-11 hover:border-brand/30 transition-all group min-w-[140px]">
                            <span id="activeVehicleLabel" class="text-[10px] font-black uppercase tracking-widest text-primary">All Types</span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-tertiary group-hover:text-brand transition-colors ml-auto"></i>
                        </button>
                        <div id="vehicleDropdown" class="hidden dropdown-menu absolute right-0 top-14 w-48 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                            <button onclick="setVehicleFilter('all', 'All Types')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all">All Types</button>
                            <button onclick="setVehicleFilter('car', 'Automobiles')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all border-t border-color">Automobiles</button>
                            <button onclick="setVehicleFilter('motorcycle', 'Two-Wheelers')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all">Two-Wheelers</button>
                        </div>
                    </div>

                    <!-- Fleet Category Dropdown -->
                    <div class="relative">
                        <button onclick="toggleDropdown(event, 'categoryDropdown')" class="flex items-center gap-3 bg-surface border border-color rounded-2xl px-5 h-11 hover:border-brand/30 transition-all group min-w-[160px]">
                            <span id="activeCategoryLabel" class="text-[10px] font-black uppercase tracking-widest text-primary">All Categories</span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-tertiary group-hover:text-brand transition-colors ml-auto"></i>
                        </button>
                        <div id="categoryDropdown" class="hidden dropdown-menu absolute right-0 top-14 w-56 bg-surface border border-color rounded-2xl shadow-2xl z-[100] py-2 overflow-hidden animate-in fade-in zoom-in duration-200">
                            <button onclick="setCategoryFilter('all', 'All Categories')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all">All Categories</button>
                            <button onclick="setCategoryFilter('REGULAR', 'Standard Regular')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all border-t border-color">Standard Regular</button>
                            <button onclick="setCategoryFilter('RSV ZONE', 'Reservation Only Zone')" class="w-full px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-brand/[0.03] hover:text-brand transition-all">Reservation Only Zone</button>
                        </div>
                    </div>
                </div>

                <?php if ($can_manage): ?>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="flex items-center gap-2 bg-brand text-white text-[10px] font-black uppercase tracking-widest px-6 h-11 rounded-2xl transition-all shadow-xl shadow-brand/20 hover:brightness-110">
                    <i class="fa-solid fa-plus-circle text-sm"></i> ADD SLOT
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card Body -->
        <div class="flex-1 overflow-y-auto <?= $view === 'list' ? 'p-0' : 'p-8' ?> no-scrollbar bg-page/10">
            <?php if ($view === 'map'): ?>
                <?php foreach ($types_map as $type => $slots): ?>
                <div class="mb-12 last:mb-0 section-container" data-type="<?= $type ?>">
                    <div class="flex items-center gap-5 mb-8">
                        <div class="w-10 h-10 rounded-2xl bg-surface border border-color flex items-center justify-center shadow-sm">
                            <i class="fa-solid <?= $type === 'car' ? 'fa-car text-brand' : 'fa-motorcycle text-status-available-text' ?> text-lg"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-[0.2em] text-tertiary"><?= $type === 'car' ? 'Automobile Section' : 'Two-Wheeler Section' ?></span>
                            <div class="text-[9px] text-tertiary/50 uppercase font-bold tracking-widest mt-0.5"><?= count($slots) ?> total slots</div>
                        </div>
                        <div class="flex-1 h-px bg-gradient-to-r from-border-color to-transparent"></div>
                    </div>
                    <div class="slot-grid">
                        <?php foreach ($slots as $s): ?>
                        <div class="slot-box <?= $s['eff_status'] ?> slot-item" data-type="<?= $s['slot_type'] ?>" data-category="<?= $s['display_category'] ?>" data-number="<?= $s['slot_number'] ?>">
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
                <div class="overflow-visible">
                    <table class="w-full activity-table font-inter border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-surface sticky top-0 z-10">
                                <th class="text-left px-8 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color rounded-tl-2xl">Slot Index</th>
                                <th class="text-center px-4 py-4 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Category</th>
                                <th class="text-center px-4 py-4 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Type</th>
                                <th class="text-center px-4 py-4 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color">Status</th>
                                <?php if ($can_manage): ?>
                                <th class="text-right px-8 py-6 text-[10px] font-black uppercase tracking-widest text-tertiary border-b border-color rounded-tr-2xl">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-color">
                            <?php foreach ($all_slots as $s): ?>
                             <tr class="hover:bg-surface-alt/30 transition-all group slot-row" data-type="<?= $s['slot_type'] ?>" data-category="<?= $s['display_category'] ?>" data-number="<?= $s['slot_number'] ?>">
                                <td class="px-8 py-5">
                                    <div class="font-manrope font-extrabold text-primary text-[13px] leading-tight"><?= htmlspecialchars($s['display_label']) ?></div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-tertiary mt-0.5 opacity-60"><?= htmlspecialchars($s['slot_number']) ?></div>
                                </td>
                                <td class="px-4 py-4 text-center text-[10px] font-black uppercase tracking-widest text-tertiary opacity-70"><?= $s['display_category'] ?></td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-surface border border-color flex items-center justify-center shadow-sm"><i class="fa-solid <?= $s['slot_type'] === 'car' ? 'fa-car text-brand' : 'fa-motorcycle text-status-available-text' ?> text-[10px]"></i></div>
                                        <span class="text-[11px] font-black uppercase tracking-widest text-secondary"><?= ucfirst($s['slot_type']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <?php 
                                        $st = $s['eff_status'] === 'occupied' ? 'parked' : $s['eff_status'];
                                    ?>
                                    <div class="status-badge-<?= $st ?> px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2">
                                        <span class="status-dot-<?= $st ?>"></span>
                                        <?= $s['eff_status'] ?>
                                    </div>
                                </td>
                                <?php if ($can_manage): ?>
                                <td class="px-8 py-5 text-right">
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
                                                    <button type="submit" class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-brand/[0.03] transition-all group/item <?= $s['status'] === $st ? 'bg-brand/[0.05] text-brand' : 'text-primary' ?>">
                                                        <div class="w-2 h-2 rounded-full status-dot-<?= $vis_st ?> shadow-[0_0_8px_var(--status-<?= $vis_st ?>-text)]"></div>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider"><?= ucfirst($st) ?></span>
                                                        <?php if ($s['status'] === $st): ?>
                                                        <i class="fa-solid fa-check text-[9px] ml-auto"></i>
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
                <div class="status-badge-available px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                    <span class="status-dot-available"></span> Available
                </div>
                <div class="status-badge-parked px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                    <span class="status-dot-parked"></span> Occupied
                </div>
                <div class="status-badge-reserved px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-sm">
                    <span class="status-dot-reserved"></span> Reserved
                </div>
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
                <input type="text" name="slot_number" required placeholder="E.G. C-01" class="modal-input w-full border-2 border-color rounded-2xl px-6 py-5 text-base font-black font-manrope text-primary uppercase focus:outline-none focus:border-brand transition-all shadow-sm" oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="space-y-3"><label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Vehicle Class</label><div class="relative"><select name="slot_type" class="appearance-none h-14 w-full modal-input border-2 border-color rounded-2xl px-6 text-[11px] font-black uppercase tracking-widest text-primary focus:outline-none focus:border-brand transition-all cursor-pointer shadow-sm"><option value="car">🚗 Automobile</option><option value="motorcycle">🏍 Two-Wheeler</option></select><i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary/50 pointer-events-none text-[9px]"></i></div></div>
            <div class="space-y-3"><label class="block text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-1">Fleet Category</label><div class="relative"><select name="is_reservation_only" class="appearance-none h-14 w-full modal-input border-2 border-color rounded-2xl px-6 text-[11px] font-black uppercase tracking-widest text-primary focus:outline-none focus:border-brand transition-all cursor-pointer shadow-sm"><option value="0">Standard Regular</option><option value="1">Reservation Only Zone</option></select><i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-tertiary/50 pointer-events-none text-[9px]"></i></div></div>
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
                                 slot.dataset.number.toLowerCase().includes(search) || 
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
                                 row.dataset.number.toLowerCase().includes(search) || 
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
</script>

<?php include '../../includes/footer.php'; ?>
