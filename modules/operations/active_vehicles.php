<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$active = $pdo->query("
    SELECT t.transaction_id, t.ticket_code, s.slot_number, f.floor_code AS floor,
           t.check_in_time, v.plate_number, v.vehicle_type,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM `transaction` t
    JOIN vehicle v       ON t.vehicle_id  = v.vehicle_id
    JOIN parking_slot s  ON t.slot_id     = s.slot_id
    JOIN floor f         ON s.floor_id    = f.floor_id
    WHERE t.payment_status = 'unpaid'
    ORDER BY t.check_in_time
")->fetchAll();

$page_title = 'Kendaraan Aktif';
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Kendaraan Aktif</h4>
            <small class="text-muted">Total: <?= count($active) ?> kendaraan terparkir</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button onclick="location.reload()" class="btn btn-primary d-flex align-items-center gap-2" style="border-radius: 8px;">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
    </div>

    <div class="glass-panel p-0">
        <div class="table-responsive" style="border: none;">
            <table class="table table-glass table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Trx ID</th>
                        <th>Tiket</th>
                        <th>Tipe</th>
                        <th>Slot / Lantai</th>
                        <th>Waktu Check-In</th>
                        <th class="pe-4">Durasi Parkir</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($active)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-car-slash fs-3 mb-3 d-block"></i>Tidak ada kendaraan yang sedang parkir saat ini.</td></tr>
                <?php else: foreach ($active as $row):
                    $mins = (int)$row['minutes_parked'];
                    $dur  = floor($mins/60).'j '.($mins%60).'m';
                    $is_overdue = $mins >= 480;
                ?>
                <tr style="<?= $is_overdue ? 'background-color: rgba(245, 158, 11, 0.05);' : '' ?>">
                    <td class="text-muted small ps-4">#<?= $row['transaction_id'] ?></td>
                    <td><code class="text-primary-glow font-monospace fs-6"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code></td>
                    <td>
                        <?php if ($row['vehicle_type'] === 'car'): ?>
                            <i class="fas fa-car-side text-primary"></i> <span class="ms-1 small">Mobil</span>
                        <?php else: ?>
                            <i class="fas fa-motorcycle text-success"></i> <span class="ms-1 small">Motor</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-dark border border-secondary text-light">Str. <?= htmlspecialchars($row['floor']) ?></span>
                        <span class="ms-1 fw-bold"><?= htmlspecialchars($row['slot_number']) ?></span>
                    </td>
                    <td class="text-muted"><i class="far fa-clock me-1"></i><?= date('H:i, d M', strtotime($row['check_in_time'])) ?></td>
                    <td class="pe-4">
                        <span class="badge <?= $is_overdue ? 'bg-warning text-dark border border-warning shadow-sm' : 'bg-primary bg-opacity-25 text-primary border border-primary' ?> px-3 py-2" style="border-radius: 20px;">
                            <?= $dur ?>
                            <?php if ($is_overdue): ?> <i class="fas fa-exclamation-circle ms-1"></i> <?php endif; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 mb-2 text-muted small px-2">
        <i class="fas fa-info-circle me-1 text-warning"></i>
        Sorotan tabel peringatan kuning menandakan kendaraan telah parkir lebih dari 8 jam.
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
