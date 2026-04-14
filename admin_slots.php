<?php
require_once 'includes/auth_guard.php';
require_once 'config/connection.php';
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
                    $msg = "Slot <strong class='text-white'>{$num}</strong> berhasil diinisialisasi dalam database.";
                } catch (PDOException $e) {
                    $error = 'Nomor slot sudah terdaftar pada floor record.';
                }
            }
        }
    }

    if ($action === 'status') {
        $id     = (int)$_POST['slot_id'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['available','occupied','reserved','maintenance'])) {
            $error = 'Nilai instance state tidak valid.';
        } else {
            $pdo->prepare("UPDATE parking_slot SET status=? WHERE slot_id=?")->execute([$status, $id]);
            $msg = 'State mesin slot berhasil disinkronisasi.';
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

$page_title = 'Kelola Slot Parkir';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Manajemen Inventori Slot</h4>
            <small class="text-muted">Konfigurasi kapasitas, letak, dan penguncian state pada area parkir.</small>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success glass-panel mb-4 p-3 border border-success border-opacity-50 d-flex align-items-center">
        <i class="fas fa-check-circle fs-4 text-success me-3"></i>
        <div class="text-white"><?= $msg ?></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger glass-panel mb-4 p-3 border border-danger border-opacity-50 d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fs-4 text-danger me-3"></i>
        <div class="text-white"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add slot form -->
        <div class="col-xl-4">
            <div class="glass-panel sticky-top" style="top: 100px;">
                <div class="p-4 border-bottom" style="border-color: var(--border-glass) !important;">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-plus-square text-primary me-2"></i>Inisialisasi Slot Baru</h5>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Nomor Node (Slot)</label>
                            <input type="text" name="slot_number" class="form-control form-control-lg bg-dark text-white font-monospace border-secondary"
                                   placeholder="C-G11" required oninput="this.value=this.value.toUpperCase()">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Tipe Kendaraan</label>
                            <select name="slot_type" class="form-select bg-dark text-white border-secondary" required>
                                <option value="car">🚗 Kelas Mobil</option>
                                <option value="motorcycle">🏍️ Kelas Motor</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Mapping Lantai</label>
                            <select name="floor_id" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach ($floors_list as $fl): ?>
                                <option value="<?= $fl['floor_id'] ?>">
                                    [<?= htmlspecialchars($fl['floor_code']) ?>] <?= htmlspecialchars($fl['floor_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold mt-2" style="border-radius: 12px; letter-spacing: 1px;">
                            DEPLOY REGISTRASI SLOT
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Slot list -->
        <div class="col-xl-8">
            <div class="glass-panel overflow-hidden">
                <div class="p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="border-color: var(--border-glass) !important;">
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><i class="fas fa-server text-info me-2"></i>Database Slot</h5>
                        <p class="small text-muted mb-0 mt-1">Menampilkan <?= count($slots) ?> endpoint slot terdaftar.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <select id="pageSize" class="form-select bg-dark border-secondary text-white" style="width: auto; border-radius: 8px;" onchange="updatePagination()">
                            <option value="30">30 Baris</option>
                            <option value="50">50 Baris</option>
                            <option value="all" selected>All (Semua)</option>
                        </select>
                        <div class="position-relative">
                            <i class="fas fa-search position-absolute top-50 translate-middle-y text-muted ms-3"></i>
                            <input type="text" id="searchSlot" class="form-control bg-dark border-secondary text-white ps-5"
                                   placeholder="Filter ID atau status..." oninput="this.value=this.value.toUpperCase(); filterSlots(this.value)" style="width: 250px; border-radius: 50px;" list="slotSuggestions" autocomplete="off">
                            <datalist id="slotSuggestions">
                                <?php
                                $suggestions = [];
                                foreach ($slots as $s) {
                                    $suggestions[$s['slot_number']] = 1;
                                    $suggestions[strtoupper($s['status'])] = 1;
                                    $suggestions[$s['floor_code']] = 1;
                                }
                                foreach (array_keys($suggestions) as $sg): ?>
                                    <option value="<?= htmlspecialchars($sg) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive" style="border: none; max-height: 700px; overflow-y: auto;">
                    <table class="table table-glass table-hover mb-0" id="slotTable">
                        <thead style="position: sticky; top:0; background: var(--card-bg); z-index: 10;">
                            <tr>
                                <th class="ps-4">No. Node</th>
                                <th>Klasifikasi</th>
                                <th>Lokasi (Zona)</th>
                                <th>State Realtime</th>
                                <th class="text-end pe-4">Command</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($slots as $s): 
                            $status_color = [
                                'available' => 'success',
                                'occupied' => 'danger',
                                'reserved' => 'warning',
                                'maintenance' => 'secondary'
                            ][$s['status']];
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold font-monospace fs-6 align-middle text-white"><?= htmlspecialchars($s['slot_number']) ?></td>
                            <td class="align-middle">
                                <?= $s['slot_type'] === 'car' ? '<span class="text-info"><i class="fas fa-car me-2"></i>Mobil</span>' : '<span class="text-success"><i class="fas fa-motorcycle me-2"></i>Motor</span>' ?>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-dark border border-secondary text-light fw-bold" style="letter-spacing: 1px;">[<?= htmlspecialchars($s['floor_code']) ?>]</span>
                                <small class="text-muted ms-2"><?= htmlspecialchars($s['floor_name']) ?></small>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $status_color ?> bg-opacity-25 text-<?= $status_color ?> border border-<?= $status_color ?> px-3 py-1 rounded-pill">
                                    <i class="fas fa-circle me-1" style="font-size: 8px; vertical-align: middle;"></i> <?= strtoupper($s['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4 align-middle">
                                <div class="d-flex gap-2 justify-content-end">
                                    <!-- Change status -->
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                        <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white"
                                                onchange="this.form.submit()" style="width: 140px; cursor: pointer;">
                                            <option value="available"   <?= $s['status'] === 'available' ? 'selected' : '' ?>>⟳ Available</option>
                                            <option value="occupied"    <?= $s['status'] === 'occupied' ? 'selected' : '' ?>>⟳ Occupied</option>
                                            <option value="reserved"    <?= $s['status'] === 'reserved' ? 'selected' : '' ?>>⟳ Reserved</option>
                                            <option value="maintenance" <?= $s['status'] === 'maintenance' ? 'selected' : '' ?>>⟳ Maint.</option>
                                        </select>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" onsubmit="return confirm('Peringatan: Mengapus registry slot ini bersifat permanen. Lanjutkan?')" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm px-3 rounded" title="Hapus Node Registry">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
function updatePagination() {
    let size = document.getElementById('pageSize').value;
    let rows = document.querySelectorAll('#slotTable tbody tr');
    let q = document.getElementById('searchSlot').value.toLowerCase();
    
    let visibleRows = Array.from(rows).filter(tr => tr.textContent.toLowerCase().includes(q));
    
    rows.forEach(tr => tr.style.display = 'none');
    
    if (size === 'all') {
        visibleRows.forEach(tr => tr.style.display = '');
    } else {
        size = parseInt(size);
        let start = (currentPage - 1) * size;
        let end = start + size;
        visibleRows.slice(start, end).forEach(tr => tr.style.display = '');
    }
}

function filterSlots(q) {
    currentPage = 1;
    updatePagination();
}

document.addEventListener('DOMContentLoaded', updatePagination);
</script>
<?php include 'includes/footer.php'; ?>