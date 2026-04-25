<?php
/**
 * api/get_ticket_by_plate.php
 * Finds an active ticket/reservation code for a given plate number.
 * Used for ALPR-based exit for VIPs.
 */
header('Content-Type: application/json');
require_once '../config/connection.php';

$plate = strtoupper(trim($_POST['plate_number'] ?? ''));

if (!$plate) {
    echo json_encode(['error' => 'Plate number is required.']);
    exit;
}

try {
    $normalizedPlate = str_replace(' ', '', $plate);
    
    // Golden Regex for Indonesian Plates: [Prefix] [1-4 Digits] [Suffix]
    // Extracts the core and ignores noise like expiry dates (06 25)
    $corePlate = $normalizedPlate;
    if (preg_match('/([A-Z]{1,2})?(\d{1,4})([A-Z]{1,3})?/', $normalizedPlate, $matches)) {
        // Construct core: Prefix (if exists) + Digits + Suffix (if exists)
        $corePlate = ($matches[1] ?? '') . ($matches[2] ?? '') . ($matches[3] ?? '');
    }

    // Find active ticket linked to this plate
    // This works for both normal tickets and VIP (reservation) entries
    $stmt = $pdo->prepare("
        SELECT tk.ticket_code, v.plate_number as actual_plate
        FROM ticket tk
        JOIN `transaction` t ON tk.transaction_id = t.transaction_id
        JOIN vehicle v       ON t.vehicle_id       = v.vehicle_id
        WHERE (REPLACE(v.plate_number, ' ', '') = ? 
            OR REPLACE(v.plate_number, ' ', '') = ?
            OR REPLACE(v.plate_number, ' ', '') LIKE ?)
          AND tk.status = 'active'
        ORDER BY t.check_in_time DESC
        LIMIT 1
    ");
    $stmt->execute([$normalizedPlate, $corePlate, '%' . $corePlate . '%']);
    $ticket = $stmt->fetch();

    if ($ticket) {
        echo json_encode([
            'success'     => true,
            'ticket_code' => $ticket['ticket_code'],
            'plate'       => $ticket['actual_plate']
        ]);
    } else {
        echo json_encode(['error' => 'No active vehicle found for plate: ' . $plate]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
