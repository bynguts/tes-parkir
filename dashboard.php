<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$active = $pdo->query("
    SELECT t.transaction_id, t.ticket_code, s.slot_number, s.floor,
           t.check_in_time, v.plate_number, v.vehicle_type,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM `transaction` t
    JOIN vehicle v      ON t.vehicle_id = v.vehicle_id
    JOIN parking_slot s ON t.slot_id    = s.slot_id
    WHERE t.payment_status = 'unpaid'
    ORDER BY t.check_in_time
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendaraan Aktif — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#f0f2f5;padding-top:70px}.card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06)}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">🚗 Kendaraan Aktif</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Parkir Aktif (<?= count($active) ?> kendaraan)</h5>
        <button onclick="location.reload()" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sync me-1"></i>Refresh
        </button>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Trx ID</th>
                        <th>Tiket</th>
                        <th>Plat</th>
                        <th>Tipe</th>
                        <th>Slot / Lantai</th>
                        <th>Check-In</th>
                        <th>Durasi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($active)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada kendaraan yang sedang parkir.</td></tr>
                <?php else: foreach ($active as $row):
                    $mins = (int)$row['minutes_parked'];
                    $dur  = floor($mins/60).'j '.($mins%60).'m';
                    $warning = $mins >= 480 ? 'table-warning' : '';
                ?>
                <tr class="<?= $warning ?>">
                    <td class="text-muted small">#<?= $row['transaction_id'] ?></td>
                    <td><code class="fw-bold"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['plate_number']) ?></td>
                    <td><?= $row['vehicle_type'] === 'car' ? '🚗' : '🏍️' ?></td>
                    <td><?= htmlspecialchars($row['slot_number']) ?> / <?= $row['floor'] ?></td>
                    <td><?= htmlspecialchars($row['check_in_time']) ?></td>
                    <td>
                        <span class="badge <?= $mins >= 480 ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                            <?= $dur ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>Baris kuning = parkir lebih dari 8 jam.</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
