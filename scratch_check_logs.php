<?php
require_once 'config/connection.php';

echo "--- Distinct action_type in transaction_log ---\n";
$stmt = $pdo->query("SELECT DISTINCT action_type FROM transaction_log");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['action_type']}\n";
}

echo "\n--- Sample notes in transaction_log ---\n";
$stmt = $pdo->query("SELECT notes FROM transaction_log WHERE notes IS NOT NULL AND notes != '' LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['notes']}\n";
}

echo "\n--- Distinct payment_method in transaction ---\n";
$stmt = $pdo->query("SELECT DISTINCT payment_method FROM `transaction` ");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['payment_method']}\n";
}
