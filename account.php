<?php
/**
 * account.php — User Account Dashboard
 * Premium dynamic interface for Parkhere customers
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/connection.php';
require_once 'includes/functions.php';

// Redirect to auth if not logged in
if (empty($_SESSION['customer_id'])) {
    header('Location: auth.php?redirect=account');
    exit;
}

$customer_id = $_SESSION['customer_id'];

// ── 1. Fetch Customer Profile ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

// ── 2. Fetch Customer Vehicles ─────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM vehicle WHERE customer_id = ? ORDER BY vehicle_id DESC");
$stmt->execute([$customer_id]);
$vehicles = $stmt->fetchAll();

// ── 3. Fetch Statistics ───────────────────────────────────────────────────
// Total Reservations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$total_reservations = $stmt->fetchColumn();

// Active Reservations (pending or confirmed)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation WHERE customer_id = ? AND status IN ('pending', 'confirmed')");
$stmt->execute([$customer_id]);
$active_reservations = $stmt->fetchColumn();

$current_page = 'account';
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>My Account — Parkhere</title>
    
    <!-- Icons (Keep here if specific version needed) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>

    <style>
        body {
            background-color: var(--bg-page);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .bento-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.04);
            border-color: var(--brand);
        }

        .avatar-glow {
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.15);
        }

        .modal-backdrop {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-bg-page text-primary selection:bg-brand/30">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <main class="pt-32 pb-20 px-6 max-w-7xl mx-auto">
        <!-- Profile Header Section -->
        <section class="mb-12">
            <div class="flex flex-col md:flex-row items-center gap-10 bento-card p-10 rounded-[2.5rem]">
                <div class="relative">
                    <div class="w-36 h-36 rounded-[2rem] overflow-hidden shadow-2xl avatar-glow border-4 border-white">
                        <img id="profileAvatar" alt="Profile" class="w-full h-full object-cover" 
                             src="<?= htmlspecialchars($customer['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($customer['full_name']) . '&background=6366f1&color=fff&size=256') ?>"/>
                    </div>
                    <input type="file" id="avatarInput" class="hidden" accept="image/*">
                    <button onclick="document.getElementById('avatarInput').click()" class="absolute -bottom-2 -right-2 bg-brand text-white w-12 h-12 rounded-2xl shadow-xl hover:scale-110 transition-transform flex items-center justify-center">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </div>
                
                <div class="text-center md:text-left flex-1">
                    <div class="flex flex-col md:flex-row md:items-center gap-4 mb-3">
                        <h1 class="font-manrope font-800 text-4xl text-primary">
                            <?= htmlspecialchars($customer['full_name']) ?>
                        </h1>

                    </div>
                    <p class="text-secondary font-semibold text-lg mb-8"><?= htmlspecialchars($customer['email']) ?></p>
                    
                    <div class="flex flex-wrap justify-center md:justify-start gap-12">
                        <div class="flex flex-col">
                            <span class="text-brand font-manrope font-800 text-3xl mb-1"><?= $total_reservations ?></span>
                            <span class="text-[10px] font-800 text-secondary uppercase tracking-[0.2em]">Total Bookings</span>
                        </div>
                        <div class="flex flex-col border-l border-border-color pl-12">
                            <span class="text-primary font-manrope font-800 text-3xl mb-1"><?= $active_reservations ?></span>
                            <span class="text-[10px] font-800 text-secondary uppercase tracking-[0.2em]">Active Now</span>
                        </div>
                        <div class="flex flex-col border-l border-border-color pl-12">
                            <span class="text-primary font-manrope font-800 text-3xl mb-1"><?= count($vehicles) ?></span>
                            <span class="text-[10px] font-800 text-secondary uppercase tracking-[0.2em]">Vehicles</span>
                        </div>
                    </div>
                </div>
                
                <div class="w-full md:w-auto">
                    <button onclick="toggleModal('editProfileModal')" class="w-full bg-brand text-white px-8 py-4 rounded-2xl font-bold flex items-center justify-center gap-3 shadow-lg shadow-brand/20 hover:brightness-110 transition-all">
                        <i class="fa-solid fa-user-pen"></i>
                        Edit Profile
                    </button>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- My Vehicles Section -->
            <section class="lg:col-span-8 space-y-8">
                <div class="flex items-center justify-between px-2">
                    <h2 class="font-manrope font-800 text-3xl text-primary">My Vehicles</h2>
                    <button onclick="toggleModal('addVehicleModal')" class="text-brand flex items-center gap-2 font-800 hover:brightness-90 transition-all">
                        <i class="fa-solid fa-circle-plus text-xl"></i>
                        Add New
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($vehicles)): ?>
                        <div class="md:col-span-2 bento-card p-16 rounded-[2.5rem] text-center border-dashed">
                            <div class="w-20 h-20 bg-brand/5 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fa-solid fa-car-side text-secondary text-4xl"></i>
                            </div>
                            <p class="text-secondary font-semibold text-lg">No vehicles registered yet.</p>
                            <button onclick="toggleModal('addVehicleModal')" class="text-brand text-sm font-800 mt-3 hover:underline underline-offset-4 tracking-widest uppercase">Register First Vehicle</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                            <div class="bento-card p-8 rounded-[2.5rem] relative overflow-hidden group">
                                <div class="absolute -right-6 -top-6 p-10 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity transform group-hover:scale-110 group-hover:rotate-12 duration-500">
                                    <i class="fa-solid <?= $v['vehicle_type'] === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' ?> text-9xl"></i>
                                </div>
                                
                                <div class="flex justify-between items-start mb-8 relative z-10">
                                    <div class="w-14 h-14 bg-brand/10 rounded-2xl flex items-center justify-center text-brand">
                                        <i class="fa-solid <?= $v['vehicle_type'] === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' ?> text-2xl"></i>
                                    </div>
                                    <div class="flex gap-3">
                                        <button class="w-10 h-10 rounded-xl bg-surface-alt text-secondary hover:text-brand hover:bg-brand/5 transition-all flex items-center justify-center">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button class="w-10 h-10 rounded-xl bg-surface-alt text-red-400/50 hover:text-red-500 hover:bg-red-50 transition-all flex items-center justify-center">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="relative z-10">
                                    <h3 class="font-manrope font-800 text-2xl text-primary mb-2">
                                        <?= htmlspecialchars($v['owner_name'] ?: 'My Vehicle') ?>
                                    </h3>
                                    <p class="text-brand font-mono tracking-[0.25em] text-lg mb-6 font-800">
                                        <?= htmlspecialchars($v['plate_number']) ?>
                                    </p>
                                    
                                    <div class="flex items-center gap-2 text-secondary font-800 text-[10px] uppercase tracking-[0.2em]">
                                        <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.4)]"></span>
                                        System Verified
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Sidebar Content -->
            <aside class="lg:col-span-4 space-y-8">
                <div class="bento-card p-8 rounded-[2.5rem]">
                    <h3 class="font-manrope font-800 text-xl text-primary mb-8 flex items-center gap-3">
                        <i class="fa-solid fa-shield-halved text-brand"></i>
                        Security & Access
                    </h3>
                    
                    <div class="space-y-3">
                        <a href="my_bookings.php" class="flex items-center gap-5 p-5 rounded-3xl hover:bg-surface-alt transition-all group border border-transparent hover:border-border-color">
                            <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 group-hover:bg-brand group-hover:text-white transition-all shadow-sm">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-800 text-primary">Booking History</p>
                                <p class="text-[10px] text-secondary font-semibold uppercase tracking-wider">Review all visits</p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-xs text-secondary group-hover:translate-x-1 transition-transform"></i>
                        </a>

                        <button onclick="toggleModal('securityModal')" class="w-full flex items-center gap-5 p-5 rounded-3xl hover:bg-surface-alt transition-all group border border-transparent hover:border-border-color text-left">
                            <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-all shadow-sm">
                                <i class="fa-solid fa-lock text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-800 text-primary">Security Settings</p>
                                <p class="text-[10px] text-secondary font-semibold uppercase tracking-wider">Password & 2FA</p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-xs text-secondary group-hover:translate-x-1 transition-transform"></i>
                        </button>

                        <a href="logout.php?type=customer" class="flex items-center gap-5 p-5 rounded-3xl hover:bg-red-50 transition-all group border border-transparent hover:border-red-100">
                            <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 group-hover:bg-red-500 group-hover:text-white transition-all shadow-sm">
                                <i class="fa-solid fa-power-off text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-800 text-primary">Logout</p>
                                <p class="text-[10px] text-secondary font-semibold uppercase tracking-wider">End current session</p>
                            </div>
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Modals -->
    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-6">
        <div class="absolute inset-0 modal-backdrop" onclick="toggleModal('addVehicleModal')"></div>
        <div class="bg-white w-full max-w-md rounded-[2.5rem] overflow-hidden relative z-10 shadow-2xl border border-border-color">
            <div class="p-10">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-manrope font-800 text-2xl text-primary">Add Vehicle</h3>
                    <button onclick="toggleModal('addVehicleModal')" class="w-10 h-10 rounded-xl hover:bg-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-xmark text-secondary"></i>
                    </button>
                </div>
                
                <form action="api/manage_vehicles.php" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">License Plate</label>
                        <input type="text" name="plate_number" required placeholder="B 1234 ABC" 
                               pattern="^[A-Za-z]{1,3}\s*\d{1,4}\s*[A-Za-z]{0,3}\s*$"
                               title="Format: A 1234 BCD (Uppercase, spaces optional)"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-mono tracking-[0.2em] uppercase text-lg">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">Vehicle Type</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative flex items-center justify-center p-6 rounded-2xl border-2 border-slate-100 cursor-pointer hover:bg-slate-50 transition-all group">
                                <input type="radio" name="vehicle_type" value="car" checked class="peer sr-only">
                                <div class="peer-checked:text-brand transition-colors text-slate-400 flex flex-col items-center">
                                    <i class="fa-solid fa-car text-2xl mb-2"></i>
                                    <span class="text-xs font-800 tracking-widest uppercase">Car</span>
                                </div>
                                <div class="absolute inset-0 border-2 border-transparent peer-checked:border-brand rounded-2xl pointer-events-none"></div>
                            </label>
                            
                            <label class="relative flex items-center justify-center p-6 rounded-2xl border-2 border-slate-100 cursor-pointer hover:bg-slate-50 transition-all group">
                                <input type="radio" name="vehicle_type" value="motorcycle" class="peer sr-only">
                                <div class="peer-checked:text-brand transition-colors text-slate-400 flex flex-col items-center">
                                    <i class="fa-solid fa-motorcycle text-2xl mb-2"></i>
                                    <span class="text-xs font-800 tracking-widest uppercase">Moto</span>
                                </div>
                                <div class="absolute inset-0 border-2 border-transparent peer-checked:border-brand rounded-2xl pointer-events-none"></div>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-brand text-white py-5 rounded-2xl font-bold text-lg shadow-lg shadow-brand/20 hover:brightness-110 active:scale-95 transition-all">
                        Register Vehicle
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-6">
        <div class="absolute inset-0 modal-backdrop" onclick="toggleModal('editProfileModal')"></div>
        <div class="bg-white w-full max-w-md rounded-[2.5rem] overflow-hidden relative z-10 shadow-2xl border border-border-color">
            <div class="p-10">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-manrope font-800 text-2xl text-primary">Edit Profile</h3>
                    <button onclick="toggleModal('editProfileModal')" class="w-10 h-10 rounded-xl hover:bg-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-xmark text-secondary"></i>
                    </button>
                </div>
                
                <form action="api/manage_profile.php" method="POST" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">Full Name</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($customer['full_name']) ?>" required
                               pattern="^[a-zA-Z\s\.\,\']{3,50}$"
                               title="Full name (3-50 characters, letters only)"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-semibold">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($customer['phone'] ?: '') ?>" placeholder="+62..."
                               pattern="^(\+62|0)8[1-9][0-9]{7,11}$"
                               title="Indonesian phone number (e.g. 0812... or +62812...)"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-semibold">
                    </div>
                    
                    <button type="submit" class="w-full bg-brand text-white py-5 rounded-2xl font-bold text-lg shadow-lg shadow-brand/20 hover:brightness-110 active:scale-95 transition-all">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Security Settings Modal -->
    <div id="securityModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-6">
        <div class="absolute inset-0 modal-backdrop" onclick="toggleModal('securityModal')"></div>
        <div class="bg-white w-full max-w-md rounded-[2.5rem] overflow-hidden relative z-10 shadow-2xl border border-border-color">
            <div class="p-10">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-manrope font-800 text-2xl text-primary">Security Settings</h3>
                    <button onclick="toggleModal('securityModal')" class="w-10 h-10 rounded-xl hover:bg-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-xmark text-secondary"></i>
                    </button>
                </div>
                
                <form id="changePasswordForm" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">Current Password</label>
                        <input type="password" name="current_password" required placeholder="••••••••"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-semibold">
                    </div>

                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">New Password</label>
                        <input type="password" name="new_password" required placeholder="••••••••"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-semibold">
                    </div>

                    <div>
                        <label class="block text-[10px] font-800 text-secondary uppercase tracking-[0.2em] mb-3 ml-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="••••••••"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all font-semibold">
                    </div>
                    
                    <button type="submit" class="w-full bg-brand text-white py-5 rounded-2xl font-bold text-lg shadow-lg shadow-brand/20 hover:brightness-110 active:scale-95 transition-all">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleModal(id) {
            const modal = document.getElementById(id);
            modal.classList.toggle('hidden');
            if (!modal.classList.contains('hidden')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }

        // Avatar Upload Logic
        const avatarInput = document.getElementById('avatarInput');
        const profileAvatar = document.getElementById('profileAvatar');

        avatarInput.addEventListener('change', async function() {
            if (this.files && this.files[0]) {
                const formData = new FormData();
                formData.append('avatar', this.files[0]);

                try {
                    const response = await fetch('api/upload_avatar.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        profileAvatar.src = data.avatar_url;
                        alert('Profile picture updated successfully!');
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred during upload.');
                }
            }
        });

        // Change Password Logic
        const changePasswordForm = document.getElementById('changePasswordForm');
        changePasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const response = await fetch('api/change_password.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    toggleModal('securityModal');
                    this.reset();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    </script>
</body>
</html>
