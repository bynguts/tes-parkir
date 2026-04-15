<?php
/**
 * includes/auth_guard.php
 * Paste at the very top of every protected page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Rotate session ID every 20 minutes to prevent fixation
if (empty($_SESSION['last_regenerated'])) {
    $_SESSION['last_regenerated'] = time();
} elseif (time() - $_SESSION['last_regenerated'] > 1200) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}
