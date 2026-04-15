<?php
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

if (empty($_GET['kode_tiket'])) {
    header("Location: gate_simulator.php"); exit;
}

$code = trim($_GET['kode_tiket']);

// ── 1. Validate ticket ────────────────────────────────────────────────────
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
$vtype_icon  = $trx['vehicle_type'] === 'car' ? 'directions_car' : 'two_wheeler';
$fee_fmt     = fmt_idr($total_fee);
$duration_label = $hours_total . ' jam (' . (int)$trx['minutes_parked'] . ' menit)';
$slot_label  = htmlspecialchars($trx['slot_number'] . ' / Lantai ' . $trx['floor']);
$now_fmt     = date('d M Y H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendaraan Keluar — SmartParking</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s cubic-bezier(.16,1,.3,1) forwards; }
    </style>
</head>
<body class="min-h-screen bg-[#f2f4f7] flex items-center justify-center px-4">

<div class="w-full max-w-sm fade-up">

    <!-- Success badge -->
    <div class="text-center mb-6">
        <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-200">
            <span class="material-symbols-outlined text-white text-3xl">check_circle</span>
        </div>
        <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Kendaraan Keluar</h1>
        <p class="text-slate-400 text-sm mt-1">Tiket tervalidasi — Gerbang Terbuka</p>
        <span class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 text-xs font-bold font-inter uppercase tracking-widest px-4 py-1.5 rounded-full mt-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            GATE OPEN
        </span>
    </div>

    <!-- Receipt card -->
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-4">

        <div class="space-y-3 mb-5">
            <?php
            $rows = [
                ['Kendaraan',  '<span class="material-symbols-outlined text-base align-middle">' . $vtype_icon . '</span> ' . ($trx['vehicle_type'] === 'car' ? 'Mobil' : 'Motor')],
                ['Slot',       $slot_label],
                ['Kode Tiket', '<code class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">' . htmlspecialchars($code) . '</code>'],
                ['Check-in',   htmlspecialchars($trx['check_in_time'])],
                ['Check-out',  $now_fmt],
                ['Durasi',     $duration_label],
            ];
            foreach ($rows as [$label, $value]):
            ?>
            <div class="flex justify-between items-start py-2 border-b border-slate-50">
                <span class="text-slate-400 text-sm font-inter"><?= $label ?></span>
                <span class="text-slate-800 text-sm font-semibold font-inter text-right max-w-[55%]"><?= $value ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Fee highlight -->
        <div class="bg-slate-900 rounded-xl px-5 py-4 text-center">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-1">Total Biaya Parkir</p>
            <p class="font-manrope font-extrabold text-3xl text-white"><?= $fee_fmt ?></p>
        </div>
    </div>

    <a href="gate_simulator.php"
       class="flex items-center justify-center gap-2 w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3.5 transition-all">
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Kembali ke Gate Simulator
    </a>

    <p class="text-center text-slate-400 text-xs font-inter mt-4">Auto-redirect in <span id="cnt">8</span>s</p>
</div>

<script>
let s = 8;
const c = document.getElementById('cnt');
setInterval(() => { s--; c.textContent = s; if (s <= 0) window.location.href = 'gate_simulator.php'; }, 1000);
</script>
</body>
</html>
