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
$page_subtitle = "Total: " . count($active) . " kendaraan terparkir";
$page_actions = '
<button onclick="forceCheckoutAll()"
        class="flex items-center gap-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 text-[11px] font-bold font-inter uppercase tracking-widest px-4 py-2 rounded-xl transition-all border border-red-500/20 mr-2">
    <i class="fa-solid fa-triangle-exclamation"></i>
    Clear All
</button>
<button onclick="location.reload()"
        class="flex items-center gap-2 btn-primary text-[11px] font-bold font-inter uppercase tracking-widest px-4 py-2 rounded-xl transition-all">
    <i class="fa-solid fa-arrows-rotate"></i>
    Refresh
</button>';

include '../../includes/header.php';
?>

    <div class="px-10 py-6">
        <div class="bento-card overflow-hidden">
            <div class="overflow-auto max-h-[72vh] custom-scrollbar">
                <table class="w-full text-left font-inter border-collapse">
                    <thead class="sticky top-0 bg-surface z-10">
                    <tr class="border-b border-color">
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Trx ID</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Tiket</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Kendaraan</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Slot</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Waktu Masuk</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary">Durasi</th>
                        <th class="px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-tertiary text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-color">
                    <?php if (empty($active)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-16">
                            <i class="fa-solid fa-car-tunnel text-5xl text-tertiary opacity-20 block mb-3"></i>
                            <p class="text-tertiary text-sm font-inter">Tidak ada kendaraan yang sedang parkir saat ini.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($active as $row):
                        $mins = (int)$row['minutes_parked'];
                        $dur  = floor($mins/60).'j '.($mins%60).'m';
                        $is_overdue = $mins >= 480;
                    ?>
                    <tr class="hover:bg-surface-alt/50 transition-colors <?= $is_overdue ? 'bg-orange-500/5' : '' ?>">
                        <td class="px-6 py-4 text-tertiary text-sm font-inter">#<?= $row['transaction_id'] ?></td>
                        <td class="px-4 py-4">
                            <span class="font-mono text-xs font-bold text-primary bg-primary/10 px-2.5 py-1 rounded-md border border-primary/20"><?= htmlspecialchars($row['ticket_code'] ?? '-') ?></span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg icon-container flex items-center justify-center">
                                    <i class="fa-solid <?= $row['vehicle_type'] === 'car' ? 'fa-car' : 'fa-motorcycle' ?> text-lg text-primary"></i>
                                </div>
                                <div>
                                    <p class="font-manrope font-bold text-sm text-primary leading-tight"><?= htmlspecialchars($row['plate_number']) ?></p>
                                    <p class="text-[10px] text-tertiary uppercase"><?= $row['vehicle_type'] === 'car' ? 'Mobil' : 'Motor' ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="font-manrope font-bold text-primary"><?= htmlspecialchars($row['slot_number']) ?></span>
                        </td>
                        <td class="px-4 py-4 text-primary text-sm font-manrope font-medium">
                            <?= date('H:i, d M', strtotime($row['check_in_time'])) ?>
                        </td>
                        <td class="px-4 py-4">
                            <?php if ($is_overdue): ?>
                            <span class="flex items-center gap-1.5 text-orange-500 text-sm font-bold font-inter">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= $dur ?>
                            </span>
                            <?php else: ?>
                            <span class="text-primary text-sm font-inter font-medium"><?= $dur ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <button onclick="forceCheckout(<?= $row['transaction_id'] ?>)" 
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all tooltip-trigger"
                                    title="Force Checkout">
                                <i class="fa-solid fa-person-walking-arrow-right"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($active)): ?>
        <div class="flex items-center gap-2 mt-4 text-tertiary text-xs font-inter">
            <i class="fa-solid fa-circle-info text-orange-500"></i>
            Sorotan oranye menandakan kendaraan telah parkir lebih dari 8 jam (overdue).
        </div>
        <?php endif; ?>
    </div>

    <script>
    function forceCheckout(id) {
        if (!confirm('Peringatan: Anda akan melakukan Force Checkout untuk tiket ini (misal tiket hilang/sistem error). Lanjutkan?')) return;
        
        fetch('../../api/force_checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Force checkout berhasil!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        })
        .catch(err => alert('Error: ' + err));
    }

    function forceCheckoutAll() {
        if (!confirm('BAHAYA: Anda akan melakukan Force Checkout untuk SEMUA kendaraan yang sedang parkir! Lanjutkan?')) return;
        if (!confirm('Apakah Anda benar-benar yakin? Aksi ini tidak dapat dibatalkan.')) return;
        
        fetch('../../api/force_checkout_all.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Berhasil checkout ' + data.affected + ' kendaraan!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        })
        .catch(err => alert('Error: ' + err));
    }
    </script>

<?php include '../../includes/footer.php'; ?>
