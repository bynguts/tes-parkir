# Parkhere Design System (Tema Master)

This document serves as the master source of truth for UI theming and coloring within the Parkhere application. 

## 1. Core Philosophy: Token-Based Theming
To ensure consistency across the application, especially with Light and Dark modes, **we use CSS Custom Properties (Tokens)** defined in `:root` and `[data-theme="dark"]`. 

> [!WARNING]
> **STRICT RULE**: Never use hardcoded HEX or RGB color codes directly in PHP or HTML files. You MUST use the semantic CSS classes provided by the theme.

## 2. Testing & Applying New Colors

We have separated the core structural CSS (`assets/css/theme.css`) from the pure color tokens (`assets/css/tokens.css`). 

### How to Apply the Theme to a Page
To apply the theme and any specific color tests, ensure these files are included in the `<head>` of your document. Typically, `theme.css` is included via `header.php`.

If you are testing a new color override on a specific page (like `index.php`), inject the `tokens.css` immediately after the header:
```php
<?php include 'includes/header.php'; ?>
<!-- Inject Test Color Tokens -->
<link rel="stylesheet" href="assets/css/tokens.css?v=<?= time() ?>">
```

### Modifying the Theme Colors
To change the brand color (e.g., from Indigo to Emerald), modify `assets/css/tokens.css`:
```css
:root {
    --brand: #10b981; /* Primary Light Mode Color */
    --brand-subtle: #ecfdf5; /* Subtle Light Mode Background */
    --hover-border: #10b981;
}

[data-theme="dark"] {
    --brand: #34d399; /* Primary Dark Mode Color */
    --brand-subtle: #064e3b; /* Subtle Dark Mode Background */
    --hover-border: #34d399;
}
```

## 3. Semantic CSS Utility Classes
When building UI components, rely exclusively on these semantic utility classes to automatically sync with the active theme.

### Backgrounds
- `bg-brand` : Uses `--brand`. Standard for primary buttons, active icons.
- `bg-surface` : Uses `--surface`. The background of Bento Cards (`.bento-card`).
- `bg-surface-alt` : Uses `--surface-alt`. Inputs, toggles, or secondary containers.
- `bg-page` : Uses `--bg-page`. The main `<body>` background.

### Text Colors
- `text-brand` : Primary accent color.
- `text-primary` : Standard high-contrast text (`--text-primary`).
- `text-secondary` : Muted text (`--text-secondary`).
- `text-tertiary` : Highly muted text (uses opacity).

### Borders
- `border-color` : Standard structural borders.
- `border-brand` : Brand-colored borders.

## 4. Universal Status Colors
Do not manually style status indicators. Use the global `.status-badge` combined with semantic modifiers:
- `.status-badge-available` (Green/Emerald)
- `.status-badge-parked` (Amber)
- `.status-badge-reserved` (Purple)
- `.status-badge-maintenance` (Blue)
- `.status-badge-departed` (Slate)
- `.status-badge-over` (Red)

## 5. UI Components
When creating a layout, wrap content inside the standardized bento layout using:
```html
<div class="bento-card p-4">
    <!-- Content goes here -->
</div>
```
This automatically applies the correct surface color, border, and dynamic hover shadow defined by the active tokens.
