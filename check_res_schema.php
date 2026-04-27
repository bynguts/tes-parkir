<?php
require_once 'config/connection.php';
$stmt = $pdo->query("DESCRIBE reservation");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
