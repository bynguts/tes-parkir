<?php
require_once __DIR__ . '/../backend/config/connection.php';

$sql = "
CREATE TABLE IF NOT EXISTS reservasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plat_nomor VARCHAR(20) NOT NULL,
    jam_masuk_rencana DATETIME NOT NULL,
    jam_keluar_rencana DATETIME,
    status ENUM('BOOKED', 'IN_PARK', 'COMPLETED', 'CANCELLED') DEFAULT 'BOOKED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plat_nomor),
    INDEX (status)
) ENGINE=InnoDB;

-- Insert some dummy data for testing
INSERT INTO reservasi (plat_nomor, jam_masuk_rencana, status) 
VALUES ('B1234ABC', DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'BOOKED')
ON DUPLICATE KEY UPDATE plat_nomor=plat_nomor;
";

try {
    $pdo->exec($sql);
    echo "Table 'reservasi' created successfully (or already exists).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
