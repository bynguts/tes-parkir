<?php
/**
 * config/connection.php
 * Loads .env, creates PDO, defines BASE_URL.
 */

$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'parking_db';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user, $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed.']);
    } else {
        echo '<h1>503</h1><p>Database connection failed.</p>';
    }
    exit;
}

if (!defined('BASE_URL')) {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script    = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_path = rtrim(dirname(str_replace(basename($script), '', $script)), '/\\');
    define('BASE_URL', $protocol . '://' . $host . $base_path . '/');
}
