<?php
require_once 'config/connection.php';

$stmt = $pdo->query("SELECT slot_type, status, COUNT(*) as cnt FROM parking_slot GROUP BY slot_type, status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Type: {$row['slot_type']} | Status: {$row['status']} | Count: {$row['cnt']}\n";
}
