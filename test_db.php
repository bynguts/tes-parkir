<?php
require 'backend/config/connection.php';
try {
    $res = $pdo->query('DESCRIBE reservasi')->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);
} catch (Exception $e) {
    echo $e->getMessage();
}
