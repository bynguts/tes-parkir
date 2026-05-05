<?php
/**
 * home.php — Public Landing Page
 * Premium design for Cereza Parkhere
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/connection.php';
require_once 'includes/functions.php';
$current_page = 'home';
$customer_logged_in = !empty($_SESSION['customer_id']);

// Get some real-time stats for the "social proof" section
$stats = $pdo->query("SELECT COUNT(*) as transactions FROM `transaction` WHERE payment_status = 'paid'")->fetch();
$happy_customers = round(($stats['transactions'] ?? 0) / 10) * 10; 
if ($happy_customers < 50) $happy_customers = "500+"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parkhere — Secure & Seamless Parking</title>
    
    <!-- Icons (Keep here if specific version needed, or move to theme_init later) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>
    
    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />


    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--surface);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        h1, h2, h3, .font-manrope {
            font-family: 'Manrope', sans-serif;
        }

        .hero-gradient {
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.04) 0%, transparent 60%);
        }

        .btn-primary {
            background: var(--brand);
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            filter: brightness(1.1);
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
        }

        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee {
            animation: marquee 35s linear infinite;
        }

        /* Force feature icons to turn white on hover */
        .bento-card:hover .feature-icon-wrapper i {
            color: #ffffff !important;
        }

        /* Hero Word Carousel CSS-only */
        @keyframes slideWords {
            0%, 20% { transform: translateY(0); }
            25%, 45% { transform: translateY(-20%); }
            50%, 70% { transform: translateY(-40%); }
            75%, 95% { transform: translateY(-60%); }
            100% { transform: translateY(-80%); }
        }
        .word-slider {
            animation: slideWords 10s cubic-bezier(0.175, 0.885, 0.32, 1.275) infinite;
        }

        /* Aurora Background Effect */
        :root {
            --aurora-1: #4f83cc;
            --aurora-2: #5c8eb8;
            --aurora-3: #1a3a5c;
        }

        [data-theme="dark"] {
            --aurora-1: #4f83cc;
            --aurora-2: #5c8eb8;
            --aurora-3: #1a3a5c;
        }

        @keyframes aurora-float {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-5%, 5%) scale(1.1); }
            100% { transform: translate(5%, -5%) scale(1); }
        }

        #hero {
            position: relative;
            overflow: hidden;
            background-color: var(--surface);
        }

        .hero-mesh-gradient {
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 131, 204, 0.15) 0px, transparent 50%),
                radial-gradient(at 50% 0%, rgba(92, 142, 184, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(26, 58, 92, 0.1) 0px, transparent 50%);
            z-index: 0;
        }

        [data-theme="dark"] .hero-mesh-gradient {
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 131, 204, 0.2) 0px, transparent 50%),
                radial-gradient(at 50% 0%, rgba(92, 142, 184, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(26, 58, 92, 0.3) 0px, transparent 50%);
        }

        .hero-content-wrapper {
            position: relative;
            z-index: 10;
        }

        /* Fix contrast for text on top of aurora blobs */
        .hero-text-shadow {
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .hero-text-shadow {
            text-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
        }

        /* Premium Dot Grid (Stitch Interactive Spotlight) */
        .dot-grid {
            background-image: radial-gradient(var(--text-secondary) 1px, transparent 1px);
            background-size: 24px 24px;
            position: absolute;
            inset: 0;
            opacity: 0.5; /* Restored for strong mouse highlight */
            mask-image: radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), black 0%, transparent 100%);
            -webkit-mask-image: radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), black 0%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }



    </style>
</head>
<body class="bg-bg-page">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section id="hero" class="min-h-screen flex items-center pt-24 pb-20 relative">
        <div class="hero-mesh-gradient"></div>
        <div class="dot-grid"></div>


        <svg style="position:absolute;width:0;height:0;pointer-events:none;visibility:hidden;">
          <defs>
            <filter id="blob-filter">
              <feTurbulence type="turbulence" baseFrequency="0.008" numOctaves="3" 
                            seed="2" stitchTiles="stitch">
                <animate attributeName="baseFrequency" 
                         values="0.008;0.012;0.008" dur="20s" repeatCount="indefinite"/>
              </feTurbulence>
              <feDisplacementMap in="SourceGraphic" xChannelSelector="R" 
                                 yChannelSelector="G" scale="180"/>
            </filter>
          </defs>
        </svg>

        <div class="hero-content-wrapper max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center text-center max-w-4xl mx-auto">
                <h1 class="text-5xl md:text-7xl font-manrope font-800 leading-[1.1] mb-8 tracking-tight text-primary flex flex-col items-center hero-text-shadow">
                    <span>Smart Parking for a</span>
                    <div class="h-[1.3em] overflow-hidden text-brand relative w-full text-center mt-1">
                        <div class="absolute w-full flex flex-col items-center word-slider">
                            <span class="h-[1.3em] flex items-center justify-center">Smarter Lifestyle</span>
                            <span class="h-[1.3em] flex items-center justify-center">Faster Check-in</span>
                            <span class="h-[1.3em] flex items-center justify-center">Secure Vehicle</span>
                            <span class="h-[1.3em] flex items-center justify-center">Digital Entry</span>
                            <span class="h-[1.3em] flex items-center justify-center">Smarter Lifestyle</span>
                        </div>
                    </div>
                </h1>
                
                <p class="text-secondary text-lg md:text-xl font-medium mb-10 leading-relaxed max-w-2xl mx-auto hero-text-shadow">
                    Secure your parking spot at <span class="text-brand font-bold">top malls across the city</span> in seconds. Fast, secure, and completely digital entry for your convenience.
                </p>
                
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <a href="reserve.php" class="btn-primary px-8 py-4 rounded-2xl text-base font-bold text-white flex items-center gap-3 w-full sm:w-auto justify-center group shadow-xl shadow-brand/20">
                        <span>Reserve My Spot</span>
                        <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </a>
                    <a href="#features" class="px-8 py-4 rounded-2xl bg-surface border border-color hover:border-brand transition-all text-base font-bold text-primary w-full sm:w-auto justify-center flex items-center gap-3">
                        View Features
                    </a>
                </div>


            </div>
        </div>

        <!-- Floating Elements -->
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-brand/5 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-brand/5 rounded-full blur-[120px] pointer-events-none"></div>
    </section>

    <!-- Partner Malls Marquee Section -->
    <section class="py-16 border-t border-color bg-bg-page overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 mb-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <h2 class="text-2xl md:text-3xl font-manrope font-800 text-primary">Available Locations</h2>
            <div class="flex items-center gap-2 text-brand font-bold text-sm bg-brand/10 px-4 py-2 rounded-full border border-brand/20">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-brand"></span>
                </span>
                Expanding Rapidly Across Indonesia
            </div>
        </div>

        <?php
        $malls = [
            ['name' => 'Mall Lippo Cikarang', 'city' => 'Bekasi, Jawa Barat', 'status' => 'active', 'link' => 'explore_map.php?id=lippo_cikarang', 'icon' => 'fa-building', 'image' => 'assets/images/malls/lippo_cikarang.jpg'],
            ['name' => 'Grand Indonesia', 'city' => 'Jakarta Pusat', 'status' => 'active', 'link' => 'explore_map.php?id=grand_indonesia', 'icon' => 'fa-city', 'image' => 'assets/images/malls/grand_indonesia.jpg'],
            ['name' => 'Plaza Senayan', 'city' => 'Jakarta Pusat', 'status' => 'soon', 'link' => '#', 'icon' => 'fa-store', 'image' => 'assets/images/malls/plaza_senayan.jpg'],
            ['name' => 'Tunjungan Plaza', 'city' => 'Surabaya, Jatim', 'status' => 'soon', 'link' => '#', 'icon' => 'fa-shop', 'image' => 'assets/images/malls/tunjungan_plaza.jpg'],
            ['name' => 'Pakuwon Mall', 'city' => 'Surabaya, Jatim', 'status' => 'soon', 'link' => '#', 'icon' => 'fa-building-user', 'image' => 'assets/images/malls/pakuwon_mall.jpg'],
            ['name' => 'Sun Plaza', 'city' => 'Medan, Sumut', 'status' => 'soon', 'link' => '#', 'icon' => 'fa-hotel', 'image' => 'assets/images/malls/sun_plaza.jpg'],
            ['name' => 'Trans Studio Mall', 'city' => 'Bandung, Jabar', 'status' => 'soon', 'link' => '#', 'icon' => 'fa-tree-city', 'image' => 'assets/images/malls/tsm_bandung.jpg'],
        ];
        
        // Double the array to make the infinite loop seamless
        $marquee_items = array_merge($malls, $malls);
        ?>
        
        <div class="w-full relative group">
            <!-- Left and right gradient masks for smooth fade -->
            <div class="absolute left-0 top-0 bottom-0 w-12 md:w-32 bg-gradient-to-r from-bg-page to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-12 md:w-32 bg-gradient-to-l from-bg-page to-transparent z-10 pointer-events-none"></div>
            
            <div class="flex w-max animate-marquee gap-6 px-6 group-hover:[animation-play-state:paused]">
                <?php foreach($marquee_items as $mall): ?>
                    <?php 
                        $has_image = !empty($mall['image']);
                        $bg_style = $has_image ? "background-image: url('{$mall['image']}'); background-size: cover; background-position: center;" : "";
                    ?>
                    <?php if($mall['status'] === 'active'): ?>
                        <a href="<?= $mall['link'] ?>" 
                           class="flex-shrink-0 w-[280px] md:w-[320px] bg-surface border border-color rounded-3xl p-6 transition-all duration-300 hover:-translate-y-3 hover:border-brand hover:shadow-[0_20px_40px_-15px_rgba(99,102,241,0.3)] block cursor-pointer group/card relative overflow-hidden"
                           style="<?= $bg_style ?>">
                            
                            <?php if($has_image): ?>
                                <!-- Dark Overlay for readability -->
                                <div class="absolute inset-0 bg-black/40 group-hover/card:bg-black/20 transition-all"></div>
                            <?php endif; ?>

                            <div class="relative z-10">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="w-14 h-14 <?= $has_image ? 'bg-white/20 backdrop-blur-md' : 'bg-brand/10' ?> rounded-2xl flex items-center justify-center transition-colors group-hover/card:<?= $has_image ? 'bg-white/40' : 'bg-brand/20' ?>">
                                        <i class="fa-solid <?= $mall['icon'] ?> <?= $has_image ? 'text-white' : 'text-brand' ?> text-2xl"></i>
                                    </div>
                                    <span class="px-3 py-1.5 <?= $has_image ? 'bg-white/20 backdrop-blur-md text-white' : 'bg-brand/10 text-brand' ?> text-[10px] uppercase tracking-wider font-bold rounded-full border <?= $has_image ? 'border-white/30' : 'border-brand/20' ?>">Active</span>
                                </div>
                                <h3 class="text-xl md:text-2xl font-manrope font-800 <?= $has_image ? 'text-white' : 'text-primary' ?> mb-2"><?= $mall['name'] ?></h3>
                                <p class="<?= $has_image ? 'text-white/80' : 'text-secondary' ?> text-sm font-medium"><i class="fa-solid fa-location-dot mr-2 opacity-50"></i><?= $mall['city'] ?></p>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="flex-shrink-0 w-[280px] md:w-[320px] bg-surface/40 border border-color rounded-3xl p-6 transition-all duration-300 hover:-translate-y-3 hover:bg-surface/80 block grayscale hover:grayscale-0 opacity-60 hover:opacity-100 cursor-default relative overflow-hidden"
                             style="<?= $bg_style ?>">
                            
                            <?php if($has_image): ?>
                                <div class="absolute inset-0 bg-black/60"></div>
                            <?php endif; ?>

                            <div class="relative z-10">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="w-14 h-14 <?= $has_image ? 'bg-white/10 backdrop-blur-md' : 'bg-white/5' ?> rounded-2xl flex items-center justify-center">
                                        <i class="fa-solid <?= $mall['icon'] ?> <?= $has_image ? 'text-white/50' : 'text-secondary' ?> text-2xl"></i>
                                    </div>
                                    <span class="px-3 py-1.5 <?= $has_image ? 'bg-white/10 backdrop-blur-md text-white/70' : 'bg-white/5 text-secondary' ?> text-[10px] uppercase tracking-wider font-bold rounded-full border <?= $has_image ? 'border-white/20' : 'border-white/10' ?>">Coming Soon</span>
                                </div>
                                <h3 class="text-xl md:text-2xl font-manrope font-800 <?= $has_image ? 'text-white/70' : 'text-primary' ?> mb-2"><?= $mall['name'] ?></h3>
                                <p class="<?= $has_image ? 'text-white/50' : 'text-secondary' ?> text-sm font-medium"><i class="fa-solid fa-location-dot mr-2 opacity-50"></i><?= $mall['city'] ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-bg-page border-y border-color">
        <div class="max-w-7xl mx-auto px-6" data-aos="fade-up">
            <div class="flex flex-col items-center text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-manrope font-800 mb-6 text-primary">Our Platform Advantage</h2>
                <p class="text-secondary max-w-xl font-medium">Experience the next generation of parking management with our premium features.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-bolt text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Instant Booking</h3>
                    <p class="text-secondary text-sm leading-relaxed">Reserve your spot in under 60 seconds with our streamlined guest checkout process.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-shield-halved text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Guaranteed Safety</h3>
                    <p class="text-secondary text-sm leading-relaxed">24/7 high-fidelity monitoring and secure gate integration keeps your vehicle protected.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-qrcode text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Smart Access</h3>
                    <p class="text-secondary text-sm leading-relaxed">Digital receipts and QR codes for seamless entry and exit. No more physical tickets.</p>
                </div>

                <!-- Feature 4 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-satellite-dish text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Live Tracking</h3>
                    <p class="text-secondary text-sm leading-relaxed">View real-time availability of parking spots before you even arrive at the location.</p>
                </div>

                <!-- Feature 5 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-wallet text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Flexible Payments</h3>
                    <p class="text-secondary text-sm leading-relaxed">Multiple payment options including e-wallets, credit cards, and automated billing.</p>
                </div>

                <!-- Feature 6 -->
                <div class="bento-card p-8 group" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-icon-wrapper w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors text-brand">
                        <i class="fa-solid fa-headset text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">24/7 Support</h3>
                    <p class="text-secondary text-sm leading-relaxed">Round-the-clock dedicated customer assistance to ensure your parking experience is flawless.</p>
                </div>
            </div>
        </div>
    </section>


    <!-- How It Works Section -->
    <section id="how-it-works" class="py-24 bg-bg-page">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-manrope font-800 mb-6 text-primary">How It Works</h2>
                <p class="text-secondary max-w-xl font-medium">Three simple steps to secure your premium parking spot across our network.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative">
                <!-- Connecting Line (Desktop only) -->
                <div class="hidden md:block absolute top-1/2 left-0 w-full h-0.5 bg-border-color -z-10 transform -translate-y-1/2"></div>
                
                <div class="flex flex-col items-center text-center bento-card p-8 relative" data-aos="zoom-in-up" data-aos-delay="0">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-bg-page shadow-lg shadow-brand/20">1</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Find Your Spot</h3>
                    <p class="text-secondary text-sm leading-relaxed">Select your destination mall, date, and preferred duration through our unified portal.</p>
                </div>
                
                <div class="flex flex-col items-center text-center bento-card p-8 relative" data-aos="zoom-in-up" data-aos-delay="200">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-bg-page shadow-lg shadow-brand/20">2</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Secure Payment</h3>
                    <p class="text-secondary text-sm leading-relaxed">Complete your reservation instantly with our seamless, zero-fee booking process.</p>
                </div>
                
                <div class="flex flex-col items-center text-center bento-card p-8 relative" data-aos="zoom-in-up" data-aos-delay="400">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-bg-page shadow-lg shadow-brand/20">3</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Scan & Park</h3>
                    <p class="text-secondary text-sm leading-relaxed">Arrive at the location and our ALPR cameras will automatically scan your license plate to match your booking. (Physical tickets still supported)</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about-us" class="py-24 border-t border-color bg-bg-page relative overflow-hidden">
        <!-- Decorative Background Blurs -->
        <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand/10 blur-[120px]"></div>
            <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-brand/5 blur-[120px]"></div>
        </div>

        <div class="max-w-7xl mx-auto px-6" data-aos="fade-up">
            <!-- Header -->
            <div class="mb-16 text-center">

                <h2 class="mb-6 text-4xl font-manrope font-800 tracking-tight text-primary md:text-5xl">
                    Meet the people behind<br />
                    <span class="text-brand">our success</span>
                </h2>

                <p class="mx-auto max-w-2xl text-lg text-secondary font-medium">
                    A diverse team of talented individuals working together to build amazing products and deliver exceptional results.
                </p>
            </div>
            
            <!-- Team Grid -->
            <div class="grid gap-8 sm:grid-cols-2 max-w-5xl mx-auto">
                <!-- Team Card 1 -->
                <div class="group relative perspective-1000">
                    <div class="tilt-card relative overflow-hidden rounded-3xl border border-color bg-surface backdrop-blur-xl transition-all duration-300 ease-out shadow-lg hover:shadow-[0_20px_40px_-15px_rgba(0,0,0,0.3)]">
                        
                        <!-- Animated gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-br from-white/5 via-white/0 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100 pointer-events-none"></div>

                        <!-- Sparkle effect on hover -->
                        <div class="absolute right-6 top-6 z-10 opacity-0 scale-50 transition-all duration-500 group-hover:opacity-100 group-hover:scale-100">
                            <i class="fa-solid fa-star text-brand text-xl"></i>
                        </div>

                        <div class="relative z-10 p-8">
                            <!-- Avatar Section -->
                            <div class="mb-6 flex justify-center">
                                <div class="relative group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute -inset-2 rounded-full opacity-0 blur-xl transition-opacity duration-500 group-hover:opacity-100 bg-gradient-to-br from-brand/40 to-transparent"></div>
                                    <div class="relative h-28 w-28 overflow-hidden rounded-full border border-color bg-bg-page p-1">
                                        <img src="assets/images/team/tyo.png" alt="Tyo Naufal Asyarif" class="pointer-events-none h-full w-full rounded-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                    </div>
                                </div>
                            </div>

                            <!-- Info Section -->
                            <div class="text-center">
                                <h3 class="mb-1 text-2xl font-manrope font-800 tracking-tight text-primary transition-transform duration-300 group-hover:scale-105">
                                    Tyo Naufal Asyarif
                                </h3>
                                
                                <div class="mb-3">
                                    <span class="inline-block rounded-full bg-bg-page border border-color px-3 py-1 text-[10px] font-bold uppercase tracking-[0.25em] text-brand">
                                        Developer
                                    </span>
                                </div>

                                <div class="mb-4 flex items-center justify-center gap-2 text-xs font-medium text-secondary opacity-70 group-hover:opacity-100 transition-opacity">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span>Cikarang, Bekasi</span>
                                </div>

                                <p class="mb-6 text-sm text-secondary leading-relaxed">
                                    Passionate about creating intuitive user interfaces and crafting seamless digital experiences that delight every user.
                                </p>

                                <!-- Skills -->
                                <div class="mb-8 flex flex-wrap justify-center gap-2 translate-y-2 opacity-80 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">UI/UX</span>
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">Branding</span>
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">Motion</span>
                                </div>

                                <!-- Social Links -->
                                <div class="flex justify-center gap-3 translate-y-4 opacity-0 transition-all duration-500 delay-100 group-hover:translate-y-0 group-hover:opacity-100">
                                    <a href="https://www.instagram.com/nevermore.84/" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-instagram text-lg"></i>
                                    </a>
                                    <a href="https://www.linkedin.com/in/tyonaufal/" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-linkedin text-lg"></i>
                                    </a>
                                    <a href="https://github.com/Flitz6" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-github text-lg"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Card 2 -->
                <div class="group relative perspective-1000">
                    <div class="tilt-card relative overflow-hidden rounded-3xl border border-color bg-surface backdrop-blur-xl transition-all duration-300 ease-out shadow-lg hover:shadow-[0_20px_40px_-15px_rgba(0,0,0,0.3)]">
                        
                        <div class="absolute inset-0 bg-gradient-to-br from-white/5 via-white/0 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100 pointer-events-none"></div>

                        <div class="absolute right-6 top-6 z-10 opacity-0 scale-50 transition-all duration-500 group-hover:opacity-100 group-hover:scale-100">
                            <i class="fa-solid fa-star text-brand text-xl"></i>
                        </div>

                        <div class="relative z-10 p-8">
                            <!-- Avatar Section -->
                            <div class="mb-6 flex justify-center">
                                <div class="relative group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute -inset-2 rounded-full opacity-0 blur-xl transition-opacity duration-500 group-hover:opacity-100 bg-gradient-to-br from-brand/40 to-transparent"></div>
                                    <div class="relative h-28 w-28 overflow-hidden rounded-full border border-color bg-bg-page p-1">
                                        <img src="assets/images/team/muqorobin.jpg" alt="Muhammad Muqorrobin Al Anshori" class="pointer-events-none h-full w-full rounded-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                    </div>
                                </div>
                            </div>

                            <!-- Info Section -->
                            <div class="text-center">
                                <h3 class="mb-1 text-2xl font-manrope font-800 tracking-tight text-primary transition-transform duration-300 group-hover:scale-105">
                                    Muhammad Muqorrobin Al Anshori
                                </h3>
                                
                                <div class="mb-3">
                                    <span class="inline-block rounded-full bg-bg-page border border-color px-3 py-1 text-[10px] font-bold uppercase tracking-[0.25em] text-brand">
                                        Developer
                                    </span>
                                </div>

                                <div class="mb-4 flex items-center justify-center gap-2 text-xs font-medium text-secondary opacity-70 group-hover:opacity-100 transition-opacity">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span>Cikarang, Bekasi</span>
                                </div>

                                <p class="mb-6 text-sm text-secondary leading-relaxed">
                                    Expert in building robust server-side logic and architecting scalable databases for high-performance applications.
                                </p>

                                <!-- Skills -->
                                <div class="mb-8 flex flex-wrap justify-center gap-2 translate-y-2 opacity-80 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">Backend</span>
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">Architecture</span>
                                    <span class="rounded-full border border-color bg-bg-page px-3 py-1 text-xs text-secondary transition-colors hover:bg-surface hover:text-primary">Database</span>
                                </div>

                                <!-- Social Links -->
                                <div class="flex justify-center gap-3 translate-y-4 opacity-0 transition-all duration-500 delay-100 group-hover:translate-y-0 group-hover:opacity-100">
                                    <a href="https://www.instagram.com/bibinanshori?igsh=MTBnZmJyZDAzNTNlYw==" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-instagram text-lg"></i>
                                    </a>
                                    <a href="https://www.linkedin.com/in/anshorimuqorrobin?utm_source=share_via&utm_content=profile&utm_medium=member_android" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-linkedin text-lg"></i>
                                    </a>
                                    <a href="https://github.com/bynguts" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-full border border-color bg-bg-page text-secondary transition-all hover:bg-brand hover:text-white hover:border-brand hover:scale-110">
                                        <i class="fa-brands fa-github text-lg"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Us / Partner with Us Section -->
    <section id="contact-us" class="py-24 relative overflow-hidden bg-surface border-y border-color">
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="mb-16 text-center max-w-2xl mx-auto" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-manrope font-800 tracking-tight text-primary mb-6">Contact Us</h2>
                <p class="text-secondary text-lg">Interested in integrating Parkhere or need assistance? We're here to help.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <!-- Left Side: Get In Touch -->
                <div class="flex flex-col gap-6" data-aos="fade-right">
                    <div class="rounded-3xl border border-color bg-bg-page p-8 h-full flex flex-col relative overflow-hidden group">
                        <div class="absolute inset-0 bg-gradient-to-br from-brand/5 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100 pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="mb-8 text-2xl font-bold text-primary border-b border-color pb-4 text-center lg:text-left">Get In Touch With Us Now!</h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-10 flex-1">
                                <!-- Phone -->
                                <div class="flex flex-col items-center lg:items-start text-center lg:text-left group/item">
                                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface border border-color text-brand transition-all duration-300 group-hover/item:scale-110 group-hover/item:border-brand group-hover/item:shadow-lg">
                                        <i class="fa-solid fa-phone text-xl"></i>
                                    </div>
                                    <h4 class="mb-2 text-lg font-bold text-primary">Phone Number</h4>
                                    <p class="text-secondary text-sm">+62 812-3456-7890</p>
                                </div>
                                
                                <!-- Email -->
                                <div class="flex flex-col items-center lg:items-start text-center lg:text-left group/item">
                                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface border border-color text-brand transition-all duration-300 group-hover/item:scale-110 group-hover/item:border-brand group-hover/item:shadow-lg">
                                        <i class="fa-solid fa-envelope text-xl"></i>
                                    </div>
                                    <h4 class="mb-2 text-lg font-bold text-primary">Email</h4>
                                    <p class="text-secondary text-sm leading-relaxed">info@parkhere.com<br>sales@parkhere.com</p>
                                </div>

                                <!-- Location -->
                                <div class="flex flex-col items-center lg:items-start text-center lg:text-left group/item">
                                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface border border-color text-brand transition-all duration-300 group-hover/item:scale-110 group-hover/item:border-brand group-hover/item:shadow-lg">
                                        <i class="fa-solid fa-location-dot text-xl"></i>
                                    </div>
                                    <h4 class="mb-2 text-lg font-bold text-primary">Location</h4>
                                    <p class="text-secondary text-sm leading-relaxed">SCBD District<br>Jakarta Selatan</p>
                                </div>

                                <!-- Working Hours -->
                                <div class="flex flex-col items-center lg:items-start text-center lg:text-left group/item">
                                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface border border-color text-brand transition-all duration-300 group-hover/item:scale-110 group-hover/item:border-brand group-hover/item:shadow-lg">
                                        <i class="fa-solid fa-clock text-xl"></i>
                                    </div>
                                    <h4 class="mb-2 text-lg font-bold text-primary">Working Hours</h4>
                                    <p class="text-secondary text-sm leading-relaxed">Monday To Saturday<br>09:00 AM To 06:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Contact Form -->
                <div class="rounded-3xl border border-color bg-bg-page p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] relative overflow-hidden" data-aos="fade-left">
                    <div class="absolute inset-0 bg-gradient-to-br from-brand/5 via-transparent to-transparent pointer-events-none"></div>
                    <div class="relative z-10">
                        <h3 class="mb-8 text-2xl font-bold text-primary border-b border-color pb-4 text-center lg:text-left">Send a Message</h3>
                        
                        <form id="contactForm" class="flex flex-col gap-5">
                            <div>
                                <label class="block text-[11px] font-bold text-secondary mb-2 uppercase tracking-wider">Full Name <span class="text-brand">*</span></label>
                                <div class="relative">
                                    <i class="fa-regular fa-user absolute left-4 top-1/2 -translate-y-1/2 text-secondary"></i>
                                    <input type="text" id="contact_name" name="name" required class="w-full h-12 pl-11 pr-4 bg-surface border border-color rounded-xl text-sm text-primary placeholder-secondary/50 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-colors" placeholder="Enter your full name">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-secondary mb-2 uppercase tracking-wider">Email Address <span class="text-brand">*</span></label>
                                <div class="relative">
                                    <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-secondary"></i>
                                    <input type="email" id="contact_email" name="email" required class="w-full h-12 pl-11 pr-4 bg-surface border border-color rounded-xl text-sm text-primary placeholder-secondary/50 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-colors" placeholder="yourname@email.com">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-secondary mb-2 uppercase tracking-wider">Message</label>
                                <textarea id="contact_message" name="message" rows="5" required class="w-full p-4 bg-surface border border-color rounded-xl text-sm text-primary placeholder-secondary/50 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-colors resize-none" placeholder="How can we help you?"></textarea>
                            </div>
                            
                            <button type="submit" id="submitBtn" class="w-full lg:w-auto lg:self-end mt-2 h-12 px-8 bg-brand text-white rounded-xl font-bold text-sm hover:brightness-110 active:scale-95 transition-all shadow-lg shadow-brand/20 flex items-center justify-center gap-2">
                                <span id="btnText">Submit Message</span>
                                <i class="fa-solid fa-paper-plane" id="btnIcon"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vanilla JS for 3D Tilt Effect (Framer Motion alternative) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.tilt-card');
            
            cards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    // Tilt logic (max 5 degrees)
                    const rotateX = ((y - centerY) / centerY) * -5;
                    const rotateY = ((x - centerX) / centerX) * 5;
                    
                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`;
                });
            });
        });
    </script>

    <!-- Footer -->
    <footer id="footer" class="py-12 border-t border-color bg-bg-page">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="Parkhere" class="w-8 h-8 object-contain">
                <span class="text-lg font-manrope font-800 tracking-tight text-primary"><span class="text-brand">Park</span>here</span>
            </div>
            
            <p class="text-secondary text-sm">&copy; 2026 Parkhere. Built for perfection. &nbsp;<a href="login.php" class="opacity-30 hover:opacity-100 transition-opacity text-xs text-brand font-bold">Staff Portal</a></p>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Parallax and Spotlight effect on mouse move for the hero section
        const heroSection = document.getElementById('hero');
        document.addEventListener('mousemove', (e) => {
            // Update Spotlight Coordinates
            if (heroSection) {
                const rect = heroSection.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                heroSection.style.setProperty('--mouse-x', `${x}px`);
                heroSection.style.setProperty('--mouse-y', `${y}px`);
            }

            // Parallax
            const moveX = (e.clientX - window.innerWidth / 2) * 0.01;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.01;
            
            document.querySelectorAll('.animate-float').forEach(el => {
                el.style.transform = `translate(${moveX}px, ${moveY}px)`;
            });
        });




        // Contact Form Handling
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const submitBtn = document.getElementById('submitBtn');
                const btnText = document.getElementById('btnText');
                const btnIcon = document.getElementById('btnIcon');
                
                // Loading State
                const originalText = btnText.innerText;
                submitBtn.disabled = true;
                btnText.innerText = 'Sending...';
                btnIcon.className = 'fa-solid fa-circle-notch fa-spin';
                
                const formData = new FormData(contactForm);
                
                try {
                    const response = await fetch('api/submit_contact.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(result.success);
                        contactForm.reset();
                    } else {
                        alert(result.error || 'Something went wrong.');
                    }
                } catch (error) {
                    alert('Failed to connect to the server. Please try again.');
                } finally {
                    // Reset State
                    submitBtn.disabled = false;
                    btnText.innerText = originalText;
                    btnIcon.className = 'fa-solid fa-paper-plane';
                }
            });
        }
    </script>

</body>
</html>
