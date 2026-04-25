<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$summary = get_slot_summary($pdo);

$page_title = 'Smart Gate Simulator';
$page_subtitle = 'Physical sensor simulation for entry/exit gates.';

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/theme.css">

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
/* Barcode scanner viewport */
#qrReader, #qrReader video, #qrReader__scan_region, #qrReader__canvas_border, #cameraReader, #cameraReader video, div[id*="html5-qrcode"] {
    border-radius: 1.5rem !important;
}
#qrReader {
    width: 100% !important;
    height: 100% !important;
    max-width: 100%;
    overflow: hidden;
    background: var(--surface) !important;
    margin: 0;
    position: relative;
    border: 2px solid var(--border-color) !important;
    box-shadow: 0 15px 35px -5px var(--shadow-color) !important;
    box-sizing: border-box;
    border-radius: 1.5rem !important;
}
#qrReader video {
    border-radius: 1.5rem !important;
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}
/* Barcode guide overlay */
.barcode-guide {
    position: absolute;
    inset: 16px;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none; z-index: 60;
}
/* Simplified gate ticket buttons to use theme standards */
.gate-ticket-btn {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.gate-ticket-btn:hover {
    transform: translateY(-4px);
}
.action-switch-outline.brand {
    background-color: var(--surface-alt) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: 0 4px 15px -2px var(--shadow-color) !important;
    border-radius: 1.25rem !important;
}
.action-switch-outline.brand i {
    color: var(--brand) !important;
    transition: transform 0.3s ease;
}
.action-switch-outline.brand:hover {
    background-color: var(--brand) !important;
    border-color: var(--brand) !important;
    color: #ffffff !important;
    transform: translateY(-2px);
}
.action-switch-outline.brand:hover i {
    color: #ffffff !important;
    transform: scale(1.1);
}
.action-switch-input {
    height: 52px;
    padding: 0 20px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    line-height: 1;
    background-color: var(--surface-alt) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: 0 4px 15px -2px var(--shadow-color) !important;
    border-radius: 1.25rem !important;
}
.action-switch-input::placeholder {
    color: var(--text-tertiary) !important;
    opacity: 0.5;
    font-size: 12px;
    letter-spacing: 0.1em;
}
.action-switch-input:focus {
    border-color: var(--brand) !important;
    background-color: var(--surface) !important;
}
.vip-simulate-btn {
    height: 52px;
    padding: 0 28px;
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 700;
    text-transform: none;
    letter-spacing: 0.05em;
    background: var(--brand) !important;
    border: none !important;
    color: white !important;
    border-radius: 1.25rem !important;
    box-shadow: 0 8px 20px -6px var(--brand) !important;
    transition: all 0.3s ease;
}
.vip-simulate-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -8px var(--brand) !important;
    filter: brightness(1.1);
}
.vip-simulate-btn:active {
    transform: scale(0.95);
}
.ocr-scan-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--brand-subtle);
    color: var(--brand);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}
.ocr-scan-btn:hover {
    background: var(--brand);
    color: white;
}
.barcode-frame {
    width: 100%; height: 100%;
    border: none;
    background: transparent;
    border-radius: 0 !important;
    position: relative;
    backdrop-filter: none;
    overflow: hidden;
}
.scanner-sweep {
    position: absolute;
    left: 6px;
    right: 6px;
    height: 3px;
    background: linear-gradient(90deg, transparent 0%, rgba(56,189,248,0.85) 20%, rgba(59,130,246,0.95) 50%, rgba(56,189,248,0.85) 80%, transparent 100%);
    box-shadow: 0 0 10px rgba(56,189,248,0.7), 0 0 18px rgba(59,130,246,0.45);
    animation: scanner-sweep 4.2s ease-in-out infinite;
    z-index: 1;
}
.scanner-corner {
    position: absolute;
    width: 34px;
    height: 34px;
    border-color: rgba(255,255,255,0.95);
    border-style: solid;
    border-width: 0;
}
.scanner-corner.tl {
    top: 0;
    left: 0;
    border-top-width: 3px;
    border-left-width: 3px;
}
.scanner-corner.tr {
    top: 0;
    right: 0;
    border-top-width: 3px;
    border-right-width: 3px;
}
.scanner-corner.bl {
    bottom: 0;
    left: 0;
    border-bottom-width: 3px;
    border-left-width: 3px;
}
.scanner-corner.br {
    bottom: 0;
    right: 0;
    border-bottom-width: 3px;
    border-right-width: 3px;
}
@keyframes scanner-sweep {
    0%, 100% {
        top: 10px;
        opacity: 0.45;
    }
    50% {
        top: calc(100% - 12px);
        opacity: 1;
    }
}
#qrReader__scan_region {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}
#qrReader * {
    outline: none !important;
    box-shadow: none !important;
}
#qrReader__scan_region > div,
#qrReader__scan_region div,
.qr-shaded-region {
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
}
#qrReader__scan_region > img,
#qrReader__scan_region img {
    display: none !important;
}
#qrReader select { display: none !important; }
#qrReader span { display: none !important; }
#qrReader img { display: none !important; }
#qrReader__header_message { display: none !important; }
#html5-qrcode-button-camera-start,
#html5-qrcode-button-camera-stop {
    background: var(--brand) !important; 
    color: white !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 1rem !important;
    margin: 16px auto 0 !important;
    display: block !important;
    font-family: 'Manrope', sans-serif !important; 
    font-size: 13px !important;
    font-weight: 700 !important;
    letter-spacing: 0.02em !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 8px 20px -6px var(--brand) !important;
}
#html5-qrcode-button-camera-start:hover,
#html5-qrcode-button-camera-stop:hover {
    background: var(--hover-border) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 12px 25px -8px var(--brand) !important;
}
#html5-qrcode-button-camera-start:active,
#html5-qrcode-button-camera-stop:active {
    transform: scale(0.98) !important;
}

/* Camera Modal */
#cameraModal {
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, var(--bg-page) 88%, transparent);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(10px);
}

.camera-container {
    width: min(780px, calc(100vw - 2rem));
    max-height: calc(100vh - 2rem);
    background: var(--surface);
    border-radius: 1.5rem;
    overflow: hidden;
    border: 1px solid var(--border-color);
    position: relative;
    box-shadow: 0 20px 50px -20px var(--shadow-color);
}

/* Ticket Display Modal */
#ticketModal {
    position: fixed !important;
    inset: 0;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    width: 100vw;
    min-width: 100vw;
    width: 100dvw;
    min-height: 100vh;
    height: 100dvh;
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: color-mix(in srgb, var(--bg-page) 70%, transparent);
    backdrop-filter: blur(8px);
}

.ticket-container {
    width: min(360px, calc(100vw - 2rem));
    max-height: calc(100vh - 2rem);
    overflow: auto;
    background: var(--surface);
    border-radius: 1.5rem;
    position: relative;
    box-shadow: 0 30px 80px -30px var(--shadow-color);
    border: 1px solid var(--border-color);
    animation: ticketSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes ticketSlideIn {
    from { transform: translateX(50px) scale(0.9); opacity: 0; }
    to { transform: translateX(0) scale(1); opacity: 1; }
}

.ticket-content {
    padding: 22px 16px;
    color: var(--text-primary);
    font-family: 'Courier Prime', monospace;
    text-align: center;
}

.scanner-viewport {
    height: clamp(220px, 38vh, 420px);
}

#cameraModal .scanner-viewport {
    background: var(--surface-alt) !important;
}

#cameraModal .scanner-viewport #videoFeed {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scanner-viewport {
    width: 100%;
    aspect-ratio: 16/10;
    background: var(--surface-alt);
    position: relative;
}

#videoFeed {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.camera-overlay {
    position: absolute;
    inset: 0;
    border: 2px solid rgba(255, 255, 255, 0.2);
    margin: 40px;
    pointer-events: none;
}

.camera-overlay::before, .camera-overlay::after {
    content: '';
    position: absolute;
    width: 40px;
    height: 40px;
    border-color: var(--brand);
    border-style: solid;
}

.camera-overlay::before { top: -2px; left: -2px; border-width: 4px 0 0 4px; }
.camera-overlay::after { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; }

.scan-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--brand);
    box-shadow: 0 0 20px var(--brand);
    animation: scanMove 2s infinite linear;
}

@keyframes scanMove {
    from { top: 0; }
    to { top: 100%; }
}

/* Push Notification Animations */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
@keyframes progressShrink {
    from { width: 100%; }
    to { width: 0%; }
}

.animate-slide-in { animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-out { animation: slideOut 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

.notification-progress {
    animation: progressShrink 5s linear forwards;
}
</style>

<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
        
        <!-- Slot Availability -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            $types = [
                'car'        => ['car',        'Car'],
                'motorcycle' => ['motorcycle', 'Motorcycle'],
            ];
            foreach ($types as $t => $cfg):
                $icon  = $cfg[0];
                $label = $cfg[1];
                $avail = $summary[$t]['avail'] ?? 0;
                $total = $summary[$t]['total'] ?? 0;
                $pct   = $total > 0 ? round(($avail / $total) * 100) : 100;
            ?>
            <div class="bento-card p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-<?= $icon ?> text-lg"></i>
                </div>
                <div class="flex flex-col min-w-0 flex-1">
                    <div class="flex items-end gap-2 mb-1">
                        <span class="text-3xl font-manrope font-bold text-primary leading-none"><?= $avail ?></span>
                        <span class="text-xs font-inter text-tertiary pb-0.5">/ <?= $total ?> Total</span>
                    </div>
                    <span class="text-[13px] font-inter text-tertiary truncate"><?= $label ?> Slots Available</span>
                    <div class="mt-3">
                        <div class="w-full h-2 progress-track rounded-full overflow-hidden">
                            <div class="h-full progress-fill rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <!-- Push Notification Container -->
    <div id="push-notification-container" class="fixed top-[90px] right-10 z-[3000] flex flex-col gap-3 w-[380px] pointer-events-none"></div>
    
    <!-- Ticket Display Modal (Compact Receipt) -->
    <div id="ticketModal" style="display: none;">
        <div class="ticket-container">
            <div class="ticket-content" id="ticketContent">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>
    
    <!-- Gate Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">

        <!-- ENTRY GATE -->
        <div class="bento-card p-0 flex flex-col min-h-[500px] relative overflow-hidden group">

            
            <!-- Card Header -->
            <div class="p-4 border-b border-color shrink-0 flex items-center justify-between bg-surface-alt/30 backdrop-blur-sm z-10">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 border border-brand/20 flex items-center justify-center shrink-0 shadow-lg shadow-brand/5">
                        <i class="fa-solid fa-right-to-bracket text-brand text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-manrope font-extrabold text-primary leading-tight tracking-tight">Entry Terminal</h3>
                    </div>
                </div>
                <div class="status-badge status-badge-available">
                    <span class="status-dot status-dot-available"></span>
                    Online
                </div>
            </div>

            <div class="flex-grow flex flex-col divide-y divide-color/50 z-10">
                <!-- SECTION 01: REGULAR KIOSK -->
                <div class="flex-1 p-10 flex flex-col justify-center relative">

                    
                    <div class="flex flex-col items-center mb-8 text-center">
                        <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center mb-4">
                            <i class="fa-solid fa-qrcode text-xl"></i>
                        </div>
                        <h2 class="text-lg font-manrope font-extrabold text-primary tracking-tight">Regular Entry</h2>
                        <p class="text-[11px] text-tertiary font-medium">Generate guest parking ticket</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 max-w-[420px] mx-auto w-full">
                        <button class="gate-ticket-btn bento-card group/btn flex flex-col items-center justify-center gap-4 p-6"
                                onclick="cetakTiketOtomatis('car', this)">
                            <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center group-hover/btn:bg-brand group-hover/btn:text-white transition-all">
                                <i class="fa-solid fa-car text-2xl"></i>
                            </div>
                            <div class="text-center">
                                <span class="block text-sm font-manrope font-bold text-primary leading-tight">Car Ticket</span>
                                <span class="block text-[10px] font-inter text-tertiary uppercase tracking-widest mt-0.5">Issue Receipt</span>
                            </div>
                        </button>
                        <button class="gate-ticket-btn bento-card group/btn flex flex-col items-center justify-center gap-4 p-6"
                                onclick="cetakTiketOtomatis('motorcycle', this)">
                            <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center group-hover/btn:bg-brand group-hover/btn:text-white transition-all">
                                <i class="fa-solid fa-motorcycle text-2xl"></i>
                            </div>
                            <div class="text-center">
                                <span class="block text-sm font-manrope font-bold text-primary leading-tight">Motor Ticket</span>
                                <span class="block text-[10px] font-inter text-tertiary uppercase tracking-widest mt-0.5">Issue Receipt</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- SECTION 02: VIP/RESERVATION -->
                <div class="flex-1 p-10 flex flex-col justify-center bg-surface relative overflow-hidden">

                    
                    <div class="flex items-center gap-5 mb-8">
                        <div class="w-14 h-14 rounded-2xl icon-container flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-shield-halved text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-manrope font-extrabold text-primary tracking-tight">VIP & Seamless</h2>
                            <p class="text-[11px] text-tertiary font-medium">Automatic plate recognition</p>
                        </div>
                    </div>
                    
                    <div class="w-full max-w-[420px] mx-auto">
                        <div class="relative group/input">
                            <div class="absolute inset-0 bg-indigo-500/5 blur-xl rounded-full opacity-0 group-focus-within/input:opacity-100 transition-opacity pointer-events-none"></div>
                            <div class="relative flex gap-3 p-2 bg-surface-alt/40 border border-color rounded-[2rem] focus-within:border-brand/30 transition-all shadow-inner">
                                <input type="text" id="vipPlate" 
                                       class="bg-transparent flex-1 px-4 text-center text-[15px] font-manrope font-bold text-primary placeholder:text-secondary uppercase tracking-[0.2em] focus:outline-none"
                                       placeholder="B 1234 XYZ"
                                       autocomplete="off">
                                <button onclick="openCamera('entry')" 
                                        class="w-10 h-10 rounded-xl bg-brand text-white flex items-center justify-center transition-all hover:scale-105 active:scale-95 shadow-md">
                                    <i class="fa-solid fa-camera text-sm"></i>
                                </button>
                                <button onclick="simulateVIPEntry()" 
                                        class="px-6 h-11 rounded-full bg-brand text-white font-bold text-[13px] hover:brightness-110 active:scale-95 transition-all shadow-md">
                                    Process
                                </button>
                            </div>
                        </div>
                        
                        <!-- Status Area -->
                        <div id="alprStatus" class="mt-5 flex items-center justify-center h-5">
                            <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-surface-alt/30 border border-color/50 shadow-sm">
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                                </span>
                                <span class="text-[10px] font-bold text-tertiary tracking-wide">Awaiting detection...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EXIT GATE -->
        <div class="bento-card p-0 flex flex-col min-h-[500px] relative overflow-hidden group">


            <!-- Card Header -->
            <div class="p-4 border-b border-color shrink-0 flex items-center justify-between bg-surface-alt/30 backdrop-blur-sm z-10">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0 shadow-lg shadow-rose-500/5">
                        <i class="fa-solid fa-right-from-bracket text-rose-500 text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-manrope font-extrabold text-primary leading-tight tracking-tight">Exit Terminal</h3>
                    </div>
                </div>
                <div class="status-badge status-badge-available">
                    <span class="status-dot status-dot-available"></span>
                    Online
                </div>
            </div>

            <div class="flex-grow flex flex-col p-10 justify-center bg-surface-alt/5 z-10 relative">


                <!-- Scanner Container -->
                <div class="flex flex-col items-center mb-10">
                    <div class="relative w-full max-w-[240px] aspect-square group/scanner">
                        <!-- Holographic Frame -->
                        <div class="absolute inset-0 border-2 border-rose-500/20 rounded-[2.5rem] shadow-2xl transition-all group-hover/scanner:border-rose-500/40"></div>
                        <div class="absolute -inset-2 border border-rose-500/10 rounded-[3rem] opacity-50"></div>
                        
                        <div class="relative w-full h-full rounded-[2.2rem] overflow-hidden bg-slate-900 shadow-inner">
                            <div id="qrReader" class="w-full h-full opacity-80 group-hover/scanner:opacity-100 transition-opacity"></div>
                            
                            <!-- Enhanced Scanner UI -->
                            <div class="barcode-guide !bg-transparent">
                                <div class="barcode-frame !border-none">
                                    <span class="scanner-sweep !bg-gradient-to-b !from-transparent !via-rose-500 !to-transparent !h-1 !opacity-40"></span>
                                    
                                    <!-- L-Corners -->
                                    <span class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-rose-500 rounded-tl-2xl"></span>
                                    <span class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-rose-500 rounded-tr-2xl"></span>
                                    <span class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-rose-500 rounded-bl-2xl"></span>
                                    <span class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-rose-500 rounded-br-2xl"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="gateScanResult" class="mt-6 h-8 flex items-center justify-center">
                         <div class="flex items-center gap-3 px-5 py-2 rounded-2xl bg-rose-500/5 border border-rose-500/10">
                            <i class="fa-solid fa-qrcode text-rose-500 text-sm animate-pulse"></i>
                            <span class="text-[11px] font-bold text-tertiary tracking-wide">Scanning for tickets...</span>
                        </div>
                    </div>
                </div>

                <!-- Action Area -->
                <div class="w-full max-w-[420px] mx-auto space-y-4">
                    <!-- Manual Input -->
                    <div class="relative group/manual">
                        <div class="flex gap-3 p-2 bg-surface-alt/40 border border-color rounded-[2rem] focus-within:border-rose-500/30 transition-all shadow-inner">
                            <div class="w-11 h-11 rounded-full bg-surface-alt flex items-center justify-center text-slate-400">
                                <i class="fa-solid fa-keyboard text-sm"></i>
                            </div>
                            <input type="text" id="manualCode"
                                   class="bg-transparent flex-1 px-2 text-center text-[15px] font-manrope font-bold text-primary placeholder:text-secondary uppercase tracking-[0.2em] focus:outline-none"
                                   placeholder="CODE-XXXX"
                                   autocomplete="off">
                            <button onclick="processTicket(document.getElementById('manualCode').value)"
                                    class="px-6 h-11 rounded-full bg-rose-600 text-white font-bold text-[13px] hover:bg-rose-700 active:scale-95 transition-all shadow-sm">
                                Verify
                            </button>
                        </div>
                    </div>

                    <!-- ALPR Exit Button -->
                    <button onclick="openCamera('exit')" class="w-full h-[52px] rounded-xl bg-surface-alt/40 border border-color flex items-center justify-center gap-3 text-secondary hover:text-rose-500 hover:bg-rose-500/5 hover:border-rose-500/30 transition-all group shadow-sm">
                        <i class="fa-solid fa-camera text-sm"></i>
                        <span class="text-[13px] font-manrope font-bold uppercase tracking-wider">ALPR Recognition</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CAMERA MODAL -->
    <div id="cameraModal">
        <div class="camera-container animate-bounce-in shadow-2xl">
            <div class="p-6 border-b border-color flex items-center justify-between bg-surface-alt/20 backdrop-blur-md">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20" id="camIconBg">
                        <i class="fa-solid fa-camera text-indigo-500 text-lg" id="camIcon"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-manrope font-bold text-primary tracking-tight" id="camTitle">Live Plate Scanner</h3>
                        <p class="text-[10px] text-tertiary font-bold tracking-wide">Artificial Intelligence Detection</p>
                    </div>
                </div>
                <button onclick="closeCamera()" class="w-10 h-10 flex items-center justify-center hover:bg-rose-500/10 text-tertiary hover:text-rose-500 rounded-xl transition-all group">
                    <i class="fa-solid fa-xmark text-sm group-hover:rotate-90 transition-transform"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="scanner-viewport relative rounded-[1.5rem] overflow-hidden border border-color shadow-inner group min-h-[280px]">
                    <div id="cameraReader" class="w-full h-full">
                        <video id="videoFeed" autoplay playsinline class="w-full h-full object-cover"></video>
                    </div>
                    
                    <!-- Holographic Overlay -->
                    <div class="absolute inset-0 pointer-events-none z-10">
                        <!-- Corners -->
                        <div class="absolute top-6 left-6 w-8 h-8 border-t-2 border-l-2 border-indigo-500/40 rounded-tl-lg"></div>
                        <div class="absolute top-6 right-6 w-8 h-8 border-t-2 border-r-2 border-indigo-500/40 rounded-tr-lg"></div>
                        <div class="absolute bottom-6 left-6 w-8 h-8 border-b-2 border-l-2 border-indigo-500/40 rounded-bl-lg"></div>
                        <div class="absolute bottom-6 right-6 w-8 h-8 border-b-2 border-r-2 border-indigo-500/40 rounded-br-lg"></div>
                        
                        <!-- Scanning Line -->
                        <div class="scanner-sweep absolute left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent"></div>
                    </div>

                    <!-- Status Pill (Floating) -->
                    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20" id="cameraScanStatus">
                        <div class="flex items-center gap-2.5 px-4 py-2 rounded-full bg-black/60 backdrop-blur-xl border border-white/10 shadow-2xl">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-[10px] font-bold text-white tracking-widest uppercase">System Active</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-4">
                    <div class="p-4 rounded-2xl bg-surface-alt/30 border border-color/60 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                            <i class="fa-solid fa-expand text-indigo-500"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-[10px] text-tertiary font-bold tracking-wide uppercase mb-0.5">Scanner Status</p>
                            <h4 class="text-[13px] font-bold text-primary tracking-tight" id="alprStatusRealtime">Awaiting vehicle capture...</h4>
                        </div>
                    </div>
                    
                    <button id="autoScanBtn" class="w-full h-14 rounded-2xl bg-brand text-white font-manrope font-extrabold text-[14px] flex items-center justify-center gap-3 transition-all hover:brightness-110 active:scale-95 shadow-md">
                        <i class="fa-solid fa-circle-notch animate-spin text-xs"></i>
                        Initializing Scanner...
                    </button>
                </div>
            </div>
        </div>
    </div>


<script>
    // PUSH NOTIFICATION SYSTEM
    function pushNotify(title, message, type = 'info', code = null) {
        const container = document.getElementById('push-notification-container');
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
            <div id="${id}" class="notification-item bento-card !p-0 flex flex-col animate-slide-in pointer-events-auto shadow-2xl border border-color overflow-hidden w-[380px]">
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
                ${code ? `
                <div class="px-5 py-2.5 bg-surface-alt/40 border-t border-color flex items-center justify-between">
                    <span class="text-[10px] font-extrabold text-tertiary/60 tracking-wider">Reference Code</span>
                    <span class="text-[12px] font-code font-bold text-brand tracking-widest">${code}</span>
                </div>
                ` : ''}
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
            el.classList.add('animate-slide-out');
            setTimeout(() => el.remove(), 400);
        }, 5000);
    }

    // CHECK FOR URL NOTIFICATIONS
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        const type = urlParams.get('type') || 'info';
        const title = urlParams.get('title') || (type === 'error' ? 'Error' : 'Notification');
        const code = urlParams.get('code');
        
        if (msg) {
            pushNotify(title, msg, type, code);
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    // REGULAR ENTRY
    async function cetakTiketOtomatis(type, btn) {
        const orig = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch text-xs animate-spin"></i>';

        try {
            const res = await fetch(`<?= BASE_URL ?>modules/operations/print_ticket.php?auto=1&vtype=${type}`);
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            
            showTicketModal(data);
            refreshGateStats();
            const slotDisplay = String(data.slot_label || data.slot || 'N/A');
            pushNotify('Entry Successful', `Slot allocated: ${slotDisplay}`, 'success');
        } catch (e) {
            pushNotify('Entry Failed', e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // VIP ALPR SIMULATION
    function simulateVIPEntry() {
        const plate = document.getElementById('vipPlate').value.trim();
        const statusEl = document.getElementById('alprStatus');

        if (!plate) {
            pushNotify('Input Required', 'Please enter a plate number', 'error');
            return;
        }

        statusEl.innerHTML = `
            <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-indigo-500/5 border border-indigo-500/10 shadow-sm animate-pulse">
                <i class="fa-solid fa-expand text-indigo-500 text-[10px]"></i>
                <span class="text-[10px] font-bold text-indigo-500 tracking-wide">Scanning: ${plate}...</span>
            </div>`;

        setTimeout(() => {
            const formData = new FormData();
            formData.append('plate_number', plate);

            fetch('<?= BASE_URL ?>api/validate_vip.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusEl.innerHTML = `
                        <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-emerald-500/5 border border-emerald-500/10 shadow-sm">
                            <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                            <span class="text-[10px] font-bold text-emerald-500 tracking-wide">VIP Matched: ${data.details.slot}</span>
                        </div>`;
                    
                    pushNotify('VIP Access Granted', `Seamless entry for ${data.details.plate}`, 'vip', `Slot: ${data.details.slot}`);
                    
                    setTimeout(() => {
                        document.getElementById('vipPlate').value = '';
                        statusEl.innerHTML = `
                            <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-surface-alt/30 border border-color/50 shadow-sm">
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                                </span>
                                <span class="text-[10px] font-bold text-tertiary tracking-wide">Awaiting detection...</span>
                            </div>`;
                        refreshGateStats();
                    }, 2000);
                } else {
                    statusEl.innerHTML = `
                        <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-rose-500/5 border border-rose-500/10 shadow-sm">
                            <i class="fa-solid fa-xmark text-rose-500 text-[10px]"></i>
                            <span class="text-[10px] font-bold text-rose-500 tracking-wide">Access Denied</span>
                        </div>`;
                    pushNotify('Access Denied', data.error, 'error');
                }
            })
            .catch(err => {
                pushNotify('System Error', 'Could not validate VIP', 'error');
            });
        }, 1200);
    }

    // OCR SCANNING (OCR Space API)
    let cameraStream = null;
    let autoScanTimer = null;
    let currentCameraMode = 'entry'; // 'entry' or 'exit'

    async function openCamera(mode = 'entry') {
        currentCameraMode = mode;
        const modal = document.getElementById('cameraModal');
        const video = document.getElementById('videoFeed');
        const title = document.getElementById('camTitle');
        const iconBg = document.getElementById('camIconBg');
        const icon = document.getElementById('camIcon');
        
        if (mode === 'exit') {
            title.innerText = 'Exit Plate Scanner';
            iconBg.classList.remove('bg-indigo-500/10');
            iconBg.classList.add('bg-rose-500/10');
            icon.classList.remove('text-indigo-500');
            icon.classList.add('text-rose-500');
        } else {
            title.innerText = 'Entry Plate Scanner';
            iconBg.classList.remove('bg-rose-500/10');
            iconBg.classList.add('bg-indigo-500/10');
            icon.classList.remove('text-rose-500');
            icon.classList.add('text-indigo-500');
        }

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' } 
            });
            video.srcObject = cameraStream;
            modal.style.display = 'flex';
            
            // Start Auto-Scan Loop after 2s stabilization
            setTimeout(startAutoScan, 2000);
        } catch (err) {
            pushNotify('Camera Error', err.message, 'error');
        }
    }

    function startAutoScan() {
        if (!cameraStream) return;
        
        const scanBtn = document.getElementById('autoScanBtn');
        if (scanBtn) {
            scanBtn.innerHTML = `<i class="fa-solid fa-sync fa-spin text-xs"></i> Auto-scanning...`;
        }
        
        autoScanTimer = setInterval(() => {
            if (cameraStream) {
                capturePlate(true); // pass true for auto-capture
            }
        }, 4000); // Scan every 4 seconds
    }

    function closeCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        if (autoScanTimer) {
            clearInterval(autoScanTimer);
            autoScanTimer = null;
        }
        document.getElementById('cameraModal').style.display = 'none';
    }

    function capturePlate(isAuto = false) {
        const video = document.getElementById('videoFeed');
        if (!video || video.readyState < 2) return;

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        canvas.toBlob(blob => {
            const file = new File([blob], "capture.jpg", { type: "image/jpeg" });
            const mockInput = { files: [file], value: '' };
            
            if (!isAuto) closeCamera();
            handleOCR(mockInput, isAuto);
        }, 'image/jpeg', 0.8);
    }

    function handleOCR(input, isAuto = false) {
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const statusEl = document.getElementById('alprStatus');
        const plateInput = document.getElementById('vipPlate');

        if (!isAuto) {
            statusEl.innerHTML = `
                <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-surface-alt/40 border border-color shadow-sm animate-pulse">
                    <i class="fa-solid fa-microchip ${currentCameraMode === 'exit' ? 'text-rose-500' : 'text-indigo-500'} text-[10px]"></i>
                    <span class="text-[10px] font-bold text-primary tracking-wide">AI Processing...</span>
                </div>`;
        }

        const formData = new FormData();
        formData.append('file', file);

        fetch('<?= BASE_URL ?>api/ocr_proxy.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let rawPlate = data.plate.toUpperCase().trim();
                
                // Clean Plate: Minimal cleaning to preserve all data
                let plate = rawPlate.replace(/[^A-Z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
                
                // If auto-scanning, only proceed if we actually found a meaningful plate
                if (isAuto && plate.length < 3) return;

                if (currentCameraMode === 'exit') {
                    // EXIT MODE: Find ticket by plate
                    closeCamera();
                    const fd = new FormData();
                    fd.append('plate_number', plate);
                    fetch('<?= BASE_URL ?>api/get_ticket_by_plate.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            pushNotify('Exit Verified', `Vehicle ${res.plate} is ready for checkout`, 'success', res.ticket_code);
                            setTimeout(() => processTicket(res.ticket_code), 1500);
                        } else {
                            pushNotify('Resolution Failed', res.error, 'error');
                        }
                    });
                } else {
                    // ENTRY MODE
                    plateInput.value = plate;
                    statusEl.innerHTML = `
                        <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-emerald-500/5 border border-emerald-500/10 shadow-sm">
                            <i class="fa-solid fa-wand-magic-sparkles text-emerald-500 text-[10px]"></i>
                            <span class="text-[10px] font-bold text-emerald-500 tracking-wide">Plate Detected: ${plate}</span>
                        </div>`;
                    
                    closeCamera();
                    setTimeout(simulateVIPEntry, 800);
                }
            }
        })
        .catch(err => {
            if (!isAuto) statusEl.innerHTML = `<span class="text-[11px] font-bold text-rose-500 flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i> Network error</span>`;
        });
        input.value = '';
    }

    function refreshGateStats() {
        if (typeof updateStats === 'function') updateStats();
        window.dispatchEvent(new CustomEvent('data-updated'));
    }

let scanned = false;
function processTicket(code) {
    if (scanned) return;
    code = (code || '').trim().toUpperCase();
    if (code.length < 4) { 
        pushNotify('Invalid Code', 'Please enter a valid ticket code.', 'error');
        return; 
    }
    scanned = true;

    const gateScanResult = document.getElementById('gateScanResult');
    if (gateScanResult) {
        gateScanResult.innerHTML =
        `<div class="flex flex-col items-center">
            <div class="flex items-center gap-2.5 px-5 py-2 rounded-2xl bg-emerald-500/8 border border-emerald-500/20">
                <i class="fa-solid fa-spinner fa-spin text-emerald-500 text-sm"></i>
                <p class="text-[11px] font-bold text-emerald-600 tracking-wide">Synchronizing hardware state...</p>
            </div>
         </div>`;
    }

    pushNotify('Exit Processing', `Ticket ${code} verified`, 'success');

    setTimeout(() => {
        try {
            html5QrcodeScanner.clear().finally(() => {
                window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
            });
        } catch (e) {
            window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
        }
    }, 1200);
}

document.getElementById('manualCode').addEventListener('keydown', e => {
    if (e.key === 'Enter') processTicket(e.target.value);
    else e.target.value = e.target.value.toUpperCase();
});

const html5QrcodeScanner = new Html5QrcodeScanner(
    "qrReader",
    { fps: 20, aspectRatio: 1.0, rememberLastUsedCamera: true },
    false
);
    html5QrcodeScanner.render(
        decodedText => processTicket(decodedText),
        () => {}
    );

    // TICKET MODAL LOGIC
    function showTicketModal(data) {
        const modal = document.getElementById('ticketModal');
        const content = document.getElementById('ticketContent');

        // Move modal to body so fixed overlay always starts at viewport top.
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        const displayTime = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        
        const barcodeUrl = `https://quickchart.io/qr?text=${encodeURIComponent(data.ticket_code)}&size=160&margin=0&ecLevel=M`;
        
        const vehicleType = String(data.type || data.vtype || '').trim();
        const normalizedType = vehicleType || 'regular';
        const slotLabel = String(data.slot_label || data.slot || '').trim() || 'N/A';

        content.innerHTML = `
            <div class="relative overflow-hidden">
                <!-- Close Button -->
                <button onclick="closeTicketModal()" class="absolute top-0 right-0 w-8 h-8 rounded-full bg-surface-alt/50 flex items-center justify-center text-tertiary hover:text-rose-500 hover:bg-rose-500/10 transition-all z-20 active:scale-90">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>

                <!-- Header -->
                <div class="flex flex-col items-center mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 flex items-center justify-center border border-indigo-600/20 mb-3">
                        <i class="fa-solid fa-ticket-simple text-indigo-600 text-xl"></i>
                    </div>
                    <div class="text-center">
                        <h2 class="text-[15px] font-manrope font-extrabold text-primary tracking-tight">Parking Access Pass</h2>
                        <p class="text-[10px] text-tertiary font-bold tracking-widest uppercase mt-0.5">${data.plate || 'Plate Captured'}</p>
                    </div>
                </div>
                
                <!-- Ticket Code Pill -->
                <div class="bg-surface-alt/40 border border-color rounded-2xl p-4 mb-6 text-center relative group">
                    <div class="text-[9px] font-bold text-tertiary tracking-widest uppercase mb-1">Validation Code</div>
                    <div class="text-xl font-code font-bold text-primary tracking-[0.2em]">${data.ticket_code}</div>
                    <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-lg bg-indigo-500/5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fa-solid fa-fingerprint text-[10px] text-indigo-500/30"></i>
                    </div>
                </div>

                <!-- QR Area -->
                <div class="relative flex justify-center mb-6">
                    <div class="relative p-2 rounded-3xl shadow-xl shadow-indigo-500/10 border border-indigo-500/10" style="background:#fff !important;">
                        <img src="${barcodeUrl}" class="w-[140px] h-[140px] rounded-2xl" alt="QR Code">
                        <!-- Scanning Corners -->
                        <div class="absolute -top-1 -left-1 w-6 h-6 border-t-2 border-l-2 border-indigo-500/40 rounded-tl-xl"></div>
                        <div class="absolute -top-1 -right-1 w-6 h-6 border-t-2 border-r-2 border-indigo-500/40 rounded-tr-xl"></div>
                        <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-2 border-l-2 border-indigo-500/40 rounded-bl-xl"></div>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-2 border-r-2 border-indigo-500/40 rounded-br-xl"></div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="p-2.5 rounded-2xl bg-surface-alt/20 border border-color/50">
                        <div class="text-[9px] font-bold text-tertiary uppercase tracking-wider mb-0.5">Assigned Slot</div>
                        <div class="text-[13px] font-bold text-primary">${slotLabel}</div>
                    </div>
                    <div class="p-2.5 rounded-2xl bg-surface-alt/20 border border-color/50">
                        <div class="text-[9px] font-bold text-tertiary uppercase tracking-wider mb-0.5">Vehicle Category</div>
                        <div class="text-[13px] font-bold text-primary capitalize">${normalizedType}</div>
                    </div>
                </div>

                <!-- Footer / Timestamp -->
                <div class="flex flex-col items-center gap-1 opacity-50">
                    <div class="flex items-center gap-2 text-[9px] font-bold text-tertiary">
                        <i class="fa-solid fa-clock"></i>
                        <span>${dateStr} • ${displayTime}</span>
                    </div>
                    <div class="w-12 h-0.5 bg-color rounded-full mt-1"></div>
                </div>
            </div>
                <div class="pt-4 border-t border-color/60 mt-6">
                    <p class="text-[9px] font-bold text-tertiary leading-relaxed text-center">
                        Retain ticket for automated exit.
                    </p>
                </div>
            </div>
        `;
        
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Success Haptic/Feedback
        if ('vibrate' in navigator) navigator.vibrate(50);
    }

    function closeTicketModal() {
        document.getElementById('ticketModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    </script>
<?php include '../../includes/footer.php'; ?>
