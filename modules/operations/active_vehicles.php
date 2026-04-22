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

$page_title = 'Live Fleet Status';
$page_subtitle = "Actively monitoring " . count($active) . " occupied zones.";
$page_actions = '
<button onclick="location.reload()"
        class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-[10px] font-extrabold font-inter uppercase tracking-[0.2em] px-6 py-3 rounded-2xl transition-all shadow-xl shadow-slate-900/10 active:scale-95">
    <i class="fa-solid fa-arrows-rotate text-sm"></i>
    Refresh Data
</button>';

include '../../includes/header.php';
?>

    <div class="p-8">
        <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-xl shadow-slate-900/[0.03] overflow-hidden">
            <div class="overflow-auto max-h-[72vh] no-scrollbar">
                <table class="w-full">
                    <thead class="sticky top-0 bg-white z-20">
                    <tr class="border-b border-slate-900/10">
                        <th class="text-left px-8 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Reference</th>
                        <th class="text-left px-4 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Secure Token</th>
                        <th class="text-left px-4 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Classification</th>
                        <th class="text-left px-4 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Zone / Level</th>
                        <th class="text-left px-4 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Arrival Time</th>
                        <th class="text-left px-4 py-5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-900/40 font-inter">Stay Duration</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-900/[0.03]">
                    <?php if (empty($active)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-24">
                            <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                                <i class="fa-solid fa-car-side text-4xl text-slate-900/10"></i>
                            </div>
                            <p class="text-slate-900/40 text-sm font-inter font-medium tracking-tight">System state: Zero occupancy detected.</p>
                            <p class="text-slate-900/20 text-[10px] font-extrabold uppercase tracking-widest mt-2">All sectors clear</p>
                        </td>
                    </tr>
                    <?php else: foreach ($active as $row):
                        $mins = (int)$row['minutes_parked'];
                        $dur  = floor($mins/60).'h '.($mins%60).'m';
                        $is_overdue = $mins >= 480;
                    ?>
                    <tr class="hover:bg-slate-900/[0.01] transition-colors group <?= $is_overdue ? 'bg-amber-50/[0.02]' : '' ?>">
                        <td class="px-8 py-5 text-slate-900/40 text-xs font-bold font-inter tracking-tight">#<?= str_pad($row['transaction_id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-4 py-5">
                            <span class="font-code text-[13px] text-slate-900 bg-slate-900/5 px-3 py-1.5 rounded-xl font-bold border border-slate-900/5 transition-all group-hover:bg-slate-900 group-hover:text-white"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></span>
                        </td>
                        <td class="px-4 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl <?= $row['vehicle_type'] === 'car' ? 'bg-indigo-50/10 text-indigo-600 border-indigo-500/10' : 'bg-emerald-50/10 text-emerald-600 border-emerald-500/10' ?> border flex items-center justify-center transition-all group-hover:scale-110">
                                    <i class="fa-solid fa-<?= $row['vehicle_type'] ?> text-lg"></i>
                                </div>
                                <span class="font-inter font-extrabold text-[11px] text-slate-900 uppercase tracking-widest"><?= ucfirst($row['vehicle_type']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-5">
                            <div class="flex flex-col">
                                <span class="font-manrope font-extrabold text-slate-900 text-lg tracking-tight"><?= htmlspecialchars($row['slot_number']) ?></span>
                                <span class="text-[9px] font-extrabold text-slate-900/40 uppercase tracking-[0.15em]"><?= htmlspecialchars($row['floor']) ?> Area</span>
                            </div>
                        </td>
                        <td class="px-4 py-5 text-slate-900/60 text-sm font-inter">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-status-online"></div>
                                <span class="font-medium text-slate-900/80"><?= date('H:i', strtotime($row['check_in_time'])) ?></span>
                                <span class="text-slate-900/40 font-extrabold text-[10px] uppercase ml-1"><?= date('d M', strtotime($row['check_in_time'])) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-5">
                            <?php if ($is_overdue): ?>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-amber-50/10 border border-amber-500/20 rounded-xl w-fit">
                                <i class="fa-solid fa-hourglass-half text-amber-600 text-xs"></i>
                                <span class="text-amber-700 text-[11px] font-extrabold font-inter uppercase tracking-widest"><?= $dur ?></span>
                            </div>
                            <?php else: ?>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-900/5 border border-slate-900/5 rounded-xl w-fit">
                                <i class="fa-solid fa-clock text-slate-900/40 text-xs"></i>
                                <span class="text-slate-900/60 text-[11px] font-extrabold font-inter uppercase tracking-widest"><?= $dur ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($active)): ?>
        <div class="flex items-center gap-3 mt-6 px-6 py-4 bg-slate-900/[0.03] rounded-2xl border border-slate-900/5">
            <div class="w-8 h-8 rounded-lg bg-amber-50/10 flex items-center justify-center text-amber-600">
                <i class="fa-solid fa-circle-info text-sm"></i>
            </div>
            <p class="text-slate-900/50 text-[11px] font-bold font-inter uppercase tracking-widest leading-relaxed">
                <span class="text-amber-600">Extended Stay Protocol:</span> Highlighting applied to units exceeding an 8-hour operational threshold.
            </p>
        </div>
        <?php endif; ?>
    </div>

<?php include '../../includes/footer.php'; ?>
