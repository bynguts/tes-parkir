<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$stmt = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.status,
           f.floor_code AS floor, f.floor_name,
           t.check_in_time, t.ticket_code,
           v.plate_number, v.owner_name,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    LEFT JOIN `transaction` t ON t.slot_id = ps.slot_id AND t.payment_status = 'unpaid'
    LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    ORDER BY f.floor_code, ps.slot_type, ps.slot_number
");
$all_slots = $stmt->fetchAll();

$floors = [];
foreach ($all_slots as $slot) {
    $floors[$slot['floor']][$slot['slot_type']][] = $slot;
}
ksort($floors);

$floor_summary = $pdo->query("
    SELECT f.floor_code AS floor, f.floor_name,
           SUM(ps.slot_type='car'        AND ps.status='available') AS car_avail,
           SUM(ps.slot_type='car')                                  AS car_total,
           SUM(ps.slot_type='motorcycle' AND ps.status='available') AS moto_avail,
           SUM(ps.slot_type='motorcycle')                           AS moto_total
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    GROUP BY f.floor_id, f.floor_code, f.floor_name
    ORDER BY f.floor_code
")->fetchAll();
$fs = [];
foreach ($floor_summary as $row) { $fs[$row['floor']] = $row; }

$page_title = 'Parking Slot Map';
$page_subtitle = 'Real-time visualization of vehicle slot mapping per floor.';
$page_actions = '
<div class="flex items-center gap-2 bg-emerald-500/10 text-emerald-700 text-[11px] font-extrabold font-inter uppercase tracking-[0.2em] px-4 py-2 rounded-lg border border-emerald-500/20">
    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
    <span id="lastRefresh">Live Sync</span>
</div>';

include '../../includes/header.php';
?>

<style>
<style>
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 12px; }
.slot-box {
    border-radius: 1rem;
    height: 110px; 
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px;
    cursor: pointer;
    transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    background: white;
    border: 1px solid rgba(15, 23, 42, 0.05);
    border-left: 4px solid transparent;
}
.slot-box:hover { transform: translateY(-4px); box-shadow: 0 12px 30px -10px rgba(15, 23, 42, 0.1); border-color: rgba(15, 23, 42, 0.1); }

/* Silk White Status Accents */
.slot-box.available   { border-left-color: #10b981; }
.slot-box.occupied    { border-left-color: #ef4444; }
.slot-box.reserved    { border-left-color: #f59e0b; }
.slot-box.maintenance { border-left-color: #64748b; }

.slot-num  { 
    font-weight: 800; 
    font-size: 15px; 
    font-family: 'Manrope', sans-serif; 
    color: #0f172a;
}
.slot-icon { font-size: 20px; color: rgba(15, 23, 42, 0.2); margin-bottom: 4px; }
.slot-plate    { font-size: 9px; color: #0f172a; margin-top: 8px; background: rgba(15, 23, 42, 0.04); border-radius: 6px; padding: 3px 8px; font-family: monospace; font-weight: 700; width: 100%; text-align: center; }
.slot-duration { font-size: 9px; color: rgba(15, 23, 42, 0.4); margin-top: 3px; font-family: 'Inter', sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
</style>

    <div class="p-6">

        <!-- Legend -->
        <div class="bg-white rounded-2xl px-6 py-4 mb-6 flex flex-wrap items-center gap-6 ring-1 ring-slate-900/5 shadow-sm">
            <span class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Legend:</span>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-900/60 font-semibold">
                <i class="fa-solid fa-circle text-emerald-500 text-[10px]"></i> Available
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-900/60 font-semibold">
                <i class="fa-solid fa-circle text-red-500 text-[10px]"></i> Occupied
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-900/60 font-semibold">
                <i class="fa-solid fa-circle text-amber-500 text-[10px]"></i> Reserved
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-900/60 font-semibold">
                <i class="fa-solid fa-circle text-slate-900/20 text-[10px]"></i> Maintenance
            </div>
        </div>

        <?php foreach ($floors as $floor_code => $types): ?>
        <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-slate-900/5 overflow-hidden mb-6">
            <!-- Floor header -->
            <div class="px-8 py-6 flex justify-between items-center border-b border-slate-900/10">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-building text-slate-900/20 text-lg"></i>
                        <h2 class="font-manrope font-extrabold text-xl text-slate-900 tracking-tight"><?= htmlspecialchars($fs[$floor_code]['floor_name'] ?? $floor_code) ?></h2>
                    </div>
                    <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code]; ?>
                    <div class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-[0.2em] font-inter flex gap-4 mt-1.5 ml-7">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-car text-slate-900/20"></i> Cars: <strong class="text-slate-900 font-manrope"><?= $f['car_avail'] ?>/<?= $f['car_total'] ?></strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-motorcycle text-slate-900/20"></i> Moto: <strong class="text-slate-900 font-manrope"><?= $f['moto_avail'] ?>/<?= $f['moto_total'] ?></strong></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code];
                    $total_avail = $f['car_avail'] + $f['moto_avail'];
                    $total_all   = $f['car_total'] + $f['moto_total'];
                    $pct = $total_all > 0 ? round($total_avail / $total_all * 100) : 0;
                    $pct_cls = $pct > 50 ? 'bg-emerald-50/10 text-emerald-700 border-emerald-500/10' : ($pct > 20 ? 'bg-amber-50/10 text-amber-700 border-amber-500/10' : 'bg-red-50/10 text-red-700 border-red-500/10');
                ?>
                <div class="text-right">
                    <div class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter mb-1">Live Occupancy</div>
                    <span class="font-manrope font-extrabold text-3xl <?= explode(' ', $pct_cls)[1] ?>"><?= $pct ?>%</span>
                    <div class="text-slate-900/40 text-[11px] uppercase font-extrabold tracking-[0.2em] font-inter mt-1">available</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-8">
                <?php foreach ($types as $type => $slots): ?>
                <div class="mb-8 last:mb-0">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-slate-900/5 text-slate-900/40 border border-slate-900/10 flex items-center justify-center transition-all group-hover:bg-slate-900 group-hover:text-white">
                            <i class="fa-solid <?= $type === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg"></i>
                        </div>
                        <span class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter"><?= $type === 'car' ? 'Automobile Section' : 'Two-Wheeler Section' ?></span>
                        <div class="flex-1 h-px bg-slate-900/[0.05]"></div>
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
                            <div class="slot-num"><?= htmlspecialchars($s['slot_number']) ?></div>
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
