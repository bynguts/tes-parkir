<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=parking_db_v2', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "--- VEHICLE TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE vehicle");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    echo "\n--- PLATE_SCAN_LOG TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE plate_scan_log");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

    echo "\n--- TRANSACTION TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE `transaction` ");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
