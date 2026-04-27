<?php
require_once 'config/connection.php';
try {
    echo "--- TODAY'S TRANSACTIONS BY HOUR ---\n";
    $stmt = $pdo->query("SELECT HOUR(check_in_time) as h, COUNT(*) as count FROM transaction WHERE DATE(check_in_time) = CURDATE() GROUP BY HOUR(check_in_time)");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Hour {$row['h']}: {$row['count']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
