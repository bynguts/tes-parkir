<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role      = $_SESSION['role'] ?? 'operator';
$page_title = $page_title ?? 'Parking System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — SmartParking</title>

    <!-- Google Fonts: Manrope + Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">

    <!-- Font Awesome 6.5.1 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
                    'surface': '#f8fafc',
                    'surface-bright': '#ffffff',
                    'on-surface': '#0f172a',
                    'primary-fixed': '#0f172a',
                    'secondary-fixed': 'rgba(15, 23, 42, 0.4)',
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

        /* Dashboard & Content Hierarchy */
        main p, main span, main div, main td, main th, main label, main a { font-weight: 500; }

        /* Icon Defaults: FontAwesome Solid Override */
        .fas, .fa-solid {
            font-weight: 900 !important;
            vertical-align: middle;
        }

        i { display: inline-block; }

        /* Global Scrollbar Reset & Standard Look */
        ::-webkit-scrollbar-button {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        /* Standard custom look for ALL scrollable elements */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 20px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* --- Sticky Header Fix ---
         * Body must NOT scroll. Scroll happens inside <main>.
         * This makes `sticky top-0` inside <main> work on ALL pages. */
        html {
            height: 100%;
            overflow: hidden;
        }
        body {
            height: 100%;
            overflow: hidden;
            overflow-x: hidden;
        }
        /* <main> is the actual scroll container */
        main {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-gutter: stable;
            scrollbar-width: none;      /* Firefox */
            -ms-overflow-style: none;   /* IE/Edge */
        }
        main::-webkit-scrollbar {
            display: none;
        }

        /* Support for hover-to-scroll on any element */
        .custom-scrollbar {
            scrollbar-width: thin;
            -ms-overflow-style: auto;
        }

        /* Utility to hide scrollbar while keeping scroll alive */
        .no-scrollbar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .no-scrollbar {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }

        /* Sidebar active link */
        .nav-active {
            background-color: #0f172a !important;
            color: #ffffff !important;
        }
        .nav-active i { color: #ffffff !important; }

        /* Status badge dot */
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
        .animate-pulse { animation: pulse 2s cubic-bezier(.4,0,.6,1) infinite; }

        /* Progress bar */
        .progress-bar-fill { transition: width 0.8s ease; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
<?php
if (!isset($hide_sidebar) || !$hide_sidebar) {
    include 'sidebar.php';
}
?>

<!-- Main Content Wrapper & Scroll Container -->
<main class="pl-64 min-h-screen bg-slate-50 text-on-surface">

    <!-- Global Top Bar (Sticky) -->
    <?php if (!isset($hide_header) || !$hide_header): ?>
    <header class="flex justify-between items-center px-10 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-900/10">
        <!-- Search Bar (Left Aligned) -->
        <div class="relative group">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-900/30 group-focus-within:text-slate-900 transition-colors"></i>
            <input type="text" 
                   placeholder="Search anything about website..." 
                   class="w-[320px] bg-slate-900/5 border border-slate-900/5 px-11 py-2.5 rounded-2xl text-sm font-inter placeholder:text-slate-900/30 focus:outline-none focus:bg-white focus:border-slate-900/10 focus:ring-4 focus:ring-slate-900/[0.02] transition-all"
            >
        </div>

        <div class="flex items-center gap-3 ml-auto">
            <!-- Theme Toggle -->
            <button id="theme-toggle" class="w-11 h-11 rounded-2xl bg-white border border-slate-900/10 flex items-center justify-center text-slate-900 hover:bg-slate-900/5 transition-all shadow-sm">
                <i class="fa-solid fa-moon text-slate-900/60 transition-all duration-300" id="theme-icon"></i>
            </button>

            <!-- Universal Export -->
            <button class="flex items-center gap-2 bg-white border border-slate-900/10 text-slate-900 px-4 py-2.5 rounded-2xl font-inter font-semibold text-sm transition-all hover:bg-slate-900/5 hover:border-slate-900/20 shadow-sm">
                <i class="fa-solid fa-file-export text-slate-900/60"></i>
                <span>Export</span>
            </button>

            <script>
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            const root = document.documentElement;

            // Check for saved theme
            if (localStorage.getItem('theme') === 'dark') {
                root.setAttribute('data-theme', 'dark');
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                root.setAttribute('data-theme', 'light');
            }

            themeToggle.addEventListener('click', () => {
                const isDark = root.getAttribute('data-theme') === 'dark';
                const newTheme = isDark ? 'light' : 'dark';
                
                root.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                
                if (newTheme === 'dark') {
                    themeIcon.classList.replace('fa-moon', 'fa-sun');
                } else {
                    themeIcon.classList.replace('fa-sun', 'fa-moon');
                }
            });
            </script>

            <!-- How to use -->
            <button class="flex items-center gap-2 bg-white border border-slate-900/10 text-slate-900 px-4 py-2.5 rounded-2xl font-inter font-semibold text-sm transition-all hover:bg-slate-900/5 hover:border-slate-900/20 shadow-sm">
                <i class="fa-solid fa-circle-question text-slate-900/60"></i>
                <span>How to use</span>
            </button>

            <!-- Divider -->
            <div class="w-px h-6 bg-slate-900/10 mx-1"></div>

            <!-- Notifications -->
            <button class="w-11 h-11 rounded-2xl bg-white border border-slate-900/10 flex items-center justify-center text-slate-900 hover:bg-slate-900/5 transition-all shadow-sm relative">
                <i class="fa-solid fa-bell text-slate-900/60"></i>
                <span class="absolute top-3 right-3.5 w-2 h-2 bg-slate-900 rounded-full border-2 border-white"></span>
            </button>
        </div>
    </header>
    <?php endif; ?>
