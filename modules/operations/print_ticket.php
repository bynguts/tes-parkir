<?php
require_once '../../config/connection.php';
require_once '../../includes/functions.php';

// ── MODE 1: ?auto=1 — AJAX JSON response ──────────────────────────────────
if (isset($_GET['auto'])) {
    header('Content-Type: application/json');

    // Pick available slot (based on vtype parameter if provided)
    $requested_type = $_GET['vtype'] ?? null;
    $types_to_try   = ($requested_type && in_array($requested_type, ['car', 'motorcycle'])) 
                    ? [$requested_type] 
                    : ['car', 'motorcycle'];

    $slot  = null;
    $vtype = null;
    foreach ($types_to_try as $try_type) {
        // [3NF FIX] JOIN floor untuk mendapatkan floor_code
        $stmt = $pdo->prepare("
            SELECT ps.slot_id, ps.slot_number, f.floor_code AS floor, ps.slot_type
            FROM parking_slot ps
            JOIN floor f ON ps.floor_id = f.floor_id
            WHERE ps.slot_type = ? AND ps.status = 'available'
            ORDER BY f.floor_code, ps.slot_number LIMIT 1
        ");
        $stmt->execute([$try_type]);
        $row = $stmt->fetch();
        if ($row) { $slot = $row; $vtype = $try_type; break; }
    }

    if (!$slot) {
        echo json_encode(['error' => 'All slots are full!']); exit;
    }

    $plate   = null;
    $code    = generate_ticket_code($pdo);
    $slot_id = (int)$slot['slot_id'];

    // Rate lookup
    $rate = $pdo->prepare("SELECT rate_id FROM parking_rate WHERE vehicle_type = ?");
    $rate->execute([$vtype]);
    $rate_id = (int)$rate->fetchColumn();

    // Insert vehicle
    $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name) VALUES (?,?,?)")
        ->execute([$plate, $vtype, 'Guest']);
    $vid = (int)$pdo->lastInsertId();

    // Insert transaction
    $pdo->prepare("INSERT INTO `transaction` (vehicle_id, slot_id, operator_id, rate_id, ticket_code, payment_status)
                   VALUES (?,?,1,?,?,'unpaid')")
        ->execute([$vid, $slot_id, $rate_id, $code]);
    $trx_id = (int)$pdo->lastInsertId();

    // [3NF FIX] INSERT ticket tanpa plate_number (kolom dihapus dari schema)
    $pdo->prepare("INSERT INTO ticket (ticket_code, transaction_id) VALUES (?,?)")
        ->execute([$code, $trx_id]);

    // Mark slot occupied
    $pdo->prepare("UPDATE parking_slot SET status = 'occupied' WHERE slot_id = ?")
        ->execute([$slot_id]);

    // Log entry scan
    $pdo->prepare("INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action)
                   VALUES (?,?,?,0,'open')")
        ->execute([$plate, 'entry', $code]);

    echo json_encode([
        'ticket_code' => $code,
        'plate'       => $plate,
        'slot'        => str_replace(['-G', '-L'], '-', $slot['slot_number']),
        'vtype'       => $vtype,
    ]);
    exit;
}

// ── MODE 2: ?ticket_code=XXX — render print page ──────────────────────────
if (isset($_GET['ticket_code'])) {
    $code = trim($_GET['ticket_code']);

    // [3NF FIX] plate_number diambil dari vehicle (bukan dari ticket),
    //           floor diambil dari tabel floor via floor_id FK
    $stmt = $pdo->prepare("
        SELECT v.plate_number, tk.issued_at,
               v.vehicle_type,
               s.slot_number, f.floor_code AS floor
        FROM ticket tk
        JOIN `transaction` t ON tk.transaction_id = t.transaction_id
        JOIN vehicle v       ON t.vehicle_id       = v.vehicle_id
        JOIN parking_slot s  ON t.slot_id          = s.slot_id
        JOIN floor f         ON s.floor_id         = f.floor_id
        WHERE tk.ticket_code = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $d = $stmt->fetch();

    if (!$d) { echo "<script>alert('Ticket not found.');window.close();</script>"; exit; }

    $plate_raw   = $d['plate_number'];
    $vtype_label = $d['vehicle_type'] === 'car' ? 'CAR' : 'MOTORCYCLE';
    $barcode_url = 'https://quickchart.io/qr?text=' . urlencode($code) . '&size=140&margin=0&ecLevel=M';
    $checkin_fmt = date('d M Y - H.i.s', strtotime($d['issued_at']));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Location: print_ticket.php?auto=1"); exit;
} else {
    header("Location: gate_simulator.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parking Ticket — <?= htmlspecialchars($code) ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Courier Prime', 'Courier New', monospace;background:#f0f0f0;display:flex;justify-content:center;padding:20px}
        .ticket{background:#fff;width:320px;padding:25px 20px;text-align:center;box-shadow:0 0 10px rgba(0,0,0,.1)}
        .logo-wrap{display:flex;justify-content:center;margin-bottom:12px}
        .logo-oval{width:150px;height:75px;background:#0f172a;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;padding:10px;position:relative;overflow:hidden;border:2px solid #0f172a}
        .logo-oval::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;background-image:radial-gradient(rgba(255,255,255,0.1) 1px,transparent 1px);background-size:3px 3px;opacity:.5}
        .logo-text{font-size:16px;font-weight:900;letter-spacing:1px;text-transform:uppercase;z-index:1;line-height:1}
        .welcome{font-size:14px;margin-bottom:5px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;margin-top:10px}
        .ticket-id{font-size:18px;font-weight:900;margin:10px 0;letter-spacing:2px;background:#f8fafc;padding:8px 15px;display:inline-block;border:2px solid #0f172a;border-radius:8px}
        .barcode-container{margin:15px 0}.barcode-container img{max-width:160px;height:auto;border: 1px solid #f1f5f9; padding: 10px; border-radius: 12px;}
        .branch-name{font-size:14px;font-weight:900;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px}
        .info-row{font-size:12px;margin:4px 0;font-weight:700}
        .disclaimer{font-size:10px;margin-top:20px;line-height:1.4;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:#0f172a/60}
        @media print{body{background:none;padding:0}.ticket{box-shadow:none;width:100%;margin:0;padding:10px}.no-print{display:none}}
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <div class="logo-wrap">
            <div class="logo-oval">
                <span class="logo-text">SMART</span>
                <span class="logo-text" style="font-size:12px;opacity:0.7">PARKING</span>
            </div>
        </div>
        <div class="welcome">Validated Ticket</div>
        <div class="ticket-id"><?= htmlspecialchars($code) ?></div>
        <div class="ticket-date" style="font-weight:800; font-size:11px; margin-bottom:5px;"><?= date('d F Y, H:i') ?></div>
        <div class="barcode-container"><img src="<?= $barcode_url ?>" alt="QR Code"></div>
        <div class="branch-name">Enterprise Parking Hub</div>
        <div class="info-row">ENTRY_SCAN : <?= $checkin_fmt ?></div>
        <div class="info-row">LOC_ALLOC : <?= htmlspecialchars(str_replace(['-G', '-L'], '-', $d['slot_number'])) ?></div>
        <p class="disclaimer">Secure vehicle lock engaged.<br>Retain ticket for automated exit.</p>
    </div>
</body>
</html>
