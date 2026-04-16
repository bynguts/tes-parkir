<?php
require_once '../includes/auth_guard.php';
require_once '../config/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

csrf_verify();

$staff_id = (int)($_POST['staff_id'] ?? 0);
$user_id  = (int)($_SESSION['user_id'] ?? 0);

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Please select your name.']);
    exit;
}

try {
    // 1. Mark existing active attendance for this session/user as inactive (optional, but good for safety)
    $pdo->prepare("UPDATE shift_attendance SET is_active = 0 WHERE user_id = ? AND is_active = 1")
        ->execute([$user_id]);

    // 2. Insert new record
    $stmt = $pdo->prepare("INSERT INTO shift_attendance (user_id, staff_id, check_in_time, is_active) VALUES (?, ?, NOW(), 1)");
    $stmt->execute([$user_id, $staff_id]);
    
    // 3. Get staff data for session
    $staff = $pdo->prepare("SELECT full_name FROM operator WHERE operator_id = ?");
    $staff->execute([$staff_id]);
    $staff_data = $staff->fetch();

    if ($staff_data) {
        $_SESSION['staff_id']   = $staff_id;
        $_SESSION['staff_name'] = $staff_data['full_name'];
        echo json_encode(['success' => true, 'message' => 'Attendance successful. Have a great shift!', 'name' => $staff_data['full_name']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Personnel identity not found.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
