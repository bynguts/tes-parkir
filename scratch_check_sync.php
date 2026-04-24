<?php
require_once 'config/connection.php';

echo "Transactions without Entry Scan Log:\n";
$stmt = $pdo->query("
    SELECT t.transaction_id, t.ticket_code, t.check_in_time, v.plate_number
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id = v.vehicle_id
    WHERE NOT EXISTS (
        SELECT 1 FROM plate_scan_log psl 
        WHERE psl.ticket_code = t.ticket_code 
        AND psl.scan_type = 'entry'
    )
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\nEntry Scan Logs without Transactions:\n";
$stmt = $pdo->query("
    SELECT e.scan_id, e.ticket_code, e.scan_time, e.plate_number
    FROM plate_scan_log e
    WHERE e.scan_type = 'entry'
    AND NOT EXISTS (
        SELECT 1 FROM `transaction` t 
        WHERE t.ticket_code = e.ticket_code
    )
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
