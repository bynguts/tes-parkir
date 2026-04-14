<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

// scan_log query hanya menggunakan plate_scan_log — tidak ada JOIN ke parking_slot,
// jadi tidak ada perubahan 3NF di sini. File ini sudah benar.
$logs = $pdo->query("
    SELECT
        e.ticket_code,
        e.scan_time        AS waktu_masuk,
        x.scan_time        AS waktu_keluar,
        e.plate_number,
        TIMESTAMPDIFF(MINUTE, e.scan_time, IFNULL(x.scan_time, NOW())) AS durasi_menit,
        CASE WHEN x.scan_id IS NOT NULL THEN 'keluar' ELSE 'parkir' END AS status_parkir
    FROM plate_scan_log e
    LEFT JOIN plate_scan_log x
        ON x.ticket_code = e.ticket_code AND x.scan_type = 'exit'
    WHERE e.scan_type = 'entry' AND e.gate_action = 'open'
    ORDER BY e.scan_time DESC
    LIMIT 200
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Log — Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; padding-top: 70px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .status-parkir { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .status-keluar { background: #d1e7dd; color: #0f5132; border: 1px solid #198754; }
        .duration-badge { font-size: 11px; color: #6c757d; }
        .modal-dates-list { max-height: 280px; overflow-y: auto; }
        .date-item { cursor: pointer; transition: background .15s; }
        .date-item:hover { background: #f8f9fa; }
        .date-item.selected { background: #fff3cd; border-left: 3px solid #ffc107; }
        .table th { font-size: 13px; }
        .table td { font-size: 13px; vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid d-flex justify-content-between">
        <button onclick="history.back()" class="btn btn-outline-light btn-sm">←</button>
        <span class="navbar-brand mb-0 h1">📹 Gate Activity Log</span>
        <div class="d-flex gap-2">
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapus">
                <i class="fas fa-trash me-1"></i>Hapus
            </button>
            <a href="index.php" class="btn btn-outline-light btn-sm">Menu</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Riwayat Scan (<?= count($logs) ?> entri)</h5>
        <input type="text" id="searchLog" class="form-control form-control-sm w-auto"
               placeholder="Cari tiket/plat..." oninput="filterLog(this.value)" style="width:200px!important">
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-striped text-center align-middle mb-0" id="logTable">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tiket</th>
                        <th>Plat</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Durasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data log.</td></tr>
                    <?php else: $no = 1; foreach ($logs as $row):
                        $menit = (int)$row['durasi_menit'];
                        $jam   = floor($menit / 60);
                        $sisa  = $menit % 60;
                        $dur   = $jam > 0 ? "{$jam}j {$sisa}m" : "{$sisa}m";

                        if ($row['status_parkir'] === 'parkir') {
                            $badge = "<span class='badge status-parkir px-2 py-1'>🟡 Masih Parkir</span>";
                            $dur_html = "<span class='text-warning fw-bold'>{$dur}</span><br><span class='duration-badge'>(berjalan)</span>";
                            $keluar_str = '-';
                        } else {
                            $badge = "<span class='badge status-keluar px-2 py-1'>✅ Sudah Keluar</span>";
                            $dur_html = $dur;
                            $keluar_str = htmlspecialchars($row['waktu_keluar']);
                        }
                    ?>
                    <tr>
                        <td class="text-muted"><?= $no++ ?></td>
                        <td><code class="fw-bold"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code></td>
                        <td class="fw-semibold"><?= htmlspecialchars($row['plate_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['waktu_masuk']) ?></td>
                        <td><?= $keluar_str ?></td>
                        <td><?= $dur_html ?></td>
                        <td><?= $badge ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: Hapus Riwayat -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-trash me-2"></i>Hapus Riwayat Log</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Pilih tanggal yang ingin dihapus, atau hapus semua sekaligus.<br>
                    <strong class="text-danger">⚠️ Data revenue juga akan terpengaruh.</strong>
                </p>

                <ul class="nav nav-tabs mb-3" id="hapusTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabTanggal">
                            📅 Pilih Tanggal
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#tabSemua">
                            🗑️ Hapus Semua
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabTanggal">
                        <p class="small text-muted mb-2">Tanggal yang memiliki data log:</p>
                        <div class="modal-dates-list border rounded" id="daftarTanggal">
                            <div class="text-center py-3 text-muted small">
                                <div class="spinner-border spinner-border-sm me-2"></div>Memuat...
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-danger w-100" id="btnHapusTanggal" disabled onclick="hapusLog('by_date')">
                                Hapus Tanggal Dipilih
                            </button>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabSemua">
                        <div class="alert alert-danger">
                            <strong>⚠️ Peringatan!</strong><br>
                            Ini akan menghapus seluruh riwayat scan, transaksi selesai (paid), dan tiket yang sudah digunakan.<br>
                            <small>Kendaraan yang masih parkir (unpaid) tidak akan dihapus.</small>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold" onclick="hapusLog('all')">
                            <i class="fas fa-trash me-2"></i>HAPUS SEMUA RIWAYAT
                        </button>
                    </div>
                </div>

                <div id="hapusResult" class="mt-3" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
let selectedDate = null;

document.getElementById('modalHapus').addEventListener('show.bs.modal', loadDates);

function loadDates() {
    const c = document.getElementById('daftarTanggal');
    c.innerHTML = '<div class="text-center py-3 text-muted small"><div class="spinner-border spinner-border-sm me-2"></div>Memuat...</div>';
    selectedDate = null;
    document.getElementById('btnHapusTanggal').disabled = true;

    fetch('get_log_dates.php')
        .then(r => r.json())
        .then(data => {
            if (!data.length) { c.innerHTML = '<div class="text-center py-3 text-muted small">Tidak ada data log.</div>'; return; }
            let html = '';
            data.forEach(d => {
                html += `<div class="date-item d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
                              data-date="${d.date}" onclick="selectDate(this,'${d.date}')">
                    <div><strong>${d.date}</strong><span class="text-muted small ms-2">${d.day}</span></div>
                    <div>
                        <span class="badge bg-secondary">${d.scan_count} scan</span>
                        <span class="badge bg-success ms-1">${d.exited} keluar</span>
                        <span class="badge bg-warning text-dark ms-1">${d.still_parked} parkir</span>
                    </div>
                </div>`;
            });
            c.innerHTML = html;
        })
        .catch(() => { c.innerHTML = '<div class="text-center py-3 text-danger small">Gagal memuat data.</div>'; });
}

function selectDate(el, date) {
    document.querySelectorAll('.date-item').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');
    selectedDate = date;
    document.getElementById('btnHapusTanggal').disabled = false;
}

function hapusLog(mode) {
    const box = document.getElementById('hapusResult');
    box.style.display = 'none';
    if (mode === 'by_date' && !selectedDate) return;

    const konfirm = mode === 'by_date'
        ? `Yakin hapus log tanggal ${selectedDate}?\n\nData revenue tanggal tersebut juga akan dihapus.`
        : `Yakin hapus SEMUA riwayat log?\n\nSemua transaksi selesai dan revenue akan dihapus.\n(Kendaraan masih parkir tidak akan dihapus)`;

    if (!confirm(konfirm)) return;

    const btns = document.querySelectorAll('#modalHapus button');
    btns.forEach(b => b.disabled = true);

    const body = mode === 'by_date'
        ? `mode=by_date&date=${selectedDate}&csrf_token=${encodeURIComponent(CSRF)}`
        : `mode=all&csrf_token=${encodeURIComponent(CSRF)}`;

    fetch('delete_logs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    })
    .then(r => r.json())
    .then(data => {
        btns.forEach(b => b.disabled = false);
        box.style.display = 'block';
        if (data.success) {
            box.innerHTML = `<div class="alert alert-success mb-0">✅ <strong>${data.message}</strong><br><small>${data.deleted_scans} scan dihapus, ${data.deleted_trx} transaksi dihapus.</small></div>`;
            setTimeout(() => location.reload(), 1500);
        } else {
            box.innerHTML = `<div class="alert alert-danger mb-0">❌ ${data.message}</div>`;
        }
    })
    .catch(() => {
        btns.forEach(b => b.disabled = false);
        box.style.display = 'block';
        box.innerHTML = '<div class="alert alert-danger mb-0">❌ Gagal menghubungi server.</div>';
    });
}

function filterLog(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#logTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>