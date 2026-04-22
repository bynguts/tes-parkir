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
                    'brand': 'var(--brand)',
                    'surface': 'var(--surface)',
                    'surface-alt': 'var(--surface-alt)',
                    'bg-page': 'var(--bg-page)',
                    'primary': 'var(--text-primary)',
                    'secondary': 'var(--text-secondary)',
                    'border-color': 'var(--border-color)',
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
<body class="bg-page text-primary overflow-hidden">
<?php
if (!isset($hide_sidebar) || !$hide_sidebar) {
    include 'sidebar.php';
}
?>

<!-- Main Content Wrapper & Scroll Container -->
<main class="pl-64 min-h-screen bg-page text-primary">

    <!-- Global Top Bar (Sticky) -->
    <?php if (!isset($hide_header) || !$hide_header): ?>
    <header class="flex justify-between items-center px-10 h-20 sticky top-0 z-30 bg-page border-b border-color">
        <!-- Search Bar (Left Aligned) -->
        <div class="group h-11 bento-card flex items-center px-4 gap-3 transition-all">
            <i class="fa-solid fa-magnifying-glass text-brand transition-all pointer-events-none"></i>
            <input type="text" 
                   placeholder="Search anything about website..." 
                   class="w-[340px] h-full bg-transparent text-[13px] font-inter font-medium text-primary placeholder:text-primary transition-colors focus:outline-none"
            >
        </div>

        <div class="flex items-center gap-3 ml-auto">
            <!-- Theme Toggle Switch -->
            <div class="flex items-center gap-3 h-11 bento-card px-4 flex items-center transition-all">
                <span id="theme-label" class="text-[13px] font-inter font-medium text-primary">Light</span>
                <div id="theme-toggle" class="w-10 h-5 bg-slate-200 dark:bg-brand/30 rounded-full p-1 cursor-pointer transition-all relative flex items-center">
                    <div id="theme-thumb" class="w-3 h-3 bg-white rounded-full transition-transform transform translate-x-0 shadow-sm"></div>
                </div>
            </div>

            <!-- Universal Export -->
            <button class="flex items-center gap-3 h-11 bento-card px-4 font-inter font-medium text-[13px] text-primary transition-all group">
                <i class="fa-solid fa-file-export text-brand text-lg transition-colors"></i>
                <span>Export</span>
            </button>

            <script>
            const themeToggle = document.getElementById('theme-toggle');
            const themeThumb = document.getElementById('theme-thumb');
            const themeLabel = document.getElementById('theme-label');
            const root = document.documentElement;

            function updateToggleUI(isDark) {
                if (isDark) {
                    themeToggle.classList.replace('bg-slate-200', 'bg-brand');
                    themeThumb.style.transform = 'translateX(20px)';
                    themeLabel.textContent = 'Dark';
                } else {
                    themeToggle.classList.replace('bg-brand', 'bg-slate-200');
                    themeThumb.style.transform = 'translateX(0)';
                    themeLabel.textContent = 'Light';
                }
            }

            // Init
            const savedTheme = localStorage.getItem('theme') || 'light';
            root.setAttribute('data-theme', savedTheme);
            updateToggleUI(savedTheme === 'dark');

            themeToggle.addEventListener('click', () => {
                const currentTheme = root.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                root.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                
                // Cookie for PHP
                document.cookie = "theme=" + newTheme + "; path=/; max-age=" + (30*24*60*60);
                
                updateToggleUI(newTheme === 'dark');
            });
            </script>

        </div>
    </header>
    <?php endif; ?>
