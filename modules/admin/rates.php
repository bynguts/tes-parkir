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



<div class="px-10 py-10 max-w-[1600px] mx-auto space-y-10">
    
    <!-- HEADER -->
    <div class="flex items-center gap-6">
        <div class="w-16 h-16 rounded-[2rem] icon-container flex items-center justify-center shadow-2xl shrink-0">
            <i class="fa-solid fa-receipt text-3xl"></i>
        </div>
        <div>
            <h2 class="text-4xl font-manrope font-black text-primary tracking-tight">Rate Configuration</h2>
            <p class="text-tertiary mt-1 text-sm font-medium">Financial parameter settings for the parking auto-billing system.</p>
        </div>
    </div>

    <!-- STICKY JUMP MENU -->
    <div class="sticky top-20 z-40 bg-page py-5 -mx-10 px-10 border-b border-color shadow-sm">
        <div class="flex items-center gap-3 overflow-x-auto no-scrollbar">
            <a href="#car-rates" class="jump-link flex items-center gap-3 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-tertiary hover:bg-surface border border-transparent hover:border-color shadow-sm transition-all">
                <i class="fa-solid fa-car text-sm"></i>
                Car Class
            </a>
            <a href="#moto-rates" class="jump-link flex items-center gap-3 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-tertiary hover:bg-surface border border-transparent hover:border-color shadow-sm transition-all">
                <i class="fa-solid fa-motorcycle text-sm"></i>
                Motorcycle Class
            </a>
        </div>
    </div>

    <div class="space-y-24">
        
        <?php if ($msg): ?>
        <div class="flex items-center gap-4 status-badge-paid rounded-2xl px-6 py-5 border shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-status-available-text/10 flex items-center justify-center">
                <i class="fa-solid fa-circle-check text-xl"></i>
            </div>
            <p class="text-sm font-manrope font-bold tracking-tight"><?= $msg ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="flex items-center gap-4 status-badge-lost rounded-2xl px-6 py-5 border shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
            <div class="w-10 h-10 rounded-xl bg-status-lost-text/10 flex items-center justify-center">
                <i class="fa-solid fa-circle-exclamation text-xl"></i>
            </div>
            <p class="text-sm font-manrope font-bold tracking-tight"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php foreach ($rates as $r):
            $is_car = $r['vehicle_type'] === 'car';
            $section_id = $is_car ? 'car-rates' : 'moto-rates';
            $icon   = $is_car ? 'fa-car' : 'fa-motorcycle';
            $label  = $is_car ? 'Car Class (Type 1)' : 'Motorcycle Class (Type 2)';
        ?>
        <!-- SECTION: <?= strtoupper($r['vehicle_type']) ?> -->
        <section id="<?= $section_id ?>" class="scroll-section space-y-10">
            <div class="flex items-end justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-1.5 h-10 bg-brand rounded-full"></div>
                    <div>
                        <h2 class="text-2xl font-manrope font-black text-primary tracking-tight"><?= $label ?></h2>
                        <p class="text-tertiary mt-1 text-sm font-medium">Algorithmic pricing parameters for <?= $r['vehicle_type'] ?> vehicles.</p>

                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <!-- Parameters Card -->
                <div class="lg:col-span-7 bento-card bg-surface border-color rounded-[2.5rem] p-10 shadow-2xl relative overflow-hidden group">
                    <div class="absolute -right-16 -top-16 w-48 h-48 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-700"></div>
                    <form method="POST" class="relative z-10 space-y-12">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rate_id" value="<?= $r['rate_id'] ?>">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php
                            $fields = [
                                ['first_hour_rate', 'First Hour Rate',       500,  (int)$r['first_hour_rate'],  'fa-clock'],
                                ['next_hour_rate',  'Next Hour Rate',        500,  (int)$r['next_hour_rate'],   'fa-forward'],
                                ['daily_max_rate',  'Daily Max Limit',       1000, (int)$r['daily_max_rate'],   'fa-bolt'],
                            ];
                            foreach ($fields as [$fname, $flabel, $step, $fval, $ficon]):
                            ?>
                            <div class="space-y-4">
                                <label class="flex items-center gap-2 text-[9px] font-bold uppercase tracking-[0.25em] text-tertiary ml-1">
                                    <i class="fa-solid <?= $ficon ?> text-[8px] opacity-50"></i>
                                    <?= $flabel ?>
                                </label>
                                <div class="relative group/input flex items-center bg-surface-alt border border-color rounded-2xl px-5 py-4 focus-within:border-brand focus-within:ring-4 focus-within:ring-brand/5 transition-all duration-300">
                                    <div class="flex flex-col border-r border-color/50 pr-4 mr-1">
                                        <span class="text-[8px] font-black text-tertiary/40 uppercase leading-none mb-1">UNIT</span>
                                        <span class="text-[10px] font-black text-primary/70 leading-none">IDR</span>
                                    </div>
                                    <input type="number" name="<?= $fname ?>"
                                           value="<?= $fval ?>" min="0" step="<?= $step ?>" required
                                           id="<?= $fname . '_' . $r['rate_id'] ?>"
                                           oninput="updatePreview(<?= $r['rate_id'] ?>)"
                                           class="w-full bg-transparent border-none text-xl font-manrope font-black text-primary focus:outline-none text-right placeholder:text-tertiary/20">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pt-4">
                            <button type="submit"
                                    class="w-full bg-brand hover:brightness-110 text-white font-black text-[11px] uppercase tracking-[0.3em] rounded-2xl py-5 transition-all flex items-center justify-center gap-3 shadow-xl shadow-brand/20 active:scale-[0.99]">
                                <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                                Synchronize <?= $is_car ? 'Car' : 'Moto' ?> Parameters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Simulation Card -->
                <div class="lg:col-span-5 bento-card bg-surface border-color rounded-[2.5rem] p-10 shadow-2xl relative overflow-hidden group">
                    <div class="absolute -right-16 -top-16 w-48 h-48 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-700"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-surface-alt border border-color flex items-center justify-center shadow-sm">
                                    <i class="fa-solid fa-microchip text-brand text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Intelligence Simulation</p>
                                    <p class="text-[9px] font-bold text-tertiary uppercase mt-0.5">Live Engine Response</p>
                                </div>
                            </div>
                            <div class="px-3 py-1 rounded-full bg-brand/10 border border-brand/20 flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></div>
                                <span class="text-[9px] font-black text-brand uppercase tracking-widest">Active</span>
                            </div>
                        </div>
                    </div>

                    <div id="preview_<?= $r['rate_id'] ?>" class="space-y-1">
                        <!-- JS Dynamic -->
                    </div>

                    <div class="mt-8 pt-8 border-t border-color flex items-center gap-3 text-tertiary">
                        <i class="fa-solid fa-circle-info text-xs"></i>
                        <p class="text-[10px] font-medium leading-relaxed uppercase tracking-wider">Preview reflects auto-billing logic applied at exit gates.</p>
                    </div>
                </div>
            </div>
        </section>
        <?php endforeach; ?>

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
        const isMax = fee >= max && max > 0;
        html += `<div class="flex justify-between items-center py-3 border-b border-color last:border-0">
                    <span class="text-tertiary text-[10px] font-black uppercase tracking-widest">${h} hours</span>
                    <div class="flex items-center gap-2">
                        <span class="font-manrope font-black text-sm text-primary">${fmt(fee)}</span>
                        ${isMax ? '<span class="text-[8px] font-black uppercase status-badge-over px-2 py-0.5 rounded-md">Limit</span>' : ''}
                    </div>
                 </div>`;
    });
    box.innerHTML = html;
}

// Scroll Spy for Jump Menu
const scrollContainer = document.querySelector('main');
scrollContainer.addEventListener('scroll', () => {
    let current = '';
    document.querySelectorAll('.scroll-section').forEach(section => {
        const sectionTop = section.offsetTop;
        if (scrollContainer.scrollTop >= sectionTop - 250) {
            current = section.getAttribute('id');
        }
    });

    document.querySelectorAll('.jump-link').forEach(link => {
        link.classList.remove('bg-surface', 'border-color', 'text-primary');
        link.classList.add('text-tertiary', 'border-transparent');
        if (link.getAttribute('href').includes(current)) {
            link.classList.add('bg-surface', 'border-color', 'text-primary');
            link.classList.remove('text-tertiary', 'border-transparent');
        }
    });
});

<?php foreach ($rates as $r): ?>
updatePreview(<?= $r['rate_id'] ?>);
<?php endforeach; ?>
</script>

<?php include '../../includes/footer.php'; ?>
