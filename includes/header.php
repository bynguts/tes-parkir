<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    define('BASE_URL', ''); 
}
$username  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$role      = $_SESSION['role'] ?? 'operator';
$page_title = $page_title ?? 'Parking System';
?>
<!DOCTYPE html>
<?php $sidebar_collapsed = ($_COOKIE['sidebar_collapsed'] ?? 'false') === 'true'; ?>
<html lang="en" class="<?= $sidebar_collapsed ? 'sidebar-collapsed' : '' ?>" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Parkhere</title>

    <!-- Font Awesome 6.5.1 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Flatpickr (Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Theme Logic & Tailwind -->
    <?php include __DIR__ . '/theme_init.php'; ?>

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

        /* Standard custom look for ALL scrollable elements - MOVED TO THEME.CSS */

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


        /* 1. Sidebar Base Widths (Immediate, no transition on load) */
        aside { 
            width: 256px; 
            overflow-x: hidden;
        }
        main { padding-left: 256px; }
        header {
            left: 256px;
            right: 0;
            width: auto !important;
        }

        /* Enable transitions ONLY after load (prevents jump on refresh) */
        html.sidebar-ready aside, 
        html.sidebar-ready main,
        html.sidebar-ready header,
        html.sidebar-ready .sidebar-label,
        html.sidebar-ready .sidebar-brand-text,
        html.sidebar-ready .sidebar-badge,
        html.sidebar-ready .sidebar-label-text,
        html.sidebar-ready .sidebar-link,
        html.sidebar-ready aside nav {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* 2. Sidebar Collapsed State (Single Source of Truth) */
        html.sidebar-collapsed aside { width: 80px; }
        html.sidebar-collapsed main { padding-left: 80px; }
        html.sidebar-collapsed header { left: 80px; }

        /* 3. Element Visibility & Centering */
        .sidebar-label,
        .sidebar-brand-text,
        .sidebar-badge,
        .sidebar-label-text {
            opacity: 1;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
            width: auto;
        }

        html.sidebar-collapsed .sidebar-label,
        html.sidebar-collapsed .sidebar-brand-text,
        html.sidebar-collapsed .sidebar-badge,
        html.sidebar-collapsed .sidebar-label-text {
            opacity: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            pointer-events: none !important;
            overflow: hidden;
        }

        html.sidebar-collapsed aside .sidebar-link {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: auto !important;
            margin-right: auto !important;
            width: 48px !important;
            height: 48px !important;
            gap: 0 !important;
        }

        .sidebar-link i {
            min-width: 24px;
            text-align: center;
        }

        html.sidebar-collapsed aside .sidebar-link i {
            margin: 0 !important;
            font-size: 1.25rem !important;
        }

        /* Brand Box Centering */
        html.sidebar-collapsed .sidebar-brand-box {
            padding-left: 0 !important;
            padding-right: 0 !important;
            justify-content: center !important;
        }

        html.sidebar-collapsed .sidebar-brand-box > div {
            gap: 0 !important;
            justify-content: center !important;
        }

    </style>
</head>
<body class="bg-page text-primary overflow-hidden">
<?php
$sidebar_collapsed = ($_COOKIE['sidebar_collapsed'] ?? 'false') === 'true';
if (!isset($hide_sidebar) || !$hide_sidebar) {
    include 'sidebar.php';
}
?>

<!-- Global Notification Container -->
<div id="push-notification-container" class="fixed top-10 right-10 z-[200000] flex flex-col gap-3 w-[380px] pointer-events-none"></div>

<script>
    // GLOBAL PUSH NOTIFICATION SYSTEM
    function pushNotify(title, message, type = 'info', code = null) {
        const container = document.getElementById('push-notification-container');
        if (!container) return;
        const id = 'notif-' + Date.now();
        
        let iconBg = 'bg-indigo-500/10';
        let iconColor = 'text-indigo-500';
        let icon = 'fa-info-circle';
        
        if (type === 'success') {
            iconBg = 'bg-emerald-500/10';
            iconColor = 'text-emerald-500';
            icon = 'fa-circle-check';
        } else if (type === 'error') {
            iconBg = 'bg-rose-500/10';
            iconColor = 'text-rose-500';
            icon = 'fa-circle-exclamation';
        } else if (type === 'ticket') {
            iconBg = 'bg-brand/10';
            iconColor = 'text-brand';
            icon = 'fa-ticket';
        } else if (type === 'vip') {
            iconBg = 'bg-indigo-500/10';
            iconColor = 'text-indigo-500';
            icon = 'fa-crown';
        } else if (type === 'exit') {
            iconBg = 'bg-rose-500/10';
            iconColor = 'text-rose-500';
            icon = 'fa-car-side';
        }

        const html = `
            <div id="${id}" class="notification-item bento-card !p-0 flex flex-col animate-slide-in pointer-events-auto border border-color overflow-hidden w-[380px]" style="box-shadow: 0 20px 50px -10px rgba(0,0,0,0.5) !important;">
                <div class="flex items-center gap-4 p-4">
                    <div class="w-12 h-12 rounded-2xl ${iconBg} flex items-center justify-center shrink-0 transition-transform group-hover:scale-110">
                        <i class="fa-solid ${icon} text-xl ${iconColor}"></i>
                    </div>
                    <div class="flex flex-col min-w-0 flex-1">
                        <h4 class="text-[15px] font-manrope font-extrabold text-primary truncate tracking-tight">${title}</h4>
                        <p class="text-[12px] font-medium text-tertiary leading-snug">${message}</p>
                    </div>
                    <button onclick="this.closest('.notification-item').remove()" class="w-8 h-8 rounded-full hover:bg-rose-500/10 text-tertiary/30 hover:text-rose-500 transition-all flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>
                <div class="h-[3px] bg-brand/5 w-full overflow-hidden">
                    <div class="h-full bg-brand notification-progress opacity-60"></div>
                </div>
            </div>
        `;
        
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const el = temp.firstElementChild;
        container.appendChild(el);
        
        setTimeout(() => {
            if (el) {
                el.classList.add('animate-slide-out');
                setTimeout(() => el.remove(), 400);
            }
        }, 5000);
    }
</script>

<!-- Main Content Wrapper & Scroll Container -->
<main class="min-h-screen bg-page text-primary">

    <!-- Global Top Bar (Sticky) -->
    <?php if (!isset($hide_header) || !$hide_header): ?>
    <header class="flex items-center pl-10 pr-10 h-20 sticky top-0 z-30 bg-page border-b border-color gap-3">
        <button id="sidebar-toggle" class="w-11 h-11 bento-card flex items-center justify-center transition-all shrink-0 group hover:scale-105 active:scale-95" title="Toggle sidebar">
            <i class="fa-solid fa-bars text-lg text-secondary group-hover:text-brand transition-colors"></i>
        </button>

        <!-- Search Bar -->
        <div id="global-search-wrap" class="group relative h-11 bento-card flex items-center px-4 gap-3 transition-all">
            <div class="w-5 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-magnifying-glass text-brand text-lg transition-all pointer-events-none"></i>
            </div>
            <input id="global-search-input" type="text" 
                   placeholder="Search anything about website..." 
                   class="w-[240px] h-full bg-transparent text-[13px] font-inter font-medium text-primary placeholder:text-secondary transition-colors focus:outline-none"
                   autocomplete="off"
            >
            <div id="global-search-results" class="hidden absolute left-0 top-[calc(100%+8px)] w-[540px] max-h-[360px] overflow-y-auto rounded-2xl border border-color bg-page shadow-xl z-50 p-2"></div>
        </div>



            <!-- Visit Public Site -->
            <a href="<?= BASE_URL ?>home.php" class="ml-auto flex items-center gap-3 h-11 bento-card px-4 font-manrope font-bold text-[13px] text-primary transition-all group hover:border-brand">
                <div class="w-5 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-earth-americas text-brand text-lg transition-colors"></i>
                </div>
                <span>Public Site</span>
            </a>

            <!-- Theme Switch (3-Option Segmented Control) -->
            <div class="theme-segmented-control bg-surface border border-color rounded-full p-1 flex items-center relative overflow-hidden h-11 w-[130px]">
                <!-- Sliding Indicator -->
                <div id="theme-indicator" class="absolute h-9 w-[38px] bg-brand rounded-full transition-all duration-300 ease-out shadow-sm"></div>
                
                <button data-theme-btn="system" class="flex-1 h-full flex items-center justify-center relative z-10 text-primary transition-colors" title="System Theme">
                    <i class="fa-solid fa-desktop text-[13px]"></i>
                </button>
                <button data-theme-btn="light" class="flex-1 h-full flex items-center justify-center relative z-10 text-primary transition-colors" title="Light Theme">
                    <i class="fa-solid fa-sun text-[14px]"></i>
                </button>
                <button data-theme-btn="dark" class="flex-1 h-full flex items-center justify-center relative z-10 text-primary transition-colors" title="Dark Theme">
                    <i class="fa-solid fa-moon text-[14px]"></i>
                </button>
            </div>

            <!-- Universal Export -->
            <a href="<?= BASE_URL ?>modules/reports/export_excel.php" target="_blank" class="flex items-center gap-3 h-11 bento-card px-4 font-manrope font-bold text-[13px] text-primary transition-all group hover:border-brand">
                <div class="w-5 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-file-export text-brand text-lg transition-colors"></i>
                </div>
                <span>Export</span>
            </a>

            <!-- User Profile Dropdown -->
            <div class="relative" id="profile-dropdown-container">
                <button id="profile-dropdown-toggle" class="w-11 h-11 rounded-full bento-card flex items-center justify-center transition-all group overflow-hidden">
                    <div class="w-8 h-8 rounded-full bg-brand flex items-center justify-center border border-white/10 shrink-0">
                        <span class="text-white text-xs font-bold font-inter"><?= strtoupper(substr($username, 0, 1)) ?></span>
                    </div>
                </button>

                <!-- Dropdown Menu -->
                <div id="profile-menu" class="absolute right-0 mt-2 w-56 bento-card opacity-0 invisible scale-95 origin-top-right transition-all z-50 overflow-hidden shadow-2xl">
                    <div class="px-5 py-4 border-b border-color bg-surface-alt/30">
                        <div class="text-[10px] font-bold text-secondary uppercase tracking-[0.2em] mb-2">Logged in as</div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand flex items-center justify-center border border-white/10 shadow-lg">
                                <span class="text-white text-sm font-bold"><?= strtoupper(substr($username, 0, 1)) ?></span>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <div class="text-sm font-bold text-primary truncate"><?= $username ?></div>
                                <div class="text-[10px] text-secondary font-bold uppercase tracking-wider"><?= $role ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="p-1.5">
                        <a href="<?= BASE_URL ?>logout.php" class="flex items-center gap-3 px-4 py-3 text-[13px] text-rose-500 hover:bg-rose-500/10 rounded-xl transition-colors group">
                            <i class="fa-solid fa-power-off text-sm group-hover:rotate-12 transition-transform"></i>
                            <span class="font-bold">Logout System</span>
                        </a>
                    </div>
                </div>
        </div>

            <script>
            // Profile Dropdown Toggle
            (function initProfileDropdown() {
                const toggle = document.getElementById('profile-dropdown-toggle');
                const menu = document.getElementById('profile-menu');
                const container = document.getElementById('profile-dropdown-container');

                if (!toggle || !menu) return;

                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !menu.classList.contains('invisible');
                    if (isOpen) {
                        menu.classList.add('opacity-0', 'invisible', 'scale-95');
                    } else {
                        menu.classList.remove('opacity-0', 'invisible', 'scale-95');
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!container.contains(e.target)) {
                        menu.classList.add('opacity-0', 'invisible', 'scale-95');
                    }
                });
            })();
            </script>

            <script>
            (function initThemeToggle() {
                const indicator = document.getElementById('theme-indicator');
                const buttons = document.querySelectorAll('[data-theme-btn]');
                const root = document.documentElement;

                if (!indicator || !buttons.length) return;

                const themePositions = {
                    'system': '4px',
                    'light': '46px',
                    'dark': '88px'
                };

                function updateThemeUI(mode) {
                    indicator.style.left = themePositions[mode] || themePositions['system'];
                    buttons.forEach(btn => {
                        const btnTheme = btn.getAttribute('data-theme-btn');
                        if (btnTheme === mode) {
                            btn.classList.add('text-white');
                            btn.classList.remove('text-primary');
                        } else {
                            btn.classList.add('text-primary');
                            btn.classList.remove('text-white');
                        }
                    });
                }

                // Init
                const currentMode = localStorage.getItem('theme') || 'system';
                updateThemeUI(currentMode);

                buttons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const newMode = btn.getAttribute('data-theme-btn');
                        localStorage.setItem('theme', newMode);
                        root.setAttribute('data-theme-mode', newMode);
                        
                        if (newMode === 'system') {
                            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                            root.setAttribute('data-theme', isDark ? 'dark' : 'light');
                        } else {
                            root.setAttribute('data-theme', newMode);
                        }
                        
                        const appliedTheme = root.getAttribute('data-theme');
                        document.cookie = "theme=" + appliedTheme + "; path=/; max-age=" + (30*24*60*60);
                        updateThemeUI(newMode);
                    });
                });
            })();

            // Sidebar toggle functionality
            (function initSidebarToggle() {
                const toggleBtn = document.getElementById('sidebar-toggle');
                const root = document.documentElement;

                if (!toggleBtn) return;

                const toggleSidebar = () => {
                    const isNowCollapsed = root.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebar-collapsed', isNowCollapsed);
                    document.cookie = "sidebar_collapsed=" + isNowCollapsed + "; path=/; max-age=" + (30*24*60*60) + "; path=/";
                };

                toggleBtn.addEventListener('click', toggleSidebar);

                // Enable transitions after a tiny delay to avoid load jump
                setTimeout(() => {
                    root.classList.add('sidebar-ready');
                }, 100);
            })();

            // Global search: index all accessible sidebar links and provide quick navigation.
            (function initGlobalSearch() {
                const input = document.getElementById('global-search-input');
                const resultsBox = document.getElementById('global-search-results');
                if (!input || !resultsBox) return;

                const linkNodes = Array.from(document.querySelectorAll('aside nav a[href]'));
                const unique = new Map();

                linkNodes.forEach((link) => {
                    const href = link.getAttribute('href') || '';
                    const labelEl = link.querySelector('.sidebar-label');
                    let label = '';
                    if (labelEl) {
                        label = (labelEl.textContent || '').trim();
                    } else {
                        label = (link.textContent || '').replace(/\s+/g, ' ').trim();
                    }
                    if (!href || !label) return;

                    const iconEl = link.querySelector('i');
                    const icon = iconEl ? iconEl.className : 'fa-solid fa-link';
                    const key = href.toLowerCase();
                    if (!unique.has(key)) {
                        unique.set(key, {
                            href,
                            label,
                            icon,
                            keywords: [label, href]
                        });
                    }
                });

                const staticItems = [
                    { href: 'index.php', label: 'Home Dashboard', icon: 'fa-solid fa-house', keywords: ['home', 'dashboard', 'ringkasan'] },
                    { href: 'logout.php', label: 'Logout', icon: 'fa-solid fa-power-off', keywords: ['keluar', 'sign out', 'logout'] }
                ];

                staticItems.forEach((item) => {
                    const key = item.href.toLowerCase();
                    if (!unique.has(key)) {
                        unique.set(key, item);
                    }
                });

                const pages = Array.from(unique.values());
                let activeIndex = -1;
                let visibleResults = [];

                function normalize(v) {
                    return (v || '').toString().toLowerCase().trim();
                }

                function score(item, q) {
                    const label = normalize(item.label);
                    const href = normalize(item.href);
                    const terms = (item.keywords || []).map(normalize);

                    if (label === q) return 100;
                    if (label.startsWith(q)) return 90;
                    if (label.includes(q)) return 75;
                    if (href.includes(q)) return 60;
                    if (terms.some((k) => k.includes(q))) return 50;
                    return 0;
                }

                function hideResults() {
                    resultsBox.classList.add('hidden');
                    resultsBox.innerHTML = '';
                    activeIndex = -1;
                    visibleResults = [];
                }

                function openResult(item) {
                    if (!item || !item.href) return;
                    window.location.href = item.href;
                }

                function renderResults(items, query) {
                    visibleResults = items;
                    activeIndex = items.length ? 0 : -1;

                    if (!items.length) {
                        resultsBox.innerHTML = '<div class="px-4 py-3 text-xs text-secondary">No result for <strong>' + query.replace(/</g, '&lt;') + '</strong></div>';
                        resultsBox.classList.remove('hidden');
                        return;
                    }

                    const html = items.map((item, idx) => {
                        const activeClass = idx === activeIndex ? 'bg-slate-900/5' : '';
                        return (
                            '<button type="button" data-index="' + idx + '" class="search-item w-full text-left flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-slate-900/5 transition-colors ' + activeClass + '">' +
                                '<i class="' + item.icon + ' text-brand w-4 text-sm"></i>' +
                                '<div class="min-w-0">' +
                                    '<div class="text-[13px] font-medium text-primary truncate">' + item.label + '</div>' +
                                '</div>' +
                            '</button>'
                        );
                    }).join('');

                    resultsBox.innerHTML = html;
                    resultsBox.classList.remove('hidden');

                    resultsBox.querySelectorAll('.search-item').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const idx = Number(btn.getAttribute('data-index'));
                            openResult(visibleResults[idx]);
                        });
                    });
                }

                function refreshActiveItem() {
                    const nodes = resultsBox.querySelectorAll('.search-item');
                    nodes.forEach((node, idx) => {
                        if (idx === activeIndex) {
                            node.classList.add('bg-slate-900/5');
                        } else {
                            node.classList.remove('bg-slate-900/5');
                        }
                    });
                }

                input.addEventListener('input', () => {
                    const q = normalize(input.value);
                    if (!q) {
                        hideResults();
                        return;
                    }

                    const matched = pages
                        .map((item) => ({ item, score: score(item, q) }))
                        .filter((x) => x.score > 0)
                        .sort((a, b) => b.score - a.score || a.item.label.localeCompare(b.item.label))
                        .slice(0, 8)
                        .map((x) => x.item);

                    renderResults(matched, q);
                });

                input.addEventListener('keydown', (e) => {
                    if (resultsBox.classList.contains('hidden') || !visibleResults.length) {
                        if (e.key === 'Enter' && input.value.trim()) {
                            const q = normalize(input.value);
                            const best = pages
                                .map((item) => ({ item, score: score(item, q) }))
                                .sort((a, b) => b.score - a.score)[0];
                            if (best && best.score > 0) openResult(best.item);
                        }
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % visibleResults.length;
                        refreshActiveItem();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex = (activeIndex - 1 + visibleResults.length) % visibleResults.length;
                        refreshActiveItem();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeIndex >= 0) openResult(visibleResults[activeIndex]);
                    } else if (e.key === 'Escape') {
                        hideResults();
                    }
                });

                document.addEventListener('click', (e) => {
                    const wrap = document.getElementById('global-search-wrap');
                    if (wrap && !wrap.contains(e.target)) {
                        hideResults();
                    }
                });
            })();
            </script>

        </div>
    </header>
    <?php endif; ?>
