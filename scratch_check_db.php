<?php
require_once 'config/connection.php';

function describe($pdo, $table) {
    echo "--- $table table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE `$table` ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

describe($pdo, 'ticket');
describe($pdo, 'plate_scan_log');
$stmt = $pdo->query("SELECT * FROM parking_rate");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
