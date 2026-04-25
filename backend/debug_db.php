<?php
require_once 'config/connection.php';
$stmt = $pdo->query("SELECT * FROM plate_scan_log ORDER BY scan_time DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Recent Reservations ---\n";
$stmt = $pdo->query("SELECT * FROM reservasi ORDER BY created_at DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Recent Transactions ---\n";
$stmt = $pdo->query("SELECT * FROM `transaction` ORDER BY check_in_time DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
