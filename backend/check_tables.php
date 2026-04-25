<?php
require 'config/connection.php';
function desc($pdo, $table) {
    echo "\n--- $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']} {$row['Extra']}\n";
        }
    } catch (Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
}
desc($pdo, 'reservasi');
desc($pdo, 'reservation');
desc($pdo, 'reservations');
