<?php
require 'config/connection.php';

$ticket = 'TKT-260504-CE52';

// Hard delete from plate_scan_log only
$stmt = $pdo->prepare("DELETE FROM plate_scan_log WHERE ticket_code = ?");
$stmt->execute([$ticket]);
$deleted = $stmt->rowCount();

if ($deleted > 0) {
    echo "<p style='color:green;font-weight:bold'>✅ Deleted $deleted row(s) for ticket: $ticket</p>";
} else {
    echo "<p style='color:orange;font-weight:bold'>⚠️ No rows found for: $ticket</p>";
    // Try to see what's in the table
    $check = $pdo->prepare("SELECT scan_id, ticket_code, is_void FROM plate_scan_log WHERE ticket_code LIKE ?");
    $check->execute(['%CE52%']);
    $rows = $check->fetchAll();
    echo "<pre>" . print_r($rows, true) . "</pre>";
}
echo "<br><a href='modules/operations/scan_log.php'>← Back to Scan Log</a>";
