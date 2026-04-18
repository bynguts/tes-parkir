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
    <header class="flex justify-between items-center px-6 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-900/10">
        <div class="flex flex-col">
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900"><?= $page_title ?></h1>
            <?php if (isset($page_subtitle) && $page_subtitle): ?>
                <span class="text-slate-900/40 text-[11px] font-inter font-medium uppercase tracking-wider -mt-1">
                    <?= $page_subtitle ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-6">
            <!-- Universal Export Button (Only on Dashboard) -->
            <?php if (isset($page_title) && $page_title === 'Dashboard'): ?>
                <?php
                // Build a root-relative path to the export script.
                // Works regardless of which module depth the page is included from.
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                // Walk up to find root (where login.php lives)
                $depth = substr_count(str_replace('\\','/',$scriptDir), '/') -
                         substr_count(str_replace('\\','/',rtrim(parse_url(BASE_URL,PHP_URL_PATH),'/\\')), '/');
                $prefix = str_repeat('../', max(0,$depth));
                $exportUrl = $prefix . 'modules/reports/export_excel.php';
                ?>
                <a href="<?= htmlspecialchars($exportUrl) ?>" target="_blank"
                   class="bg-white border border-slate-900/10 text-slate-900 px-5 py-2.5 rounded-2xl font-semibold text-sm font-manrope transition-all hover:bg-slate-900/5 flex items-center gap-2 shadow-sm"
                   title="Export all SmartParking data to Excel">
                    <i class="fa-solid fa-share-from-square text-lg opacity-75"></i>
                    Export
                </a>
            <?php endif; ?>

            <!-- Page specific actions -->
            <?php if (isset($page_actions)) echo $page_actions; ?>

            <!-- Settings / Notifications Placeholder -->
            <button class="w-10 h-10 rounded-full bg-slate-900/5 flex items-center justify-center text-slate-900/40 hover:bg-slate-900/10 transition-colors">
                <i class="fa-solid fa-bell"></i>
            </button>
        </div>
    </header>
    <?php endif; ?>
