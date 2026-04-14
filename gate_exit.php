<?php
require_once 'config/connection.php';
require_once 'includes/functions.php';

if (empty($_GET['kode_tiket'])) {
    header("Location: gate_simulator.php"); exit;
}

$code = trim($_GET['kode_tiket']);

// ── 1. Validate ticket ────────────────────────────────────────────────────
// [3NF FIX] plate_number dihapus dari tabel ticket → ambil via JOIN ke vehicle
$stmt = $pdo->prepare("
    SELECT tk.ticket_id, tk.transaction_id,
           v.plate_number
    FROM ticket tk
    JOIN `transaction` t ON tk.transaction_id = t.transaction_id
    JOIN vehicle v       ON t.vehicle_id       = v.vehicle_id
    WHERE tk.ticket_code = ? AND tk.status = 'active'
    LIMIT 1
");
$stmt->execute([$code]);
$tkt = $stmt->fetch();

if (!$tkt) {
    echo "<script>alert('❌ Tiket tidak valid atau sudah digunakan.\\nKode: ".addslashes($code)."');window.location.href='gate_simulator.php';</script>";
    exit;
}

$trx_id = (int)$tkt['transaction_id'];
$plate  = $tkt['plate_number'];

// ── 2. Get transaction ────────────────────────────────────────────────────
// [3NF FIX] parking_slot tidak lagi punya kolom floor (varchar),
//           sekarang pakai floor_id FK → JOIN tabel floor untuk dapat floor_code
$stmt = $pdo->prepare("
    SELECT t.*, v.vehicle_type, v.owner_name,
           s.slot_number, f.floor_code AS floor,
           r.first_hour_rate, r.next_hour_rate, r.daily_max_rate,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM `transaction` t
    JOIN vehicle v       ON t.vehicle_id  = v.vehicle_id
    JOIN parking_slot s  ON t.slot_id     = s.slot_id
    JOIN floor f         ON s.floor_id    = f.floor_id
    JOIN parking_rate r  ON t.rate_id     = r.rate_id
    WHERE t.transaction_id = ? AND t.payment_status = 'unpaid'
    LIMIT 1
");
$stmt->execute([$trx_id]);
$trx = $stmt->fetch();

if (!$trx) {
    echo "<script>alert('⚠️ Transaksi tidak ditemukan atau sudah dibayar.\\nKode: ".addslashes($code)."');window.location.href='gate_simulator.php';</script>";
    exit;
}

// ── 3. Calculate fee ──────────────────────────────────────────────────────
$result       = calculate_fee(
    (int)$trx['minutes_parked'],
    (float)$trx['first_hour_rate'],
    (float)$trx['next_hour_rate'],
    (float)$trx['daily_max_rate']
);
$total_fee    = $result['total_fee'];
$hours_total  = $result['hours'];
$duration_hrs = $result['duration_hours'];
$slot_id      = (int)$trx['slot_id'];

// ── 4. DB updates (atomic) ────────────────────────────────────────────────
$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE `transaction` SET check_out_time=NOW(), duration_hours=?, total_fee=?, payment_status='paid' WHERE transaction_id=?")
        ->execute([$duration_hrs, $total_fee, $trx_id]);

    $pdo->prepare("UPDATE ticket SET status='used' WHERE ticket_code=?")
        ->execute([$code]);

    $pdo->prepare("UPDATE parking_slot SET status='available' WHERE slot_id=?")
        ->execute([$slot_id]);

    $pdo->prepare("INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action) VALUES (?,?,?,1,'open')")
        ->execute([$plate, 'exit', $code]);

    $pdo->prepare("INSERT INTO transaction_log (transaction_id, action_type, total_fee) VALUES (?,'VEHICLE_CHECKOUT',?)")
        ->execute([$trx_id, $total_fee]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    echo "<script>alert('❌ Terjadi kesalahan sistem. Hubungi admin.');window.location.href='gate_simulator.php';</script>";
    exit;
}

// ── 5. Display receipt ────────────────────────────────────────────────────
$vtype_label    = $trx['vehicle_type'] === 'car' ? '🚗 Mobil' : '🏍️ Motor';
$fee_fmt        = fmt_idr($total_fee);
$duration_label = $hours_total . ' jam (' . (int)$trx['minutes_parked'] . ' menit)';
$slot_label     = htmlspecialchars($trx['slot_number'] . ' / Lantai ' . $trx['floor']);
$now_fmt        = date('d M Y H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendaraan Keluar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#1a1a2e;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .receipt{background:#fff;border-radius:12px;padding:32px 28px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
        .success-icon{font-size:64px;margin-bottom:8px}
        h2{font-size:22px;font-weight:700;color:#1b1b1b;margin-bottom:4px}
        .sub{color:#888;font-size:13px;margin-bottom:20px}
        .dashed{border-top:2px dashed #ddd;margin:16px 0}
        .info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:14px}
        .info-row .label{color:#666}
        .info-row .val{font-weight:600;color:#111;text-align:right;max-width:60%}
        .fee-box{background:#28a745;color:#fff;border-radius:10px;padding:16px;margin:16px 0}
        .fee-box .fee-label{font-size:12px;opacity:.85;margin-bottom:4px}
        .fee-box .fee-amount{font-size:32px;font-weight:800;letter-spacing:1px}
        .badge-open{display:inline-block;background:#28a745;color:white;padding:4px 16px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:16px}
        .btn-back{background:#333;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:15px;cursor:pointer;width:100%;margin-top:8px;text-decoration:none;display:block}
        .btn-back:hover{background:#555;color:#fff}
    </style>
</head>
<body>
    <div class="receipt">
        <div class="success-icon">✅</div>
        <h2>Kendaraan Keluar</h2>
        <p class="sub">Tiket tervalidasi — Gerbang Terbuka</p>
        <span class="badge-open">🟢 GATE OPEN</span>
        <div class="dashed"></div>
        <div class="info-row"><span class="label">Kendaraan</span><span class="val"><?= $vtype_label ?></span></div>
        <div class="info-row"><span class="label">Slot</span><span class="val"><?= $slot_label ?></span></div>
        <div class="info-row"><span class="label">Kode Tiket</span><span class="val" style="font-family:monospace;font-size:12px"><?= htmlspecialchars($code) ?></span></div>
        <div class="info-row"><span class="label">Plat</span><span class="val"><?= htmlspecialchars($plate) ?></span></div>
        <div class="info-row"><span class="label">Check-in</span><span class="val"><?= htmlspecialchars($trx['check_in_time']) ?></span></div>
        <div class="info-row"><span class="label">Check-out</span><span class="val"><?= $now_fmt ?></span></div>
        <div class="info-row"><span class="label">Durasi</span><span class="val"><?= $duration_label ?></span></div>
        <div class="fee-box">
            <div class="fee-label">TOTAL BIAYA PARKIR</div>
            <div class="fee-amount"><?= $fee_fmt ?></div>
        </div>
        <div class="dashed"></div>
        <a href="gate_simulator.php" class="btn-back">← Kembali ke Gate Simulator</a>
    </div>
    <script>setTimeout(() => { window.location.href = 'gate_simulator.php'; }, 8000);</script>
</body>
</html>