<?php
/**
 * api/ocr_check.php
 * Handles plate number matching from OCR results.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Method check
// ... (rest of method check)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$raw_plate = $body['plat_nomor'] ?? '';

if (!$raw_plate) {
    echo json_encode(['success' => false, 'error' => 'License plate not found.']);
    exit;
}

// 1. Data Normalization (Remove spaces, uppercase)
$normalizedPlate = strtoupper(str_replace(' ', '', $raw_plate));

// Golden Regex for Indonesian Plates: [Prefix] [1-4 Digits] [Suffix]
// Extracts the core and ignores noise like expiry dates (06 25) from OCR
$corePlate = $normalizedPlate;
if (preg_match('/([A-Z]{1,2})?(\d{1,4})([A-Z]{1,3})?/', $normalizedPlate, $matches)) {
    // Construct core: Prefix (if exists) + Digits + Suffix (if exists)
    $corePlate = ($matches[1] ?? '') . ($matches[2] ?? '') . ($matches[3] ?? '');
}

try {
    // 2. Query Logic with Buffer Time
    $stmt = $pdo->prepare("
        SELECT * FROM reservasi 
        WHERE (REPLACE(plat_nomor, ' ', '') = ? 
               OR REPLACE(plat_nomor, ' ', '') = ?
               OR REPLACE(plat_nomor, ' ', '') LIKE ?)
        AND status = 'BOOKED'
        AND jam_masuk_rencana <= DATE_ADD(NOW(), INTERVAL 30 MINUTE)
        AND jam_masuk_rencana >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        ORDER BY jam_masuk_rencana ASC
        LIMIT 1
    ");
    $stmt->execute([$normalizedPlate, $corePlate, '%' . $corePlate . '%']);
    $reservation = $stmt->fetch();

    if ($reservation) {
        $pdo->beginTransaction();

        // 3. Find Available Slot for the vehicle type
        $vtype = $reservation['vehicle_type'];
        $stmt = $pdo->prepare("
            SELECT ps.slot_id, ps.slot_number 
            FROM parking_slot ps
            JOIN floor f ON ps.floor_id = f.floor_id
            WHERE ps.slot_type = ? AND ps.status = 'available'
            ORDER BY f.floor_code, ps.slot_number LIMIT 1
        ");
        $stmt->execute([$vtype]);
        $slot = $stmt->fetch();

        if (!$slot) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Maaf, semua slot penuh.']);
            exit;
        }

        $slot_id = (int)$slot['slot_id'];
        $ticket_code = generate_ticket_code($pdo);
        
        // 4. Handle Vehicle Record
        $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE REPLACE(plate_number, ' ', '') = ? LIMIT 1");
        $stmt->execute([$normalizedPlate]);
        $vid = $stmt->fetchColumn();

        if (!$vid) {
            $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name) VALUES (?,?,?)")
                ->execute([$reservation['plat_nomor'], $vtype, 'Member']);
            $vid = $pdo->lastInsertId();
        }

        // 5. Rate lookup
        $rate = $pdo->prepare("SELECT rate_id FROM parking_rate WHERE vehicle_type = ?");
        $rate->execute([$vtype]);
        $rate_id = (int)$rate->fetchColumn();

        // 6. Create Transaction
        // We use operator_id = 1 (System/Admin) for auto-entries
        $pdo->prepare("INSERT INTO `transaction` (vehicle_id, slot_id, operator_id, rate_id, ticket_code, payment_status)
                       VALUES (?,?,1,?,?,'unpaid')")
            ->execute([$vid, $slot_id, $rate_id, $ticket_code]);
        $trx_id = $pdo->lastInsertId();

        // 7. Create Ticket
        $pdo->prepare("INSERT INTO ticket (ticket_code, transaction_id) VALUES (?,?)")
            ->execute([$ticket_code, $trx_id]);

        // 8. Update Slot Status
        $pdo->prepare("UPDATE parking_slot SET status = 'occupied' WHERE slot_id = ?")
            ->execute([$slot_id]);

        // 9. Update Reservasi Status and link Transaction
        $pdo->prepare("UPDATE reservasi SET status = 'IN_PARK', transaction_id = ? WHERE id = ?")
            ->execute([$trx_id, $reservation['id']]);

        // 10. Log entry scan
        $pdo->prepare("INSERT INTO plate_scan_log (plate_number, scan_type, ticket_code, matched, gate_action)
                       VALUES (?,?,?,1,'open')")
            ->execute([$reservation['plat_nomor'], 'entry', $ticket_code]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'should_open_gate' => true,
            'message' => "Access Granted: Reservation found. Ticket: {$ticket_code}. Slot: {$slot['slot_number']}",
            'data' => [
                'id' => $reservation['id'],
                'ticket_code' => $ticket_code,
                'slot_number' => $slot['slot_number']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'should_open_gate' => false,
            'message' => "Access Denied: No active reservation or outside buffer time for {$normalizedPlate}."
        ]);
    }

} catch (PDOException $e) {
    error_log('OCR Check failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process data on server.']);
}
