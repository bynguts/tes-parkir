<?php
require_once '../config/connection.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
$plate = $_POST['plate'] ?? '';
$is_lost = isset($_POST['is_lost']) && $_POST['is_lost'] === 'true';
$fine = $is_lost ? 50000 : 0;

try {
    $normalizedPlate = str_replace(' ', '', $plate);
    $stmt = $pdo->prepare("
        SELECT t.transaction_id, t.slot_id, t.check_in_time, t.payment_status, t.check_out_time,
               tk.ticket_code, v.plate_number,
               r.first_hour_rate, r.next_hour_rate, r.daily_max_rate,
               TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) as minutes_parked
        FROM `transaction` t
        JOIN parking_rate r ON t.rate_id = r.rate_id
        LEFT JOIN ticket tk ON t.transaction_id = tk.transaction_id
        LEFT JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        WHERE (tk.ticket_code = ? 
            OR REPLACE(v.plate_number, ' ', '') = ? 
            OR REPLACE(v.plate_number, ' ', '') = ?)
          AND t.check_out_time IS NULL
        LIMIT 1
    ");
    $stmt->execute([$ticket, $normalizedPlate, str_replace(' ', '', $ticket)]);
    $trx = $stmt->fetch();

    if (!$trx) {
        // Check if it's a reservation that hasn't checked in yet
        $stmt = $pdo->prepare("UPDATE reservation SET status = 'cancelled' WHERE (reservation_code = ? OR reservation_id = ?)");
        $stmt->execute([$ticket, $ticket]);
        echo json_encode(['success' => true, 'message' => 'Reservation removed']);
        exit;
    }

    $trx_id = $trx['transaction_id'];
    $slot_id = $trx['slot_id'];
    
    $pdo->beginTransaction();
    
    // 1. Update Transaction
    if ($trx['payment_status'] === 'unpaid') {
        $applied_rate = $trx['next_hour_rate']; // Ensure we have the snapshot
        $total_fee = ceil($trx['minutes_parked'] / 60) * $applied_rate;
        $final_amount = $total_fee + $fine;
        $is_force = !$is_lost; 
        
        $stmt = $pdo->prepare("UPDATE `transaction` SET check_out_time = NOW(), applied_rate = ?, total_fee = ?, payment_status = 'paid', is_lost_ticket = ?, is_force_checkout = ? WHERE transaction_id = ?");
        $stmt->execute([$applied_rate, $final_amount, $is_lost ? 1 : 0, $is_force ? 1 : 0, $trx_id]);
    } else {
        // Already paid, just ensure check_out_time is set and mark as force checkout
        $stmt = $pdo->prepare("UPDATE `transaction` SET check_out_time = IFNULL(check_out_time, NOW()), is_force_checkout = 1 WHERE transaction_id = ?");
        $stmt->execute([$trx_id]);
    }

    // 2. Update Slot
    $stmt = $pdo->prepare("UPDATE parking_slot SET status = 'available' WHERE slot_id = ?");
    $stmt->execute([$slot_id]);

    // 3. Update Ticket
    $stmt = $pdo->prepare("UPDATE ticket SET status = 'used' WHERE transaction_id = ?");
    $stmt->execute([$trx_id]);

    // 4. Add Exit Scan Log (Sync with Scan Log module)
    $stmt = $pdo->prepare("INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action) VALUES (?, 'exit', ?, 1, 'open')");
    $stmt->execute([$plate, $ticket]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
