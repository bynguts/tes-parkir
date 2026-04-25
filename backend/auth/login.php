<?php
/**
 * auth/login.php
 * JSON login endpoint consumed by React frontend.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Accept JSON body
$body = json_decode(file_get_contents('php://input'), true);
$username   = trim($body['username'] ?? '');
$password   = $body['password'] ?? '';
$csrf_token = $body['csrf_token'] ?? '';

// CSRF check
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Reload and try again.']);
    exit;
}

if (!$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'Username dan password wajib diisi.']);
    exit;
}

// Rate limiting (5 fails per 5 min per IP)
$ip  = $_SERVER['REMOTE_ADDR'];
$key = 'login_fail_' . md5($ip);
if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'since' => time()];
if (time() - $_SESSION[$key]['since'] > 300) $_SESSION[$key] = ['count' => 0, 'since' => time()];

if ($_SESSION[$key]['count'] >= 5) {
    $remaining = 300 - (time() - $_SESSION[$key]['since']);
    echo json_encode(['success' => false, 'error' => "Terlalu banyak percobaan. Tunggu {$remaining}s."]);
    exit;
}

// DB connection
require_once __DIR__ . '/../config/connection.php';

$stmt = $pdo->prepare("SELECT user_id, password_hash, role, full_name FROM admin_users WHERE username = ? AND is_active = 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    unset($_SESSION[$key]);
    session_regenerate_id(true);

    $_SESSION['logged_in']        = true;
    $_SESSION['user_id']          = $user['user_id'];
    $_SESSION['username']         = $username;
    $_SESSION['role']             = $user['role'];
    $_SESSION['full_name']        = $user['full_name'];
    $_SESSION['last_regenerated'] = time();

    $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE user_id = ?")
        ->execute([$user['user_id']]);

    echo json_encode([
        'success'   => true,
        'user_id'   => (int)$user['user_id'],
        'username'  => $username,
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ]);
} else {
    $_SESSION[$key]['count']++;
    usleep(300_000); // 300ms delay to slow brute force
    echo json_encode(['success' => false, 'error' => 'Otentikasi gagal: Identitas atau key tidak valid.']);
}
