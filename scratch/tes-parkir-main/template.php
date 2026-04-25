<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
require_once 'includes/functions.php';

// Default page metadata
$page_title = $page_title ?? 'Untitled Page';
$page_subtitle = $page_subtitle ?? '';

include 'includes/header.php';
?>

<!-- 
    MASTER TEMPLATE SKELETON
    Baseline for 60-30-10 Design System
-->
<div class="px-10 py-10 min-h-[calc(100vh-80px)] max-w-[1600px] mx-auto">
    
    <!-- Header Section (Optional) -->
    <?php if (!empty($page_subtitle)): ?>
    <div class="mb-8">
        <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight"><?= $page_title ?></h1>
        <p class="text-sm font-inter text-tertiary mt-1"><?= $page_subtitle ?></p>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="content-wrapper">
        <!-- 
            [PAGE CONTENT GOES HERE]
            Standard usage: Wrap main content in a .bento-card
        -->
    </div>

</div>

<?php include 'includes/footer.php'; ?>
