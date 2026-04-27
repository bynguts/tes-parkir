<?php
require_once 'config/connection.php';
$res = $pdo->query("DESCRIBE `transaction`")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
