<?php
/**
 * api/public_reserve.php — Handle public reservation requests
 */
require_once '../config/connection.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $plate_number = strtoupper(trim($_POST['plate_number'] ?? ''));
    $client_name  = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'car';
    $from_iso     = $_POST['from'] ?? '';
    $until_iso    = $_POST['until'] ?? '';

    if (empty($plate_number) || empty($client_name) || empty($client_phone) || empty($from_iso)) {
        throw new Exception("Missing required fields");
    }

    // Convert ISO dates to MySQL format
    $reserved_from   = date('Y-m-d H:i:s', strtotime($from_iso));
    $reserved_until  = !empty($until_iso) ? date('Y-m-d H:i:s', strtotime($until_iso)) : null;

    // 0. Check if the plate already has an active reservation
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservation 
        WHERE plate_number = ? 
          AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$plate_number]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("This license plate already has an active reservation.");
    }

    // 1. Find a slot that doesn't have an overlapping confirmed reservation
    // Logic: Find slots of $vehicle_type where slot_id NOT IN (overlapping reservations)
    $stmt = $pdo->prepare("
        SELECT ps.slot_id, ps.slot_number, f.floor_code
        FROM parking_slot ps
        JOIN floor f ON ps.floor_id = f.floor_id
        WHERE ps.slot_type = ? 
          AND ps.status != 'maintenance'
          AND ps.slot_id NOT IN (
              SELECT slot_id 
              FROM reservation 
              WHERE status IN ('pending', 'confirmed')
                AND reserved_from = ?
          )
        ORDER BY ps.is_reservation_only DESC, ps.slot_id ASC
        LIMIT 1
    ");
    $stmt->execute([$vehicle_type, $reserved_from]);
    $slot = $stmt->fetch();

    if (!$slot) {
        throw new Exception("No slots available for the selected timeframe.");
    }

    $slot_id = $slot['slot_id'];
    $slot_number = $slot['slot_number'];
    $floor_code = $slot['floor_code'];

    // 2. Ensure vehicle exists or create it
    $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
    $stmt->execute([$plate_number]);
    $v = $stmt->fetch();
    
    if ($v) {
        $vehicle_id = $v['vehicle_id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name) VALUES (?, ?, ?)");
        $stmt->execute([$plate_number, $vehicle_type, $client_name]);
        $vehicle_id = $pdo->lastInsertId();
    }

    // 3. Generate reservation code
    $reservation_code = generate_reservation_code($pdo);

    // 4. Insert reservation
    $stmt = $pdo->prepare("
        INSERT INTO reservation (
            vehicle_id, plate_number, client_name, client_phone, slot_id, reservation_code, 
            reserved_from, reserved_until, status, is_public
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 1)
    ");
    
    $stmt->execute([
        $vehicle_id,
        $plate_number,
        $client_name,
        $client_phone,
        $slot_id,
        $reservation_code,
        $reserved_from,
        $reserved_until
    ]);

    // 5. Update slot status if the reservation starts within the next 15 minutes
    sync_slot_statuses($pdo);

    echo json_encode([
        'success' => true, 
        'reservation_code' => $reservation_code,
        'slot_id' => $slot_id,
        'slot_number' => $slot_number,
        'floor_code' => $floor_code,
        'client_name' => $client_name,
        'plate_number' => $plate_number,
        'vehicle_type' => $vehicle_type,
        'from' => $reserved_from,
        'until' => $reserved_until
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
