<?php
require_once '../includes/auth_guard.php'; // Ensure user is logged in
require_once '../config/connection.php';

header('Content-Type: application/json');

$range = isset($_GET['range']) ? $_GET['range'] : 'today';

try {
    if ($range === 'today') {
        // Hourly view for today - 2-hour intervals
        $stmt = $pdo->prepare("
            WITH RECURSIVE hours AS (
                SELECT 7 as h
                UNION ALL
                SELECT h + 3 FROM hours WHERE h < 22
            )
            SELECT 
                h.h as period,
                COALESCE(SUM(CASE WHEN vtype = 'car' THEN 1 ELSE 0 END), 0) as car_count,
                COALESCE(SUM(CASE WHEN vtype = 'motorcycle' THEN 1 ELSE 0 END), 0) as moto_count
            FROM hours h
            LEFT JOIN (
                SELECT t.check_in_time as t_time, v.vehicle_type as vtype 
                FROM `transaction` t 
                JOIN `vehicle` v ON t.vehicle_id = v.vehicle_id 
                WHERE DATE(t.check_in_time) = CURDATE()
                UNION ALL
                SELECT r.reserved_from as t_time, ps.slot_type as vtype 
                FROM `reservation` r 
                JOIN `parking_slot` ps ON r.slot_id = ps.slot_id 
                WHERE DATE(r.reserved_from) = CURDATE() 
                  AND r.status = 'confirmed' 
                  AND ps.status = 'available'
            ) combined ON HOUR(combined.t_time) >= h.h AND HOUR(combined.t_time) < (h.h + 3)
            GROUP BY h.h
            ORDER BY h.h ASC
        ");
        $stmt->execute();
    } else {
        // Daily view (7, 30, 90 days)
        $days = (int)$range;
        if (!in_array($days, [7, 30, 90])) $days = 7;
        
        $stmt = $pdo->prepare("
            WITH RECURSIVE dates AS (
                SELECT DATE_SUB(CURDATE(), INTERVAL :d1 DAY) as date_val
                UNION ALL
                SELECT DATE_ADD(date_val, INTERVAL 1 DAY)
                FROM dates
                WHERE date_val < CURDATE()
            )
            SELECT 
                d.date_val as period,
                COALESCE(SUM(CASE WHEN v.vehicle_type = 'car' THEN 1 ELSE 0 END), 0) as car_count,
                COALESCE(SUM(CASE WHEN v.vehicle_type = 'motorcycle' THEN 1 ELSE 0 END), 0) as moto_count
            FROM dates d
            LEFT JOIN `transaction` t ON DATE(t.check_in_time) = d.date_val
            LEFT JOIN `vehicle` v ON t.vehicle_id = v.vehicle_id
            GROUP BY d.date_val
            ORDER BY d.date_val ASC
        ");
        $stmt->execute(['d1' => $days - 1]);
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $carData = [];
    $motoData = [];

    foreach ($results as $row) {
        if ($range === 'today') {
            // Label like 00:00 - 02:00? Or just 00:00? 
            // The screenshot shows single time strings. I'll use 00:00, 02:00, etc.
            $labels[] = sprintf("%02d:00", $row['period']);
        } else {
            $labels[] = date('d M', strtotime($row['period']));
        }
        $carData[] = (int)$row['car_count'];
        $motoData[] = (int)$row['moto_count'];
    }

    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Cars',
                'data' => $carData,
                'backgroundColor' => '#3b82f6', // blue-500
                'borderRadius' => 4,
            ],
            [
                'label' => 'Motorcycles',
                'data' => $motoData,
                'backgroundColor' => '#10b981', // emerald-500
                'borderRadius' => 4,
            ]
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
