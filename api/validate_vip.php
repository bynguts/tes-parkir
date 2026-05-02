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
    // Using REPLACE to ignore spaces in both scanned plate and database
    $normalizedPlate = str_replace(' ', '', $plate);
    
    // Golden Regex for Indonesian Plates: [Prefix] [1-4 Digits] [Suffix]
    // Extracts the core and ignores noise like expiry dates (06 25)
    $corePlate = $normalizedPlate;
    if (preg_match('/([A-Z]{1,2})?(\d{1,4})([A-Z]{1,3})?/', $normalizedPlate, $matches)) {
        // Construct core: Prefix (if exists) + Digits + Suffix (if exists)
        $corePlate = ($matches[1] ?? '') . ($matches[2] ?? '') . ($matches[3] ?? '');
    }
    
    $stmt = $pdo->prepare("
        SELECT r.reservation_id, r.vehicle_id, r.slot_id, r.reservation_code, 
               ps.slot_number, ps.slot_type
        FROM reservation r
        JOIN parking_slot ps ON r.slot_id = ps.slot_id
        WHERE (REPLACE(r.plate_number, ' ', '') = ? 
            OR REPLACE(r.plate_number, ' ', '') = ?
            OR REPLACE(r.plate_number, ' ', '') LIKE ?)
          AND r.status IN ('pending', 'confirmed')
          AND r.reserved_from <= (NOW() + INTERVAL 30 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$normalizedPlate, $corePlate, '%' . $corePlate . '%']);
    $res = $stmt->fetch();

    if (!$res) {
        // Fallback: Check if there's a reservation for this plate but maybe joined via vehicle table
        $stmt = $pdo->prepare("
            SELECT r.reservation_id, r.vehicle_id, r.slot_id, r.reservation_code, 
                   ps.slot_number, ps.slot_type
            FROM reservation r
            JOIN vehicle v ON r.vehicle_id = v.vehicle_id
            JOIN parking_slot ps ON r.slot_id = ps.slot_id
            WHERE (REPLACE(v.plate_number, ' ', '') = ? 
                OR REPLACE(v.plate_number, ' ', '') = ?
                OR REPLACE(v.plate_number, ' ', '') LIKE ?)
              AND r.status IN ('pending', 'confirmed')
              AND r.reserved_from <= (NOW() + INTERVAL 30 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$normalizedPlate, $corePlate, '%' . $corePlate . '%']);
        $res = $stmt->fetch();
    }

    if (!$res) {
        echo json_encode(['error' => 'No active reservation found for plate: ' . $plate . '. Note: System allows entry up to 30 min before schedule.']);
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
