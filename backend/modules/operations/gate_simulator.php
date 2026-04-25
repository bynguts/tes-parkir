<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$summary = get_slot_summary($pdo);

$page_title = 'Smart Gate Simulator';
$page_subtitle = 'Simulasi sensor entry/exit fisik. Digunakan oleh operator gate atau hardware kiosk.';

include '../../includes/header.php';
?>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<style>
/* Barcode scanner viewport — asymmetric horizontal guide lines */
#reader {
    width: 300px; height: 300px;
    max-width: 100%;
    border-radius: 20px;
    overflow: hidden;
    background: #0b1120;
    margin: 20px auto;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
#reader video {
    border-radius: 14px;
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
    border: 3px solid rgba(255,255,255,0.8);
    border-radius: 24px;
    position: relative;
}
.scan-line {
    position: absolute; left: 10%; right: 10%; height: 3px;
    background: #3b82f6;
    animation: scanline 2.5s ease-in-out infinite;
    box-shadow: 0 0 15px #3b82f6;
    border-radius: 2px;
    z-index: 20;
}
@keyframes scanline {
    0%, 100% { top: 10%; opacity: 0.3; }
    50% { top: 90%; opacity: 1; }
}
#reader select { display: none !important; }
#reader span { display: none !important; }
#html5-qrcode-button-camera-start,
#html5-qrcode-button-camera-stop {
    background: #0f172a !important; color: white !important;
    border: none !important; border-radius: 8px !important;
    padding: 8px 16px !important; margin: 10px !important;
    font-family: 'Inter', sans-serif !important; font-size: 12px !important;
}
</style>

    <div class="p-10 max-w-[1440px] mx-auto">

        <!-- Slot Availability -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <?php
            $types = [
                'car'        => ['directions_car', 'MOBIL'],
                'motorcycle' => ['two_wheeler',    'MOTOR'],
            ];
            foreach ($types as $t => $cfg):
                $icon  = $cfg[0];
                $lbl   = $cfg[1];
                $avail = $summary[$t]['avail'] ?? 0;
                $total = $summary[$t]['total'] ?? 0;
                $pct   = $total > 0 ? ($avail/$total)*100 : 100;
                $pct_cls = $pct > 30 ? 'text-emerald-600' : ($pct > 10 ? 'text-amber-600' : 'text-red-600');
                $bar_cls  = $pct > 30 ? 'bg-emerald-500' : ($pct > 10 ? 'bg-amber-400' : 'bg-red-500');
            ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot <?= $lbl ?></p>
                    <div class="w-10 h-10 rounded-xl <?= $t === 'car' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' ?> flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl"><?= $icon ?></span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-4xl <?= $pct_cls ?>"><?= $avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $total ?> tersedia</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all <?= $bar_cls ?>" style="width:<?= round($pct) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gate Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- ENTRY GATE -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="h-1.5 bg-emerald-500"></div>
                <div class="p-8 flex flex-col items-center text-center">
                    <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mb-5">
                        <span class="material-symbols-outlined text-emerald-600 text-3xl">south</span>
                    </div>
                    <h2 class="font-manrope font-extrabold text-xl text-slate-900 uppercase tracking-widest mb-2">Entry Gate</h2>
                    <p class="text-slate-400 text-sm font-inter mb-6">Tekan tombol di bawah untuk mencetak tiket otomatis. Palang akan terbuka saat tiket keluar.</p>

                    <div id="ticketStatus" class="min-h-[48px] mb-4 w-full text-center"></div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full mb-6">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all flex items-center justify-center gap-2"
                                onclick="cetakTiketOtomatis('car', this)">
                            <span class="material-symbols-outlined text-xl">directions_car</span>
                            Karcis Mobil
                        </button>
                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold font-inter text-[11px] uppercase tracking-widest rounded-xl py-4 transition-all flex items-center justify-center gap-2"
                                onclick="cetakTiketOtomatis('motorcycle', this)">
                            <span class="material-symbols-outlined text-xl">two_wheeler</span>
                            Karcis Motor
                        </button>
                    </div>

                    <!-- OCR Simulator for Reservations -->
                    <div class="w-full pt-6 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-4">AI Plate Recognition</p>
                        
                        <div class="flex gap-2 mb-4">
                            <input type="text" id="ocrPlateInput" 
                                   class="flex-1 bg-slate-100 border-none rounded-xl px-4 py-3 text-sm font-bold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary transition-all uppercase"
                                   placeholder="Manual Plate Input">
                            <button onclick="processPlate(document.getElementById('ocrPlateInput').value, this)"
                                    class="bg-slate-900 hover:bg-black text-white px-4 rounded-xl transition-all flex items-center justify-center">
                                <span class="material-symbols-outlined">check_circle</span>
                            </button>
                        </div>

                        <div id="ocrUploadArea" 
                             onclick="document.getElementById('plateImageInput').click()"
                             class="border-2 border-dashed border-slate-200 rounded-2xl p-8 cursor-pointer hover:border-primary hover:bg-primary/5 transition-all group flex flex-col items-center gap-3">
                            <span class="material-symbols-outlined text-4xl text-slate-300 group-hover:text-primary transition-colors">photo_camera</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 group-hover:text-primary">Upload Foto Plat Nomor</span>
                            <input type="file" id="plateImageInput" accept="image/*" class="hidden" onchange="handlePlateUpload(this)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXIT GATE -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="h-1.5 bg-red-500"></div>
                <div class="p-8 flex flex-col items-center text-center">
                    <div class="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mb-5">
                        <span class="material-symbols-outlined text-red-600 text-3xl">north</span>
                    </div>
                    <h2 class="font-manrope font-extrabold text-xl text-slate-900 uppercase tracking-widest mb-2">Exit Gate</h2>
                    <p class="text-slate-400 text-sm font-inter mb-4">Arahkan barcode tiket ke scanner atau masukkan token karcis secara manual.</p>

                    <!-- Barcode Scanner -->
                    <div class="relative w-full mb-4">
                        <div id="reader" class="mx-auto"></div>
                        <div class="barcode-guide">
                            <div class="barcode-frame">
                                <div class="scan-line"></div>
                            </div>
                        </div>
                    </div>

                    <div id="scanned-result" class="min-h-[48px] mb-4 w-full"></div>

                    <!-- Manual input -->
                    <div class="flex w-full gap-2">
                        <input type="text" id="manualCode"
                               class="flex-1 bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-bold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all uppercase tracking-widest text-center"
                               placeholder="Enter Token Karcis Parkir"
                               autocomplete="off">
                        <button onclick="processTicket(document.getElementById('manualCode').value)"
                                class="bg-red-600 hover:bg-red-700 text-white font-bold font-inter text-xs uppercase tracking-widest px-5 rounded-full transition-all flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">logout</span>
                            Exec
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

<script>
async function handlePlateUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const status = document.getElementById('ticketStatus');
    const area = document.getElementById('ocrUploadArea');
    
    // Visual feedback
    const origHTML = area.innerHTML;
    area.innerHTML = `
        <div class="flex flex-col items-center gap-3">
            <span class="material-symbols-outlined text-4xl text-primary animate-spin">progress_activity</span>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary">Scanning Image...</span>
        </div>`;
    status.innerHTML = `<p class="text-slate-500 text-sm font-inter animate-pulse">Running AI OCR on uploaded image...</p>`;

    try {
        // Real OCR with Tesseract.js
        const result = await Tesseract.recognize(file, 'eng', {
            logger: m => console.log(m)
        });
        
        // Clean the OCR result (remove non-alphanumeric, etc.)
        const cleanedText = result.data.text.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        console.log("OCR Result:", cleanedText);
        
        if (cleanedText) {
            document.getElementById('ocrPlateInput').value = cleanedText;
            await processPlate(cleanedText, area);
        } else {
            throw new Error("Could not detect any text in the image.");
        }
    } catch (e) {
        status.innerHTML = `<div class="flex items-center justify-center gap-2 text-red-600 text-sm font-inter"><span class="material-symbols-outlined text-base">error</span> OCR Failed: ${e.message}</div>`;
    } finally {
        area.innerHTML = origHTML;
        input.value = ''; // reset
    }
}

async function processPlate(plate, btn) {
    const status = document.getElementById('ticketStatus');
    if (!plate) { alert('Masukkan plat nomor!'); return; }

    btn.disabled = true;
    status.innerHTML = `<p class="text-slate-500 text-sm font-inter animate-pulse">Checking reservation for ${plate}...</p>`;

    try {
        const res = await fetch('../../api/ocr_check.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plat_nomor: plate })
        });
        const data = await res.json();
        
        if (data.success && data.should_open_gate) {
            status.innerHTML = `<div class="flex items-center justify-center gap-2 text-emerald-600 text-sm font-inter font-bold">
                <span class="material-symbols-outlined text-base">check_circle</span> ${data.message}
            </div>`;
            alert("GATE OPENING: Welcome " + plate + "!");
        } else {
            status.innerHTML = `<div class="flex items-center justify-center gap-2 text-red-600 text-sm font-inter font-bold">
                <span class="material-symbols-outlined text-base">cancel</span> ${data.message || data.error}
            </div>`;
        }
    } catch (e) {
        status.innerHTML = `<div class="flex items-center justify-center gap-2 text-red-600 text-sm font-inter"><span class="material-symbols-outlined text-base">error</span> ${e.message}</div>`;
    } finally {
        btn.disabled = false;
        setTimeout(() => { if(status.innerHTML.includes(plate)) status.innerHTML = ''; }, 5000);
    }
}

async function cetakTiketOtomatis(type, btn) {
    const orig = btn.innerHTML;
    const status = document.getElementById('ticketStatus');
    
    // Disable all buttons during processing
    const buttons = btn.parentElement.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);
    
    btn.innerHTML = '<span class="material-symbols-outlined text-xl animate-spin">autorenew</span> ...';
    status.innerHTML = `<p class="text-slate-500 text-sm font-inter animate-pulse">Menyiapkan slot ${type === 'car' ? 'Mobil' : 'Motor'}...</p>`;

    try {
        const res  = await fetch(`print_ticket.php?auto=1&vtype=${type}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        status.innerHTML = '<div class="flex items-center justify-center gap-2 text-emerald-600 text-sm font-inter font-bold"><span class="material-symbols-outlined text-base">check_circle</span> Tiket tervalidasi!</div>';
        window.open(`print_ticket.php?ticket_code=${encodeURIComponent(data.ticket_code)}`, '_blank', 'width=400,height=600');
    } catch (e) {
        status.innerHTML = `<div class="flex items-center justify-center gap-2 text-red-600 text-sm font-inter"><span class="material-symbols-outlined text-base">error</span> ${e.message}</div>`;
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
    if (code.length < 4) { alert('Kode tiket tidak valid.'); return; }
    scanned = true;

    document.getElementById('scanned-result').innerHTML =
        `<div class="flex items-center justify-center gap-2 bg-emerald-50 rounded-xl px-4 py-3 text-emerald-700 font-code font-bold text-sm">
            <span class="material-symbols-outlined text-base">barcode_reader</span>${code}
         </div>
         <p class="text-slate-400 text-xs font-inter mt-2 animate-pulse">Otentikasi & Kalkulasi billing...</p>`;

    setTimeout(() => {
        try {
            html5QrcodeScanner.clear().finally(() => {
                window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
            });
        } catch (e) {
            window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
        }
    }, 1000);
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
