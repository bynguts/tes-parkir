<?php
require 'config/connection.php';

$cols_scan = $pdo->query('SHOW COLUMNS FROM plate_scan_log')->fetchAll(PDO::FETCH_COLUMN);
$cols_res  = $pdo->query('SHOW COLUMNS FROM `reservation`')->fetchAll(PDO::FETCH_COLUMN);

echo "<h3>plate_scan_log columns:</h3><pre>" . implode(', ', $cols_scan) . "</pre>";
echo "<h3>reservation columns:</h3><pre>" . implode(', ', $cols_res) . "</pre>";

// Run migration if columns missing
$needs_scan = !in_array('is_void', $cols_scan);
$needs_res  = !in_array('is_void', $cols_res);

if ($needs_scan) {
    $pdo->exec("ALTER TABLE plate_scan_log ADD COLUMN is_void BOOLEAN DEFAULT 0, ADD COLUMN void_reason VARCHAR(255) NULL, ADD COLUMN void_by INT NULL, ADD COLUMN void_at TIMESTAMP NULL");
    echo "<p style='color:green'>✅ plate_scan_log: VOID columns added!</p>";
} else {
    echo "<p style='color:blue'>✅ plate_scan_log: VOID columns already exist.</p>";
}

if ($needs_res) {
    $pdo->exec("ALTER TABLE `reservation` ADD COLUMN is_void BOOLEAN DEFAULT 0, ADD COLUMN void_reason VARCHAR(255) NULL, ADD COLUMN void_by INT NULL, ADD COLUMN void_at TIMESTAMP NULL");
    echo "<p style='color:green'>✅ reservation: VOID columns added!</p>";
} else {
    echo "<p style='color:blue'>✅ reservation: VOID columns already exist.</p>";
}
