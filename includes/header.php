<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role      = $_SESSION['role'] ?? 'operator';
$page_title = $page_title ?? 'Parking System';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — SmartParking</title>

    <!-- Google Fonts: Manrope + Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Google Material Symbols Outlined -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0">

    <!-- Tailwind CSS CDN -->
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
        h1, h2, h3, .font-manrope { font-family: 'Manrope', sans-serif; }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
            line-height: 1;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Sidebar active link */
        .nav-active {
            background-color: #0f172a !important;
            color: #ffffff !important;
        }
        .nav-active .material-symbols-outlined { color: #ffffff !important; }

        /* Status badge dot */
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
        .animate-pulse { animation: pulse 2s cubic-bezier(.4,0,.6,1) infinite; }

        /* Progress bar */
        .progress-bar-fill { transition: width 0.8s ease; }
    </style>
</head>
<body class="bg-[#f2f4f7] text-slate-900">
<?php
if (!isset($hide_sidebar) || !$hide_sidebar) {
    include 'sidebar.php';
}
?>
