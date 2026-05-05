<?php
// includes/theme_init.php
// Centralized theme initialization to prevent flicker and standardize styling
?>
<!-- Theme State Preservation -->
<script>
    // Prevent theme and layout flicker by applying state before ANY render
    // Prevent theme and layout flicker by applying state before ANY render
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'system';
        document.documentElement.setAttribute('data-theme-mode', savedTheme);
        
        const applyTheme = (mode) => {
            if (mode === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            } else {
                document.documentElement.setAttribute('data-theme', mode);
            }
        };

        applyTheme(savedTheme);

        // Watch for system theme changes if in system mode
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (localStorage.getItem('theme') === 'system' || !localStorage.getItem('theme')) {
                document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
            }
        });
    })();
</script>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: ['selector', '[data-theme="dark"]'],
        theme: {
            extend: {
                spacing: {
                    '11': '2.75rem',
                    '9': '2.25rem',
                },
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

<!-- Custom Theme & Tokens -->
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>assets/css/theme.css">
<?php if (file_exists(__DIR__ . '/../assets/css/tokens.css')): ?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>assets/css/tokens.css">
<?php endif; ?>
