<?php
/**
 * includes/header_embed.php
 * Minimal header for pages rendered inside the React iframe (embed=1 mode).
 * No sidebar, no outer nav — just styles and content.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username   = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role       = $_SESSION['role'] ?? 'operator';
$page_title = $page_title ?? 'SmartParking';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

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
        html, body { overflow: auto; background: #f2f4f7; margin: 0; padding: 0; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 700, 'GRAD' 0, 'opsz' 24;
            font-weight: 700; vertical-align: middle; line-height: 1;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        main p, main span, main div, main td, main th, main label, main a { font-weight: 700; }
        h1, h2, h3 { font-family: 'Manrope', sans-serif; font-weight: 800 !important; }
        .progress-bar-fill { transition: width 1s ease; }
        .nav-active { background: #f1f5f9 !important; color: #0f172a !important; }
    </style>
</head>
<body class="bg-surface">
<main class="p-0">
