<?php
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$plat_nomor = strtoupper(preg_replace('/\s+/', '', $data['plate_number'] ?? ''));
$jam_masuk = $data['entry_datetime'] ?? null;
$jam_keluar = $data['exit_datetime'] ?? null;
$status = 'BOOKED';
$vehicle_type = $data['vehicle_type'] ?? 'car';

if (empty($plat_nomor) || empty($jam_masuk)) {
    echo json_encode(['success' => false, 'message' => 'Plate number and entry time are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO reservasi (user_id, plat_nomor, vehicle_type, jam_masuk_rencana, jam_keluar_rencana, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $plat_nomor, $vehicle_type, $jam_masuk, $jam_keluar, $status]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reservation successful!',
        'id' => $pdo->lastInsertId(),
        'data' => [
            'plate_number' => $plat_nomor,
            'vehicle_type' => $vehicle_type,
            'entry_datetime' => $jam_masuk,
            'exit_datetime' => $jam_keluar,
            'reservation_id' => $pdo->lastInsertId()
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
