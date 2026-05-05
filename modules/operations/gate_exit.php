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
    header("Location: gate_simulator.php?msg=" . urlencode("Invalid ticket or already processed.") . "&type=error&title=INVALID_TICKET&code=" . urlencode($code));
    exit;
}

$trx_id = (int)$tkt['transaction_id'];
$plate  = $tkt['plate_number'];

// ── 2. Get transaction ────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.*, v.vehicle_type, v.owner_name,
           s.slot_number, f.floor_code AS floor,
           r.first_hour_rate, r.next_hour_rate,
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
    header("Location: gate_simulator.php?msg=" . urlencode("Transaction not found or already paid.") . "&type=error&title=NOT_FOUND&code=" . urlencode($code));
    exit;
}

// ── 3. Calculate fee ──────────────────────────────────────────────────────
$result       = calculate_fee(
    (int)$trx['minutes_parked'],
    (float)$trx['first_hour_rate'],
    (float)$trx['next_hour_rate']
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

    // Update reservation status if this is a VIP entry (codes start with RSV-)
    $pdo->prepare("UPDATE reservation SET status='completed' WHERE reservation_code=?")
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
    header("Location: gate_simulator.php?msg=" . urlencode("System error occurred. Please contact administrator.") . "&type=error");
    exit;
}

// ── 5. Display receipt ────────────────────────────────────────────────────
$vtype_icon  = $trx['vehicle_type'] === 'car' ? 'directions_car' : 'two_wheeler';
$fee_fmt     = fmt_idr($total_fee);
$minutes_parked = (int)$trx['minutes_parked'];
$duration_label = intdiv($minutes_parked, 60) . 'j ' . ($minutes_parked % 60) . 'm';
$display_code = preg_replace('/[^A-Za-z0-9-]/', '', $code);
$slot_raw = trim((string)($trx['slot_number'] ?? ''));
if (preg_match('/^#+\s*(.+)$/', $slot_raw, $m)) {
    $slot_label = '#' . trim($m[1]);
} elseif (preg_match('/^\d+$/', $slot_raw)) {
    $slot_label = '#' . $slot_raw;
} else {
    $slot_label = $slot_raw !== '' ? $slot_raw : '-';
}
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
    <title>Vehicle Checkout — Parkhere</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script>
        // CRITICAL: Prevent theme flicker
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        })();
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: ['selector', '[data-theme="dark"]'],
        theme: {
            extend: {
                fontFamily: {
                    'manrope': ['Manrope', 'sans-serif'],
                    'inter': ['Inter', 'sans-serif'],
                },
                colors: {
                    'brand': 'var(--brand)',
                    'surface': 'var(--surface)',
                    'surface-alt': 'var(--surface-alt)',
                    'bg-page': 'var(--bg-page)',
                    'primary': 'var(--text-primary)',
                    'secondary': 'var(--text-secondary)',
                    'border-color': 'var(--border-color)',
                },
            }
        }
    }
    </script>
    <link rel="stylesheet" href="../../assets/css/theme.css">
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
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @keyframes progressShrink {
            from { width: 100%; }
            to { width: 0%; }
        }
        .animate-slide-in { animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-slide-out { animation: slideOut 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .notification-progress { animation: progressShrink 5s linear forwards; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-page flex items-center justify-center px-4 py-3 font-inter antialiased">

    <!-- Push Notification Container -->
    <div id="push-notification-container" class="fixed top-10 right-10 z-[999999] flex flex-col gap-3 w-[380px] pointer-events-none"></div>

<div class="receipt-shell w-full max-w-sm fade-up relative z-10">

    <!-- Status Header -->
    <div class="flex flex-col items-center gap-3">
        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center">
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
            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center mb-2">
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

// PUSH NOTIFICATION SYSTEM
function pushNotify(title, message, type = 'info', code = null) {
    const container = document.getElementById('push-notification-container');
    const id = 'notif-' + Date.now();
    
    let iconBg = 'bg-indigo-500/10';
    let iconColor = 'text-indigo-500';
    let icon = 'fa-info-circle';
    
    if (type === 'success') {
        iconBg = 'bg-emerald-500/10';
        iconColor = 'text-emerald-500';
        icon = 'fa-circle-check';
    } else if (type === 'error') {
        iconBg = 'bg-rose-500/10';
        iconColor = 'text-rose-500';
        icon = 'fa-circle-exclamation';
    } else if (type === 'ticket') {
        iconBg = 'bg-brand/10';
        iconColor = 'text-brand';
        icon = 'fa-ticket';
    }

    const html = `
        <div id="${id}" class="notification-item bento-card !p-0 flex flex-col animate-slide-in pointer-events-auto border border-color overflow-hidden w-[380px] bg-surface/80 backdrop-blur-xl" style="box-shadow: 0 20px 50px -10px rgba(0,0,0,0.5) !important;">
            <div class="flex items-center gap-4 p-4">
                <div class="w-12 h-12 rounded-2xl ${iconBg} flex items-center justify-center shrink-0">
                    <i class="fa-solid ${icon} text-xl ${iconColor}"></i>
                </div>
                <div class="flex flex-col min-w-0 flex-1">
                    <h4 class="text-[15px] font-manrope font-extrabold text-primary truncate tracking-tight">${title}</h4>
                    <p class="text-[12px] font-medium text-tertiary leading-snug">${message}</p>
                </div>
                <button onclick="this.closest('.notification-item').remove()" class="w-8 h-8 rounded-full hover:bg-rose-500/10 text-tertiary/30 hover:text-rose-500 transition-all flex items-center justify-center">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
            <div class="h-[3px] bg-brand/5 w-full overflow-hidden">
                <div class="h-full bg-brand notification-progress opacity-60"></div>
            </div>
        </div>
    `;
    
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const el = temp.firstElementChild;
    container.appendChild(el);
    
    setTimeout(() => {
        el.classList.add('animate-slide-out');
        setTimeout(() => el.remove(), 400);
    }, 5000);
}

// Trigger notification from URL if exists with slight delay for sync
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        const type = urlParams.get('type') || 'info';
        const title = urlParams.get('title') || 'Notification';
        const code = urlParams.get('code');
        
        if (msg) {
            pushNotify(title, msg, type, code);
        }
    }, 500);
});

let s = 8;
const c = document.getElementById('cnt');
setInterval(() => { 
    if (s > 0) s--; 
    if(c) c.textContent = s; 
    if (s <= 0) window.location.href = 'gate_simulator.php'; 
}, 1000);
</script>
</body>
</html>
