<?php
require_once 'config/connection.php';
$stmt = $pdo->query("DESCRIBE `transaction` ");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
