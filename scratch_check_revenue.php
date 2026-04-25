<?php
require_once 'config/connection.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'");
    echo "UNPAID TRANSACTIONS: " . $stmt->fetchColumn() . "\n";
    
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time) = '2026-04-24'");
    echo "PAID TRANSACTIONS TODAY: " . $stmt2->fetchColumn() . "\n";

    // Let's check the scan logs for today
    $stmt3 = $pdo->query("SELECT COUNT(*) FROM plate_scan_log WHERE DATE(scan_time) = '2026-04-24'");
    echo "SCAN LOGS TODAY: " . $stmt3->fetchColumn() . "\n";

} catch (Exception $e) {
    echo $e->getMessage();
}
