<?php
require_once 'config/connection.php';
$q = $pdo->query("DESCRIBE `plate_scan_log`")->fetchAll(PDO::FETCH_ASSOC);
foreach($q as $row) {
    echo $row['Field'] . "\n";
}
?>
