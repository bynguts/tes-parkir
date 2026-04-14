<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$daily = $pdo->query("
    SELECT CAST(t.check_out_time AS DATE) AS date,
           SUM(v.vehicle_type='car')         AS cars,
           SUM(v.vehicle_type='motorcycle')  AS motorcycles,
           COUNT(*)                          AS total_vehicles,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS revenue_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS revenue_motorcycle,
           SUM(t.total_fee) AS total_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
    GROUP BY CAST(t.check_out_time AS DATE)
    ORDER BY date DESC
")->fetchAll();

$totals = $pdo->query("
    SELECT SUM(v.vehicle_type='car')         AS total_cars,
           SUM(v.vehicle_type='motorcycle')  AS total_motorcycles,
           COUNT(*)                          AS grand_total,
           SUM(CASE WHEN v.vehicle_type='car'        THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_motorcycle,
           SUM(t.total_fee) AS grand_revenue
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
")->fetch();

$page_title = 'Laporan Revenue';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Analytics & Revenue</h4>
            <small class="text-muted">Ringkasan agregat finansial dan kinerja harian.</small>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Total Trx</div>
                        <div class="fs-2 fw-bold text-main mb-1">
                            <?= number_format($totals['grand_total'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="icon-box">
                        <i class="fas fa-receipt text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Mobil Keluar</div>
                        <div class="fs-2 fw-bold text-primary-glow mb-1">
                            <?= number_format($totals['total_cars'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: var(--primary);">
                        <i class="fas fa-car-side"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Motor Keluar</div>
                        <div class="fs-2 fw-bold text-success-glow mb-1">
                            <?= number_format($totals['total_motorcycles'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="icon-box" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-xl-3">
            <div class="glass-card stat-card" style="border-color: rgba(245, 158, 11, 0.3);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Total Net Revenue</div>
                        <div class="fs-4 fw-bold text-warning mb-1" style="text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);">
                            <?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?>
                        </div>
                    </div>
                    <div class="icon-box" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="glass-panel p-0 mb-5">
        <div class="p-4 border-bottom" style="border-color: var(--border-glass) !important;">
            <h5 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i>Breakdown Harian</h5>
        </div>
        <div class="table-responsive" style="border: none;">
            <table class="table table-glass table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Tanggal Operasional</th>
                        <th class="text-center">Volume Mobil</th>
                        <th class="text-center">Volume Motor</th>
                        <th class="text-center">Total Kunjungan</th>
                        <th class="text-end">Rev. Mobil</th>
                        <th class="text-end">Rev. Motor</th>
                        <th class="text-end pe-4">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-folder-open fs-3 mb-3 d-block"></i>Belum ada data transaksi yang tercatat.</td></tr>
                    <?php else: foreach ($daily as $row): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><i class="far fa-calendar-check text-muted me-2"></i><?= date('D, d M Y', strtotime($row['date'])) ?></td>
                        <td class="text-center"><span class="badge bg-primary bg-opacity-25 text-primary px-3"><?= $row['cars'] ?></span></td>
                        <td class="text-center"><span class="badge bg-success bg-opacity-25 text-success px-3"><?= $row['motorcycles'] ?></span></td>
                        <td class="text-center fw-bold fs-6"><?= $row['total_vehicles'] ?></td>
                        <td class="text-end text-muted"><?= fmt_idr((float)$row['revenue_car']) ?></td>
                        <td class="text-end text-muted"><?= fmt_idr((float)$row['revenue_motorcycle']) ?></td>
                        <td class="text-end pe-4 fw-bold text-success-glow"><?= fmt_idr((float)$row['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot style="background: rgba(0,0,0,0.2);">
                    <tr>
                        <td class="ps-4 text-uppercase fw-bold text-muted" style="letter-spacing:1px;">Kumulatif Total</td>
                        <td class="text-center fw-bold"><?= number_format($totals['total_cars'] ?? 0) ?></td>
                        <td class="text-center fw-bold"><?= number_format($totals['total_motorcycles'] ?? 0) ?></td>
                        <td class="text-center fw-bold text-primary-glow fs-5"><?= number_format($totals['grand_total'] ?? 0) ?></td>
                        <td class="text-end fw-bold"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></td>
                        <td class="text-end fw-bold"><?= fmt_idr((float)($totals['rev_motorcycle'] ?? 0)) ?></td>
                        <td class="text-end pe-4 fw-bold fs-5 text-warning" style="text-shadow: 0 0 10px rgba(245, 158, 11, 0.4);"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
