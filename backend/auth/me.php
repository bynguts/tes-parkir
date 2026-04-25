<?php
/**
 * auth/me.php
 * Returns current session user data as JSON.
 * Used by React frontend AuthProvider on page load.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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

if (!empty($_SESSION['logged_in'])) {
    echo json_encode([
        'logged_in' => true,
        'user_id'   => (int)$_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? 'operator',
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
