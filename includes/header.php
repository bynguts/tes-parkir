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
        <div id="global-search-wrap" class="group relative h-11 bento-card flex items-center px-4 gap-3 transition-all">
            <i class="fa-solid fa-magnifying-glass text-brand transition-all pointer-events-none"></i>
            <input id="global-search-input" type="text" 
                   placeholder="Search anything about website..." 
                   class="w-[340px] h-full bg-transparent text-[13px] font-inter font-medium text-primary placeholder:text-primary transition-colors focus:outline-none"
                   autocomplete="off"
            >
            <div id="global-search-results" class="hidden absolute left-0 top-[calc(100%+8px)] w-[540px] max-h-[360px] overflow-y-auto rounded-2xl border border-color bg-page shadow-xl z-50 p-2"></div>
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

            // Global search: index all accessible sidebar links and provide quick navigation.
            (function initGlobalSearch() {
                const input = document.getElementById('global-search-input');
                const resultsBox = document.getElementById('global-search-results');
                if (!input || !resultsBox) return;

                const linkNodes = Array.from(document.querySelectorAll('aside nav a[href]'));
                const unique = new Map();

                linkNodes.forEach((link) => {
                    const href = link.getAttribute('href') || '';
                    const labelNode = link.cloneNode(true);
                    labelNode.querySelectorAll('span').forEach((el) => el.remove());
                    const label = (labelNode.textContent || '').replace(/\s+/g, ' ').trim().replace(/\s+\d+$/, '');
                    if (!href || !label) return;

                    const icon = link.querySelector('i') ? link.querySelector('i').className : 'fa-solid fa-link';
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
