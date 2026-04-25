<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username   = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role       = $_SESSION['role'] ?? 'operator';
$page_title = $page_title ?? 'Parking System';

// When loaded in React iframe, skip sidebar + outer shell
$is_embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($is_embed) {
    include __DIR__ . '/header_embed.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — SmartParking</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    'manrope': ['Manrope', 'sans-serif'],
                    'inter': ['Inter', 'sans-serif'],
                },
                colors: {
                    'surface': '#f2f4f7',
                    'surface-bright': '#ffffff',
                    'on-surface': '#0f172a',
                    'primary-fixed': '#0f172a',
                    'secondary-fixed': '#64748b',
                },
            }
        }
    }
    </script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        .font-code { font-family: 'Courier Prime', monospace !important; letter-spacing: 0.05em; }
        h1, h2, h3, .font-manrope { font-family: 'Manrope', sans-serif; font-weight: 800; }
        h1, h2, h3 { font-weight: 800 !important; }
        main p, main span, main div, main td, main th, main label, main a { font-weight: 700; }
        aside, aside * { font-weight: 400 !important; }
        aside .material-symbols-outlined { font-variation-settings: 'wght' 400 !important; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 700, 'GRAD' 0, 'opsz' 24;
            font-weight: 700; vertical-align: middle; line-height: 1;
        }
        ::-webkit-scrollbar-button { display: none !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        html, body { height: 100%; overflow: hidden; }
        .progress-bar-fill { animation: progressFill 1s ease forwards; }
        @keyframes progressFill { from { width: 0; } }
        .nav-active { background: #f1f5f9 !important; color: #0f172a !important; font-weight: 600 !important; }
        .nav-active .material-symbols-outlined { color: #0f172a !important; }
    </style>
</head>
<body class="bg-surface">
<div class="flex h-screen overflow-hidden">

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="flex-1 ml-64 overflow-y-auto h-screen">
