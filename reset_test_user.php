<?php
require 'config/connection.php';
$email = 'test@user123';
$hash = password_hash('password123', PASSWORD_BCRYPT);
try {
    $stmt = $pdo->prepare('INSERT INTO customers (full_name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute(['Test User', $email, $hash]);
    echo 'Created customer test@user123 with password: password123' . "\n";
} catch(Exception $e) {
    $stmt = $pdo->prepare('UPDATE customers SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $email]);
    echo 'Reset password for customer test@user123 to: password123' . "\n";
}

try {
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', 'Test Admin')");
    $stmt->execute(['test', $hash]);
    echo 'Created admin user: test with password: password123' . "\n";
} catch(Exception $e) {
    $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'test'");
    $stmt->execute([$hash]);
    echo 'Reset password for admin user: test to: password123' . "\n";
}
