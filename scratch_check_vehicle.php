<?php
require_once 'config/connection.php';
$stmt = $pdo->query("DESCRIBE vehicle");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
