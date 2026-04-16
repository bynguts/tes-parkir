<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';
require_role('superadmin', 'admin');

$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $rate_id    = (int)$_POST['rate_id'];
        $first_rate = (float)$_POST['first_hour_rate'];
        $next_rate  = (float)$_POST['next_hour_rate'];
        $max_rate   = (float)$_POST['daily_max_rate'];

        if ($first_rate <= 0 || $next_rate <= 0 || $max_rate <= 0) {
            $error = 'Rate adjustment value must be above Rp 0.';
        } else {
            $pdo->prepare("UPDATE parking_rate SET first_hour_rate=?, next_hour_rate=?, daily_max_rate=? WHERE rate_id=?")
                ->execute([$first_rate, $next_rate, $max_rate, $rate_id]);
            $msg = 'Parking rate configuration successfully updated in the database.';
        }
    }
}

$rates = $pdo->query("SELECT * FROM parking_rate ORDER BY vehicle_type")->fetchAll();
$page_title = 'Rate Configuration';
$page_subtitle = 'Financial parameter settings for the parking auto-billing system.';

include '../../includes/header.php';
?>

    <div class="p-8 max-w-[1440px] mx-auto">

        <?php if ($msg): ?>
        <div class="flex items-center gap-3 bg-emerald-50 rounded-xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <p class="text-emerald-700 text-sm font-inter font-medium"><?= $msg ?></p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4 mb-6">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i>
            <p class="text-red-700 text-sm font-inter font-medium"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach ($rates as $r):
                $is_car = $r['vehicle_type'] === 'car';
                $icon   = $is_car ? 'fa-car' : 'fa-motorcycle';
                $label  = $is_car ? 'Car Class (Type 1)' : 'Motorcycle Class (Type 2)';
                $color  = $is_car ? 'text-blue-600 bg-blue-50' : 'text-emerald-600 bg-emerald-50';
            ?>
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="flex items-center gap-4 px-6 py-5 border-b border-slate-100 bg-slate-50">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center <?= $color ?>">
                        <i class="fa-solid <?= $icon ?> text-xl"></i>
                    </div>
                    <div>
                        <h2 class="font-manrope font-bold text-lg text-slate-900"><?= $label ?></h2>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Rate Configuration</p>
                    </div>
                </div>

                <div class="p-6">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rate_id" value="<?= $r['rate_id'] ?>">

                        <div class="space-y-4 mb-5">
                            <?php
                            $fields = [
                                ['first_hour_rate', 'First Hour Rate',       500,  (int)$r['first_hour_rate']],
                                ['next_hour_rate',  'Next Hour Rate',        500,  (int)$r['next_hour_rate']],
                                ['daily_max_rate',  'Daily Maximum Limit',   1000, (int)$r['daily_max_rate']],
                            ];
                            foreach ($fields as [$fname, $flabel, $step, $fval]):
                            ?>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2"><?= $flabel ?></label>
                                <div class="flex items-center gap-2 bg-slate-100 rounded-full px-5 py-2.5 focus-within:ring-2 focus-within:ring-slate-900 transition-all">
                                    <span class="text-slate-500 text-sm font-bold font-inter">Rp</span>
                                    <input type="number" name="<?= $fname ?>"
                                           value="<?= $fval ?>" min="0" step="<?= $step ?>" required
                                           id="<?= $fname . '_' . $r['rate_id'] ?>"
                                           oninput="updatePreview(<?= $r['rate_id'] ?>)"
                                           class="flex-1 bg-transparent border-none text-sm font-bold font-inter text-slate-900 focus:outline-none text-right">
                                    <span class="text-slate-400 text-xs font-inter">/hour</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Billing Preview -->
                        <div class="bg-slate-50 rounded-2xl p-4 mb-5">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fa-solid fa-calculator text-slate-400 text-sm"></i>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter">Billing Engine Simulation</p>
                            </div>
                            <div id="preview_<?= $r['rate_id'] ?>" class="space-y-1.5"></div>
                        </div>

                        <button type="submit"
                                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter text-xs uppercase tracking-widest rounded-xl py-3 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk text-sm"></i>
                            Update Rate Parameters
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    </div>

<script>
function updatePreview(id) {
    const first = parseFloat(document.getElementById('first_hour_rate_'+id).value) || 0;
    const next  = parseFloat(document.getElementById('next_hour_rate_'+id).value) || 0;
    const max   = parseFloat(document.getElementById('daily_max_rate_'+id).value) || 0;
    const fmt   = v => 'Rp ' + v.toLocaleString('id-ID');
    const box   = document.getElementById('preview_'+id);

    let html = '';
    [1,2,3,6,12,24].forEach(h => {
        let fee = h <= 1 ? first : first + (h-1)*next;
        fee = Math.min(fee, max);
        const overMax = fee >= max && max > 0;
        html += `<div class="flex justify-between items-center py-1.5 border-b border-slate-100 last:border-0">
                    <span class="text-slate-400 text-xs font-inter">${h} hours</span>
                    <span class="font-manrope font-bold text-sm ${overMax ? 'text-amber-600' : 'text-slate-900'}">${fmt(fee)}${overMax ? ' (max)' : ''}</span>
                 </div>`;
    });
    box.innerHTML = html;
}
<?php foreach ($rates as $r): ?>
updatePreview(<?= $r['rate_id'] ?>);
<?php endforeach; ?>
</script>

<?php include '../../includes/footer.php'; ?>
