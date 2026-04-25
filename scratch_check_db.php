<?php
require_once 'config/connection.php';
$users = $pdo->query("SELECT * FROM admin_users")->fetchAll();
print_r($users);
