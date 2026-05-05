<?php
/**
 * api/upload_avatar.php — Handle Profile Picture Upload
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/connection.php';

header('Content-Type: application/json');

if (empty($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['avatar'];
$customer_id = $_SESSION['customer_id'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WEBP allowed.']);
    exit;
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2MB.']);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../assets/uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $customer_id . '_' . time() . '.' . $extension;
$target_path = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Relative path for storage
    $avatar_url = 'assets/uploads/avatars/' . $filename;

    try {
        // Update database
        $stmt = $pdo->prepare("UPDATE customers SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$avatar_url, $customer_id]);

        echo json_encode(['success' => true, 'avatar_url' => $avatar_url]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}
