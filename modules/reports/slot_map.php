<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

sync_slot_statuses($pdo);

$stmt = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.status, ps.is_reservation_only,
           f.floor_code AS floor, f.floor_name,
           t.check_in_time, t.ticket_code,
           v.plate_number, v.owner_name,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    LEFT JOIN `transaction` t ON t.slot_id = ps.slot_id AND t.payment_status = 'unpaid'
    LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    ORDER BY ps.is_reservation_only, CAST(REPLACE(REPLACE(ps.slot_number, '#RES', ''), '#', '') AS UNSIGNED)
");
$all_slots = $stmt->fetchAll();

$categories = [];
$reg_counter = 1; $res_counter = 1;
foreach ($all_slots as &$slot) {
    $is_res_area = (int)$slot['is_reservation_only'] === 1;
    if ($is_res_area) {
        $slot['display_label'] = "#RES " . $res_counter++;
        $slot['display_category'] = "Reservation Only Zone";
    } else {
        $slot['display_label'] = "#" . $reg_counter++;
        $slot['display_category'] = "Standard Regular Area";
    }
    $categories[$slot['display_category']][$slot['slot_type']][] = $slot;
}
unset($slot);

// Summary Stats
$stats = $pdo->query("
    SELECT ps.slot_type, ps.is_reservation_only,
           SUM(ps.status='available') AS avail,
           COUNT(*) AS total
    FROM parking_slot ps
    GROUP BY ps.is_reservation_only, ps.slot_type
")->fetchAll();

$page_title = 'Parking Slot Map';
$page_subtitle = 'Real-time visualization of vehicle slot mapping and availability.';

include '../../includes/header.php';
?>



<style>
/* Visual Map Styles */
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 16px; }
.slot-box {
    border-radius: 1.5rem;
    height: 125px; 
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: var(--surface);
    border: 2px solid var(--border-color);
    border-left-width: 6px;
    transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
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
    font-size: 10px; 
    color: var(--text-primary); 
    margin-top: 10px; 
    background: var(--surface); 
    border: 1px solid var(--border-color);
    border-radius: 8px; 
    padding: 4px 12px; 
    font-family: 'Manrope', sans-serif; 
    font-weight: 800; 
    width: 100%; 
    text-align: center; 
    box-shadow: 0 2px 8px var(--shadow-color);
}
.slot-duration { font-size: 9px; color: var(--text-secondary); margin-top: 6px; font-family: 'Inter', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

.legend-bar {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: 1.5rem;
    padding: 12px 32px;
    display: flex;
    align-items: center;
    gap: 32px;
    box-shadow: 0 4px 20px var(--shadow-color);
}
</style>

<div class="px-10 py-10 max-w-[1500px] mx-auto flex flex-col gap-8">
    
    <!-- Top Header & Legend -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold text-primary tracking-tight font-manrope"><?= $page_title ?></h1>
            <p class="text-tertiary text-[13px] font-medium mt-1"><?= $page_subtitle ?></p>
        </div>
        
        <div class="legend-bar">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-tertiary">Live Status Legend:</span>
            <div class="flex items-center gap-3">
                <div class="status-badge status-badge-available">Available</div>
                <div class="status-badge status-badge-parked">Parked</div>
                <div class="status-badge status-badge-reserved">Reserved</div>
            </div>
            <div class="h-8 w-px bg-border-color mx-2"></div>
            <div class="flex items-center gap-3 bg-brand/5 px-4 py-2 rounded-xl border border-brand/10">
                <span class="w-2 h-2 rounded-full bg-brand animate-pulse"></span>
                <span id="lastRefresh" class="text-[10px] font-black uppercase tracking-widest text-brand">Live Sync</span>
            </div>
        </div>
    </div>

    <!-- Category Maps -->
    <?php foreach ($categories as $cat_name => $types): 
        $cat_avail = 0; $cat_total = 0;
        foreach($types as $ts) {
            foreach($ts as $s) {
                $cat_total++;
                if($s['status'] === 'available') $cat_avail++;
            }
        }
        $pct = $cat_total > 0 ? round($cat_avail / $cat_total * 100) : 0;
    ?>
    <div class="bento-card overflow-hidden shadow-2xl border-color">
        <!-- Category Header -->
        <div class="px-8 py-6 flex justify-between items-center border-b border-color bg-surface-alt/30">
            <div class="flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center shadow-lg">
                    <i class="fa-solid <?= str_contains($cat_name, 'Reservation') ? 'fa-shield-halved text-brand' : 'fa-layer-group' ?> text-2xl"></i>
                </div>
                <div>
                    <h2 class="font-manrope font-extrabold text-2xl text-primary tracking-tight"><?= htmlspecialchars($cat_name) ?></h2>
                    <p class="text-[10px] font-black uppercase tracking-widest text-tertiary mt-1">Unified Fleet Mapping · <?= $cat_total ?> Total Slots</p>
                </div>
            </div>

            <div class="flex items-center gap-8">
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-tertiary mb-0.5">Section Availability</div>
                    <div class="flex items-baseline gap-2">
                        <span class="font-manrope font-black text-4xl text-primary"><?= $pct ?>%</span>
                        <span class="text-[11px] font-bold text-tertiary uppercase">Free</span>
                    </div>
                </div>
                <div class="w-16 h-16 rounded-full border-4 border-border-color flex items-center justify-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-brand/10 transition-all" style="height: <?= 100-$pct ?>%; top: <?= $pct ?>%;"></div>
                    <span class="relative z-10 text-[10px] font-black text-brand"><?= $cat_avail ?></span>
                </div>
            </div>
        </div>

        <!-- Category Body (Slots) -->
        <div class="p-10 space-y-12">
            <?php foreach ($types as $type => $slots): ?>
            <div>
                <div class="flex items-center gap-5 mb-8">
                    <div class="w-10 h-10 rounded-2xl bg-surface border border-color flex items-center justify-center shadow-sm">
                        <i class="fa-solid <?= $type === 'car' ? 'fa-car text-brand' : 'fa-motorcycle text-status-available-text' ?> text-lg"></i>
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-tertiary"><?= $type === 'car' ? 'Automobile Section' : 'Two-Wheeler Section' ?></span>
                    <div class="flex-1 h-px bg-gradient-to-r from-border-color to-transparent"></div>
                </div>
                
                <div class="slot-grid">
                    <?php foreach ($slots as $s):
                        $mins = (int)$s['minutes_parked'];
                        $dur  = $mins > 0 ? ($mins >= 60 ? floor($mins/60).'h '.($mins%60).'m' : $mins.'m') : '';
                    ?>
                    <div class="slot-box <?= $s['status'] ?>">
                        <span class="slot-icon">
                            <i class="fa-solid <?= $type === 'car' ? 'fa-car' : 'fa-motorcycle' ?>"></i>
                        </span>
                        <div class="slot-num"><?= htmlspecialchars($s['display_label']) ?></div>
                        <div class="text-[9px] font-bold text-tertiary/50 uppercase tracking-tighter"><?= htmlspecialchars($s['display_category']) ?></div>
                        <?php if ($s['status'] === 'occupied' && $s['plate_number']): ?>
                        <div class="slot-plate"><?= htmlspecialchars($s['plate_number']) ?></div>
                        <div class="slot-duration"><?= $dur ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
let countdown = 30;
const badge = document.getElementById('lastRefresh');
setInterval(() => {
    countdown--;
    if (countdown <= 0) {
        location.reload();
    } else {
        badge.textContent = `Syncing in ${countdown}s`;
    }
}, 1000);
</script>

<?php include '../../includes/footer.php'; ?>
