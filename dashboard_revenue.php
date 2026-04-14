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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{background:#f0f2f5;padding-top:70px}
        .card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .summary-card{border-radius:10px;padding:16px 20px}
        tfoot td{font-weight:700}
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">📊 Revenue Dashboard</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>

<div class="container mt-4">
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-card bg-white border">
                <div class="text-muted small">Total Kendaraan</div>
                <div class="fs-3 fw-bold"><?= number_format($totals['grand_total'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card bg-white border">
                <div class="text-muted small">Total Mobil</div>
                <div class="fs-3 fw-bold text-primary"><?= number_format($totals['total_cars'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card bg-white border">
                <div class="text-muted small">Total Motor</div>
                <div class="fs-3 fw-bold text-success"><?= number_format($totals['total_motorcycles'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card bg-white border">
                <div class="text-muted small">Total Pendapatan</div>
                <div class="fs-5 fw-bold text-warning"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="card mb-5">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">Rincian Harian</h5></div>
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-secondary">
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-center">Mobil</th>
                        <th class="text-center">Motor</th>
                        <th class="text-center">Total</th>
                        <th class="text-end">Rev. Mobil</th>
                        <th class="text-end">Rev. Motor</th>
                        <th class="text-end">Total Rev.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada data transaksi.</td></tr>
                    <?php else: foreach ($daily as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['date']) ?></strong></td>
                        <td class="text-center"><span class="badge bg-primary"><?= $row['cars'] ?></span></td>
                        <td class="text-center"><span class="badge bg-success"><?= $row['motorcycles'] ?></span></td>
                        <td class="text-center fw-bold"><?= $row['total_vehicles'] ?></td>
                        <td class="text-end"><?= fmt_idr((float)$row['revenue_car']) ?></td>
                        <td class="text-end"><?= fmt_idr((float)$row['revenue_motorcycle']) ?></td>
                        <td class="text-end fw-bold"><?= fmt_idr((float)$row['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-center"><?= number_format($totals['total_cars'] ?? 0) ?></td>
                        <td class="text-center"><?= number_format($totals['total_motorcycles'] ?? 0) ?></td>
                        <td class="text-center"><?= number_format($totals['grand_total'] ?? 0) ?></td>
                        <td class="text-end"><?= fmt_idr((float)($totals['rev_car'] ?? 0)) ?></td>
                        <td class="text-end"><?= fmt_idr((float)($totals['rev_motorcycle'] ?? 0)) ?></td>
                        <td class="text-end"><?= fmt_idr((float)($totals['grand_revenue'] ?? 0)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
