<?php
require_once 'config/connection.php';
$res = $pdo->query("DESCRIBE `ticket`")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
