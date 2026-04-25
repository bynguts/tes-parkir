<?php
require_once 'config/connection.php';
try {
    $pdo->exec("ALTER TABLE reservasi ADD COLUMN transaction_id INT NULL AFTER user_id");
    echo "Column transaction_id added to reservasi table successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
