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
            <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
            <div>
                <p class="font-inter font-semibold text-red-700 text-sm">Car Capacity Almost Full!</p>
                <p class="font-inter text-red-500 text-xs">Only <?= $car_avail ?> of <?= $car_total ?> slots available.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($moto_pct <= 20 && $moto_total > 0): ?>
        <div class="flex items-center gap-3 bg-amber-50 rounded-xl px-5 py-4 mb-4">
            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
            <div>
                <p class="font-inter font-semibold text-amber-700 text-sm">Motorcycle Capacity Almost Full!</p>
                <p class="font-inter text-amber-500 text-xs">Only <?= $moto_avail ?> of <?= $moto_total ?> slots available.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bento Grid -->
        <div class="grid grid-cols-12 gap-6 mb-6">

            <!-- Active Vehicles -->
            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl p-6 flex flex-col justify-between hover:-translate-y-1 hover:shadow-xl transition-all duration-300 group ring-1 ring-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Active Vehicles</p>
                    <i class="fa-solid fa-clock-rotate-left text-slate-300 group-hover:text-slate-500 transition-colors"></i>
                </div>
                <div class="font-manrope font-extrabold text-5xl text-slate-900 leading-none"><?= $active ?></div>
                <p class="text-slate-400 text-xs font-inter mt-2 font-medium">Currently parked</p>
            </div>

            <!-- Motorcycle Slots -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 ring-1 ring-slate-200 group">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Motorcycle Slots</p>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center border border-emerald-100 group-hover:bg-emerald-100 transition-all">
                        <i class="fa-solid fa-motorcycle text-emerald-600 text-lg"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $moto_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter">/ <?= $moto_total ?> available</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $moto_pct > 50 ? 'bg-emerald-500' : ($moto_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $moto_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider font-inter mt-3"><?= $moto_pct ?>% Available</p>
            </div>

            <!-- Car Slots -->
            <div class="col-span-6 lg:col-span-4 bg-white rounded-2xl p-6 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 ring-1 ring-slate-200 group">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Car Slots</p>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center border border-blue-100 group-hover:bg-blue-100 transition-all">
                        <i class="fa-solid fa-car text-blue-600 text-lg"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-3">
                    <span class="font-manrope font-extrabold text-3xl text-slate-900"><?= $car_avail ?></span>
                    <span class="text-slate-400 text-sm font-inter font-medium">/ <?= $car_total ?> available</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="progress-bar-fill h-2 rounded-full <?= $car_pct > 50 ? 'bg-emerald-500' : ($car_pct > 20 ? 'bg-amber-400' : 'bg-red-500') ?>"
                         style="width: <?= $car_pct ?>%"></div>
                </div>
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider font-inter mt-3"><?= $car_pct ?>% Available</p>
            </div>

            <!-- Today Revenue — large hero card -->
            <div class="col-span-12 lg:col-span-4 bg-slate-900 rounded-2xl p-8 flex flex-col justify-between min-h-[180px] hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/20 transition-all duration-300 group">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 font-inter group-hover:text-slate-400 transition-colors">Today's Revenue</p>
                    <i class="fa-solid fa-money-bill-wave text-slate-600 text-lg group-hover:text-slate-400 transition-colors"></i>
                </div>
                <div>
                    <div class="font-manrope font-extrabold text-4xl text-white leading-none tracking-tight"><?= fmt_idr((float)$today_rev) ?></div>
                    <p class="text-slate-500 text-xs font-inter mt-2"><?= date('d M Y') ?></p>
                </div>
            </div>

            <!-- Quick Access -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl p-6 ring-1 ring-slate-200">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-5">Quick Access</p>
                <div class="grid grid-cols-3 gap-6">
                    <?php
                    $quick = [
                        ['modules/operations/gate_simulator.php', 'fa-solid fa-door-open', 'Smart Gate', 'Simulator entry & exit'],
                        ['modules/operations/reservation.php',    'fa-solid fa-calendar-check', 'Reservations', 'Pre-booking slot'],
                        ['modules/reports/slot_map.php',          'fa-solid fa-map-location-dot', 'Slot Map', 'Real-time slot map'],
                        ['modules/operations/active_vehicles.php','fa-solid fa-car', 'Active Vehicles', 'Monitor vehicles'],
                        ['modules/reports/revenue.php',           'fa-solid fa-chart-simple', 'Revenue', 'Financial report'],
                        ['modules/operations/scan_log.php',       'fa-solid fa-receipt', 'Scan Log', 'Gate sensor log'],
                    ];
                    foreach ($quick as $q): ?>
                    <a href="<?= $q[0] ?>" class="flex flex-col gap-3 bg-slate-50 hover:bg-slate-100 border border-slate-100 hover:border-slate-300 rounded-2xl p-4 transition-all group">
                        <i class="<?= $q[1] ?> text-slate-400 group-hover:text-slate-700 text-xl group-hover:translate-x-1 transition-all"></i>
                        <div>
                            <div class="font-inter font-bold text-sm text-slate-800"><?= $q[2] ?></div>
                            <div class="font-inter text-xs text-slate-400 mt-0.5 font-medium"><?= $q[3] ?></div>
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
        <div class="bg-white rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-2 mb-5">
                <i class="fa-solid fa-users text-slate-400 text-base"></i>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Current Active Personnel</p>
            </div>
            <?php if (empty($active_staff)): ?>
                <div class="flex items-center gap-3 text-slate-400 text-sm font-inter">
                    <i class="fa-solid fa-user-slash text-slate-300"></i>
                    No staff have checked in for this session.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
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
                <i class="fa-solid fa-user-check text-white text-2xl"></i>
            </div>
            <h2 class="font-manrope font-extrabold text-xl text-slate-900 mb-1">Attendance Confirmation</h2>
            <p class="text-slate-400 text-sm font-inter">Hello <span class="font-bold text-slate-700"><?= strtoupper($role) ?></span>, select your personnel identity.</p>
        </div>

        <form id="attendanceForm" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Select Personnel Name</label>
                <select name="staff_id" required
                        class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all font-inter font-semibold">
                    <option value="">-- Select Your Name --</option>
                    <?php foreach ($staff_list as $s): ?>
                        <option value="<?= $s['operator_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['shift'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="attendBtn"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter rounded-xl uppercase tracking-widest text-xs py-3.5 transition-all">
                Start Duty →
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
    btn.textContent = 'Processing...';

    fetch('api/submit_attendance.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('attendanceOverlay').remove();
                location.reload();
            } else {
                const msg = document.getElementById('attendMsg');
                msg.className = 'mt-4 flex items-center gap-2 text-red-600 text-sm font-inter';
                msg.innerHTML = '<i class="fa-solid fa-circle-exclamation text-base"></i>' + data.message;
                msg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Start Duty →';
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'Start Duty →'; });
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
