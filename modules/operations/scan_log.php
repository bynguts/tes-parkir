<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$logs = $pdo->query("
    SELECT
        e.ticket_code,
        e.scan_time        AS waktu_masuk,
        x.scan_time        AS waktu_keluar,
        TIMESTAMPDIFF(MINUTE, e.scan_time, IFNULL(x.scan_time, NOW())) AS durasi_menit,
        CASE WHEN x.scan_id IS NOT NULL THEN 'keluar' ELSE 'parkir' END AS status_parkir
    FROM plate_scan_log e
    LEFT JOIN plate_scan_log x
        ON x.ticket_code = e.ticket_code AND x.scan_type = 'exit'
    WHERE e.scan_type = 'entry' AND e.gate_action = 'open'
    ORDER BY e.scan_time DESC
    LIMIT 200
")->fetchAll();

$page_title = 'Security Scan History';
$page_subtitle = 'Forensic logs for entry/exit gate sensor activity.';
$page_actions = '
<div class="flex items-center gap-3">
    <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" id="searchLog" placeholder="Search ticket..."
               oninput="filterLog(this.value)"
               class="bg-slate-100 border-none rounded-full pl-10 pr-5 py-2.5 text-sm font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all w-56">
    </div>
    <button onclick="document.getElementById(\'modalHapus\').classList.remove(\'hidden\')"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all">
        <i class="fa-solid fa-broom text-base"></i>
        Purge History
    </button>
</div>';

include '../../includes/header.php';
?>

    <div class="p-8">
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-auto max-h-[72vh] no-scrollbar">
                <table class="w-full" id="logTable">
                    <thead class="sticky top-0 bg-white z-10">
                        <tr class="border-b border-slate-100">
                            <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter w-12">No</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Ticket Code</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Entry Timestamp</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Exit Timestamp</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Duration</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-16">
                                <i class="fa-solid fa-clock-rotate-left text-5xl text-slate-200 block mb-3"></i>
                                <p class="text-slate-400 text-sm font-inter">No sensor activity logs detected.</p>
                            </td>
                        </tr>
                        <?php else: $no = 1; foreach ($logs as $row):
                            $menit = (int)$row['durasi_menit'];
                            $jam   = floor($menit / 60);
                            $sisa  = $menit % 60;
                            $dur   = $jam > 0 ? "{$jam}h {$sisa}m" : "{$sisa}m";
                            $is_aktif = $row['status_parkir'] === 'parkir';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-slate-400 text-sm font-inter"><?= $no++ ?></td>
                            <td class="px-4 py-4">
                                <code class="font-code text-sm text-slate-800 bg-slate-100 px-3 py-1 rounded-lg font-bold transition-all hover:bg-slate-200"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code>
                            </td>
                            <td class="px-4 py-4 text-slate-600 text-sm font-inter">
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-right-to-bracket text-blue-400 text-sm"></i>
                                    <?= date('H:i:s, d M Y', strtotime($row['waktu_masuk'])) ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-600 text-sm font-inter">
                                <?php if ($is_aktif): ?>
                                    <span class="text-slate-400">—</span>
                                <?php else: ?>
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-right-from-bracket text-emerald-400 text-sm"></i>
                                        <?= date('H:i:s, d M Y', strtotime($row['waktu_keluar'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4">
                                <?php if ($is_aktif): ?>
                                    <span class="text-amber-600 text-sm font-bold font-inter"><?= $dur ?></span>
                                <?php else: ?>
                                    <span class="text-slate-600 text-sm font-inter"><?= $dur ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <?php if ($is_aktif): ?>
                                    <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 text-xs font-bold font-inter px-3 py-1 rounded-full">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-xs font-bold font-inter px-3 py-1 rounded-full">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i>
                                        Exited
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- MODAL: Hapus Riwayat (Tailwind) -->
<div id="modalHapus" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-trash-can text-red-500 text-lg"></i>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Danger Zone: Purge History</h2>
            </div>
            <button onclick="document.getElementById('modalHapus').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="px-6 py-5">
            <p class="text-slate-500 text-sm font-inter mb-5">Deleting this data is permanent. Revenue data related to these logs may also be affected.</p>

            <!-- Tabs -->
            <div class="flex gap-2 mb-5">
                <button id="tabBtnDate" onclick="switchTab('date')"
                        class="flex-1 text-xs font-bold font-inter uppercase tracking-widest py-2 rounded-xl bg-slate-900 text-white transition-all">
                    By Date
                </button>
                <button id="tabBtnAll" onclick="switchTab('all')"
                        class="flex-1 text-xs font-bold font-inter uppercase tracking-widest py-2 rounded-xl bg-slate-100 text-red-600 transition-all">
                    Wipe All
                </button>
            </div>

            <div id="tabDate">
                <div id="daftarTanggal" class="max-h-52 overflow-y-auto no-scrollbar rounded-xl bg-slate-50 p-2 mb-4 space-y-1"></div>
                <button id="btnHapusTanggal" disabled onclick="hapusLog('by_date')"
                        class="w-full bg-red-600 text-white text-xs font-bold font-inter uppercase tracking-widest py-3 rounded-xl disabled:opacity-40 transition-all">
                    Delete Selected Date
                </button>
            </div>

            <div id="tabAll" class="hidden">
                <div class="bg-red-50 rounded-xl p-4 mb-4 text-center">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 text-4xl block mb-2"></i>
                    <p class="text-red-700 font-bold text-sm font-inter">SYSTEM WIPE WARNING</p>
                    <p class="text-slate-500 text-xs font-inter mt-1">This action will destroy ALL recorded operational history.</p>
                </div>
                <button onclick="hapusLog('all')"
                        class="w-full bg-red-600 text-white text-xs font-bold font-inter uppercase tracking-widest py-3 rounded-xl transition-all">
                    Execute Total Format
                </button>
            </div>

            <div id="hapusResult" class="mt-4 hidden text-sm font-inter"></div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
let selectedDate = null;

function switchTab(tab) {
    document.getElementById('tabDate').classList.toggle('hidden', tab !== 'date');
    document.getElementById('tabAll').classList.toggle('hidden', tab !== 'all');
    document.getElementById('tabBtnDate').className = `flex-1 text-xs font-bold font-inter uppercase tracking-widest py-2 rounded-xl transition-all ${tab==='date' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}`;
    document.getElementById('tabBtnAll').className  = `flex-1 text-xs font-bold font-inter uppercase tracking-widest py-2 rounded-xl transition-all ${tab==='all'  ? 'bg-red-600 text-white' : 'bg-slate-100 text-red-600'}`;
}

document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});

// Load dates when modal opens
const observer = new MutationObserver(() => {
    if (!document.getElementById('modalHapus').classList.contains('hidden')) loadDates();
});
observer.observe(document.getElementById('modalHapus'), { attributes: true, attributeFilter: ['class'] });

function loadDates() {
    const c = document.getElementById('daftarTanggal');
    c.innerHTML = '<div class="text-center py-4 text-slate-400 text-sm animate-pulse">Scanning index...</div>';
    selectedDate = null;
    document.getElementById('btnHapusTanggal').disabled = true;

    fetch('get_log_dates.php')
        .then(r => r.json())
        .then(data => {
            if (!data.length) { c.innerHTML = '<div class="text-center py-4 text-slate-400 text-sm">Index empty.</div>'; return; }
            let html = '';
            data.forEach(d => {
                html += `<div class="date-item flex justify-between items-center px-4 py-3 rounded-xl cursor-pointer hover:bg-slate-100 transition-all" data-date="${d.date}" onclick="selectDate(this,'${d.date}')">
                    <div>
                        <div class="font-inter font-bold text-sm text-slate-800">${d.date}</div>
                        <div class="text-slate-400 text-xs font-inter mt-0.5">${d.day}</div>
                    </div>
                    <div class="flex gap-2 text-xs font-inter">
                        <span class="bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full">${d.scan_count} In</span>
                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">${d.exited} Out</span>
                    </div>
                </div>`;
            });
            c.innerHTML = html;
        })
        .catch(() => { c.innerHTML = '<div class="text-center py-3 text-red-500 text-sm">Connection failure.</div>'; });
}

function selectDate(el, date) {
    document.querySelectorAll('.date-item').forEach(d => {
        d.classList.remove('bg-red-50', 'ring-2', 'ring-red-400');
    });
    el.classList.add('bg-red-50', 'ring-2', 'ring-red-400');
    selectedDate = date;
    document.getElementById('btnHapusTanggal').disabled = false;
}

function hapusLog(mode) {
    const box = document.getElementById('hapusResult');
    box.classList.add('hidden');
    if (mode === 'by_date' && !selectedDate) return;

    const konfirm = mode === 'by_date'
        ? `Are you sure you want to delete logs for ${selectedDate}?`
        : `Are you sure you want to WIPE ALL operational logs?`;
    if (!confirm(konfirm)) return;

    const body = mode === 'by_date'
        ? `mode=by_date&date=${selectedDate}&csrf_token=${encodeURIComponent(CSRF)}`
        : `mode=all&csrf_token=${encodeURIComponent(CSRF)}`;

    fetch('delete_logs.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
    .then(r => r.json())
    .then(data => {
        box.classList.remove('hidden');
        if (data.success) {
            box.className = 'mt-4 flex items-center gap-2 text-emerald-700 font-inter text-sm';
            box.innerHTML = `<i class="fa-solid fa-circle-check text-base"></i> Success: ${data.deleted_scans} records purged.`;
            setTimeout(() => location.reload(), 1500);
        } else {
            box.className = 'mt-4 flex items-center gap-2 text-red-600 font-inter text-sm';
            box.innerHTML = `<i class="fa-solid fa-circle-exclamation text-base"></i> ${data.message}`;
        }
    })
    .catch(() => {
        box.classList.remove('hidden');
        box.className = 'mt-4 text-red-600 font-inter text-sm';
        box.innerHTML = '❌ HTTP 500: Server Request Failed.';
    });
}

function filterLog(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#logTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
