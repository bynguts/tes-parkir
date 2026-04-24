<?php
require_once 'config/connection.php';

try {
    $pdo->exec("ALTER TABLE `transaction` ADD COLUMN is_lost_ticket TINYINT(1) DEFAULT 0");
    echo "Column is_lost_ticket added.\n";
} catch (Exception $e) {
    echo "is_lost_ticket: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE `transaction` ADD COLUMN is_force_checkout TINYINT(1) DEFAULT 0");
    echo "Column is_force_checkout added.\n";
} catch (Exception $e) {
    echo "is_force_checkout: " . $e->getMessage() . "\n";
}
