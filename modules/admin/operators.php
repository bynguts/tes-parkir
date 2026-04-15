<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
            $type  = $_POST['staff_type'] ?? 'operator';
            $valid_shifts = [
                '06:00 - 12:00', '12:00 - 18:00', '18:00 - 00:00', '00:00 - 06:00',
                '06:00 - 18:00 (Day)', '18:00 - 06:00 (Night)'
            ];
            if (!$name || !in_array($shift, $valid_shifts)) {
                $error = 'Nama dan shift wajib diisi.';
            } else {
                $pdo->prepare("INSERT INTO operator (full_name, shift, staff_type, phone) VALUES (?,?,?,?)")
                    ->execute([$name, $shift, $type, $phone ?: null]);
                $msg = "Profil {$name} berhasil terdaftar pada sistem HRD parkir.";
            }
    }
        if ($action === 'edit') {
        $id    = (int)$_POST['operator_id'];
        $name  = trim($_POST['full_name'] ?? '');
        $shift = $_POST['shift'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $type  = $_POST['staff_type'] ?? 'operator';
        $pdo->prepare("UPDATE operator SET full_name=?, shift=?, staff_type=?, phone=? WHERE operator_id=?")
            ->execute([$name, $shift, $type, $phone ?: null, $id]);
        $msg = 'Basis data profil personalia berhasil diperbarui.';
    }
    if ($action === 'delete') {
        $id = (int)$_POST['operator_id'];
        // Check if in use
        $used = $pdo->prepare("SELECT COUNT(*) FROM `transaction` WHERE operator_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $error = 'Pelanggaran Constraint: Operator ini memegang log transaksi aktif di database.';
        } else {
            $pdo->prepare("DELETE FROM operator WHERE operator_id=?")->execute([$id]);
            $msg = 'Profil operator dihapus dari sistem.';
        }
    }
}

$is_admin_only = ($_SESSION['role'] === 'admin');
$role_filter = $is_admin_only ? "WHERE o.staff_type = 'operator'" : "";
$operators = $pdo->query("SELECT o.*, COUNT(t.transaction_id) AS total_trx
    FROM operator o
    LEFT JOIN `transaction` t ON t.operator_id = o.operator_id
    $role_filter
    GROUP BY o.operator_id 
    ORDER BY o.staff_type ASC, o.full_name ASC")->fetchAll();

$page_title = 'Kelola Operator';
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Human Resources: Operator Gate</h4>
            <small class="text-muted">Kelola data petugas gate yang bertugas pada setiap shift.</small>
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
        <!-- Tambah Operator -->
        <div class="col-xl-4">
            <div class="glass-panel sticky-top" style="top: 100px;">
                <div class="p-4 border-bottom" style="border-color: var(--border-glass) !important;">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-user-plus text-primary me-2"></i>Rekrutmen Operator Baru</h5>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Nama Lengkap Sesuai ID</label>
                            <input type="text" name="full_name" class="form-control form-control-lg bg-dark text-white border-secondary" required placeholder="Cth: Budiman Sudjatmiko">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Waktu Kerja (Shift)</label>
                            <select name="shift" class="form-select form-control-lg bg-dark text-white border-secondary" required>
                                <optgroup label="Shift 6 Jam (Operator)">
                                    <option value="06:00 - 12:00">🌅 Shift 1 (06:00 - 12:00)</option>
                                    <option value="12:00 - 18:00">🌇 Shift 2 (12:00 - 18:00)</option>
                                    <option value="18:00 - 00:00">🌃 Shift 3 (18:00 - 00:00)</option>
                                    <option value="00:00 - 06:00">🌑 Shift 4 (00:00 - 06:00)</option>
                                </optgroup>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                <optgroup label="Shift 12 Jam (Admin)">
                                    <option value="06:00 - 18:00 (Day)">☀️ Day Shift (06:00 - 18:00)</option>
                                    <option value="18:00 - 06:00 (Night)">🌙 Night Shift (18:00 - 06:00)</option>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Kategori Role Personel</label>
                            <select name="staff_type" class="form-select form-control-lg bg-dark text-white border-secondary" required>
                                <option value="operator">🖥️ Staff Operator</option>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <option value="admin">⚒️ Staff Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Kontak Darurat (No. Telp)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-phone"></i></span>
                                <input type="tel" name="phone" class="form-control form-control-lg bg-dark text-white border-secondary" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold mt-2" style="border-radius: 12px; letter-spacing: 1px;">
                            SIMPAN PROFIL OPERATOR
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Operator -->
        <div class="col-xl-8">
            <div class="glass-panel overflow-hidden">
                <div class="p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="border-color: var(--border-glass) !important;">
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><i class="fas fa-users text-info me-2"></i>Database Personalia (<?= count($operators) ?>)</h5>
                    </div>
                </div>
                
                <div class="table-responsive" style="border: none;">
                    <table class="table table-glass table-hover mb-0">
                        <thead style="background: rgba(0,0,0,0.2);">
                            <tr>
                                <th class="ps-4">Nama Personalia</th>
                                <th>Role</th>
                                <th>Shift</th>
                                <th>Kontak</th>
                                <th class="text-center">Total Trx</th>
                                <th class="text-end pe-4">Command</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $current_type = '';
                        foreach ($operators as $op):
                            if ($current_type !== $op['staff_type']): 
                                $current_type = $op['staff_type'];
                                $group_label = ($current_type === 'admin') ? '👔 MANAGEMENT' : '👷 FIELD STAFF';
                                $group_color = ($current_type === 'admin') ? 'warning' : 'info';
                        ?>
                            <tr class="bg-<?= $group_color ?> bg-opacity-10">
                                <td colspan="6" class="ps-4 py-2">
                                    <span class="small fw-bold text-<?= $group_color ?>"><?= $group_label ?> (<?= strtoupper($current_type) ?>)</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php
                            $shift_config = [
                                '06:00 - 12:00' => ['info', '🌅 S1 (06-12)'],
                                '12:00 - 18:00' => ['warning', '🌇 S2 (12-18)'],
                                '18:00 - 00:00' => ['secondary', '🌌 S3 (18-00)'],
                                '00:00 - 06:00' => ['dark', '🌑 S4 (00-06)'],
                                '06:00 - 18:00 (Day)' => ['primary', '☀️ DAY (12h)'],
                                '18:00 - 06:00 (Night)' => ['indigo', '🌙 NIGHT (12h)']
                            ][$op['shift']] ?? ['light', $op['shift']];
                        ?>
                        <tr>
                            <td class="ps-4 align-middle">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar bg-primary bg-opacity-25 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; border: 1px solid var(--primary);">
                                        <?= strtoupper(substr($op['full_name'], 0, 1)) ?>
                                    </div>
                                    <span class="fw-bold text-white"><?= htmlspecialchars($op['full_name']) ?></span>
                                </div>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $op['staff_type'] === 'admin' ? 'warning' : 'info' ?> bg-opacity-25 text-<?= $op['staff_type'] === 'admin' ? 'warning' : 'info' ?> border border-<?= $op['staff_type'] === 'admin' ? 'warning' : 'info' ?> px-2 py-1 rounded">
                                    <?= strtoupper($op['staff_type']) ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $shift_config[0] ?> bg-opacity-25 text-<?= $shift_config[0] ?> border border-<?= $shift_config[0] ?> px-3 py-1 rounded-pill">
                                    <?= $shift_config[1] ?>
                                </span>
                            </td>
                            <td class="align-middle font-monospace text-muted">
                                <?= htmlspecialchars($op['phone'] ?? '-') ?>
                            </td>
                            <td class="align-middle text-center">
                                <span class="badge bg-dark border border-secondary text-white px-2 py-1">
                                    <?= number_format($op['total_trx']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4 align-middle">
                                <button class="btn btn-outline-info btn-sm px-3 rounded" data-bs-toggle="modal"
                                        data-bs-target="#editModal" title="Edit Profil"
                                        onclick="fillEdit(<?= $op['operator_id'] ?>, '<?= htmlspecialchars($op['full_name'],ENT_QUOTES) ?>', '<?= $op['shift'] ?>', '<?= $op['staff_type'] ?>', '<?= htmlspecialchars($op['phone']??'',ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Proses terminasi operator ini dari sistem. Lanjutkan?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm px-3 rounded ms-1" title="Terminasi Operator"><i class="fas fa-trash"></i></button>
                                </form>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0" style="background: #1e1e2d; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div class="modal-header border-bottom border-secondary border-opacity-50">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-user-edit me-2 text-primary"></i> Update Data Operator</h5>
                <button type="button" class="btn-close btn-close-white opacity-50" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="operator_id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nama Baru</label>
                        <input type="text" name="full_name" id="edit_name" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-4" <?php if ($_SESSION['role'] !== 'superadmin') echo 'style="display:none;"'; ?>>
                        <label class="form-label text-muted small fw-bold text-uppercase">Role Personel</label>
                        <select name="staff_type" id="edit_type" class="form-select bg-dark text-white border-secondary">
                            <option value="operator">🖥️ Operator</option>
                            <option value="admin">⚒️ Admin Staff</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase">Pemindahan Shift</label>
                        <select name="shift" id="edit_shift" class="form-select bg-dark text-white border-secondary">
                            <optgroup label="Shift 6 Jam (Operator)">
                                <option value="06:00 - 12:00">🌅 Shift 1 (06:00 - 12:00)</option>
                                <option value="12:00 - 18:00">🌇 Shift 2 (12:00 - 18:00)</option>
                                <option value="18:00 - 00:00">🌃 Shift 3 (18:00 - 00:00)</option>
                                <option value="00:00 - 06:00">🌑 Shift 4 (00:00 - 06:00)</option>
                            </optgroup>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                            <optgroup label="Shift 12 Jam (Admin)">
                                <option value="06:00 - 18:00 (Day)">☀️ Day Shift (06:00 - 18:00)</option>
                                <option value="18:00 - 06:00 (Night)">🌙 Night Shift (18:00 - 06:00)</option>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold text-uppercase">Update Kontak Darurat</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control bg-dark text-white border-secondary font-monospace">
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-50">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow">SIMPAN PERUBAHAN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEdit(id, name, shift, type, phone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_shift').value = shift;
    document.getElementById('edit_type').value = type;
    document.getElementById('edit_phone').value = phone;
}
</script>

<?php include '../../includes/footer.php'; ?>
