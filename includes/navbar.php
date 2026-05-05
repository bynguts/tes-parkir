<?php
/**
 * includes/navbar.php
 * Premium global top navigation bar for customer-facing pages.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$customer_logged_in = !empty($_SESSION['customer_id']);
$current_page = $current_page ?? '';
$is_dark = ($_COOKIE['theme'] ?? 'light') === 'dark';
?>
<style>
    .glass-nav {
        background: var(--surface);
        border-bottom: 1px solid var(--border-color);
    }
    .nav-link {
        position: relative;
        transition: all 0.3s ease;
        color: var(--text-secondary);
    }
    .nav-link::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 100%;
        height: 2px;
        background: var(--brand);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    .nav-link.origin-left::after {
        transform-origin: left;
    }
    .nav-link.origin-right::after {
        transform-origin: right;
    }
    .nav-link:hover::after, .nav-link.active::after {
        transform: scaleX(1);
    }
    .nav-link:hover, .nav-link.active {
        color: var(--text-primary) !important;
    }
</style>

<nav class="fixed top-0 left-0 right-0 z-50 glass-nav">
    <div class="w-full px-8 md:px-12 h-20 flex items-center justify-between">
        <!-- Logo -->
        <a href="home.php" class="flex items-center gap-3 group">
            <img src="assets/images/logo.png" alt="Parkhere" class="w-10 h-10 object-contain group-hover:scale-110 transition-transform duration-300">
            <span class="text-xl font-manrope font-800 tracking-tight text-primary"><span class="text-brand">Park</span>here</span>
        </a>
        
        <!-- Navigation Links -->
        <div class="hidden lg:flex items-center gap-5 text-sm font-semibold absolute left-1/2 -translate-x-1/2 whitespace-nowrap">
            <?php
            // Calculate hover animation direction based on current page position
            $nav_pages = ['home' => 0, 'features' => 1, 'how-it-works' => 2, 'about-us' => 3, 'contact' => 4, 'bookings' => 5];
            $current_pos = isset($nav_pages[$current_page]) ? $nav_pages[$current_page] : 0;
            ?>
            <a href="home.php" class="nav-link <?= $current_page === 'home' ? 'active' : '' ?> <?= 0 > $current_pos ? 'origin-left' : 'origin-right' ?>">Home</a>
            
            <?php if ($current_page === 'home'): ?>
                <a href="#features" class="nav-link <?= 1 > $current_pos ? 'origin-left' : 'origin-right' ?>">Features</a>
                <a href="#how-it-works" class="nav-link <?= 2 > $current_pos ? 'origin-left' : 'origin-right' ?>">How It Works</a>
                <a href="#about-us" class="nav-link <?= 3 > $current_pos ? 'origin-left' : 'origin-right' ?>">About Us</a>
                <a href="#contact-us" class="nav-link <?= 4 > $current_pos ? 'origin-left' : 'origin-right' ?>">Contact Us</a>
            <?php else: ?>
                <a href="home.php#features" class="nav-link <?= 1 > $current_pos ? 'origin-left' : 'origin-right' ?>">Features</a>
                <a href="home.php#how-it-works" class="nav-link <?= 2 > $current_pos ? 'origin-left' : 'origin-right' ?>">How It Works</a>
                <a href="home.php#about-us" class="nav-link <?= 3 > $current_pos ? 'origin-left' : 'origin-right' ?>">About Us</a>
                <a href="home.php#contact-us" class="nav-link <?= 4 > $current_pos ? 'origin-left' : 'origin-right' ?>">Contact Us</a>
            <?php endif; ?>

            <?php if ($customer_logged_in): ?>
                <a href="my_bookings.php" class="nav-link <?= $current_page === 'bookings' ? 'active' : '' ?> <?= 5 > $current_pos ? 'origin-left' : 'origin-right' ?>">My Bookings</a>
            <?php endif; ?>
        </div>

        <!-- Auth & Actions -->
        <div class="flex items-center gap-4">
            <!-- 1. Book Now -->
            <a href="reserve.php" class="bg-brand hover:brightness-110 px-6 py-[10px] rounded-full text-sm font-bold text-white shadow-lg shadow-brand/20 active:scale-95 transition-all duration-300">Book Now</a>
            
            <!-- 2. Theme Switch (3-Option Segmented Control) -->
            <div class="theme-segmented-control bg-surface border border-color rounded-full p-1 flex items-center relative overflow-hidden h-11 w-[130px]">
                <!-- Sliding Indicator -->
                <div id="nav-theme-indicator" class="absolute h-9 w-[38px] bg-brand rounded-full transition-all duration-300 ease-out shadow-sm"></div>
                
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

            <!-- 3. Account / Sign In -->
            <?php if ($customer_logged_in): ?>
                <a href="account.php" class="flex items-center gap-2 text-sm font-semibold text-primary px-4 py-2 bg-surface border border-color hover:border-brand rounded-full transition-all group">
                    <div class="w-6 h-6 rounded-full bg-brand/10 flex items-center justify-center">
                        <i class="fa-solid fa-user text-[10px] text-brand"></i>
                    </div>
                    <?= htmlspecialchars($_SESSION['customer_name'] ?? 'Account') ?>
                </a>
            <?php else: ?>
                <a href="auth.php" class="text-sm font-semibold text-secondary hover:text-primary transition-colors px-4 py-2">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Navbar Spacer -->
<div class="h-20"></div>

<script>
(function() {
    const indicator = document.getElementById('nav-theme-indicator');
    const buttons = document.querySelectorAll('[data-theme-btn]');
    const root = document.documentElement;

    if (!indicator || !buttons.length) return;

    const themePositions = {
        'system': '4px',
        'light': '46px',
        'dark': '88px'
    };

    function updateThemeUI(mode) {
        // Move indicator
        indicator.style.left = themePositions[mode] || themePositions['system'];
        
        // Update button colors
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

    // Init from mode
    const currentMode = localStorage.getItem('theme') || 'system';
    updateThemeUI(currentMode);

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const newMode = btn.getAttribute('data-theme-btn');
            
            // Set state
            localStorage.setItem('theme', newMode);
            root.setAttribute('data-theme-mode', newMode);
            
            // Apply actual theme
            if (newMode === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                root.setAttribute('data-theme', isDark ? 'dark' : 'light');
            } else {
                root.setAttribute('data-theme', newMode);
            }
            
            // Cookie for PHP (keep it as the applied theme for now)
            const appliedTheme = root.getAttribute('data-theme');
            document.cookie = "theme=" + appliedTheme + "; path=/; max-age=" + (30*24*60*60);
            
            updateThemeUI(newMode);
        });
    });
})();
</script>
