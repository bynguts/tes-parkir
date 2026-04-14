<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

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
include 'includes/header.php';
?>

<style>
    .slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
    .slot-box { 
        border-radius: 12px; 
        padding: 16px 8px; 
        text-align: center; 
        cursor: pointer; 
        transition: all .2s; 
        position: relative; 
        border: 1px solid rgba(255,255,255,0.1); 
        background: rgba(30,30,40,0.4);
    }
    .slot-box:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
    
    .slot-box.available   { background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.4); box-shadow: inset 0 0 15px rgba(34, 197, 94, 0.05); }
    .slot-box.occupied    { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.4); box-shadow: inset 0 0 15px rgba(239, 68, 68, 0.05); }
    .slot-box.reserved    { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.4); box-shadow: inset 0 0 15px rgba(245, 158, 11, 0.05); }
    .slot-box.maintenance { background: rgba(100, 116, 139, 0.2); border-color: rgba(100, 116, 139, 0.4); filter: grayscale(1); }
    
    .slot-box .slot-num  { font-weight: 700; font-size: 16px; margin-top: 8px; letter-spacing: 1px; color: var(--text-main); }
    .slot-box .slot-icon { font-size: 26px; display: block; opacity: 0.9; }
    
    .slot-box .slot-plate    { font-size: 11px; color: var(--text-main); margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; background: rgba(0,0,0,0.3); border-radius: 4px; padding: 2px; }
    .slot-box .slot-duration { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
    
    .legend-dot { width: 14px; height: 14px; border-radius: 4px; display: inline-block; vertical-align: middle; }
    
    .tooltip-slot { 
        position: absolute; 
        bottom: calc(100% + 10px); 
        left: 50%; transform: translateX(-50%); 
        background: rgba(15, 23, 42, 0.95); 
        color: #fff; padding: 10px 14px; 
        border-radius: 8px; font-size: 12px; 
        white-space: nowrap; z-index: 10; 
        pointer-events: none; display: none; 
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    .slot-box:hover .tooltip-slot { display: block; }
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Live Slot Map</h4>
            <small class="text-muted">Visualisasi pemetaan slot kendaraan per lantai secara real-time.</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-3 py-2 rounded-pill d-flex align-items-center">
                <div class="spinner-grow spinner-grow-sm text-primary me-2" role="status" style="width: 10px; height: 10px;"></div>
                <span id="lastRefresh">Live Sync</span>
            </span>
        </div>
    </div>

    <!-- Legend -->
    <div class="glass-panel p-3 mb-4 d-flex justify-content-center gap-4 flex-wrap" style="background: rgba(15, 23, 42, 0.6);">
        <span class="text-white small fw-bold"><i class="fas fa-layer-group text-muted me-2"></i>LEGENDA:</span>
        <span class="text-muted small"><span class="legend-dot bg-success border border-success me-2"></span> Slot Tersedia</span>
        <span class="text-muted small"><span class="legend-dot bg-danger border border-danger me-2"></span> Kendaraan Terparkir</span>
        <span class="text-muted small"><span class="legend-dot bg-warning border border-warning me-2"></span> Telah Direservasi</span>
        <span class="text-muted small"><span class="legend-dot bg-secondary border border-secondary me-2" style="background:#475569"></span> Dalam Perawatan</span>
    </div>

    <?php foreach ($floors as $floor_code => $types): ?>
    <div class="glass-panel mb-5 p-0 overflow-hidden">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-glass) !important; background: rgba(0,0,0,0.2);">
            <div>
                <h5 class="mb-1 fw-bold text-white d-flex align-items-center">
                    <i class="fas fa-building text-primary me-2 opacity-75"></i> 
                    <?= htmlspecialchars($fs[$floor_code]['floor_name'] ?? $floor_code) ?>
                </h5>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code]; ?>
                <div class="text-muted small d-flex gap-3 mt-2">
                    <span><i class="fas fa-car-side me-1"></i> Mobil: <strong><?= $f['car_avail'] ?>/<?= $f['car_total'] ?></strong></span>
                    <span><i class="fas fa-motorcycle me-1"></i> Motor: <strong><?= $f['moto_avail'] ?>/<?= $f['moto_total'] ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code];
                $total_avail = $f['car_avail'] + $f['moto_avail'];
                $total_all   = $f['car_total'] + $f['moto_total'];
                $pct = $total_all > 0 ? round($total_avail / $total_all * 100) : 0;
                $p_cls = $pct > 50 ? 'success' : ($pct > 20 ? 'warning' : 'danger');
                ?>
                <div class="text-end">
                    <div class="text-uppercase small fw-bold text-muted mb-1" style="letter-spacing:1px;">Kapasitas</div>
                    <span class="badge bg-<?= $p_cls ?> bg-opacity-25 border border-<?= $p_cls ?> text-<?= $p_cls ?> fs-5 px-3 py-2 rounded">
                        <?= $pct ?>% Free
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-4">
            <?php foreach ($types as $type => $slots): ?>
            <h6 class="text-muted text-uppercase fw-bold mb-3 d-flex align-items-center" style="letter-spacing: 1px; font-size: 12px;">
                <span style="width: 30px; height: 1px; background: var(--border-glass); margin-right: 15px;"></span>
                <?= $type === 'car' ? '<i class="fas fa-car text-primary me-2"></i> Zona Mobil' : '<i class="fas fa-motorcycle text-success me-2"></i> Zona Motor' ?>
                <span style="flex-grow: 1; height: 1px; background: var(--border-glass); margin-left: 15px;"></span>
            </h6>
            
            <div class="slot-grid mb-5">
                <?php foreach ($slots as $s):
                    $icon = $s['slot_type'] === 'car' ? '🚗' : '🏍️';
                    $mins = (int)$s['minutes_parked'];
                    $dur  = $mins > 0 ? ($mins >= 60 ? floor($mins/60).'j '.($mins%60).'m' : $mins.'m') : '';
                    $tooltip = '';
                    if ($s['status'] === 'occupied') {
                        $tooltip = "<div class='fw-bold text-info mb-1'>" . ($s['plate_number'] ?? 'Unknown') . "</div>" . 
                                   "<div class='text-muted small'><i class='far fa-clock me-1'></i> " . $dur . "</div>";
                    } else if ($s['status'] === 'reserved') {
                        $tooltip = "Slot telah direservasi.";
                    } else if ($s['status'] === 'maintenance') {
                        $tooltip = "Slot sedang dalam perbaikan.";
                    }
                ?>
                <div class="slot-box <?= $s['status'] ?>">
                    <?php if ($tooltip): ?>
                    <div class="tooltip-slot"><?= $tooltip ?></div>
                    <?php endif; ?>
                    
                    <span class="slot-icon" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5));">
                        <?php if ($s['status'] === 'available'): ?>
                            <?= $s['slot_type'] === 'car' ? '<i class="fas fa-car-side text-success"></i>' : '<i class="fas fa-motorcycle text-success"></i>' ?>
                        <?php elseif ($s['status'] === 'occupied'): ?>
                            <?= $s['slot_type'] === 'car' ? '<i class="fas fa-car-side text-danger"></i>' : '<i class="fas fa-motorcycle text-danger"></i>' ?>
                        <?php elseif ($s['status'] === 'reserved'): ?>
                            <i class="fas fa-lock text-warning"></i>
                        <?php else: ?>
                            <i class="fas fa-tools text-secondary"></i>
                        <?php endif; ?>
                    </span>
                    
                    <div class="slot-num"><?= htmlspecialchars($s['slot_number']) ?></div>
                    
                    <?php if ($s['status'] === 'occupied' && $s['plate_number']): ?>
                    <div class="slot-plate fw-bold font-monospace"><?= htmlspecialchars($s['plate_number']) ?></div>
                    <div class="slot-duration"><i class="far fa-clock me-1"></i><?= $dur ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
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
        badge.innerHTML = `Syncing in ${countdown}s`;
    }
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>