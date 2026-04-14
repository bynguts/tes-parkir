<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

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

$page_title = 'Scan Log Engine';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Gate Activity Log</h4>
            <small class="text-muted">Log forensik aktivitas sensor gate masuk dan keluar secara mendetail.</small>
        </div>
        <div class="d-flex gap-3">
            <input type="text" id="searchLog" class="form-control" style="width:250px; background: rgba(0,0,0,0.2);"
                   placeholder="Pencarian plat / tiket..." oninput="filterLog(this.value)">
            <button class="btn btn-danger d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalHapus" style="border-radius: 8px;">
                <i class="fas fa-trash-alt"></i> Hapus Log
            </button>
        </div>
    </div>

    <div class="glass-panel p-0 mb-4">
        <div class="table-responsive" style="border: none; max-height: 70vh;">
            <table class="table table-glass table-hover mb-0" id="logTable">
                <thead style="position: sticky; top:0; background: var(--sidebar-bg); z-index: 10;">
                    <tr>
                        <th class="ps-4 text-center" width="5%">No</th>
                        <th width="15%">Kode Tiket</th>
                        <th width="15%">Plat Nomor</th>
                        <th width="20%">Timestamp Masuk</th>
                        <th width="20%">Timestamp Keluar</th>
                        <th width="10%">Durasi</th>
                        <th class="pe-4 text-center" width="15%">Status Flow</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-history fs-2 text-muted mb-3 opacity-25"></i><br>Belum ada aktivitas sensor tercatat.</td></tr>
                    <?php else: $no = 1; foreach ($logs as $row):
                        $menit = (int)$row['durasi_menit'];
                        $jam   = floor($menit / 60);
                        $sisa  = $menit % 60;
                        $dur   = $jam > 0 ? "{$jam}j {$sisa}m" : "{$sisa}m";

                        if ($row['status_parkir'] === 'parkir') {
                            $badge = "<span class='badge bg-warning bg-opacity-25 text-warning border border-warning px-3 py-2 w-100' style='border-radius: 20px;'><i class='fas fa-parking me-1'></i> Aktif</span>";
                            $dur_html = "<span class='text-warning fw-bold'>{$dur}</span>";
                            $keluar_str = '<span class="text-muted"><i class="fas fa-minus"></i></span>';
                        } else {
                            $badge = "<span class='badge bg-success bg-opacity-25 text-success border border-success px-3 py-2 w-100' style='border-radius: 20px;'><i class='fas fa-check me-1'></i> Keluar</span>";
                            $dur_html = "<span class='text-muted'>{$dur}</span>";
                            $keluar_str = '<i class="far fa-clock text-muted me-1"></i>' . date('H:i:s, d M', strtotime($row['waktu_keluar']));
                        }
                    ?>
                    <tr>
                        <td class="text-muted text-center ps-4"><?= $no++ ?></td>
                        <td><code class="text-info fs-6 bg-dark bg-opacity-50 px-2 py-1 rounded"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code></td>
                        <td class="fw-bold" style="letter-spacing: 1px;"><?= htmlspecialchars($row['plate_number'] ?? '-') ?></td>
                        <td><i class="fas fa-sign-in-alt text-primary opacity-50 me-2"></i><?= date('H:i:s, d M Y', strtotime($row['waktu_masuk'])) ?></td>
                        <td><?= $keluar_str ?></td>
                        <td><?= $dur_html ?></td>
                        <td class="pe-4 text-center"><?= $badge ?></td>
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
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone: Hapus Log</h5>
                <button type="button" class="btn-close btn-close-white" style="filter: invert(1) grayscale(100%) brightness(200%);" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">
                    Penghapusan data ini bersifat permanen. Data revenue dan analitik yang terkait dengan tanggal tersebut akan ikut terhapus dari sistem.
                </p>

                <ul class="nav nav-pills mb-4 gap-2" id="hapusTabs">
                    <li class="nav-item flex-fill">
                        <button class="nav-link active w-100 text-center" style="border-radius: 8px;" data-bs-toggle="tab" data-bs-target="#tabTanggal">
                            <i class="far fa-calendar-alt me-1"></i> Per Tanggal
                        </button>
                    </li>
                    <li class="nav-item flex-fill">
                        <button class="nav-link w-100 text-center text-danger" style="border-radius: 8px; background: rgba(239, 68, 68, 0.1);" data-bs-toggle="tab" data-bs-target="#tabSemua">
                            <i class="fas fa-dumpster-fire me-1"></i> Wipe All
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabTanggal">
                        <div class="border rounded border-secondary border-opacity-25 p-2 mb-3" id="daftarTanggal" style="max-height: 250px; overflow-y: auto;">
                            <!-- dates load here via JS -->
                        </div>
                        <button class="btn btn-danger w-100 fw-bold py-2" id="btnHapusTanggal" disabled onclick="hapusLog('by_date')" style="border-radius: 8px;">
                            JALANKAN PENGHAPUSAN PARTSIAL
                        </button>
                    </div>

                    <div class="tab-pane fade" id="tabSemua">
                        <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 p-4 rounded text-center">
                            <i class="fas fa-radiation-alt fs-1 text-danger mb-3"></i>
                            <h6 class="text-danger fw-bold">SYSTEM WIPE WARNING</h6>
                            <p class="small text-muted mb-0">Tindakan ini akan <strong>menghancurkan seluruh riwayat</strong> operasional dan analitik. Hanya kendaraan yang berstatus "masih parkir" secara fisik yang tidak akan dihapus dari buffer aktif.</p>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold py-3 mt-2" onclick="hapusLog('all')" style="border-radius: 8px; letter-spacing: 1px;">
                            EKSEKUSI FORMAT TOTAL
                        </button>
                    </div>
                </div>

                <div id="hapusResult" class="mt-3" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
let selectedDate = null;

document.getElementById('modalHapus').addEventListener('show.bs.modal', loadDates);

function loadDates() {
    const c = document.getElementById('daftarTanggal');
    c.innerHTML = '<div class="text-center py-4 text-muted small"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Memindai indeks data...</div>';
    selectedDate = null;
    document.getElementById('btnHapusTanggal').disabled = true;

    fetch('get_log_dates.php')
        .then(r => r.json())
        .then(data => {
            if (!data.length) { c.innerHTML = '<div class="text-center py-4 text-muted small opacity-50"><i class="fas fa-database mb-2 fs-3"></i><br>Index log kosong.</div>'; return; }
            let html = '';
            data.forEach(d => {
                html += `<div class="p-3 mb-2 rounded date-item" style="background: rgba(255,255,255,0.03); cursor: pointer; transition: all 0.2s; border: 1px solid transparent;" 
                              data-date="${d.date}" onclick="selectDate(this,'${d.date}')">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-white"><i class="far fa-calendar-check me-2 text-primary"></i>${d.date}</strong>
                        <span class="text-muted small">${d.day}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-secondary bg-opacity-25 text-light">${d.scan_count} Scan In</span>
                        <span class="badge bg-success bg-opacity-25 text-success">${d.exited} Scan Out</span>
                        <span class="badge bg-warning bg-opacity-25 text-warning">${d.still_parked} Aktif</span>
                    </div>
                </div>`;
            });
            c.innerHTML = html;
        })
        .catch(() => { c.innerHTML = '<div class="text-center py-3 text-danger small">Gagal menghubungi endpoint.</div>'; });
}

function selectDate(el, date) {
    document.querySelectorAll('.date-item').forEach(d => {
        d.style.borderColor = 'transparent';
        d.style.background = 'rgba(255,255,255,0.03)';
    });
    el.style.borderColor = '#EF4444';
    el.style.background = 'rgba(239, 68, 68, 0.1)';
    selectedDate = date;
    document.getElementById('btnHapusTanggal').disabled = false;
}

function hapusLog(mode) {
    const box = document.getElementById('hapusResult');
    box.style.display = 'none';
    if (mode === 'by_date' && !selectedDate) return;

    const konfirm = mode === 'by_date'
        ? `Yakin hapus log operasional tanggal ${selectedDate}?\nRevenue di tanggal tsb jg hilang.`
        : `Yakin format SEMUA data operasional log?`;

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
            box.innerHTML = `<div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 text-success mb-0">✅ <strong>Eksekusi Berhasil</strong><br><small>${data.deleted_scans} record sensor dihapus, ${data.deleted_trx} relasi transaksi putus.</small></div>`;
            setTimeout(() => location.reload(), 1500);
        } else {
            box.innerHTML = `<div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger mb-0">❌ ${data.message}</div>`;
        }
    })
    .catch(() => {
        btns.forEach(b => b.disabled = false);
        box.style.display = 'block';
        box.innerHTML = '<div class="alert alert-danger mb-0">❌ HTTP 500: Server Request Failed.</div>';
    });
}

function filterLog(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#logTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>