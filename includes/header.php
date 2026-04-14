<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role      = $_SESSION['role'] ?? 'operator';

// Determine if we need to show the navbar vs sidebar based on the page layout 
// We will set this in the individual files if needed, but for now we default to the dashboard layout
$page_title = $page_title ?? 'Parking System';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — SmartParking</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Premium Glassmorphism Theme -->
    <link rel="stylesheet" href="assets/css/premium.css?v=<?= time() ?>">
</head>
<body>
<!-- Global Background -->
<div class="app-bg"></div>

<?php 
// Include Sidebar if requested
if (!isset($hide_sidebar) || !$hide_sidebar) {
    include 'sidebar.php';
}
?>
