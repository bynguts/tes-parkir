<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$summary = get_slot_summary($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Simulator — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body{padding-top:70px;background:#f0f2f5}
        .gate-card{border-radius:14px;background:#fff;box-shadow:0 4px 16px rgba(0,0,0,.08);padding:28px 24px;height:100%}
        .big-btn{font-size:18px;font-weight:700;padding:14px;border-radius:10px;width:100%}
        #reader{width:100%;border-radius:10px;overflow:hidden;border:3px solid #333;background:#000;min-height:200px}
        #scanned-result{min-height:44px;margin-top:12px;font-size:1rem;font-weight:600}
        .slot-badge{border-radius:10px;padding:10px 14px;font-weight:700;font-size:1rem}
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light"><i class="fas fa-arrow-left"></i></button>
        <span class="navbar-brand mb-0 h1">🅿 Smart Parking Gate</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
    </div>
</nav>

<div class="container mt-4">
    <!-- Slot availability -->
    <div class="row g-2 mb-3">
        <?php
        $types = ['car' => '🚗 Mobil', 'motorcycle' => '🏍️ Motor'];
        foreach ($types as $t => $lbl):
            $avail = $summary[$t]['avail'] ?? 0;
            $total = $summary[$t]['total'] ?? 0;
            $pct   = $total > 0 ? ($avail/$total)*100 : 100;
            $cls   = $pct > 30 ? 'bg-success' : ($pct > 10 ? 'bg-warning text-dark' : 'bg-danger');
        ?>
        <div class="col-6">
            <div class="slot-badge d-flex justify-content-between align-items-center <?= $cls ?> text-white">
                <span><?= $lbl ?></span>
                <span class="fw-bold"><?= $avail ?>/<?= $total ?> tersedia</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- ENTRY GATE -->
        <div class="col-md-6">
            <div class="gate-card text-center">
                <div style="font-size:52px" class="mb-2">🟢</div>
                <h4 class="fw-bold text-success mb-1">ENTRY GATE</h4>
                <p class="text-muted small mb-4">Tekan tombol — tiket otomatis tercetak &amp; slot teralokasi.</p>
                <button class="btn btn-success big-btn" onclick="cetakTiketOtomatis(this)">
                    <i class="fas fa-print me-2"></i>CETAK TIKET MASUK
                </button>
                <div id="ticketStatus" class="mt-3"></div>
            </div>
        </div>

        <!-- EXIT GATE -->
        <div class="col-md-6">
            <div class="gate-card text-center">
                <div style="font-size:52px" class="mb-2">🔴</div>
                <h4 class="fw-bold text-danger mb-1">EXIT GATE</h4>
                <p class="text-muted small mb-2">Scan barcode tiket atau ketik kode manual.</p>
                <div id="reader"></div>
                <div id="scanned-result"></div>
                <div class="input-group mt-3">
                    <input type="text" id="manualCode" class="form-control"
                           placeholder="Ketik kode tiket manual…"
                           style="font-family:monospace;text-transform:uppercase"
                           autocomplete="off">
                    <button class="btn btn-danger"
                            onclick="processTicket(document.getElementById('manualCode').value)">
                        <i class="fas fa-sign-out-alt"></i> Proses
                    </button>
                </div>
                <small class="text-muted">Gunakan jika kamera tidak bisa membaca</small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF_TOKEN = '<?= htmlspecialchars(csrf_token()) ?>';

async function cetakTiketOtomatis(btn) {
    const orig = btn.innerHTML;
    const status = document.getElementById('ticketStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>MEMPROSES...';
    status.innerHTML = '<div class="text-info small"><i class="fas fa-cog fa-spin"></i> Menyiapkan tiket...</div>';
    try {
        const res  = await fetch('print_ticket.php?auto=1');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        status.innerHTML = '<div class="text-success small"><i class="fas fa-check"></i> Berhasil! Membuka halaman cetak...</div>';
        window.open(`print_ticket.php?ticket_code=${encodeURIComponent(data.ticket_code)}`, '_blank');
    } catch (e) {
        status.innerHTML = `<div class="text-danger small"><i class="fas fa-exclamation-triangle"></i> Gagal: ${e.message}</div>`;
    } finally {
        setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; status.innerHTML = ''; }, 3000);
    }
}

let scanned = false;
function processTicket(code) {
    if (scanned) return;
    code = (code || '').trim().toUpperCase();
    if (code.length < 4) { alert('Kode tiket tidak valid.'); return; }
    scanned = true;
    document.getElementById('scanned-result').innerHTML =
        `<span class="text-success"><i class="fas fa-check-circle"></i> <strong>${code}</strong></span><br>
         <small class="text-muted">Memproses...</small>`;
    try {
        html5QrcodeScanner.clear().finally(() => {
            window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
        });
    } catch (e) {
        window.location.href = `gate_exit.php?kode_tiket=${encodeURIComponent(code)}`;
    }
}

document.getElementById('manualCode').addEventListener('keydown', e => {
    if (e.key === 'Enter') processTicket(e.target.value);
    else e.target.value = e.target.value.toUpperCase();
});

const html5QrcodeScanner = new Html5QrcodeScanner(
    "reader",
    { fps: 20, qrbox: { width: 220, height: 220 }, rememberLastUsedCamera: true },
    false
);
html5QrcodeScanner.render(
    decodedText => processTicket(decodedText),
    () => {}
);
</script>
</body>
</html>