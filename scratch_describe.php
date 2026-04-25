<?php
require_once 'config/connection.php';

function desc($table) {
    global $pdo;
    echo "\n--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE `$table` ");
    while ($row = $stmt->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
}

desc('transaction');
desc('reservation');
desc('vehicle');
desc('parking_slot');
desc('operator');
desc('floor');
