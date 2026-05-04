<?php
require 'config/connection.php';
$slots = $pdo->query('SELECT slot_number FROM parking_slot')->fetchAll(PDO::FETCH_COLUMN);
print_r($slots);
