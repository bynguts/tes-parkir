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

        $lost_fine  = (float)($_POST['lost_ticket_fine'] ?? 0);

        if ($first_rate <= 0 || $next_rate <= 0 || $lost_fine < 0) {
            $error = 'Rate adjustment value must be valid.';
        } else {
            $pdo->prepare("UPDATE parking_rate SET first_hour_rate=?, next_hour_rate=?, lost_ticket_fine=? WHERE rate_id=?")
                ->execute([$first_rate, $next_rate, $lost_fine, $rate_id]);
            $msg = 'Parking rate configuration successfully updated in the database.';
        }
    }
}

$rates = $pdo->query("SELECT * FROM parking_rate ORDER BY vehicle_type")->fetchAll();
$page_title = 'Rate Configuration';
$page_subtitle = 'Financial parameter settings for the parking auto-billing system.';

include '../../includes/header.php';
?>



<style>
    /* Prevent number spin buttons from appearing and potentially cutting off text */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
</style>

<div class="px-10 py-10">
    
    <!-- HEADER -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-manrope font-extrabold text-primary tracking-tight">Rate Configuration</h1>
            <p class="text-sm font-inter text-tertiary mt-1">Financial parameter settings for the parking auto-billing system.</p>
        </div>
    </div>

    <!-- STICKY JUMP MENU -->
    <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <a href="#car-rates" class="jump-link flex items-center gap-2 px-4 py-2 rounded-xl text-[11px] font-inter font-medium tracking-wider text-tertiary hover:text-brand hover:bg-surface-alt transition-all">
            <i class="fa-solid fa-car text-sm"></i>
            Car Class
        </a>
        <a href="#moto-rates" class="jump-link flex items-center gap-2 px-4 py-2 rounded-xl text-[11px] font-inter font-medium tracking-wider text-tertiary hover:text-brand hover:bg-surface-alt transition-all">
            <i class="fa-solid fa-motorcycle text-sm"></i>
            Motorcycle Class
        </a>
    </div>

    <div class="space-y-12">
        
        <div id="toastContainer" class="fixed top-24 right-10 z-[100] flex flex-col gap-3 pointer-events-none">
            <?php if ($msg): ?>
            <div class="flex items-center gap-3 bg-surface border border-status-available-border rounded-2xl px-6 py-4 shadow-2xl animate-in slide-in-from-right-10 duration-500">
                <div class="w-8 h-8 rounded-full bg-status-available-bg flex items-center justify-center text-status-available-text">
                    <i class="fa-solid fa-check text-sm"></i>
                </div>
                <p class="text-primary text-sm font-manrope font-bold"><?= $msg ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="flex items-center gap-3 bg-surface border border-status-lost-border rounded-2xl px-6 py-4 shadow-2xl animate-in slide-in-from-right-10 duration-500">
                <div class="w-8 h-8 rounded-full bg-status-lost-bg flex items-center justify-center text-status-lost-text">
                    <i class="fa-solid fa-exclamation text-sm"></i>
                </div>
                <p class="text-primary text-sm font-manrope font-bold"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php foreach ($rates as $r) {
            $is_car = $r['vehicle_type'] === 'car';
            $section_id = $is_car ? 'car-rates' : 'moto-rates';
            $icon   = $is_car ? 'fa-car' : 'fa-motorcycle';
            $label  = $is_car ? 'Car Class (Type 1)' : 'Motorcycle Class (Type 2)';
        ?>
        <!-- SECTION: <?= strtoupper($r['vehicle_type']) ?> -->
        <!-- Section Identity (Sticky Anchor) -->
        <div class="space-y-12">
            <div id="<?= $section_id ?>" class="scroll-section"></div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Parameters Card -->
                <div class="lg:col-span-7 bento-card overflow-hidden group relative">
                    <!-- Decorative blur -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                    
                    <div class="flex items-center justify-between py-5 px-4 border-b border-color relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid <?= $icon ?> text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight"><?= $label ?></h3>
                                <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Pricing Configuration Strategy</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 relative z-10">
                        <form method="POST" class="space-y-12">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rate_id" value="<?= $r['rate_id'] ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php
                            $fields = [
                                ['first_hour_rate', 'First Hour Rate',       500,  (int)$r['first_hour_rate'],  'fa-clock'],
                                ['next_hour_rate',  'Next Hour Rate',        500,  (int)$r['next_hour_rate'],   'fa-forward'],
                                ['lost_ticket_fine','Lost Ticket Fine',      1000, (int)$r['lost_ticket_fine'], 'fa-ticket'],
                            ];
                            foreach ($fields as [$fname, $flabel, $step, $fval, $ficon]):
                            ?>
                            <div class="relative group">
                                <input type="number" name="<?= $fname ?>"
                                       value="<?= $fval ?>" min="0" step="<?= $step ?>" required
                                       id="<?= $fname . '_' . $r['rate_id'] ?>"
                                       oninput="updatePreview(<?= $r['rate_id'] ?>)"
                                       class="w-full bg-surface-alt border border-color rounded-xl px-4 py-3 h-[38px] text-lg font-manrope font-black text-primary focus:outline-none text-right focus:border-brand transition-all appearance-none">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center gap-2 pointer-events-none text-tertiary/50">
                                    <i class="fa-solid <?= $ficon ?> text-xs"></i>
                                    <span class="text-[9px] font-bold uppercase tracking-widest"><?= $flabel ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pt-4">
                            <button type="submit"
                                    class="flex w-full justify-center items-center gap-2 bg-brand text-white text-[11px] font-inter font-medium uppercase tracking-wider px-4 h-[38px] rounded-xl transition-all shadow-lg shadow-brand/20 hover:brightness-110 active:scale-95">
                                <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                                Synchronize <?= $is_car ? 'Car' : 'Moto' ?> Parameters
                            </button>
                        </div>
                    </form>
                    </div>
                </div>

                <!-- Simulation Card -->
                <div class="lg:col-span-5 bento-card overflow-hidden group relative flex flex-col">
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all duration-500"></div>
                    
                    <div class="flex items-center justify-between py-5 px-4 border-b border-color relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl icon-container flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-microchip text-lg"></i>
                            </div>
                            <div>
                                <h3 class="card-title leading-tight">Intelligence Simulation</h3>
                                <p class="text-[11px] text-tertiary font-inter font-medium uppercase tracking-wider">Live Engine Response</p>
                            </div>
                        </div>
                        <div class="px-3 py-1 rounded-full bg-brand/10 border border-brand/20 flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></div>
                            <span class="text-[9px] font-black text-brand uppercase tracking-widest">Active</span>
                        </div>
                    </div>

                    <div class="p-8 relative z-10 flex-1 flex flex-col">
                        <div id="preview_<?= $r['rate_id'] ?>" class="space-y-1 flex-1">
                            <!-- JS Dynamic -->
                        </div>

                        <div class="mt-8 pt-8 border-t border-color flex items-center gap-3 text-tertiary">
                            <i class="fa-solid fa-circle-info text-xs"></i>
                            <p class="text-[11px] font-inter font-medium tracking-wider">Preview reflects auto-billing logic applied at exit gates.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<script>
function updatePreview(id) {
    const first = parseFloat(document.getElementById('first_hour_rate_'+id).value) || 0;
    const next  = parseFloat(document.getElementById('next_hour_rate_'+id).value) || 0;
    const fmt   = v => 'Rp ' + v.toLocaleString('id-ID');
    const box   = document.getElementById('preview_'+id);

    let html = '';
    [1,2,3,6,12,24].forEach(h => {
        let fee = h <= 1 ? first : first + (h-1)*next;
        html += `<div class="flex justify-between items-center py-3 border-b border-color last:border-0">
                    <span class="text-tertiary text-[10px] font-black uppercase tracking-widest">${h} hours</span>
                    <div class="flex items-center gap-2">
                        <span class="font-manrope font-black text-sm text-primary">${fmt(fee)}</span>
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

<?php foreach ($rates as $r) { ?>
updatePreview(<?= $r['rate_id'] ?>);
<?php } ?>
</script>

<?php include '../../includes/footer.php'; ?>
