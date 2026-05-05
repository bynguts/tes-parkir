<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Get unique dates from plate_scan_log and reservation tables
try {
    $query = "
        SELECT DISTINCT DATE(scan_time) as log_date FROM plate_scan_log
        UNION
        SELECT DISTINCT DATE(reserved_from) as log_date FROM reservation
        ORDER BY log_date DESC
    ";

    $stmt = $pdo->query($query);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'dates' => $dates
    ]);
} catch (Exception $e) {
    error_log("get_log_dates error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dates',
        'dates' => []
    ]);
}
?>