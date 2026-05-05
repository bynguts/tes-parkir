<?php
/**
 * explore_map.php — Interactive Map Explorer
 * Premium full-screen map experience similar to Google Maps, but styled for Parkhere.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/connection.php';
require_once 'includes/functions.php';

$locations = [
    'lippo_cikarang' => [
        'name' => 'Mall Lippo Cikarang',
        'address' => 'Kawasan Lippo Cikarang CBD, Jl. M.H. Thamrin, Cibatu, Cikarang Selatan, Kabupaten Bekasi, Jawa Barat 17550',
        'lat' => -6.334082,
        'lng' => 107.136951,
        'hours' => '10:00 AM - 10:00 PM (Valet Available)',
        'rating' => 4.7,
        'reviews' => 7155,
        'image' => 'assets/img/lippo_cikarang.jpg',
        'features' => ['VIP Parking', 'EV Charging', '24/7 Security', 'Valet']
    ],
    'grand_indonesia' => [
        'name' => 'Grand Indonesia',
        'address' => 'Jl. M.H. Thamrin No.1, Kb. Melati, Kec. Menteng, Kota Jakarta Pusat, DKI Jakarta 10310',
        'lat' => -6.1950,
        'lng' => 106.8198,
        'hours' => '10:00 AM - 10:00 PM',
        'rating' => 4.8,
        'reviews' => 15203,
        'image' => 'assets/img/grand_indonesia.jpg',
        'features' => ['Premium Spots', 'Valet', 'Car Wash']
    ]
];

$loc_id = $_GET['id'] ?? 'lippo_cikarang';
if(!isset($locations[$loc_id])) {
    $loc_id = 'lippo_cikarang';
}
$loc = $locations[$loc_id];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($loc['name']) ?> - Parkhere Map</title>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>

    <!-- Leaflet JS & CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-page); color: var(--primary); overflow: hidden; }
        .font-manrope { font-family: 'Manrope', sans-serif; }
        
        /* Custom Scrollbar for Sidebar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
        
        /* Map Controls override */
        .leaflet-control-zoom a {
            background-color: #1e1e2d !important;
            color: #f8fafc !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        .leaflet-control-zoom a:hover {
            background-color: #2a2a3c !important;
        }
    </style>
</head>
<body class="h-screen w-full flex flex-col md:flex-row relative">

    <!-- Top Left Back Button (Mobile) / Absolute Logo -->
    <div class="absolute top-4 left-4 z-50 md:hidden flex items-center gap-3">
        <a href="home.php" class="w-10 h-10 bg-surface/80 backdrop-blur rounded-full flex items-center justify-center border border-border-color shadow-lg text-primary">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
    </div>

    <!-- Sidebar Info Panel (Google Maps Style) -->
    <div class="w-full md:w-[420px] h-[50vh] md:h-full bg-surface/95 backdrop-blur-2xl border-r border-border-color flex flex-col z-40 shadow-2xl relative order-2 md:order-1 transition-all">
        <!-- Back to Home -->
        <div class="hidden md:flex p-4 items-center gap-4 border-b border-border-color">
            <a href="home.php" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-full flex items-center justify-center transition-colors">
                <i class="fa-solid fa-arrow-left text-primary"></i>
            </a>
            <div class="flex items-center gap-2">
                <img src="assets/images/logo.png" alt="Parkhere" class="w-8 h-8 object-contain">
                <span class="text-xl font-manrope font-800 tracking-tight text-primary"><span class="text-brand">Park</span>here</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <!-- Image Hero -->
            <div class="w-full h-48 md:h-64 relative">
                <img src="<?= htmlspecialchars($loc['image']) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($loc['name']) ?>">
                <div class="absolute inset-0 bg-gradient-to-t from-surface to-transparent"></div>
            </div>

            <!-- Content Details -->
            <div class="px-6 -mt-10 relative z-10">
                <h1 class="text-3xl font-manrope font-800 text-primary mb-2"><?= htmlspecialchars($loc['name']) ?></h1>
                
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-amber-400 font-bold"><?= $loc['rating'] ?></span>
                    <div class="flex text-amber-400 text-sm">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star-half-stroke"></i>
                    </div>
                    <span class="text-secondary text-sm">(<?= number_format($loc['reviews']) ?> reviews)</span>
                </div>

                <div class="flex gap-3 mb-8">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($loc['name'] . ' ' . $loc['address']) ?>" target="_blank" class="flex-1 py-2.5 bg-brand/10 hover:bg-brand/20 text-brand border border-brand/20 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors">
                        <i class="fa-solid fa-route text-lg"></i>
                        <span class="text-xs font-bold">Directions</span>
                    </a>
                    <button class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-primary border border-white/10 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors">
                        <i class="fa-regular fa-bookmark text-lg"></i>
                        <span class="text-xs font-bold">Save</span>
                    </button>
                    <button class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-primary border border-white/10 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors">
                        <i class="fa-solid fa-share-nodes text-lg"></i>
                        <span class="text-xs font-bold">Share</span>
                    </button>
                </div>

                <div class="space-y-5 border-t border-border-color pt-6">
                    <div class="flex items-start gap-4">
                        <i class="fa-solid fa-location-dot text-brand mt-1 w-5 text-center"></i>
                        <p class="text-secondary text-sm leading-relaxed"><?= htmlspecialchars($loc['address']) ?></p>
                    </div>
                    <div class="flex items-start gap-4">
                        <i class="fa-regular fa-clock text-brand mt-1 w-5 text-center"></i>
                        <p class="text-primary text-sm font-medium"><span class="text-green-400 font-bold">Open</span> · <?= htmlspecialchars($loc['hours']) ?></p>
                    </div>
                </div>


            </div>
        </div>

        <!-- Sticky Action Button -->
        <div class="p-6 bg-surface border-t border-border-color shrink-0">
            <a href="reserve.php?location=<?= urlencode($loc['name']) ?>" class="w-full py-4 bg-brand hover:bg-brand-hover text-white rounded-2xl font-bold flex items-center justify-center gap-2 transition-all shadow-[0_0_20px_rgba(99,102,241,0.3)]">
                <i class="fa-solid fa-ticket"></i>
                Reserve Parking Spot
            </a>
        </div>
    </div>

    <!-- Fullscreen Map -->
    <div id="map" class="w-full h-[50vh] md:h-full flex-1 z-0 order-1 md:order-2"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var lat = <?= $loc['lat'] ?>;
            var lng = <?= $loc['lng'] ?>;
            var map = L.map('map', {
                center: [lat, lng],
                zoom: 16,
                zoomControl: false
            });

            L.control.zoom({ position: 'topright' }).addTo(map);

            // Light Mode Tile Layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(map);

            // Custom Marker
            var customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: "<div class='w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-[0_0_30px_rgba(99,102,241,0.6)] border-4 border-surface animate-bounce'><img src='assets/images/logo.png' class='w-8 h-8 object-contain'></div>",
                iconSize: [56, 56],
                iconAnchor: [28, 56]
            });

            L.marker([lat, lng], {icon: customIcon}).addTo(map);
            
            // On desktop, offset the map center so the marker isn't hidden behind the sidebar
            if(window.innerWidth > 768) {
                // Approximate offset calculation (pan slightly left)
                map.panBy([-200, 0], {animate: false});
            }
        });
    </script>
</body>
</html>
