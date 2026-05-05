<?php
/**
 * logout.php
 * Handles both operator (admin) logout and customer logout.
 * Usage: logout.php           => operator logout -> login.php
 *        logout.php?type=customer => customer logout -> home.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$type = $_GET['type'] ?? 'operator';

if ($type === 'customer') {
    // Only clear customer-specific session keys, keep operator keys intact
    unset(
        $_SESSION['customer_id'],
        $_SESSION['customer_name'],
        $_SESSION['customer_email']
    );
    // If no operator session remains, destroy fully
    if (empty($_SESSION['logged_in'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
    header('Location: home.php');
    exit;
}

// Default: full operator logout
if (!empty($_SESSION['staff_id'])) {
    require_once 'config/connection.php';
    $pdo->prepare("UPDATE shift_attendance SET is_active = 0, check_out_time = NOW() WHERE staff_id = ? AND is_active = 1")
        ->execute([$_SESSION['staff_id']]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
