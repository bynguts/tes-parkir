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
$minutes_parked = (int)$trx['minutes_parked'];
$duration_label = intdiv($minutes_parked, 60) . 'j ' . ($minutes_parked % 60) . 'm';
$display_code = preg_replace('/[^A-Za-z0-9-]/', '', $code);
$slot_label   = str_replace(['-G', '-L'], '-', $trx['slot_number']);
$now_fmt      = date('d M y H:i:s');
$theme = $_COOKIE['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Checkout — SmartParking</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .font-code { font-family: 'Courier Prime', monospace !important; letter-spacing: 0.05em; }
        i.fa-solid { vertical-align: middle; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s cubic-bezier(.16,1,.3,1) forwards; }
        .receipt-shell {
            max-height: calc(100vh - 1.5rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.75rem;
        }
        .receipt-primary-btn {
            background-color: var(--brand) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 15px var(--shadow-color) !important;
        }
        .receipt-primary-btn:hover {
            background-color: var(--hover-border) !important;
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-page flex items-center justify-center px-4 py-3 font-inter antialiased">

<div class="receipt-shell w-full max-w-sm fade-up">

    <!-- Status Header -->
    <div class="flex flex-col items-center gap-3">
        <div class="w-12 h-12 rounded-2xl icon-container flex items-center justify-center">
            <i class="fa-solid fa-check text-lg text-brand"></i>
        </div>
        <h1 class="text-[38px] font-manrope font-bold text-primary tracking-tight leading-none">Checkout Success</h1>
        <div class="inline-flex items-center gap-2 px-3 py-1 status-badge-available text-[10px] font-bold uppercase tracking-widest rounded-full">
            <span class="w-1.5 h-1.5 rounded-full status-dot-available animate-pulse"></span>
            Gate Clearance Issued
        </div>
    </div>

    <!-- Main Receipt Card -->
    <div class="bento-card p-5">
        <!-- Card Header -->
        <div class="flex items-center gap-4 mb-4">
            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                <i class="fa-solid fa-receipt text-lg"></i>
            </div>
            <div>
                <h3 class="card-title leading-tight">Payment Summary</h3>
                <p class="text-[10px] font-inter text-tertiary uppercase tracking-wider"><?= $now_fmt ?></p>
            </div>
        </div>

        <!-- Vehicle Info (Dashboard List Style) -->
        <div class="bg-surface-alt rounded-2xl p-3.5 flex flex-col items-center justify-center mb-4 border border-color">
            <div class="w-11 h-11 rounded-xl icon-container flex items-center justify-center mb-2">
                <i class="fa-solid <?= $trx['vehicle_type'] === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg"></i>
            </div>
            <span class="text-lg font-manrope font-bold text-primary leading-none mb-1"><?= $plate ?></span>
            <span class="text-[11px] font-inter text-tertiary font-medium uppercase tracking-[0.2em]"><?= htmlspecialchars($display_code) ?></span>
        </div>

        <!-- Transaction Details -->
        <div class="space-y-3 mb-5">
            <?php
            $rows = [
                ['In',       date('H:i', strtotime($trx['check_in_time']))],
                ['Out',      date('H:i')],
                ['Duration', $duration_label],
                ['Slot',     $slot_label],
            ];
            foreach ($rows as [$label, $value]):
            ?>
            <div class="flex justify-between items-center">
                <span class="text-tertiary text-[11px] font-bold uppercase tracking-wider"><?= $label ?></span>
                <span class="text-primary text-sm font-semibold"><?= $value ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Total Fee (Integrated Style) -->
        <div class="pt-4 border-t border-color">
            <div class="flex justify-between items-end">
                <div>
                    <p class="text-tertiary text-[11px] font-bold uppercase tracking-wider mb-1">Grand Total</p>
                    <p class="text-[44px] font-manrope font-extrabold text-primary tracking-tight leading-none"><?= $fee_fmt ?></p>
                </div>
                <div class="mb-1">
                    <span class="px-3 py-1 bg-brand text-white text-[10px] font-bold uppercase tracking-widest rounded-lg">PAID</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <a href="gate_simulator.php"
         class="receipt-primary-btn flex items-center justify-center gap-2 w-full font-bold text-[11px] uppercase tracking-widest rounded-xl py-3 transition-all">
        <i class="fa-solid fa-arrow-left"></i>
        Return to Simulator
    </a>

    <p class="text-center text-tertiary text-[10px] font-bold mt-0 uppercase tracking-[0.2em]">
        Auto-redirect in <span id="cnt" class="text-primary">8</span>s
    </p>
</div>

<script>
(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light' || savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
})();

let s = 8;
const c = document.getElementById('cnt');
setInterval(() => { s--; if(c) c.textContent = s; if (s <= 0) window.location.href = 'gate_simulator.php'; }, 1000);
</script>
</body>
</html>
