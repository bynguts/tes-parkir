<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

header('Content-Type: application/json');

$rows = $pdo->query("
    SELECT
        DATE(e.scan_time)                                       AS date,
        DAYNAME(DATE(e.scan_time))                              AS day,
        COUNT(DISTINCT e.scan_id)                               AS scan_count,
        SUM(x.scan_id IS NOT NULL)                              AS exited,
        SUM(x.scan_id IS NULL)                                  AS still_parked
    FROM plate_scan_log e
    LEFT JOIN plate_scan_log x
        ON x.ticket_code = e.ticket_code AND x.scan_type = 'exit'
    WHERE e.scan_type = 'entry' AND e.gate_action = 'open'
    GROUP BY DATE(e.scan_time)
    ORDER BY DATE(e.scan_time) DESC
")->fetchAll();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'date'         => $r['date'],
        'day'          => $r['day'],
        'scan_count'   => (int)$r['scan_count'],
        'exited'       => (int)$r['exited'],
        'still_parked' => (int)$r['still_parked'],
    ];
}

echo json_encode($out);
