<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'superadmin';
$_SESSION['role'] = 'superadmin';
$_SESSION['full_name'] = 'Super Administrator';
$_SESSION['last_regenerated'] = time();
header('Location: index.php');
