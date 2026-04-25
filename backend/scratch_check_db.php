<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=parking_db_v2', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $ops = $pdo->query('SELECT COUNT(*) FROM operator')->fetchColumn();
    $rates = $pdo->query('SELECT COUNT(*) FROM parking_rate')->fetchColumn();
    $slots = $pdo->query('SELECT COUNT(*) FROM parking_slot')->fetchColumn();
    
    echo "Operators: $ops\n";
    echo "Rates: $rates\n";
    echo "Slots: $slots\n";
    
    if ($ops == 0) {
        echo "WARNING: No operators found! print_ticket.php will fail because it expects operator_id=1\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
