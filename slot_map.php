<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

// Get all slots grouped by floor
$stmt = $pdo->query("
    SELECT ps.slot_id, ps.slot_number, ps.slot_type, ps.floor, ps.status,
           t.check_in_time, t.ticket_code,
           v.plate_number, v.owner_name,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM parking_slot ps
    LEFT JOIN transaction t ON t.slot_id = ps.slot_id AND t.payment_status = 'unpaid'
    LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    ORDER BY ps.floor, ps.slot_type, ps.slot_number
");
$all_slots = $stmt->fetchAll();

$floors = [];
foreach ($all_slots as $slot) {
    $floors[$slot['floor']][$slot['slot_type']][] = $slot;
}
ksort($floors);

// Summary per floor
$floor_summary = $pdo->query("
    SELECT floor,
           SUM(slot_type='car' AND status='available')        AS car_avail,
           SUM(slot_type='car')                               AS car_total,
           SUM(slot_type='motorcycle' AND status='available') AS moto_avail,
           SUM(slot_type='motorcycle')                        AS moto_total
    FROM parking_slot
    GROUP BY floor ORDER BY floor
")->fetchAll();
$fs = [];
foreach ($floor_summary as $row) { $fs[$row['floor']] = $row; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slot Map — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
        .slot-box { border-radius: 10px; padding: 12px 8px; text-align: center; cursor: pointer; transition: transform .15s, box-shadow .15s; position: relative; border: 2px solid transparent; }
        .slot-box:hover { transform: scale(1.04); box-shadow: 0 4px 16px rgba(0,0,0,.12); }
        .slot-box.available { background: #d4edda; border-color: #28a745; }
        .slot-box.occupied  { background: #f8d7da; border-color: #dc3545; }
        .slot-box.reserved  { background: #fff3cd; border-color: #ffc107; }
        .slot-box.maintenance { background: #e2e3e5; border-color: #6c757d; }
        .slot-box .slot-num { font-weight: 700; font-size: 13px; }
        .slot-box .slot-icon { font-size: 22px; display: block; }
        .slot-box .slot-plate { font-size: 10px; color: #555; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; }
        .slot-box .slot-duration { font-size: 10px; color: #888; }
        .floor-card { background: #fff; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .floor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .legend-dot { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
        .section-title { font-size: 13px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
        .tooltip-slot { position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 11px; white-space: nowrap; z-index: 10; pointer-events: none; display: none; }
        .slot-box:hover .tooltip-slot { display: block; }
        .refresh-badge { font-size: 11px; color: #888; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">🗺️ Slot Map</span>
        <div class="d-flex gap-2 align-items-center">
            <span class="refresh-badge" id="lastRefresh">Live</span>
            <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <!-- Legend -->
    <div class="d-flex gap-3 mb-4 flex-wrap">
        <span><span class="legend-dot bg-success me-1"></span> Tersedia</span>
        <span><span class="legend-dot bg-danger me-1"></span> Terisi</span>
        <span><span class="legend-dot bg-warning me-1"></span> Dipesan</span>
        <span><span class="legend-dot" style="background:#6c757d" class="me-1"></span> Maintenance</span>
    </div>

    <?php foreach ($floors as $floor_code => $types): ?>
    <div class="floor-card">
        <div class="floor-header">
            <div>
                <h5 class="mb-0 fw-bold">
                    <?= $floor_code === 'G' ? 'Ground Floor' : 'Level ' . str_replace('L', '', $floor_code) ?>
                </h5>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code]; ?>
                <small class="text-muted">
                    Mobil: <?= $f['car_avail'] ?>/<?= $f['car_total'] ?> tersedia &nbsp;|&nbsp;
                    Motor: <?= $f['moto_avail'] ?>/<?= $f['moto_total'] ?> tersedia
                </small>
                <?php endif; ?>
            </div>
            <div>
                <?php if (isset($fs[$floor_code])): $f = $fs[$floor_code];
                $total_avail = $f['car_avail'] + $f['moto_avail'];
                $total_all   = $f['car_total'] + $f['moto_total'];
                $pct = $total_all > 0 ? round($total_avail / $total_all * 100) : 0;
                ?>
                <span class="badge <?= $pct > 50 ? 'bg-success' : ($pct > 20 ? 'bg-warning text-dark' : 'bg-danger') ?> fs-6">
                    <?= $pct ?>% tersedia
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($types as $type => $slots): ?>
        <div class="section-title mb-2"><?= $type === 'car' ? '🚗 Area Mobil' : '🏍️ Area Motor' ?></div>
        <div class="slot-grid mb-4">
            <?php foreach ($slots as $s):
                $icon = $s['slot_type'] === 'car' ? '🚗' : '🏍️';
                $mins = (int)$s['minutes_parked'];
                $dur  = $mins > 0 ? ($mins >= 60 ? floor($mins/60).'j '.($mins%60).'m' : $mins.'m') : '';
                $tooltip = '';
                if ($s['status'] === 'occupied') {
                    $tooltip = ($s['plate_number'] ?? '-') . ' • ' . $dur;
                }
            ?>
            <div class="slot-box <?= $s['status'] ?>" title="<?= htmlspecialchars($s['slot_number']) ?>">
                <?php if ($tooltip): ?>
                <div class="tooltip-slot"><?= htmlspecialchars($tooltip) ?></div>
                <?php endif; ?>
                <span class="slot-icon"><?= $s['status'] === 'available' ? $icon : ($s['status'] === 'occupied' ? '🔴' : '🟡') ?></span>
                <div class="slot-num"><?= htmlspecialchars($s['slot_number']) ?></div>
                <?php if ($s['status'] === 'occupied' && $s['plate_number']): ?>
                <div class="slot-plate"><?= htmlspecialchars($s['plate_number']) ?></div>
                <div class="slot-duration"><?= $dur ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh every 30 seconds
let countdown = 30;
const badge = document.getElementById('lastRefresh');
setInterval(() => {
    countdown--;
    if (countdown <= 0) {
        location.reload();
    } else {
        badge.textContent = 'Refresh dalam ' + countdown + 's';
    }
}, 1000);
</script>
</body>
</html>
