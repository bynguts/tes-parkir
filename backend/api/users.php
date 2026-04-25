<?php
/**
 * api/users.php
 * Handles user CRUD for the React frontend.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/connection.php';
require_role('superadmin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $users = $pdo->query("SELECT user_id, username, role, full_name, last_login, is_active, created_at FROM admin_users ORDER BY role, username")->fetchAll();
    echo json_encode($users);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';

    if ($action === 'add') {
        $uname    = trim($body['username'] ?? '');
        $pass     = $body['password'] ?? '';
        $urole    = $body['role'] ?? '';
        $fullname = trim($body['full_name'] ?? '');

        if (!$uname || !$pass || !in_array($urole, ['superadmin','admin','operator'])) {
            echo json_encode(['success' => false, 'error' => 'Semua field esensial wajib diisi.']);
            exit;
        }

        try {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name) VALUES (?,?,?,?)")
                ->execute([$uname, $hash, $urole, $fullname ?: $uname]);
            echo json_encode(['success' => true, 'message' => "User '{$uname}' berhasil dibuat."]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Username sudah terdaftar.']);
        }
        exit;
    }

    if ($action === 'toggle') {
        $uid = (int)($body['user_id'] ?? 0);
        if ($uid === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Tidak dapat menonaktifkan diri sendiri.']);
            exit;
        }
        $pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE user_id=?")->execute([$uid]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset_password') {
        $uid  = (int)($body['user_id'] ?? 0);
        $pass = $body['new_password'] ?? '';
        if (strlen($pass) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password minimal 8 karakter.']);
            exit;
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE user_id=?")->execute([$hash, $uid]);
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
