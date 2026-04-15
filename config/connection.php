<?php
date_default_timezone_set('Asia/Jakarta');

/**
 * config/connection.php — PDO connection (production-ready)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Simple BASE_URL detection
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Normalize path to root
    $root_dir = $script_dir;
    if (strpos($root_dir, '/modules') !== false) {
        $root_dir = preg_replace('/\/modules\/.*/', '', $root_dir);
    }
    
    $url = $protocol . '://' . $host . rtrim($root_dir, '/\\') . '/';
    define('BASE_URL', $url);
}

$db_host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_name = defined('DB_NAME') ? DB_NAME : 'parking_db_v2';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_pass = defined('DB_PASS') ? DB_PASS : '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    error_log("DB Connection failed: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed.']));
}
