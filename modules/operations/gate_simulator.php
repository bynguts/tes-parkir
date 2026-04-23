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
#reader, #reader video, #reader__scan_region, #reader__canvas_border, div[id*="html5-qrcode"] {
    border-radius: 1.5rem !important;
}
#reader {
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
#reader video {
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
.gate-ticket-btn {
    min-width: 160px;
    height: 44px;
    padding: 0 16px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
    text-transform: none !important;
    letter-spacing: 0 !important;
}
.gate-ticket-status {
    height: 44px;
    min-width: 160px;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
    text-transform: none !important;
    letter-spacing: 0 !important;
}
.action-switch-outline.brand {
    background-color: var(--surface-alt) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
}
.action-switch-outline.brand i {
    color: var(--brand) !important;
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
    height: 44px;
    padding: 0 16px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
    background-color: var(--surface-alt) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
}
.action-switch-input::placeholder {
    color: var(--text-primary) !important;
    opacity: 1;
}
.action-switch-input:focus {
    border-color: var(--border-color) !important;
    background-color: var(--surface-alt) !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
}
.action-switch-input:focus-visible,
.action-switch-input:active {
    border-color: var(--border-color) !important;
    background-color: var(--surface-alt) !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
    outline: none !important;
}
.action-switch-outline.danger {
    height: 44px;
    padding: 0 16px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
    background-color: var(--status-over-bg) !important;
    border: 1px solid var(--status-over-border) !important;
    color: var(--status-over-text) !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.action-switch-outline.danger:hover {
    background-color: var(--trend-down) !important;
    border-color: var(--trend-down) !important;
    color: #ffffff !important;
}
.action-switch-outline.danger:focus,
.action-switch-outline.danger:focus-visible,
.action-switch-outline.danger:active {
    background-color: var(--status-over-bg) !important;
    border: 1px solid var(--status-over-border) !important;
    color: var(--status-over-text) !important;
    outline: none !important;
    box-shadow: 0 4px 10px var(--shadow-color) !important;
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
#reader__scan_region {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}
#reader * {
    outline: none !important;
    box-shadow: none !important;
}
#reader__scan_region > div,
#reader__scan_region div,
.qr-shaded-region {
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
}
#reader__scan_region > img,
#reader__scan_region img {
    display: none !important;
}
#reader select { display: none !important; }
#reader span { display: none !important; }
#reader img { display: none !important; }
#reader__header_message { display: none !important; }
#html5-qrcode-button-camera-start,
#html5-qrcode-button-camera-stop {
    background: var(--brand) !important; color: white !important;
    border-radius: 0.75rem !important;
    margin: 12px auto 0 !important;
    display: block !important;
    font-family: 'Inter', sans-serif !important; 
    transition: all 0.3s ease !important;
}
#html5-qrcode-button-camera-start:hover {
    background: var(--hover-border) !important;
    transform: translateY(-1px);
}
</style>

    <div class="px-10 py-10 max-w-[1300px] mx-auto flex flex-col gap-6">
        
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
                        <span class="text-[11px] font-inter text-tertiary pb-0.5">/ <?= $total ?> Total</span>
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

        <!-- Gate Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">

            <!-- ENTRY GATE -->
            <div class="bento-card p-4 flex flex-col min-h-[350px] relative">
                <div id="ticketStatus" class="absolute inset-0 z-10 w-full h-full flex items-center justify-center pointer-events-none"></div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Entry Terminal</h3>
                    </div>
                </div>

                <div class="flex-grow flex flex-col justify-between">
                    
                    <div class="relative z-20 flex items-center justify-center gap-3 w-full mt-auto">
                        <button class="gate-ticket-btn action-switch-outline brand rounded-lg transition-all flex items-center justify-center gap-2"
                                onclick="cetakTiketOtomatis('car', this)">
                            <i class="fa-solid fa-car text-lg"></i>
                            Car
                        </button>
                        <button class="gate-ticket-btn action-switch-outline brand rounded-lg transition-all flex items-center justify-center gap-2"
                                onclick="cetakTiketOtomatis('motorcycle', this)">
                            <i class="fa-solid fa-motorcycle text-lg"></i>
                            Motorcycle
                        </button>
                    </div>
                </div>
            </div>

            <!-- EXIT GATE -->
            <div class="bento-card p-4 flex flex-col min-h-[350px]">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </div>
                    <div>
                        <h3 class="card-title leading-tight">Exit Terminal</h3>
                    </div>
                </div>

                <div class="flex-grow flex flex-col justify-between">
                    <!-- Barcode Scanner -->
                    <div class="relative w-full max-w-[200px] aspect-square mx-auto mb-3">
                        <div id="reader" class="w-full h-full"></div>
                        <div class="barcode-guide">
                            <div class="barcode-frame">
                                <span class="scanner-sweep"></span>
                                <span class="scanner-corner tl"></span>
                                <span class="scanner-corner tr"></span>
                                <span class="scanner-corner bl"></span>
                                <span class="scanner-corner br"></span>
                            </div>
                        </div>
                    </div>

                    <div id="scanned-result" class="min-h-[40px] mb-3 w-full flex items-center justify-center"></div>

                    <!-- Manual input -->
                    <div class="flex w-full items-center justify-center gap-2 mt-auto">
                        <input type="text" id="manualCode"
                               class="w-[240px] sm:w-[280px] action-switch-input rounded-lg focus:outline-none transition-all text-center"
                               placeholder="Enter exit ticket code"
                               aria-label="Enter exit ticket code"
                               autocomplete="off">
                        <button onclick="processTicket(document.getElementById('manualCode').value)"
                            class="action-switch-outline danger rounded-lg transition-all flex items-center gap-2">
                            Execute
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

<script>
async function cetakTiketOtomatis(type, btn) {
    const orig = btn.innerHTML;
    const status = document.getElementById('ticketStatus');
    
    // Disable all buttons during processing
    const buttons = btn.parentElement.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);
    
    btn.innerHTML = '<i class="fa-solid fa-circle-notch text-xs animate-spin"></i>';
    const loadingWidth = btn.offsetWidth;
    const loadingHeight = btn.offsetHeight;
    btn.style.width = `${loadingWidth}px`;
    btn.style.height = `${loadingHeight}px`;
    status.innerHTML = '';

    try {
        const res  = await fetch(`print_ticket.php?auto=1&vtype=${type}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        status.innerHTML = `<span class="gate-ticket-status rounded-lg status-badge-available shadow-sm">
                                Ticket Ready
                            </span>`;
        window.open(`print_ticket.php?ticket_code=${encodeURIComponent(data.ticket_code)}`, '_blank', 'width=400,height=600');
    } catch (e) {
        status.innerHTML = `<span class="gate-ticket-status rounded-lg status-badge-over shadow-sm">
                                ${e.message}
                            </span>`;
    } finally {
        setTimeout(() => {
            buttons.forEach(b => b.disabled = false);
            btn.innerHTML = orig;
            btn.style.width = '';
            btn.style.height = '';
            status.innerHTML = '';
        }, 4000);
    }
}

let scanned = false;
function processTicket(code) {
    if (scanned) return;
    code = (code || '').trim().toUpperCase();
    if (code.length < 4) { alert('Invalid ticket code.'); return; }
    scanned = true;

    document.getElementById('scanned-result').innerHTML =
        `<div class="flex flex-col items-center">
            <div class="flex items-center gap-3 px-6 py-4 bg-emerald-50/10 border border-emerald-500/20 rounded-2xl text-emerald-700 font-code font-bold text-sm shadow-lg shadow-emerald-500/5">
                <i class="fa-solid fa-qrcode text-lg opacity-60"></i>
                <span class="tracking-[0.2em]">${code}</span>
            </div>
            <p class="text-slate-900/40 text-[10px] font-extrabold uppercase tracking-[0.2em] mt-3 animate-pulse">Synchronizing Hardware State...</p>
         </div>`;

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
    "reader",
    { fps: 20, aspectRatio: 1.0, rememberLastUsedCamera: true },
    false
);
html5QrcodeScanner.render(
    decodedText => processTicket(decodedText),
    () => {}
);
</script>

<?php include '../../includes/footer.php'; ?>
