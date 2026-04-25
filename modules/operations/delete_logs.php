<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

header('Content-Type: application/json');

// CSRF validation
csrf_verify();

$mode = $_POST['mode'] ?? '';
$date = $_POST['date'] ?? '';

if (!in_array($mode, ['by_date', 'all', 'single'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid mode.']); exit;
}

if ($mode === 'by_date') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format.']); exit;
    }
    $date_label = $date;
} else {
    $date_label = 'all';
}

$pdo->beginTransaction();
try {
    if ($mode === 'single') {
        $scan_id = $_POST['scan_id'] ?? '';
        $res_id = $_POST['reservation_id'] ?? '';
        
        if ($scan_id) {
            $stmt = $pdo->prepare("DELETE FROM plate_scan_log WHERE scan_id = ?");
            $stmt->execute([$scan_id]);
        } elseif ($res_id) {
            $stmt = $pdo->prepare("DELETE FROM reservation WHERE reservation_id = ?");
            $stmt->execute([$res_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Entry deleted successfully']);
        exit;
    }

    // 2. Delete plate_scan_log
    if ($mode === 'by_date') {
        $del_scan = $pdo->prepare("DELETE FROM plate_scan_log WHERE DATE(scan_time)=?");
        $del_scan->execute([$date]);
    } else {
        $del_scan = $pdo->query("DELETE FROM plate_scan_log");
    }
    $deleted_scans = $del_scan->rowCount();
    $deleted_trx = 0; // No longer deleting transactions here

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Successfully deleted logs for: {$date_label}",
        'deleted_scans' => $deleted_scans,
        'deleted_trx'   => $deleted_trx,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("delete_logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A system error occurred.']);
}
