<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

$active = $pdo->query("
    SELECT t.transaction_id, t.ticket_code, s.slot_number, f.floor_code AS floor,
           t.check_in_time, v.plate_number, v.vehicle_type,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_parked
    FROM `transaction` t
    JOIN vehicle v       ON t.vehicle_id  = v.vehicle_id
    JOIN parking_slot s  ON t.slot_id     = s.slot_id
    JOIN floor f         ON s.floor_id    = f.floor_id
    WHERE t.payment_status = 'unpaid'
    ORDER BY t.check_in_time
")->fetchAll();

$page_title = 'Kendaraan Aktif';
include '../../includes/header.php';
?>

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <!-- Top Bar -->
    <header class="flex justify-between items-center px-10 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Kendaraan Aktif</h1>
            <p class="text-slate-400 text-sm font-inter">Total: <?= count($active) ?> kendaraan terparkir</p>
        </div>
        <button onclick="location.reload()"
                class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all">
            <span class="material-symbols-outlined text-base">refresh</span>
            Refresh
        </button>
    </header>

    <div class="p-10">
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-auto max-h-[72vh] no-scrollbar">
                <table class="w-full">
                    <thead class="sticky top-0 bg-white z-10">
                    <tr class="border-b border-slate-100">
                        <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Trx ID</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Tiket</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Tipe</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot / Lantai</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Check-In</th>
                        <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Durasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($active)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-16">
                            <span class="material-symbols-outlined text-5xl text-slate-200 block mb-3">car_tag</span>
                            <p class="text-slate-400 text-sm font-inter">Tidak ada kendaraan yang sedang parkir saat ini.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($active as $row):
                        $mins = (int)$row['minutes_parked'];
                        $dur  = floor($mins/60).'j '.($mins%60).'m';
                        $is_overdue = $mins >= 480;
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors <?= $is_overdue ? 'bg-amber-50/50' : '' ?>">
                        <td class="px-6 py-4 text-slate-400 text-sm font-inter">#<?= $row['transaction_id'] ?></td>
                        <td class="px-4 py-4">
                            <code class="font-code text-sm text-slate-800 bg-slate-100 px-3 py-1 rounded-lg font-bold transition-all hover:bg-slate-200"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></code>
                        </td>
                        <td class="px-4 py-4">
                            <?php if ($row['vehicle_type'] === 'car'): ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-xl text-blue-600">directions_car</span>
                                    </div>
                                    <span class="font-inter font-semibold text-sm text-slate-800">Mobil</span>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-xl text-emerald-600">two_wheeler</span>
                                    </div>
                                    <span class="font-inter font-semibold text-sm text-slate-800">Motor</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4">
                            <span class="font-manrope font-bold text-slate-900"><?= htmlspecialchars($row['slot_number']) ?></span>
                        </td>
                        <td class="px-4 py-4 text-slate-600 text-sm font-inter">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-slate-300 text-base">schedule</span>
                                <?= date('H:i, d M', strtotime($row['check_in_time'])) ?>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <?php if ($is_overdue): ?>
                            <span class="flex items-center gap-1.5 text-amber-600 text-sm font-bold font-inter">
                                <span class="material-symbols-outlined text-base">warning</span>
                                <?= $dur ?>
                            </span>
                            <?php else: ?>
                            <span class="text-slate-700 text-sm font-inter font-semibold"><?= $dur ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($active)): ?>
        <div class="flex items-center gap-2 mt-4 text-slate-400 text-sm font-inter">
            <span class="material-symbols-outlined text-base text-amber-500">info</span>
            Sorotan kuning menandakan kendaraan telah parkir lebih dari 8 jam.
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
