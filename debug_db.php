<?php
require_once 'config/connection.php';
try {
    echo "--- VEHICLE TYPES ---\n";
    $stmt = $pdo->query("SELECT vehicle_type, COUNT(*) as count FROM vehicle GROUP BY vehicle_type");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['vehicle_type']}: {$row['count']}\n";
    }

    echo "\n--- TRANSACTION DATES ---\n";
    $stmt = $pdo->query("SELECT DATE(check_in_time) as date, COUNT(*) as count FROM transaction GROUP BY DATE(check_in_time)");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['date']}: {$row['count']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
