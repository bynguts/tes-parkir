<?php
/**
 * api/manage_profile.php — Customer Profile Management
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/connection.php';
require_once '../includes/functions.php';

// Auth check
if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$customer_id = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    // Validation Regex
    $phoneRegex = '/^(\+62|0)8[1-9][0-9]{7,11}$/';
    $nameRegex  = '/^[a-zA-Z\s\.\,\']{3,50}$/';

    // Clean data for validation
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

    if (empty($full_name)) {
        header('Location: ../account.php?error=Full name required');
        exit;
    }

    if (!preg_match($nameRegex, $full_name)) {
        header('Location: ../account.php?error=Full name must be 3-50 characters (letters only)');
        exit;
    }

    if (!empty($phone) && !preg_match($phoneRegex, $cleanPhone)) {
        header('Location: ../account.php?error=Invalid Indonesian phone format (e.g. 0812...)');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE customers SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$full_name, $cleanPhone, $customer_id]);

        // Update session name if it changed
        $_SESSION['customer_name'] = $full_name;

        header('Location: ../account.php?success=Profile updated');
        exit;
    } catch (Exception $e) {
        header('Location: ../account.php?error=Update failed: ' . $e->getMessage());
        exit;
    }
}

header('Location: ../account.php');
