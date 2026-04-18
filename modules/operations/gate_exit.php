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
    echo "<script>alert('❌ Invalid ticket or already processed.\\nCode: ".addslashes($code)."');window.location.href='gate_simulator.php';</script>";
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
    echo "<script>alert('⚠️ Transaction not found or already paid.\\nCode: ".addslashes($code)."');window.location.href='gate_simulator.php';</script>";
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
    echo "<script>alert('❌ System error occurred. Please contact administrator.');window.location.href='gate_simulator.php';</script>";
    exit;
}

// ── 5. Display receipt ────────────────────────────────────────────────────
$vtype_icon  = $trx['vehicle_type'] === 'car' ? 'directions_car' : 'two_wheeler';
$fee_fmt     = fmt_idr($total_fee);
$duration_label = $hours_total . ' h (' . (int)$trx['minutes_parked'] . ' m)';
$slot_label  = htmlspecialchars($trx['slot_number'] . ' / Floor ' . $trx['floor']);
$now_fmt     = date('d M y H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Checkout — SmartParking</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .font-code { font-family: 'Courier Prime', monospace !important; letter-spacing: 0.05em; }
        i.fa-solid { vertical-align: middle; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s cubic-bezier(.16,1,.3,1) forwards; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center px-4 font-inter antialiased">

<div class="w-full max-w-sm fade-up">

    <!-- Success badge -->
    <div class="text-center mb-6">
        <div class="w-16 h-16 bg-emerald-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl shadow-emerald-500/10 border border-emerald-500/20">
            <i class="fa-solid fa-circle-check text-emerald-600 text-3xl"></i>
        </div>
        <h1 class="font-manrope font-extrabold text-2xl text-slate-900 tracking-tight">Checkout Complete</h1>
        <p class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-widest mt-1">Ticket Validated — Gate Released</p>
        <span class="inline-flex items-center gap-2 bg-emerald-50/10 text-emerald-600 text-[10px] font-extrabold font-inter uppercase tracking-[0.15em] px-4 py-1.5 rounded-lg mt-3 border border-emerald-500/10 shadow-sm backdrop-blur-md">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            LANE_EXIT_OPEN
        </span>
    </div>

    <!-- Receipt card -->
    <div class="bg-white rounded-3xl shadow-[0_20px_50px_rgba(15,23,42,0.04)] p-8 mb-5 ring-1 ring-slate-900/5 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-slate-900/[0.02] rounded-full -mr-16 -mt-16 blur-2xl"></div>

        <div class="space-y-4 mb-8 relative z-10">
            <?php
            $rows = [
                ['Vehicle',    '<i class="fa-solid fa-' . ($trx['vehicle_type'] === 'car' ? 'car' : 'motorcycle') . ' text-sm text-slate-900/30"></i> ' . ($trx['vehicle_type'] === 'car' ? 'Car' : 'Motorcycle')],
                ['Slot',       $slot_label],
                ['Ticket Code', '<code class="font-code text-[11px] font-bold bg-slate-900/5 px-2 py-0.5 rounded-lg text-slate-900">' . htmlspecialchars($code) . '</code>'],
                ['Check-in',   date('d M H:i', strtotime($trx['check_in_time']))],
                ['Check-out',  date('H:i')],
                ['Duration',   $duration_label],
            ];
            foreach ($rows as [$label, $value]):
            ?>
            <div class="flex justify-between items-center py-1">
                <span class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-[0.2em] font-inter"><?= $label ?></span>
                <span class="text-slate-900 text-[13px] font-bold font-inter text-right"><?= $value ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Fee highlight -->
        <div class="bg-slate-900 rounded-2xl px-5 py-6 text-center shadow-2xl shadow-slate-900/20 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-white/[0.05] to-transparent pointer-events-none"></div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-white/40 font-inter mb-1.5 relative z-10">Total Parking Fee</p>
            <p class="font-manrope font-extrabold text-4xl text-white relative z-10 drop-shadow-md tracking-tighter"><?= $fee_fmt ?></p>
        </div>
    </div>

    <a href="gate_simulator.php"
       class="flex items-center justify-center gap-2 w-full bg-white hover:bg-slate-50 text-slate-900 font-extrabold font-inter text-[11px] uppercase tracking-[0.15em] rounded-2xl py-4.5 transition-all shadow-sm ring-1 ring-slate-900/5 hover:shadow-lg active:scale-[0.98]">
        <i class="fa-solid fa-arrow-left text-base text-slate-900/40"></i>
        Return to Simulator
    </a>

    <p class="text-center text-slate-900/40 text-[10px] font-extrabold font-inter mt-6 uppercase tracking-[0.3em]">
        Redirecting in <span id="cnt" class="text-slate-900">8</span>s
    </p>
</div>

<script>
let s = 8;
const c = document.getElementById('cnt');
setInterval(() => { s--; if(c) c.textContent = s; if (s <= 0) window.location.href = 'gate_simulator.php'; }, 1000);
</script>
</body>
</html>
