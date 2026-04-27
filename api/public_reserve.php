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
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'car';
    $from_iso     = $_POST['from'] ?? '';
    $until_iso    = $_POST['until'] ?? '';

    if (empty($plate_number) || empty($visitor_name) || empty($from_iso) || empty($until_iso)) {
        throw new Exception("Missing required fields");
    }

    // Convert ISO dates to MySQL format
    $reserved_from  = date('Y-m-d H:i:s', strtotime($from_iso));
    $reserved_until = date('Y-m-d H:i:s', strtotime($until_iso));

    // 0. Check if the plate already has an active reservation
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservation r
        JOIN vehicle v ON r.vehicle_id = v.vehicle_id
        WHERE v.plate_number = ? 
          AND r.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$plate_number]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("This license plate already has an active reservation.");
    }

    // 1. Find a slot that doesn't have an overlapping confirmed reservation
    // Logic: Find slots of $vehicle_type where slot_id NOT IN (overlapping reservations)
    $stmt = $pdo->prepare("
        SELECT ps.slot_id 
        FROM parking_slot ps
        WHERE ps.slot_type = ? 
          AND ps.status != 'maintenance'
          AND ps.slot_id NOT IN (
              SELECT slot_id 
              FROM reservation 
              WHERE status IN ('pending', 'confirmed')
                AND (
                    (reserved_from < ?) AND (reserved_until > ?)
                )
          )
        ORDER BY ps.is_reservation_only DESC, ps.slot_id ASC
        LIMIT 1
    ");
    $stmt->execute([$vehicle_type, $reserved_until, $reserved_from]);
    $slot = $stmt->fetch();

    if (!$slot) {
        throw new Exception("No slots available for the selected timeframe.");
    }

    $slot_id = $slot['slot_id'];

    // 2. Ensure vehicle exists or create it
    $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
    $stmt->execute([$plate_number]);
    $v = $stmt->fetch();
    
    if ($v) {
        $vehicle_id = $v['vehicle_id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name) VALUES (?, ?, 'Public Guest')");
        $stmt->execute([$plate_number, $vehicle_type]);
        $vehicle_id = $pdo->lastInsertId();
    }

    // 3. Generate reservation code
    $reservation_code = generate_reservation_code($pdo);

    // 4. Insert reservation
    $stmt = $pdo->prepare("
        INSERT INTO reservation (
            vehicle_id, slot_id, reservation_code, 
            reserved_from, reserved_until, status, is_public, visitor_name
        ) VALUES (?, ?, ?, ?, ?, 'confirmed', 1, ?)
    ");
    
    $stmt->execute([
        $vehicle_id,
        $slot_id,
        $reservation_code,
        $reserved_from,
        $reserved_until,
        $visitor_name
    ]);

    // 5. Update slot status if the reservation starts within the next 15 minutes
    sync_slot_statuses($pdo);

    echo json_encode([
        'success' => true, 
        'reservation_code' => $reservation_code,
        'slot_id' => $slot_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
