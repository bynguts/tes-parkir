<?php
require_once '../config/connection.php';

header('Content-Type: application/json');

$floor_id     = $_GET['floor_id'] ?? 1;
$vehicle_type = $_GET['vehicle_type'] ?? 'car';
$from         = $_GET['from'] ?? '';
$until        = $_GET['until'] ?? '';

if (empty($from) || empty($until)) {
    echo json_encode(['success' => false, 'error' => 'Dates are required']);
    exit;
}

try {
    $reserved_from  = date('Y-m-d H:i:s', strtotime($from));
    $reserved_until = date('Y-m-d H:i:s', strtotime($until));

    // Fetch reservation-only slots for this floor and type
    $stmt = $pdo->prepare("
        SELECT ps.slot_id, ps.slot_number, ps.pos_x, ps.pos_y, ps.is_reservation_only
        FROM parking_slot ps
        WHERE ps.floor_id = ? AND ps.slot_type = ? AND ps.status != 'maintenance'
        AND ps.is_reservation_only = 1
        ORDER BY ps.pos_y, ps.pos_x
    ");
    $stmt->execute([$floor_id, $vehicle_type]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch overlapping reservations
    $stmt = $pdo->prepare("
        SELECT slot_id 
        FROM reservation 
        WHERE status IN ('pending', 'confirmed')
          AND (
              (reserved_from < ?) AND (reserved_until > ?)
          )
    ");
    $stmt->execute([$reserved_until, $reserved_from]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Map availability
    foreach ($slots as &$slot) {
        $slot['is_available'] = !in_array($slot['slot_id'], $booked_slots);
    }

    echo json_encode(['success' => true, 'slots' => $slots]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
