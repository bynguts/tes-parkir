<?php
require_once 'config/connection.php';
try {
    $stmt = $pdo->query("DESCRIBE reservation");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in reservation: " . implode(", ", $columns) . "\n";
    
    $stmt = $pdo->query("DESCRIBE vehicle");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in vehicle: " . implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
