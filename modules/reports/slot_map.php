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

$page_title = 'Peta Slot Parkir';
include '../../includes/header.php';
?>

<style>
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
.slot-box {
    border-radius: 16px;
    padding: 16px 8px;
    text-align: center;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    position: relative;
    background: #f8fafc;
}
.slot-box:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.slot-box.available   { background: #f0fdf4; }
.slot-box.occupied    { background: #fef2f2; }
.slot-box.reserved    { background: #fffbeb; }
.slot-box.maintenance { background: #f1f5f9; filter: grayscale(1); opacity: 0.7; }
.slot-num  { font-weight: 700; font-size: 15px; margin-top: 6px; letter-spacing: 1px; color: #0f172a; font-family: 'Manrope', sans-serif; }
.slot-icon { font-size: 24px; display: block; }
.slot-plate    { font-size: 10px; color: #334155; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; background: rgba(0,0,0,0.06); border-radius: 4px; padding: 2px 4px; font-family: monospace; font-weight: 700; }
.slot-duration { font-size: 10px; color: #64748b; margin-top: 3px; font-family: 'Inter', sans-serif; }
.tooltip-slot {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%; transform: translateX(-50%);
    background: #0f172a;
    color: #fff; padding: 8px 12px;
    border-radius: 10px; font-size: 11px;
    white-space: nowrap; z-index: 30;
    pointer-events: none; display: none;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 8px 20px rgba(0,0,0,.2);
}
.slot-box:hover .tooltip-slot { display: block; }
</style>

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <header class="flex justify-between items-center px-8 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Live Slot Map</h1>
            <p class="text-slate-400 text-xs font-inter mt-0.5">Visualisasi pemetaan slot kendaraan per lantai secara real-time.</p>
        </div>
        <div class="flex items-center gap-2 bg-emerald-50 text-emerald-700 text-xs font-bold font-inter uppercase tracking-widest px-4 py-2 rounded-full">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span id="lastRefresh">Live Sync</span>
        </div>
    </header>

    <div class="p-8 max-w-[1440px] mx-auto">

        <!-- Legend -->
        <div class="bg-white rounded-2xl px-6 py-4 mb-6 flex flex-wrap items-center gap-6">
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Legenda:</span>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-600">
                <span class="w-4 h-4 rounded bg-emerald-100 border border-emerald-300 inline-block"></span> Tersedia
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-600">
                <span class="w-4 h-4 rounded bg-red-100 border border-red-300 inline-block"></span> Terisi
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-600">
                <span class="w-4 h-4 rounded bg-amber-100 border border-amber-300 inline-block"></span> Direservasi
            </div>
            <div class="flex items-center gap-2 text-sm font-inter text-slate-600">
                <span class="w-4 h-4 rounded bg-slate-200 border border-slate-300 inline-block"></span> Perawatan
            </div>
        </div>

        <?php foreach ($floors as $floor_code => $types): ?>
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
            <!-- Floor header -->
            <div class="px-6 py-5 flex justify-between items-center border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-slate-400 text-xl">apartment</span>
                        <h2 class="font-manrope font-bold text-lg text-slate-900"><?= htmlspecialchars($fs[$floor_code]['floor_name'] ?? $floor_code) ?></h2>
                    </div>
                    <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code]; ?>
                    <div class="text-slate-400 text-xs font-inter flex gap-4 mt-1 ml-7">
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-blue-400">directions_car</span> Mobil: <strong class="text-slate-700"><?= $f['car_avail'] ?>/<?= $f['car_total'] ?></strong></span>
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-emerald-400">two_wheeler</span> Motor: <strong class="text-slate-700"><?= $f['moto_avail'] ?>/<?= $f['moto_total'] ?></strong></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code];
                    $total_avail = $f['car_avail'] + $f['moto_avail'];
                    $total_all   = $f['car_total'] + $f['moto_total'];
                    $pct = $total_all > 0 ? round($total_avail / $total_all * 100) : 0;
                    $pct_cls = $pct > 50 ? 'bg-emerald-50 text-emerald-700' : ($pct > 20 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700');
                ?>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-1">Kapasitas</div>
                    <span class="font-manrope font-extrabold text-2xl <?= explode(' ', $pct_cls)[1] ?>"><?= $pct ?>%</span>
                    <div class="text-slate-400 text-xs font-inter">tersedia</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-6">
                <?php foreach ($types as $type => $slots): ?>
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-slate-400 text-base"><?= $type === 'car' ? 'directions_car' : 'two_wheeler' ?></span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter"><?= $type === 'car' ? 'Zona Mobil' : 'Zona Motor' ?></span>
                        <div class="flex-1 h-px bg-slate-100"></div>
                    </div>
                    <div class="slot-grid">
                        <?php foreach ($slots as $s):
                            $mins = (int)$s['minutes_parked'];
                            $dur  = $mins > 0 ? ($mins >= 60 ? floor($mins/60).'j '.($mins%60).'m' : $mins.'m') : '';
                            $tooltip = '';
                            if ($s['status'] === 'occupied') {
                                $tooltip = "<strong>" . ($s['plate_number'] ?? 'Unknown') . "</strong><br>" . ($dur ? "⏱ $dur" : '');
                            } elseif ($s['status'] === 'reserved') {
                                $tooltip = "Slot telah direservasi.";
                            } elseif ($s['status'] === 'maintenance') {
                                $tooltip = "Slot sedang dalam perbaikan.";
                            }
                        ?>
                        <div class="slot-box <?= $s['status'] ?>">
                            <?php if ($tooltip): ?>
                            <div class="tooltip-slot"><?= $tooltip ?></div>
                            <?php endif; ?>
                            <span class="slot-icon">
                                <?php if ($s['status'] === 'available'): ?>
                                    <?= $s['slot_type'] === 'car' ? '🟢' : '🟢' ?>
                                <?php elseif ($s['status'] === 'occupied'): ?>
                                    <?= $s['slot_type'] === 'car' ? '🚗' : '🏍️' ?>
                                <?php elseif ($s['status'] === 'reserved'): ?>
                                    🔒
                                <?php else: ?>
                                    🔧
                                <?php endif; ?>
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
</main>

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
