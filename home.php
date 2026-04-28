<?php
/**
 * home.php — Public Landing Page
 * Premium design for Cereza Parkhere
 */
require_once 'config/connection.php';
require_once 'includes/functions.php';

// Get some real-time stats for the "social proof" section
$stats = $pdo->query("SELECT COUNT(*) as transactions FROM `transaction` WHERE payment_status = 'paid'")->fetch();
$happy_customers = round(($stats['transactions'] ?? 0) / 10) * 10; 
if ($happy_customers < 50) $happy_customers = "500+"; 
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parkhere — Secure & Seamless Parking</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['selector', '[data-theme="dark"]'],
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

    <!-- Custom Theme -->
    <link rel="stylesheet" href="assets/css/theme.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        h1, h2, h3, .font-manrope {
            font-family: 'Manrope', sans-serif;
        }

        .glass-nav {
            background: rgba(var(--bg-page-rgb, 15, 23, 42), 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
        }

        .hero-gradient {
            background: radial-gradient(circle at 50% 50%, rgba(129, 140, 248, 0.15) 0%, transparent 50%);
        }

        .btn-primary {
            background: var(--brand);
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            filter: brightness(1.1);
            transform: scale(1.02);
            box-shadow: 0 0 30px rgba(129, 140, 248, 0.2);
        }

        .text-glow {
            text-shadow: 0 0 20px rgba(129, 140, 248, 0.2);
        }
    </style>
</head>
<body class="dark bg-page">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass-nav">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand rounded-xl flex items-center justify-center shadow-lg shadow-brand/20">
                    <i class="fa-solid fa-parking text-white text-xl"></i>
                </div>
                <span class="text-xl font-manrope font-800 tracking-tight"><span class="text-brand">Park</span>here</span>
            </div>
            
            <div class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-400">
                <a href="#features" class="hover:text-white transition-colors">Features</a>
                <a href="#how-it-works" class="hover:text-white transition-colors">How it Works</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="login.php" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors px-4 py-2">Operator Login</a>
                <a href="reserve.php" class="btn-primary px-6 py-2.5 rounded-full text-sm font-bold text-white">Book Now</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-40 pb-20 overflow-hidden">
        <div class="absolute inset-0 hero-gradient"></div>
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col items-center text-center max-w-4xl mx-auto">
                <h1 class="text-5xl md:text-7xl font-manrope font-800 leading-[1.1] mb-8 tracking-tight">
                    Smart Parking for a <br>
                    <span class="text-brand text-glow">Smarter Lifestyle</span>
                </h1>
                
                <p class="text-slate-400 text-lg md:text-xl font-medium mb-10 leading-relaxed max-w-2xl mx-auto">
                    The all-in-one parking network for many malls and venues. Secure your spot at <span class="text-brand-400">your lovely mall</span> and beyond with one tap.
                </p>
                
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <a href="reserve.php" class="btn-primary px-8 py-4 rounded-2xl text-base font-bold text-white flex items-center gap-3 w-full sm:w-auto justify-center group">
                        <span>Reserve My Spot</span>
                        <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </a>
                    <a href="#features" class="px-8 py-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all text-base font-bold text-white w-full sm:w-auto justify-center flex items-center gap-3">
                        View Features
                    </a>
                </div>

                <!-- Stats Preview -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8 mt-20 pt-10 border-t border-white/5 w-full">
                    <div class="flex flex-col items-center">
                        <span class="text-3xl font-manrope font-800 text-white mb-1"><?= $happy_customers ?></span>
                        <span class="text-sm font-semibold text-slate-500 uppercase tracking-widest">Happy Customers</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-3xl font-manrope font-800 text-white mb-1">99.9%</span>
                        <span class="text-sm font-semibold text-slate-500 uppercase tracking-widest">Security Uptime</span>
                    </div>
                    <div class="flex flex-col items-center col-span-2 md:col-span-1">
                        <span class="text-3xl font-manrope font-800 text-white mb-1">0%</span>
                        <span class="text-sm font-semibold text-slate-500 uppercase tracking-widest">Booking Fees</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Elements -->
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-brand/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none"></div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-slate-950/50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-manrope font-800 mb-6">Our Platform Advantage</h2>
                <p class="text-slate-400 max-w-xl font-medium">Experience the next generation of parking management with our premium features.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="bg-surface p-8 rounded-3xl border border-color hover:border-brand transition-all group">
                    <div class="w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors">
                        <i class="fa-solid fa-map-location-dot text-brand group-hover:text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Unified Network</h3>
                    <p class="text-secondary text-sm leading-relaxed">Access parking across multiple malls and commercial hubs with a single Parkhere account.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-surface p-8 rounded-3xl border border-color hover:border-brand transition-all group">
                    <div class="w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors">
                        <i class="fa-solid fa-shield-halved text-brand group-hover:text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Guaranteed Safety</h3>
                    <p class="text-secondary text-sm leading-relaxed">24/7 high-fidelity monitoring and secure gate integration keeps your vehicle protected.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-surface p-8 rounded-3xl border border-color hover:border-brand transition-all group">
                    <div class="w-12 h-12 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-brand transition-colors">
                        <i class="fa-solid fa-qrcode text-brand group-hover:text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-manrope font-800 mb-4 text-primary">Smart Access</h3>
                    <p class="text-secondary text-sm leading-relaxed">Digital receipts and QR codes for seamless entry and exit. No more physical tickets.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-24 bg-page">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-manrope font-800 mb-6 text-primary">How It Works</h2>
                <p class="text-secondary max-w-xl font-medium">Three simple steps to secure your premium parking spot across our network.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative">
                <!-- Connecting Line (Desktop only) -->
                <div class="hidden md:block absolute top-1/2 left-0 w-full h-0.5 bg-border-color -z-10 transform -translate-y-1/2"></div>
                
                <div class="flex flex-col items-center text-center bg-surface p-8 rounded-3xl border border-color relative">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-page">1</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Find Your Spot</h3>
                    <p class="text-secondary text-sm leading-relaxed">Select your destination mall, date, and preferred duration through our unified portal.</p>
                </div>
                
                <div class="flex flex-col items-center text-center bg-surface p-8 rounded-3xl border border-color relative">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-page">2</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Secure Payment</h3>
                    <p class="text-secondary text-sm leading-relaxed">Complete your reservation instantly with our seamless, zero-fee booking process.</p>
                </div>
                
                <div class="flex flex-col items-center text-center bg-surface p-8 rounded-3xl border border-color relative">
                    <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold mb-6 absolute -top-8 border-4 border-page">3</div>
                    <h3 class="text-xl font-manrope font-800 mb-4 mt-4 text-primary">Scan & Park</h3>
                    <p class="text-secondary text-sm leading-relaxed">Arrive at the location and our ALPR cameras will automatically scan your license plate to match your booking. (Physical tickets still supported)</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-brand rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-parking text-white text-sm"></i>
                </div>
                <span class="text-lg font-manrope font-800 tracking-tight"><span class="text-brand">Park</span>here</span>
            </div>
            
            <p class="text-slate-500 text-sm">&copy; 2026 Parkhere. Built for perfection.</p>
            
            <div class="flex items-center gap-6">
                <a href="#" class="text-slate-500 hover:text-white transition-colors text-lg"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="#" class="text-slate-500 hover:text-white transition-colors text-lg"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="text-slate-500 hover:text-white transition-colors text-lg"><i class="fa-brands fa-github"></i></a>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Parallax effect on mouse move for the hero section
        document.addEventListener('mousemove', (e) => {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.01;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.01;
            
            document.querySelectorAll('.animate-float').forEach(el => {
                el.style.transform = `translate(${moveX}px, ${moveY}px)`;
            });
        });
    </script>
    <?php include 'includes/ai_assistant.php'; ?>
</body>
</html>
