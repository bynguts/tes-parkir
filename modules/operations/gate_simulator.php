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
#reader {
    width: 300px; height: 300px;
    max-width: 100%;
    border-radius: 1.5rem;
    overflow: hidden;
    background: #0f172a;
    margin: 20px auto;
    position: relative;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.1);
    ring: 1px solid rgba(255,255,255,0.1);
}
#reader video {
    border-radius: 1.5rem;
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}
/* Barcode guide overlay */
.barcode-guide {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none; z-index: 10;
}
.barcode-frame {
    width: 220px; height: 220px;
    border: 2px solid rgba(255,255,255,0.4);
    border-radius: 1.5rem;
    position: relative;
    backdrop-filter: brightness(1.2);
}
.scan-line {
    position: absolute; left: 10%; right: 10%; height: 2px;
    background: #3b82f6;
    animation: scanline 2.5s ease-in-out infinite;
    box-shadow: 0 0 20px #3b82f6, 0 0 40px #3b82f6;
    border-radius: 10px;
    z-index: 20;
}
@keyframes scanline {
    0%, 100% { top: 15%; opacity: 0.2; }
    50% { top: 85%; opacity: 1; }
}
#reader select { display: none !important; }
#reader span { display: none !important; }
#html5-qrcode-button-camera-start,
#html5-qrcode-button-camera-stop {
    background: #0f172a !important; color: white !important;
    border: 1px solid rgba(255,255,255,0.1) !important; 
    border-radius: 12px !important;
    padding: 10px 20px !important; margin: 10px !important;
    font-family: 'Inter', sans-serif !important; 
    font-size: 11px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.1em !important;
    transition: all 0.3s ease !important;
}
#html5-qrcode-button-camera-start:hover {
    background: #1e293b !important;
    transform: translateY(-1px);
}
</style>

    <div class="p-8">

        <!-- Slot Availability -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php
            $types = [
                'car'        => ['car',       'CAR'],
                'motorcycle' => ['motorcycle', 'MOTORCYCLE'],
            ];
            foreach ($types as $t => $cfg):
                $icon  = $cfg[0];
                $lbl   = $cfg[1];
                $avail = $summary[$t]['avail'] ?? 0;
                $total = $summary[$t]['total'] ?? 0;
                $pct   = $total > 0 ? ($avail/$total)*100 : 100;
                $pct_cls = $pct > 30 ? 'text-emerald-500' : ($pct > 10 ? 'text-amber-500' : 'text-red-500');
                $bar_cls  = $pct > 30 ? 'bg-emerald-500' : ($pct > 10 ? 'bg-amber-400' : 'bg-red-500');
            ?>
            <div class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-900/[0.03] ring-1 ring-slate-900/5 group hover:shadow-2xl transition-all duration-500">
                <div class="flex items-center justify-between mb-6 -mt-2">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Live Capacity — <?= $lbl ?></p>
                    <div class="w-12 h-12 rounded-xl <?= $t === 'car' ? 'bg-blue-500/10 text-blue-600' : 'bg-emerald-500/10 text-emerald-600' ?> flex items-center justify-center transition-transform group-hover:scale-110 duration-500">
                        <i class="fa-solid fa-<?= $icon ?> text-xl"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="font-manrope font-extrabold text-5xl tracking-tighter <?= $pct_cls ?>"><?= $avail ?></span>
                    <span class="text-slate-900/40 text-sm font-inter font-bold uppercase tracking-widest">/ <?= $total ?> Units</span>
                </div>
                <div class="w-full bg-slate-900/5 rounded-full h-2.5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 ease-out <?= $bar_cls ?>" style="width:<?= round($pct) ?>%"></div>
                </div>
                <div class="flex justify-between items-center mt-4">
                    <p class="text-slate-900/40 text-[10px] font-extrabold uppercase tracking-[0.2em] font-inter"><?= round($pct) ?>% Optimized</p>
                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-900/5">
                        <span class="w-1.5 h-1.5 rounded-full <?= $bar_cls ?> animate-pulse"></span>
                        <span class="text-[9px] font-extrabold text-slate-900/40 uppercase tracking-tighter">Real-time</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gate Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- ENTRY GATE -->
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-900/[0.03] overflow-hidden ring-1 ring-slate-900/5 group">
                <div class="h-2 bg-emerald-500/20"></div>
                <div class="p-10 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform duration-500 shadow-lg shadow-emerald-500/10">
                        <i class="fa-solid fa-arrow-down text-emerald-600 text-4xl"></i>
                    </div>
                    <h2 class="font-manrope font-extrabold text-2xl text-slate-900 uppercase tracking-[0.15em] mb-3">Entry Terminal</h2>
                    <p class="text-slate-900/40 text-[13px] font-inter font-medium leading-relaxed max-w-xs mb-10">Select vehicle type for automated ticket issuance. Barrier releases upon validation.</p>

                    <div id="ticketStatus" class="min-h-[64px] mb-6 w-full text-center flex items-center justify-center"></div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                        <button class="bg-slate-900 hover:bg-slate-800 text-white font-extrabold font-inter text-[11px] uppercase tracking-[0.2em] rounded-2xl py-5 transition-all flex items-center justify-center gap-3 shadow-xl shadow-slate-900/20 hover:scale-[1.02] active:scale-[0.98]"
                                onclick="cetakTiketOtomatis('car', this)">
                            <i class="fa-solid fa-car text-xl opacity-80"></i>
                            Issue Car
                        </button>
                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold font-inter text-[11px] uppercase tracking-[0.2em] rounded-2xl py-5 transition-all flex items-center justify-center gap-3 shadow-xl shadow-emerald-600/20 hover:scale-[1.02] active:scale-[0.98]"
                                onclick="cetakTiketOtomatis('motorcycle', this)">
                            <i class="fa-solid fa-motorcycle text-xl opacity-80"></i>
                            Issue Moto
                        </button>
                    </div>
                </div>
            </div>

            <!-- EXIT GATE -->
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-900/[0.03] overflow-hidden ring-1 ring-slate-900/5 group">
                <div class="h-2 bg-red-500/20"></div>
                <div class="p-10 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-red-500/10 rounded-2xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform duration-500 shadow-lg shadow-red-500/10">
                        <i class="fa-solid fa-arrow-up text-red-600 text-4xl"></i>
                    </div>
                    <h2 class="font-manrope font-extrabold text-2xl text-slate-900 uppercase tracking-[0.15em] mb-3">Exit Terminal</h2>
                    <p class="text-slate-900/40 text-[13px] font-inter font-medium leading-relaxed max-w-xs mb-6">Scan QR Code or enter manual token for billing & gate clearance.</p>

                    <!-- Barcode Scanner -->
                    <div class="relative w-full mb-8">
                        <div id="reader" class="mx-auto"></div>
                        <div class="barcode-guide">
                            <div class="barcode-frame">
                                <div class="scan-line"></div>
                            </div>
                        </div>
                    </div>

                    <div id="scanned-result" class="min-h-[64px] mb-6 w-full flex items-center justify-center"></div>

                    <!-- Manual input -->
                    <div class="flex w-full gap-3">
                        <input type="text" id="manualCode"
                               class="flex-1 bg-slate-50 border border-slate-900/5 rounded-2xl px-6 py-4.5 text-sm font-extrabold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all uppercase tracking-[0.2em] text-center placeholder-slate-900/20 shadow-inner"
                               placeholder="Ticket Token"
                               autocomplete="off">
                        <button onclick="processTicket(document.getElementById('manualCode').value)"
                                class="bg-red-600 hover:bg-red-700 text-white font-extrabold font-inter text-[11px] uppercase tracking-[0.15em] px-8 rounded-2xl transition-all flex items-center gap-2 shadow-xl shadow-red-600/20 hover:scale-[1.02] active:scale-[0.98]">
                            <i class="fa-solid fa-unlock-keyhole text-sm"></i>
                            EXEC
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
    
    btn.innerHTML = '<i class="fa-solid fa-circle-notch text-xl animate-spin"></i>';
    status.innerHTML = `<div class="flex items-center gap-3 px-6 py-3 bg-slate-900/5 rounded-2xl border border-slate-900/5">
                            <span class="w-2 h-2 rounded-full bg-slate-900 animate-pulse"></span>
                            <p class="text-slate-900 text-[11px] font-extrabold uppercase tracking-widest">Allocating ${type} space...</p>
                        </div>`;

    try {
        const res  = await fetch(`print_ticket.php?auto=1&vtype=${type}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        status.innerHTML = `<div class="flex items-center gap-3 px-6 py-3 bg-emerald-50/10 rounded-2xl border border-emerald-500/20">
                                <i class="fa-solid fa-check-circle text-emerald-500"></i>
                                <p class="text-emerald-700 text-[11px] font-extrabold uppercase tracking-widest">Ticket Ready</p>
                            </div>`;
        window.open(`print_ticket.php?ticket_code=${encodeURIComponent(data.ticket_code)}`, '_blank', 'width=400,height=600');
    } catch (e) {
        status.innerHTML = `<div class="flex items-center gap-3 px-6 py-3 bg-red-50/10 rounded-2xl border border-red-500/20">
                                <i class="fa-solid fa-exclamation-triangle text-red-500"></i>
                                <p class="text-red-700 text-[11px] font-extrabold uppercase tracking-widest">${e.message}</p>
                            </div>`;
    } finally {
        setTimeout(() => {
            buttons.forEach(b => b.disabled = false);
            btn.innerHTML = orig;
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
    { fps: 20, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0, rememberLastUsedCamera: true },
    false
);
html5QrcodeScanner.render(
    decodedText => processTicket(decodedText),
    () => {}
);
</script>

<?php include '../../includes/footer.php'; ?>
