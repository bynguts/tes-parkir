<?php
/**
 * api/validate_vip.php
 * Handles VIP Seamless Entry validation and transaction creation.
 */
header('Content-Type: application/json');
require_once '../config/connection.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$plate = strtoupper(trim($_POST['plate_number'] ?? ''));

if (!$plate) {
    echo json_encode(['error' => 'Plate number is required.']);
    exit;
}

try {
    // 1. Check for matching active reservation
    $normalizedPlate = str_replace(' ', '', $plate);
    
    // Golden Regex for Indonesian Plates
    $corePlate = $normalizedPlate;
    if (preg_match('/([A-Z]{1,2})?(\d{1,4})([A-Z]{1,3})?/', $normalizedPlate, $matches)) {
        $corePlate = ($matches[1] ?? '') . ($matches[2] ?? '') . ($matches[3] ?? '');
    }
    
    // First, check if reservation exists regardless of time to give better error messages
    $stmt = $pdo->prepare("
        SELECT r.reservation_id, r.reserved_from, r.status
        FROM reservation r
        JOIN vehicle v ON r.vehicle_id = v.vehicle_id
        WHERE (REPLACE(r.plate_number, ' ', '') = ? 
            OR REPLACE(r.plate_number, ' ', '') = ?
            OR REPLACE(v.plate_number, ' ', '') = ?
            OR REPLACE(v.plate_number, ' ', '') = ?)
          AND r.status IN ('pending', 'confirmed')
        ORDER BY r.reserved_from ASC
        LIMIT 1
    ");
    $stmt->execute([$normalizedPlate, $corePlate, $normalizedPlate, $corePlate]);
    $check = $stmt->fetch();

    if ($check) {
        $startTime = strtotime($check['reserved_from']);
        $now = time();
        $diffMin = ($startTime - $now) / 60;

        if ($diffMin > 15) {
            echo json_encode(['error' => 'Entry Denied. You arrived too early. Entry is only allowed starting 15 minutes before your schedule (' . date('H:i', $startTime) . ').']);
            exit;
        }
    }

    // Now proceed with the actual lookup for the full details
    $stmt = $pdo->prepare("
        SELECT r.reservation_id, r.vehicle_id, r.slot_id, r.reservation_code, 
               ps.slot_number, ps.slot_type
        FROM reservation r
        JOIN parking_slot ps ON r.slot_id = ps.slot_id
        LEFT JOIN vehicle v ON r.vehicle_id = v.vehicle_id
        WHERE (REPLACE(r.plate_number, ' ', '') = ? 
            OR REPLACE(r.plate_number, ' ', '') = ?
            OR REPLACE(v.plate_number, ' ', '') = ?
            OR REPLACE(v.plate_number, ' ', '') = ?)
          AND r.status IN ('pending', 'confirmed')
          AND r.reserved_from <= (NOW() + INTERVAL 15 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$normalizedPlate, $corePlate, $normalizedPlate, $corePlate]);
    $res = $stmt->fetch();

    if (!$res) {
        echo json_encode(['error' => 'No active reservation found for plate: ' . $plate]);
        exit;
    }

    $pdo->beginTransaction();

    // 2. Update Reservation Status
    $updateRes = $pdo->prepare("UPDATE reservation SET status = 'used' WHERE reservation_id = ?");
    $updateRes->execute([$res['reservation_id']]);

    // 3. Update Slot Status
    $updateSlot = $pdo->prepare("UPDATE parking_slot SET status = 'occupied' WHERE slot_id = ?");
    $updateSlot->execute([$res['slot_id']]);

    // 4. Rate lookup
    $rate = $pdo->prepare("SELECT rate_id FROM parking_rate WHERE vehicle_type = ?");
    $rate->execute([$res['slot_type']]);
    $rate_id = (int)$rate->fetchColumn();

    // 5. Create Transaction
    $insertTrx = $pdo->prepare("
        INSERT INTO `transaction` (vehicle_id, slot_id, operator_id, rate_id, reservation_id, ticket_code, payment_status)
        VALUES (?, ?, 1, ?, ?, ?, 'unpaid')
    ");
    $insertTrx->execute([
        $res['vehicle_id'], 
        $res['slot_id'], 
        $rate_id, 
        $res['reservation_id'], 
        $res['reservation_code'] // VIPs use reservation code as ticket code
    ]);
    
    $trx_id = $pdo->lastInsertId();

    // 6. Create Ticket Record (To satisfy Foreign Key in plate_scan_log)
    $insertTicket = $pdo->prepare("
        INSERT INTO ticket (ticket_code, transaction_id, status)
        VALUES (?, ?, 'active')
    ");
    $insertTicket->execute([$res['reservation_code'], $trx_id]);

    // 7. Log entry scan
    $insertLog = $pdo->prepare("
        INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action)
        VALUES (?, 'entry', ?, 1, 'open')
    ");
    $insertLog->execute([$plate, $res['reservation_code']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'VIP Access Granted',
        'details' => [
            'plate' => $plate,
            'slot'  => $res['slot_number'],
            'code'  => $res['reservation_code']
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}
