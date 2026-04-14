<?php
require_once 'config/connection.php';

// Menghapus data user lama agar bersih sesuai permintaan "paling gampang"
$pdo->exec("TRUNCATE TABLE admin_users");

$users = [
    'superadmin' => ['pass' => 'superadmin123', 'role' => 'superadmin', 'name' => 'Super Administrator'],
    'admin'      => ['pass' => 'admin123',      'role' => 'admin',      'name' => 'Administrator'],
    'operator'   => ['pass' => 'operator123',   'role' => 'operator',   'name' => 'Parking Operator']
];

foreach ($users as $username => $data) {
    $hash = password_hash($data['pass'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, full_name, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$username, $hash, $data['role'], $data['name']]);
    echo "User $username created as {$data['role']}.\n";
}
echo "Database akun berhasil di-reset ke standar Role-Based!";
?>
