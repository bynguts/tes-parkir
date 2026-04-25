<?php
require_once '../config/connection.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Force Checkout Active Transactions (All that haven't exited)
    $stmt = $pdo->query("
        SELECT t.transaction_id, t.slot_id, t.check_in_time, t.payment_status, t.check_out_time,
               v.plate_number, t.ticket_code,
               r.first_hour_rate, r.next_hour_rate, r.daily_max_rate,
               TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) as minutes_parked
        FROM `transaction` t
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        JOIN parking_rate r ON t.rate_id = r.rate_id
        WHERE t.check_out_time IS NULL
    ");
    $active_trxs = $stmt->fetchAll();

    foreach ($active_trxs as $trx) {
        if ($trx['payment_status'] === 'unpaid') {
            $calc = calculate_fee($trx['minutes_parked'], $trx['first_hour_rate'], $trx['next_hour_rate'], $trx['daily_max_rate']);
            
            $upd = $pdo->prepare("UPDATE `transaction` SET check_out_time = NOW(), total_fee = ?, payment_status = 'paid', is_force_checkout = 1 WHERE transaction_id = ?");
            $upd->execute([$calc['total_fee'], $trx['transaction_id']]);
        } else {
            // Already paid, just force exit if check_out_time is missing
            $upd = $pdo->prepare("UPDATE `transaction` SET check_out_time = IFNULL(check_out_time, NOW()), is_force_checkout = 1 WHERE transaction_id = ?");
            $upd->execute([$trx['transaction_id']]);
        }

        $upd = $pdo->prepare("UPDATE parking_slot SET status = 'available' WHERE slot_id = ?");
        $upd->execute([$trx['slot_id']]);

        $upd = $pdo->prepare("UPDATE ticket SET status = 'used' WHERE transaction_id = ?");
        $upd->execute([$trx['transaction_id']]);

        $upd = $pdo->prepare("INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action) VALUES (?, 'exit', ?, 1, 'open')");
        $upd->execute([$trx['plate_number'], $trx['ticket_code']]);
    }

    // 2. Cancel Today's Confirmed Reservations
    $upd = $pdo->prepare("UPDATE `reservation` SET status = 'cancelled' WHERE status = 'confirmed' AND DATE(reserved_from) = CURDATE()");
    $upd->execute();
    $cancelled_res = $upd->rowCount();

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'checkout_count' => count($active_trxs), 
        'cancelled_count' => $cancelled_res,
        'message' => 'Successfully cleared ' . (count($active_trxs) + $cancelled_res) . ' entries.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
