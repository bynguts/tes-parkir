<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$summary = get_slot_summary($pdo);

$page_title = 'Smart Gate Simulator';
$page_subtitle = 'Physical sensor simulation for entry/exit gates.';

include '../../includes/header.php';
?>


<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
/* Barcode scanner viewport */
#qrReader, #qrReader video, #qrReader__scan_region, #qrReader__canvas_border, #cameraReader, #cameraReader video, div[id*="html5-qrcode"] {
    border-radius: inherit !important;
}
#qrReader {
    width: 100% !important;
    height: 100% !important;
    max-width: 100%;
    overflow: hidden;
    background: transparent !important;
    margin: 0;
    position: relative;
    border: none !important;
    box-shadow: none !important;
    box-sizing: border-box;
}
#qrReader video {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}
/* Barcode guide overlay — purely decorative, never intercepts clicks */
.barcode-guide {
    position: absolute;
    inset: 16px;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none !important; z-index: 60;
}
.barcode-guide * {
    pointer-events: none !important;
}
/* Simplified gate ticket buttons to use theme standards */
.gate-ticket-btn {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.gate-ticket-btn:hover {
    /* No movement on hover */
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
}
.action-switch-outline.brand:hover i {
    color: #ffffff !important;
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
/* Html5Qrcode direct API — hide any residual UI chrome */
#qrReader * {
    outline: none !important;
    box-shadow: none !important;
}
#qrReader video {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
    border-radius: inherit;
}
/* Hide all library-generated UI elements (not needed with direct API) */
#qrReader__dashboard,
#qrReader__dashboard_section,
#qrReader__status_span,
#qrReader__header_message,
#qrReader select,
#qrReader img,
#qrReader__filescan_input { display: none !important; }
#qrReader__scan_region {
    width: 100% !important;
    height: 100% !important;
}
#qrReader__scan_region > div,
#qrReader__scan_region div,
.qr-shaded-region {
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
}

/* Camera Modal */
#cameraModal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: color-mix(in srgb, var(--bg-page) 88%, transparent);
    z-index: 1000001 !important;
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
    box-shadow: 0 30px 80px -20px var(--shadow-color);
}

/* Ticket Display Modal */
#ticketModal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 99999 !important;
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

<div class="px-10 py-10 space-y-6">
        
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
            <div class="bento-card p-4 flex items-center gap-4 slot-summary-card <?= $t ?>">
                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-<?= $icon ?> fa-fw text-lg"></i>
                </div>
                <div class="flex flex-col min-w-0 flex-1">
                    <div class="flex items-end gap-2 mb-1">
                        <span class="text-3xl font-manrope font-bold text-primary leading-none"><?= $avail ?></span>
                        <span class="text-xs font-inter text-tertiary pb-0.5">/ <?= $total ?> Total</span>
                    </div>
                    <span class="text-[13px] font-inter text-tertiary truncate"><?= $label ?> Slots Available</span>
                    <div class="mt-3">
                        <div class="w-full h-2 progress-track rounded-full overflow-hidden">
                            <div class="h-full progress-fill animate-growth rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <!-- Push Notification Container -->
    <!-- Global push-notification-container is now in header.php -->

    
    <!-- Gate Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">

        <!-- ENTRY GATE -->
        <div class="bento-card p-0 flex flex-col relative overflow-hidden group">

            
            <!-- Card Header -->
            <div class="p-4 border-b border-color shrink-0 flex items-center justify-between bg-surface-alt/30 backdrop-blur-sm z-10">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Entry Terminal</h3>
                    </div>
                </div>
                <div class="status-badge status-badge-online">
                    <span class="status-dot-online"></span>
                    <span class="text-primary">Online</span>
                </div>
            </div>

            <div class="flex-grow flex flex-col z-10">
                <!-- SECTION 01: REGULAR KIOSK -->
                <div class="flex-1 p-4 min-h-[340px] flex flex-col justify-center relative">
                    <div class="flex flex-col items-center">
                        <div class="grid grid-cols-1 grid-rows-2 gap-4 w-full max-w-[280px] h-[280px] mx-auto">
                            <button class="gate-ticket-btn bento-card group/btn flex flex-row items-center justify-center gap-4 p-4 h-full"
                                    onclick="cetakTiketOtomatis('car', this)">
                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-car text-lg"></i>
                                </div>
                                <div class="text-left">
                                    <span class="block text-sm font-manrope font-bold text-primary leading-tight">Car Ticket</span>
                                </div>
                            </button>
                            <button class="gate-ticket-btn bento-card group/btn flex flex-row items-center justify-center gap-4 p-4 h-full"
                                    onclick="cetakTiketOtomatis('motorcycle', this)">
                                <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-motorcycle text-lg"></i>
                                </div>
                                <div class="text-left">
                                    <span class="block text-sm font-manrope font-bold text-primary leading-tight">Motorcycle Ticket</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 02: VIP/RESERVATION -->
                <div class="flex-1 p-4 flex flex-col justify-center relative z-10 pointer-events-auto">

                    
                    <div class="flex justify-center mb-4">
                        <span class="text-[13px] font-inter text-tertiary text-center block">Reservation Scan (ALPR)</span>
                    </div>
                    
                    <div class="w-full max-w-[320px] mx-auto">
                        <div class="relative group/input">
                            <div class="flex items-center gap-2 h-11 bento-card pl-3 pr-1.5 transition-all">
                                <button onclick="triggerALPRCamera('entry')" 
                                        class="w-8 h-8 flex items-center justify-center transition-all hover:scale-110 active:scale-90 group/cam"
                                        title="Open Camera">
                                    <i class="fa-solid fa-camera text-lg text-brand transition-colors group-hover/cam:text-brand-hover"></i>
                                </button>
                                <input type="text" id="entry-manual-lp" 
                                       placeholder="Plate Number..." 
                                       class="flex-1 h-full bg-transparent text-[13px] font-inter font-medium text-primary px-2 focus:outline-none placeholder:text-tertiary">
                                <button onclick="processALPR('entry')"
                                        class="ml-auto h-8 px-3 rounded-full bg-brand text-white font-manrope font-bold text-[11px] transition-all hover:bg-brand-hover active:scale-95">
                                    Verify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EXIT GATE -->
        <div class="bento-card p-0 flex flex-col relative overflow-hidden group">


            <!-- Card Header -->
            <div class="p-4 border-b border-color shrink-0 flex items-center justify-between bg-surface-alt/30 backdrop-blur-sm z-10">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Exit Terminal</h3>
                    </div>
                </div>
                <div class="status-badge status-badge-online">
                    <span class="status-dot-online"></span>
                    <span class="text-primary">Online</span>
                </div>
            </div>

            <div class="flex-grow flex flex-col z-10">
                <!-- EXIT SECTION 01: SCANNER -->
                <div class="flex-1 p-4 min-h-[340px] flex flex-col justify-center relative">
                    <!-- Scanner Container -->
                    <div class="flex flex-col items-center">
                        <div class="relative w-full max-w-[280px] h-[280px] group/scanner">
                            <!-- Holographic Frame -->
                            <div class="absolute inset-0 border-2 border-color rounded-[2.5rem] shadow-xl transition-all group-hover/scanner:border-brand/30"></div>
                            
                            <div class="relative w-full h-full rounded-[2.2rem] overflow-hidden bg-slate-900 shadow-inner">
                                <div id="qrReader" class="absolute inset-0"></div>
                                
                                <!-- Enhanced Scanner UI — pointer-events-none so scanner buttons remain clickable -->
                                <div class="barcode-guide !bg-transparent" style="pointer-events:none;">
                                    <div class="barcode-frame !border-none" style="pointer-events:none;">
                                        <span class="scanner-sweep !bg-gradient-to-b !from-transparent !via-rose-500 !to-transparent !h-1 !opacity-40" style="pointer-events:none;"></span>
                                        
                                        <!-- L-Corners -->
                                        <span class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-rose-500 rounded-tl-2xl" style="pointer-events:none;"></span>
                                        <span class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-rose-500 rounded-tr-2xl" style="pointer-events:none;"></span>
                                        <span class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-rose-500 rounded-bl-2xl" style="pointer-events:none;"></span>
                                        <span class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-rose-500 rounded-br-2xl" style="pointer-events:none;"></span>
                                    </div>
                                </div>

                                <!-- START PROMPT: Always in HTML, hidden after scanner starts -->
                                <div id="qrStartPrompt" class="absolute inset-0 flex flex-col items-center justify-center gap-3" style="z-index:200; pointer-events:auto; background:rgba(15,23,42,0.5);">
                                    <i class="fa-solid fa-qrcode text-white/20 text-4xl"></i>
                                    <button onclick="startQRManually()"
                                            id="qrStartBtn"
                                            style="pointer-events:auto; cursor:pointer;"
                                            class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white text-[12px] font-bold transition-all active:scale-95 shadow-lg">
                                        <i class="fa-solid fa-spinner fa-spin mr-2"></i>Starting...
                                    </button>
                                    <p class="text-[10px] font-bold text-white/40 text-center">Activating QR scanner...</p>
                                </div>
                                <script>
                                    if (sessionStorage.getItem('qr_scanner_active') === 'true') {
                                        const btn = document.querySelector('#qrStartPrompt button');
                                        if (btn) {
                                            btn.disabled = true;
                                            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Starting...';
                                        }
                                    }
                                </script>
                            </div>
                        </div>
                        

                    </div>
                </div>

                <!-- EXIT SECTION 02: MANUAL/ALPR -->
                <div class="flex-1 p-4 flex flex-col justify-center relative z-10 pointer-events-auto">
                    <div class="flex justify-center mb-4">
                        <span class="text-[13px] font-inter text-tertiary text-center block">Reservation Scan (ALPR)</span>
                    </div>
                    <div class="w-full max-w-[320px] mx-auto">
                        <div class="relative group/manual">
                            <div class="flex items-center gap-2 h-11 bento-card pl-3 pr-1.5 transition-all">
                                <button onclick="triggerALPRCamera('exit')" 
                                        class="w-8 h-8 flex items-center justify-center transition-all hover:scale-110 active:scale-90 group/cam"
                                        title="Open Camera">
                                    <i class="fa-solid fa-camera text-lg text-rose-600 transition-colors group-hover/cam:text-rose-700"></i>
                                </button>
                                <input type="text" id="exit-manual-lp" 
                                       placeholder="Plate or Ticket Code..." 
                                       class="flex-1 h-full bg-transparent text-[13px] font-inter font-medium text-primary px-2 focus:outline-none placeholder:text-tertiary">
                                <button onclick="processALPR('exit')"
                                        class="ml-auto h-8 px-3 rounded-full bg-rose-600 text-white font-manrope font-bold text-[11px] transition-all hover:bg-rose-700 active:scale-95">
                                    Verify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- MODALS ARE AT THE END OF THE FILE -->



<script>
    // QR Scanner instance (declared at top to avoid TDZ errors)
    let qrInstance = null;

    // PUSH NOTIFICATION SYSTEM is now handled globally in header.php

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
                    
                    pushNotify('Reservation Access Granted', `Seamless entry for ${data.details.plate}`, 'vip', `Slot: ${data.details.slot}`);
                    
                    setTimeout(() => {
                        document.getElementById('vipPlate').value = '';
                        statusEl.innerHTML = `
                            <div class="status-badge status-badge-awaiting">
                                Awaiting detection...
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
        const detIconBg = document.getElementById('detIconBg');
        const detIcon = document.getElementById('detIcon');
        
        if (mode === 'exit') {
            title.innerText = 'Exit Plate Scanner';
            iconBg.classList.remove('bg-indigo-500/10');
            iconBg.classList.add('bg-rose-500/10');
            icon.classList.remove('text-indigo-500');
            icon.classList.add('text-rose-500');
            
            if (detIconBg) {
                detIconBg.classList.remove('bg-indigo-500/10');
                detIconBg.classList.add('bg-rose-500/10');
            }
            if (detIcon) {
                detIcon.classList.remove('text-indigo-500');
                detIcon.classList.add('text-rose-500');
            }
        } else {
            title.innerText = 'Entry Plate Scanner';
            iconBg.classList.remove('bg-rose-500/10');
            iconBg.classList.add('bg-indigo-500/10');
            icon.classList.remove('text-rose-500');
            icon.classList.add('text-indigo-500');

            if (detIconBg) {
                detIconBg.classList.remove('bg-rose-500/10');
                detIconBg.classList.add('bg-indigo-500/10');
            }
            if (detIcon) {
                detIcon.classList.remove('text-rose-500');
                detIcon.classList.add('text-indigo-500');
            }
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
        // Use the correct input ID based on camera mode
        const plateInputId = currentCameraMode === 'exit' ? 'exit-manual-lp' : 'entry-manual-lp';
        const plateInput = document.getElementById(plateInputId);

        if (!isAuto && statusEl) {
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
                            const msg = `Vehicle ${res.plate} is ready for checkout`;
                            processTicket(res.ticket_code, 'Exit Verified', msg);
                        } else {
                            pushNotify('Resolution Failed', res.error, 'error');
                        }
                    });
                } else {
                    // ENTRY MODE: populate input and auto-verify
                    if (plateInput) plateInput.value = plate;
                    if (statusEl) {
                        statusEl.innerHTML = `
                            <div class="flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-emerald-500/5 border border-emerald-500/10 shadow-sm">
                                <i class="fa-solid fa-wand-magic-sparkles text-emerald-500 text-[10px]"></i>
                                <span class="text-[10px] font-bold text-emerald-500 tracking-wide">Plate Detected: ${plate}</span>
                            </div>`;
                    }
                    closeCamera();
                    setTimeout(() => processALPR('entry'), 800);
                }
            }
        })
        .catch(err => {
            if (!isAuto && statusEl) statusEl.innerHTML = `<span class="text-[11px] font-bold text-rose-500 flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i> Network error</span>`;
        });
        input.value = '';
    }

    // ─── ALPR BRIDGE FUNCTIONS ───────────────────────────────────────────────
    // These are called by the HTML buttons and bridge to the camera/verify logic

    function triggerALPRCamera(mode) {
        openCamera(mode);
    }

    function processALPR(mode) {
        if (mode === 'entry') {
            const plate = (document.getElementById('entry-manual-lp')?.value || '').trim().toUpperCase();
            if (!plate) { pushNotify('Input Required', 'Please enter a plate number.', 'error'); return; }

            const formData = new FormData();
            formData.append('plate_number', plate);
            fetch('<?= BASE_URL ?>api/validate_vip.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        pushNotify('Reservation Access Granted', `Seamless entry for ${data.details.plate}`, 'vip', `Slot: ${data.details.slot}`);
                        document.getElementById('entry-manual-lp').value = '';
                        refreshGateStats();
                    } else {
                        pushNotify('Access Denied', data.error || 'No active reservation found.', 'error');
                    }
                })
                .catch(() => pushNotify('System Error', 'Could not validate reservation.', 'error'));

        } else {
            const inputVal = (document.getElementById('exit-manual-lp')?.value || '').trim().toUpperCase();
            if (!inputVal) { pushNotify('Input Required', 'Please enter a plate number or ticket code.', 'error'); return; }

            // SMART DETECT: If it's a ticket code (TKT- or RSV-), process directly
            if (inputVal.startsWith('TKT-') || inputVal.startsWith('RSV-')) {
                processTicket(inputVal, 'Manual Code Entry', `Processing ticket ${inputVal}...`);
                return;
            }

            const fd = new FormData();
            fd.append('plate_number', inputVal);
            fetch('<?= BASE_URL ?>api/get_ticket_by_plate.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        processTicket(res.ticket_code, 'Exit Verified', `Vehicle ${res.plate} is ready for checkout`);
                    } else {
                        pushNotify('Resolution Failed', res.error || 'Plate not found in active sessions.', 'error');
                    }
                })
                .catch(() => pushNotify('System Error', 'Could not process exit by plate.', 'error'));
        }
    }

    function refreshGateStats() {
        if (typeof updateStats === 'function') updateStats();
        window.dispatchEvent(new CustomEvent('data-updated'));
    }

let scanned = false;
function processTicket(code, notifyTitle = null, notifyMsg = null) {
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

    const msg = notifyMsg || `Ticket ${code} verified`;
    const title = notifyTitle || 'Exit Processing';

    setTimeout(() => {
        const url = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}&msg=${encodeURIComponent(msg)}&title=${encodeURIComponent(title)}&type=success&code=${encodeURIComponent(code)}`;
        try {
            if (qrInstance) {
                qrInstance.stop().finally(() => { window.location.href = url; });
            } else {
                window.location.href = url;
            }
        } catch (e) {
            window.location.href = url;
        }
    }, 800);
}



// ─── QR SCANNER: Smart start with camera enumeration ────────────────────────

function hideQRPrompt() {
    const p = document.getElementById('qrStartPrompt');
    if (p) p.style.display = 'none';
}

async function startQRManually(retryCount = 0) {
    // Show loading state on button
    const btn = document.querySelector('#qrStartPrompt button');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>' + (retryCount > 0 ? 'Retrying...' : 'Starting...'); }

    try {
        const cameras = await Html5Qrcode.getCameras();
        if (!cameras || cameras.length === 0) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-camera mr-2"></i>No Camera Found'; }
            return;
        }
        const frontCam = cameras.find(c => /front|user|face|integrated|webcam/i.test(c.label));
        const cam = frontCam || cameras[0];

        // Clear any previous instance
        if (qrInstance) {
            try { await qrInstance.stop(); } catch(_) {}
            qrInstance = null;
        }
        document.getElementById('qrReader').innerHTML = '';
        qrInstance = new Html5Qrcode("qrReader");
        await qrInstance.start(
            cam.id,
            { fps: 15, aspectRatio: 1.0 },
            (decodedText) => processTicket(decodedText),
            () => {}
        );
        
        // Success: set session flag and hide prompt
        sessionStorage.setItem('qr_scanner_active', 'true');
        hideQRPrompt();
    } catch (err) {
        if (retryCount < 3 && err.name !== 'NotAllowedError') {
            // Retry if it's not a strict permission denial (likely device in use lock)
            setTimeout(() => startQRManually(retryCount + 1), 600);
            return;
        }

        const prompt = document.getElementById('qrStartPrompt');
        if (prompt) prompt.style.display = 'flex'; // Unhide if it failed
        
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-camera mr-2"></i>Retry Camera'; }
        const lbl = document.querySelector('#qrStartPrompt p');
        if (lbl) lbl.textContent = 'Error: ' + (err.message || 'Camera locked/denied');
    }
}

async function initQRScanner() {
    // If we've already activated in this session, use robust start
    if (sessionStorage.getItem('qr_scanner_active') === 'true') {
        const prompt = document.getElementById('qrStartPrompt');
        if (prompt) prompt.style.display = 'flex'; // Keep it flex so spinner is visible
        startQRManually();
        return;
    }

    // Otherwise, attempt a silent auto-start just in case the browser allows it natively
    try {
        const cameras = await Html5Qrcode.getCameras();
        if (!cameras || cameras.length === 0) return;
        const frontCam = cameras.find(c => /front|user|face|integrated|webcam/i.test(c.label));
        const cam = frontCam || cameras[0];

        qrInstance = new Html5Qrcode("qrReader");
        await qrInstance.start(
            cam.id,
            { fps: 15, aspectRatio: 1.0 },
            (decodedText) => processTicket(decodedText),
            () => {}
        );
        hideQRPrompt();
    } catch (_) {
        // Fallback: wait for user to click "Start Scanner"
    }
}

// Release camera lock on page unload
window.addEventListener('beforeunload', () => {
    if (qrInstance) {
        try { qrInstance.stop(); } catch(e) {}
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (sessionStorage.getItem('qr_scanner_active') === 'true') {
        initQRScanner();
    } else {
        setTimeout(initQRScanner, 800);
    }
});



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
                </div>

                <!-- QR Area -->
                <div class="relative flex justify-center mb-6">
                    <div class="relative p-2 rounded-3xl shadow-xl shadow-indigo-500/10 border border-indigo-500/10" style="background:#fff !important;">
                        <img src="${barcodeUrl}" class="w-[140px] h-[140px] rounded-sm" alt="QR Code">
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

    // Teleport modals to body to bypass stacking contexts
    document.addEventListener('DOMContentLoaded', () => {
        const modals = ['cameraModal', 'ticketModal'];
        modals.forEach(id => {
            const el = document.getElementById(id);
            if (el) document.body.appendChild(el);
        });
    });
    </script>

    <!-- CAMERA MODAL -->
    <div id="cameraModal" style="position: fixed !important; inset: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 1000001 !important; display: none; align-items: center; justify-content: center; background: color-mix(in srgb, var(--bg-page) 90%, transparent); backdrop-filter: blur(12px);">
        <div class="camera-container animate-bounce-in shadow-2xl">
            <div class="p-6 border-b border-color flex items-center justify-between bg-surface-alt/20 backdrop-blur-md">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0" id="camIconBg">
                        <i class="fa-solid fa-camera fa-fw text-lg" id="camIcon"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-manrope font-bold text-primary tracking-tight" id="camTitle">Live Plate Scanner</h3>
                        <p class="text-[10px] text-tertiary font-bold tracking-wide uppercase">AI Neural Vision Active</p>
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
                        <div class="absolute top-6 left-6 w-8 h-8 border-t-2 border-l-2 border-brand/40 rounded-tl-lg"></div>
                        <div class="absolute top-6 right-6 w-8 h-8 border-t-2 border-r-2 border-brand/40 rounded-tr-lg"></div>
                        <div class="absolute bottom-6 left-6 w-8 h-8 border-b-2 border-l-2 border-brand/40 rounded-bl-lg"></div>
                        <div class="absolute bottom-6 right-6 w-8 h-8 border-b-2 border-r-2 border-brand/40 rounded-br-lg"></div>
                        
                        <!-- Scanning Line -->
                        <div class="scanner-sweep absolute left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-brand/50 to-transparent"></div>
                    </div>

                    <!-- Status Pill (Floating) -->
                    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 hidden" id="cameraScanStatus">
                        <div class="flex items-center gap-2.5 px-4 py-2 rounded-full bg-black/60 backdrop-blur-xl border border-white/10 shadow-2xl">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-[10px] font-bold text-white tracking-widest uppercase">Core Online</span>
                        </div>
                    </div>
                </div>

                <!-- Recognition Status -->
                <div class="mt-6 p-4 rounded-2xl bg-surface-alt/40 border border-color flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0" id="detIconBg">
                            <i class="fa-solid fa-expand text-lg" id="detIcon"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-tertiary uppercase tracking-wider mb-0.5">Detection Log</p>
                            <h4 class="text-[13px] font-bold text-primary" id="cameraStatusText">Awaiting vehicle capture...</h4>
                        </div>
                    </div>

                </div>


            </div>
        </div>
    </div>


    <!-- Ticket Display Modal (Compact Receipt) -->
    <div id="ticketModal" style="position: fixed !important; inset: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 100000 !important; display: none; align-items: center; justify-content: center; background: color-mix(in srgb, var(--bg-page) 75%, transparent); backdrop-filter: blur(8px);">
        <div class="ticket-container">
            <div class="ticket-content" id="ticketContent">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>



<?php include '../../includes/footer.php'; ?>
