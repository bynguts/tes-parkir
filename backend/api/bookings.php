<?php
/**
 * api/bookings.php
 * Returns reservations for the current user.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // If not logged in, maybe return based on plate number from cookie? 
    // For now, just return empty if not logged in to encourage login.
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM reservasi WHERE user_id = ? ORDER BY jam_masuk_rencana DESC");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $bookings
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
