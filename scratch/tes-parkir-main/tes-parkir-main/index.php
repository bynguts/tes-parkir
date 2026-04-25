<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';

$summary   = get_slot_summary($pdo);
$car_avail = $summary['car']['avail'] ?? 0;
$car_total = $summary['car']['total'] ?? 0;
$moto_avail = $summary['motorcycle']['avail'] ?? 0;
$moto_total = $summary['motorcycle']['total'] ?? 0;

$car_pct  = $car_total  > 0 ? round(($car_avail  / $car_total)  * 100) : 100;
$moto_pct = $moto_total > 0 ? round(($moto_avail / $moto_total) * 100) : 100;

$active = $pdo->query("SELECT COUNT(*) FROM `transaction` WHERE payment_status='unpaid'")->fetchColumn();
$today_rev = $pdo->query("SELECT COALESCE(SUM(total_fee),0) FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=CURDATE()")->fetchColumn();

$page_title = 'Dashboard';
$page_subtitle = date('l, d F Y');

// Attendance logic
$on_duty = is_on_duty();
$staff_list = [];
if (!$on_duty) {
    $search_type = ($_SESSION['role'] === 'admin') ? 'admin' : 'operator';
    $stmt = $pdo->prepare("SELECT operator_id, full_name, shift FROM operator WHERE staff_type = ? ORDER BY full_name");
    $stmt->execute([$search_type]);
    $staff_list = $stmt->fetchAll();
}

include 'includes/header.php';
?>

    <div class="p-10 max-w-[1440px] mx-auto">

        <!-- Alerts -->
        <?php if ($car_pct <= 20 && $car_total > 0): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-4">
            <span class="material-symbols-outlined text-red-500">warning</span>
            <div>
                <p class="font-inter font-semibold text-red-700 text-sm">Kapasitas Mobil Hampir Penuh!</p>
                <p class="font-inter text-red-500 text-xs">Hanya <?= $car_avail ?> dari <?= $car_total ?> slot tersedia.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
        <div class="flex items-center gap-3 bg-amber-50 rounded-xl px-5 py-4 mb-4">
            <span class="material-symbols-outlined text-amber-500">warning</span>
            <div>
                <p class="font-inter font-semibold text-amber-700 text-sm">Kapasitas Motor Hampir Penuh!</p>
                <p class="font-inter text-amber-500 text-xs">Hanya <?= $moto_avail ?> dari <?= $moto_total ?> slot tersedia.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bento Grid -->
        <div class="grid grid-cols-12 gap-4 mb-8">

            <!-- Today Revenue — large hero card -->
            <div class="col-span-12 lg:col-span-5 bg-slate-900 rounded-2xl p-8 flex flex-col justify-between min-h-[180px]">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Pendapatan Hari Ini</p>
                    <span class="material-symbols-outlined text-slate-600 text-xl" data-icon="payments">payments</span>
                </div>
                <div>
                    <div class="font-manrope font-extrabold text-4xl text-white leading-none"><?= fmt_idr((float)$today_rev) ?></div>
                    <p class="text-slate-500 text-xs font-inter mt-2"><?= date('d M Y') ?></p>
                </div>
            </div>

            <!-- Kendaraan Aktif -->
            <div class="col-span-6 lg:col-span-3 bg-white rounded-2xl p-6 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Kendaraan Aktif</p>
                    <span class="material-symbols-outlined text-slate-300">timer</span>
                </div>
                <div class="font-manrope font-extrabold text-5xl text-slate-900"><?= $active ?></div>
                <p class="text-slate-400 text-xs font-inter mt-2">Sedang parkir saat ini</p>
            </div>

            <!-- Slot Mobil -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Mobil</p>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-xl">directions_car</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $car_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $car_total ?> tersedia</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $car_pct > 50 ? 'bg-emerald-500' : ($car_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $car_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $car_pct ?>% tersedia</p>
            </div>

            <!-- Slot Motor -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot Motor</p>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-emerald-600 text-xl">two_wheeler</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $moto_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $moto_total ?> tersedia</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $moto_pct > 50 ? 'bg-emerald-500' : ($moto_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $moto_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-xs font-inter mt-2"><?= $moto_pct ?>% tersedia</p>
            </div>

            <!-- Quick Access -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-5">Akses Cepat</p>
                <div class="grid grid-cols-3 gap-3">
                    <?php
                    $quick = [
                        ['modules/operations/gate_simulator.php', 'door_sensor', 'Smart Gate', 'Simulator entry & exit'],
                        ['modules/operations/reservation.php',    'event_available', 'Reservasi', 'Pre-booking slot'],
                        ['modules/reports/slot_map.php',          'map', 'Peta Slot', 'Real-time slot map'],
                        ['modules/operations/active_vehicles.php','directions_car', 'Kendaraan Aktif', 'Monitor kendaraan'],
                        ['modules/reports/revenue.php',           'bar_chart_4_bars', 'Revenue', 'Laporan finansial'],
                        ['modules/operations/scan_log.php',       'receipt_long', 'Scan Log', 'Log sensor gate'],
                    ];
                    foreach ($quick as $q): ?>
                    <a href="<?= $q[0] ?>" class="flex flex-col gap-2 bg-slate-50 hover:bg-slate-100 rounded-xl p-4 transition-all group">
                        <span class="material-symbols-outlined text-slate-400 group-hover:text-slate-700 text-2xl transition-colors"><?= $q[1] ?></span>
                        <div>
                            <div class="font-inter font-semibold text-sm text-slate-800"><?= $q[2] ?></div>
                            <div class="font-inter text-xs text-slate-400 mt-0.5"><?= $q[3] ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Active Staff Section (admin/superadmin only) -->
        <?php if ($role === 'superadmin' || $role === 'admin'):
            $filter = ($role === 'admin') ? 'operator' : null;
            $active_staff = get_active_attendance($pdo, $filter);
        ?>
        <div class="bg-white rounded-2xl p-6 mb-8">
            <div class="flex items-center gap-2 mb-5">
                <span class="material-symbols-outlined text-slate-400 text-xl">groups</span>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Petugas Aktif Saat Ini</p>
            </div>
            <?php if (empty($active_staff)): ?>
                <div class="flex items-center gap-3 text-slate-400 text-sm font-inter">
                    <span class="material-symbols-outlined text-slate-300">person_off</span>
                    Belum ada petugas yang absensi pada sesi ini.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($active_staff as $st): ?>
                    <div class="flex items-center gap-3 bg-slate-50 rounded-xl p-3">
                        <div class="relative flex-shrink-0">
                            <div class="w-9 h-9 rounded-full bg-slate-900 flex items-center justify-center">
                                <span class="text-white text-xs font-manrope font-bold"><?= strtoupper(substr($st['full_name'], 0, 1)) ?></span>
                            </div>
                            <!-- Status Indicator -->
                            <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0">
                            <div class="font-inter font-semibold text-sm text-slate-800 truncate"><?= htmlspecialchars($st['full_name']) ?></div>
                            <div class="text-[10px] font-inter text-slate-400 flex items-center gap-1 mt-0.5">
                                In: <?= date('H:i', strtotime($st['check_in_time'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

<!-- Attendance Modal (Tailwind) -->
<?php if (!$on_duty): ?>
<div id="attendanceOverlay" class="fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-sm mx-4">
        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-white text-2xl">person_check</span>
            </div>
            <h2 class="font-manrope font-extrabold text-xl text-slate-900 mb-1">Konfirmasi Kehadiran</h2>
            <p class="text-slate-400 text-sm font-inter">Halo <span class="font-bold text-slate-700"><?= strtoupper($role) ?></span>, pilih identitas petugas Anda.</p>
        </div>

        <form id="attendanceForm" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Pilih Nama Petugas</label>
                <select name="staff_id" required
                        class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all font-inter font-semibold">
                    <option value="">-- Pilih Nama Anda --</option>
                    <?php foreach ($staff_list as $s): ?>
                        <option value="<?= $s['operator_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['shift'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="attendBtn"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter rounded-xl uppercase tracking-widest text-xs py-3.5 transition-all">
                Mulai Bertugas →
            </button>
        </form>

        <div id="attendMsg" class="mt-4 hidden"></div>
    </div>
</div>

<script>
document.getElementById('attendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = document.getElementById('attendBtn');
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    fetch('api/submit_attendance.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('attendanceOverlay').remove();
                location.reload();
            } else {
                const msg = document.getElementById('attendMsg');
                msg.className = 'mt-4 flex items-center gap-2 text-red-600 text-sm font-inter';
                msg.innerHTML = '<span class="material-symbols-outlined text-base">error</span>' + data.message;
                msg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Mulai Bertugas →';
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'Mulai Bertugas →'; });
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
