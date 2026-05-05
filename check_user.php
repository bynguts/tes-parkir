<?php
require 'config/connection.php';
$email = 'test@user123';
if (strpos($email, '@') === false) { $email .= '@example.com'; } // just in case

// Check customers
$stmt = $pdo->prepare("SELECT id, full_name, email FROM customers WHERE email LIKE ?");
$stmt->execute(["%test%"]);
echo "Customers:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check staff
$stmt = $pdo->prepare("SELECT id, username, email FROM staff WHERE username LIKE ? OR email LIKE ?");
$stmt->execute(["%test%", "%test%"]);
echo "\nStaff:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Reset password for a specific user if requested
// $hash = password_hash('password123', PASSWORD_BCRYPT);
// $pdo->query("UPDATE staff SET password_hash = '$hash' WHERE username = 'test@user123'");
