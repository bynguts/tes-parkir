<?php
require_once 'config/connection.php';
$stmt = $pdo->query("DESCRIBE plate_scan_log");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
