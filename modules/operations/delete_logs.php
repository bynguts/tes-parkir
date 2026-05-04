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
    $admin_id = $_SESSION['user_id'] ?? null;
    $void_reason = $_POST['void_reason'] ?? 'System Purge';

    if ($mode === 'single') {
        $scan_id = $_POST['scan_id'] ?? '';
        $res_id = $_POST['reservation_id'] ?? '';
        $ticket = $_POST['ticket'] ?? '';
        
        if (!empty($scan_id) && $scan_id !== 'null') {
            $stmt = $pdo->prepare("UPDATE plate_scan_log SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE scan_id = ?");
            $stmt->execute([$void_reason, $admin_id, $scan_id]);
        } elseif (!empty($res_id) && $res_id !== 'null') {
            $stmt = $pdo->prepare("UPDATE reservation SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE reservation_id = ?");
            $stmt->execute([$void_reason, $admin_id, $res_id]);
        } elseif (!empty($ticket)) {
            $stmt = $pdo->prepare("UPDATE plate_scan_log SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE ticket_code = ?");
            $stmt->execute([$void_reason, $admin_id, $ticket]);
            
            $stmt = $pdo->prepare("UPDATE reservation SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE reservation_code = ?");
            $stmt->execute([$void_reason, $admin_id, $ticket]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Entry voided successfully']);
        exit;
    }

    // Bulk VOID
    if ($mode === 'by_date') {
        $stmt = $pdo->prepare("UPDATE plate_scan_log SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE DATE(scan_time) = ?");
        $stmt->execute([$void_reason, $admin_id, $date]);
        
        $stmt = $pdo->prepare("UPDATE reservation SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW() WHERE DATE(reserved_from) = ?");
        $stmt->execute([$void_reason, $admin_id, $date]);
    } else {
        $stmt = $pdo->prepare("UPDATE plate_scan_log SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW()");
        $stmt->execute([$void_reason, $admin_id]);
        
        $stmt = $pdo->prepare("UPDATE reservation SET is_void = 1, void_reason = ?, void_by = ?, void_at = NOW()");
        $stmt->execute([$void_reason, $admin_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Successfully voided logs for: {$date_label}"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("delete_logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A system error occurred.']);
}
