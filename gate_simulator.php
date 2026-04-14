<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$summary = get_slot_summary($pdo);

$page_title = 'Smart Gate Simulator';
include 'includes/header.php';
?>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<style>
    .gate-card { 
        border-radius: 16px; 
        background: var(--card-bg); 
        backdrop-filter: blur(12px);
        padding: 40px; 
        height: 100%;
        border: 1px solid var(--border-glass);
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        transition: transform 0.3s ease;
    }
    .gate-card:hover { transform: translateY(-5px); }
    
    .big-btn { 
        font-size: 16px; 
        font-weight: 700; 
        padding: 18px; 
        border-radius: 12px; 
        width: 100%;
        letter-spacing: 1px;
        text-transform: uppercase;
        transition: all 0.3s;
    }
    
    #reader { 
        width: 100%; max-width: 380px; 
        border-radius: 16px; 
        overflow: hidden; 
        border: 2px dashed rgba(255,255,255,0.2) !important; 
        background: rgba(0,0,0,0.3); 
        min-height: 250px;
        margin: 20px auto;
    }
    #reader video { border-radius: 14px; object-fit: cover; }
    #html5-qrcode-button-camera-start, #html5-qrcode-button-camera-stop {
        background: var(--primary) !important; color: white !important;
        border: none !important; border-radius: 8px !important;
        padding: 8px 16px !important; margin: 10px !important;
    }
    
    .status-light {
        width: 80px; height: 80px;
        border-radius: 50%;
        margin: 0 auto 24px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.5), 0 0 30px currentColor;
    }
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Smart Gate Interface</h4>
            <small class="text-muted">Simulasi sensor entry/exit fisik. Digunakan oleh operator gate atau hardware kiosk.</small>
        </div>
    </div>

    <!-- Slot availability -->
    <div class="row g-4 mb-4">
        <?php
        $types = ['car' => ['🚗 Mobil', 'primary'], 'motorcycle' => ['🏍️ Motor', 'success']];
        foreach ($types as $t => $cfg):
            $lbl = $cfg[0]; $color = $cfg[1];
            $avail = $summary[$t]['avail'] ?? 0;
            $total = $summary[$t]['total'] ?? 0;
            $pct   = $total > 0 ? ($avail/$total)*100 : 100;
            $cls   = $pct > 30 ? $color : ($pct > 10 ? 'warning' : 'danger');
        ?>
        <div class="col-md-6">
            <div class="glass-panel d-flex justify-content-between align-items-center p-4 border border-<?= $cls ?> border-opacity-25" style="background: rgba(var(--bs-<?= $cls ?>-rgb), 0.05);">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-<?= $cls ?> opacity-75 fs-2"><?= explode(' ', $lbl)[0] ?></div>
                    <div>
                        <h5 class="mb-0 text-white fw-bold"><?= explode(' ', $lbl)[1] ?> Capacity</h5>
                        <div class="text-muted small">Live allocation tracking</div>
                    </div>
                </div>
                <div class="text-end">
                    <h2 class="mb-0 text-<?= $cls ?>-glow fw-bold"><?= $avail ?><span class="fs-5 text-muted ms-1">/ <?= $total ?></span></h2>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-5">
        <!-- ENTRY GATE -->
        <div class="col-md-6">
            <div class="gate-card" style="border-top: 4px solid var(--success);">
                <div class="status-light text-success bg-dark" style="color: var(--success);">
                    <i class="fas fa-arrow-down fs-1"></i>
                </div>
                <h4 class="fw-bold text-white mb-2" style="letter-spacing: 2px;">ENTRY GATE</h4>
                <p class="text-muted small mb-4 px-4">Tekan tombol di bawah untuk mencetak tiket otomatis. Palang akan terbuka saat tiket keluar.</p>
                
                <div class="mt-auto w-100">
                    <button class="btn btn-success big-btn shadow-lg" onclick="cetakTiketOtomatis(this)">
                        <i class="fas fa-ticket-alt me-2 fs-5"></i> OTOMATISASI TIKET MASUK
                    </button>
                    <div id="ticketStatus" class="mt-4" style="min-height: 40px;"></div>
                </div>
            </div>
        </div>

        <!-- EXIT GATE -->
        <div class="col-md-6">
            <div class="gate-card" style="border-top: 4px solid var(--danger);">
                <div class="status-light text-danger bg-dark" style="color: var(--danger);">
                    <i class="fas fa-arrow-up fs-1"></i>
                </div>
                <h4 class="fw-bold text-white mb-2" style="letter-spacing: 2px;">EXIT GATE</h4>
                <p class="text-muted small mb-4 px-4">Arahkan barcode tiket ke scanner atau input PIN tiket pada keypad untuk membuka palang.</p>
                
                <div id="reader"></div>
                <div id="scanned-result" class="my-3 text-center" style="min-height: 50px;"></div>
                
                <div class="input-group mt-auto w-100 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                    <span class="input-group-text bg-dark border-0 text-muted"><i class="fas fa-keyboard"></i></span>
                    <input type="text" id="manualCode" class="form-control bg-dark border-0 text-white font-monospace fs-5 text-center"
                           placeholder="KODE MANUAL"
                           style="letter-spacing: 2px;"
                           autocomplete="off">
                    <button class="btn btn-danger px-4"
                            onclick="processTicket(document.getElementById('manualCode').value)">
                        <i class="fas fa-sign-out-alt"></i> EXEC
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function cetakTiketOtomatis(btn) {
    const orig = btn.innerHTML;
    const status = document.getElementById('ticketStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin me-2"></i>MENCETAK...';
    btn.classList.add('opacity-75');
    status.innerHTML = '<div class="text-info small fw-bold"><i class="fas fa-cog fa-spin me-2"></i> Sistem sedang mengalokasikan slot...</div>';
    
    try {
        const res  = await fetch('print_ticket.php?auto=1');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        status.innerHTML = '<div class="text-success small fw-bold"><i class="fas fa-check-circle me-2"></i> Tiket berhasil dicetak!</div>';
        window.open(`print_ticket.php?ticket_code=${encodeURIComponent(data.ticket_code)}`, '_blank', 'width=400,height=600');
    } catch (e) {
        status.innerHTML = `<div class="text-danger small fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Error: ${e.message}</div>`;
    } finally {
        setTimeout(() => { 
            btn.disabled = false; 
            btn.innerHTML = orig; 
            btn.classList.remove('opacity-75');
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
        `<div class="badge bg-success bg-opacity-25 text-success border border-success p-3 rounded-3 mb-2 fs-5 w-100">
            <i class="fas fa-qrcode me-2"></i> <span class="font-monospace">${code}</span>
         </div>
         <div class="spinner-border spinner-border-sm text-muted mt-2"></div> <small class="text-muted ms-1">Otentikasi & Kalkulasi billing...</small>`;
         
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
    { fps: 20, qrbox: { width: 250, height: 250 }, rememberLastUsedCamera: true },
    false
);
html5QrcodeScanner.render(
    decodedText => processTicket(decodedText),
    () => {}
);
</script>

<?php include 'includes/footer.php'; ?>