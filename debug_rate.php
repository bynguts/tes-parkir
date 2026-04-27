<?php
require_once 'config/connection.php';
$res = $pdo->query("SELECT * FROM parking_rate LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
