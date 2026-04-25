<?php
require_once 'config/connection.php';
$stmt = $pdo->query('DESCRIBE `transaction`');
while($row = $stmt->fetch()) echo "{$row['Field']} - {$row['Type']}\n";
