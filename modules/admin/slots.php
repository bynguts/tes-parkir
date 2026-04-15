<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $num      = strtoupper(trim($_POST['slot_number'] ?? ''));
        $type     = $_POST['slot_type'] ?? '';
        $floor_id = (int)($_POST['floor_id'] ?? 0);

        if (!$num || !in_array($type, ['car','motorcycle']) || $floor_id <= 0) {
            $error = 'Data konfigurasi slot tidak lengkap.';
        } else {
            $fcheck = $pdo->prepare("SELECT floor_id FROM floor WHERE floor_id = ?");
            $fcheck->execute([$floor_id]);
            if (!$fcheck->fetch()) {
                $error = 'Referensi lantai tidak valid pada sistem.';
            } else {
                try {
                    $pdo->prepare("INSERT INTO parking_slot (slot_number, slot_type, floor_id) VALUES (?,?,?)")
                        ->execute([$num, $type, $floor_id]);
                    $msg = "Slot <strong>{$num}</strong> berhasil diinisialisasi dalam database.";
                } catch (PDOException $e) {
                    $error = 'Nomor slot sudah terdaftar pada floor record ini.';
                }
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved','maintenance'])) {
            $error = 'Nilai state tidak valid.';
        } else {
            $pdo->prepare("UPDATE parking_slot SET status=? WHERE slot_id=?")->execute([$status, $id]);
            $msg = 'State slot berhasil disinkronisasi.';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['slot_id'];
        $occupied = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE slot_id=? AND payment_status='unpaid'");
        $occupied->execute([$id]);
        if ($occupied->fetchColumn() > 0) {
            $error = 'Pelanggaran Constraint: Slot aktif terikat dengan sesi transaksi yang berjalan.';
        } else {
            $pdo->prepare("DELETE FROM parking_slot WHERE slot_id=?")->execute([$id]);
            $msg = 'Slot dihapus secara permanen.';
        }
    }
}

$slots = $pdo->query("
    SELECT ps.*, f.floor_code, f.floor_name
    FROM parking_slot ps
    JOIN floor f ON ps.floor_id = f.floor_id
    ORDER BY f.floor_code, ps.slot_type, ps.slot_number
")->fetchAll();

$floors_list = $pdo->query("SELECT floor_id, floor_code, floor_name FROM floor ORDER BY floor_code")->fetchAll();

$page_title = 'Kelola Inventori Slot';
include '../../includes/header.php';
?>

<main class="pl-64 min-h-screen bg-[#f2f4f7]">

    <header class="flex justify-between items-center px-8 h-20 sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div>
            <h1 class="font-manrope font-extrabold text-2xl text-slate-900">Inventori Slot Parkir</h1>
            <p class="text-slate-400 text-xs font-inter mt-0.5">Konfigurasi kapasitas, letak, dan state slot pada area parkir.</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold font-inter uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all">
            <span class="material-symbols-outlined text-base">add_circle</span>
            Tambah Slot
        </button>
    </header>

    <div class="p-8 max-w-[1440px] mx-auto">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-xl px-5 py-4 mb-6">
            <span class="material-symbols-outlined text-emerald-600">check_circle</span>
            <p class="text-emerald-700 text-sm font-inter"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-6">
            <span class="material-symbols-outlined text-red-600">error</span>
            <p class="text-red-700 text-sm font-inter"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Slot / Lantai</th>
                            <th class="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Tipe</th>
                            <th class="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Status</th>
                            <th class="text-right px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php if (empty($slots)): ?>
                    <tr><td colspan="4" class="text-center py-16">
                        <span class="material-symbols-outlined text-5xl text-slate-200 block mb-3">local_parking</span>
                        <p class="text-slate-400 text-sm font-inter">Belum ada slot yang dikonfigurasi.</p>
                    </td></tr>
                    <?php else: foreach ($slots as $s):
                        $stMap = [
                            'available'   => ['bg-emerald-50 text-emerald-700',  'Tersedia'],
                            'occupied'    => ['bg-red-50 text-red-700',          'Terisi'],
                            'reserved'    => ['bg-amber-50 text-amber-700',      'Direservasi'],
                            'maintenance' => ['bg-slate-100 text-slate-500',     'Perawatan'],
                        ];
                        [$stCls, $stLabel] = $stMap[$s['status']] ?? ['bg-slate-100 text-slate-500', $s['status']];
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-manrope font-bold text-slate-900"><?= htmlspecialchars($s['slot_number']) ?></div>
                            <div class="text-slate-400 text-xs font-inter mt-0.5"><?= htmlspecialchars($s['floor_code']) ?> — <?= htmlspecialchars($s['floor_name']) ?></div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2 text-slate-600 text-sm font-inter">
                                <span class="material-symbols-outlined text-slate-400 text-base"><?= $s['slot_type'] === 'car' ? 'directions_car' : 'two_wheeler' ?></span>
                                <?= $s['slot_type'] === 'car' ? 'Mobil' : 'Motor' ?>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full font-inter <?= $stCls ?>"><?= $stLabel ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Status change -->
                                <form method="POST" class="flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <select name="status" onchange="this.form.submit()"
                                            class="bg-slate-100 border-none rounded-full px-3 py-1.5 text-xs font-bold font-inter text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all appearance-none cursor-pointer">
                                        <?php foreach (['available','occupied','reserved','maintenance'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>

                                <!-- Delete -->
                                <?php if ($s['status'] !== 'occupied'): ?>
                                <form method="POST" onsubmit="return confirm('Hapus slot <?= htmlspecialchars($s['slot_number'], ENT_QUOTES) ?> secara permanen?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                    <button class="flex items-center gap-1 text-red-600 bg-red-50 hover:bg-red-100 text-xs font-bold font-inter px-3 py-2 rounded-xl transition-all">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Slot Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 backdrop-blur-md bg-slate-900/40 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-slate-600">add_box</span>
                <h2 class="font-manrope font-bold text-lg text-slate-900">Inisialisasi Slot Baru</h2>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Nomor Slot</label>
                <input type="text" name="slot_number" required placeholder="Contoh: A-01"
                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-bold font-manrope text-slate-900 uppercase focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all"
                       oninput="this.value=this.value.toUpperCase()">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Tipe Kendaraan</label>
                <select name="slot_type" class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-bold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all appearance-none">
                    <option value="car">🚗 Mobil</option>
                    <option value="motorcycle">🏍 Motor</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Lantai</label>
                <select name="floor_id" class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm font-bold font-inter text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all appearance-none" required>
                    <?php foreach ($floors_list as $f): ?>
                    <option value="<?= $f['floor_id'] ?>"><?= htmlspecialchars($f['floor_code']) ?> — <?= htmlspecialchars($f['floor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="flex-1 bg-slate-100 text-slate-700 font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3 transition-all">Batal</button>
                <button type="submit"
                        class="flex-1 bg-slate-900 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3 transition-all">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php include '../../includes/footer.php'; ?>
