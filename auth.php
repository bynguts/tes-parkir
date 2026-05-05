<?php
// auth.php - Customer Authentication (Login & Register)
if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in as customer, redirect to account
if (!empty($_SESSION['customer_id'])) {
    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'account';
    $safe = ['account', 'my_bookings', 'home', 'reserve'];
    header('Location: ' . (in_array($redirect, $safe) ? $redirect . '.php' : 'account.php'));
    exit;
}

$redirect_to = htmlspecialchars($_GET['redirect'] ?? '');
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/connection.php';
    $action = $_POST['action'] ?? '';
    $redirect_to = htmlspecialchars($_POST['redirect'] ?? '');

    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            // Look up customer in customers table
            $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['customer_id']    = $customer['id'];
                $_SESSION['customer_name']  = $customer['full_name'];
                $_SESSION['customer_email'] = $customer['email'];

                $safe = ['account', 'my_bookings', 'home', 'reserve'];
                $dest = in_array($redirect_to, $safe) ? $redirect_to . '.php' : 'account.php';
                header('Location: ' . $dest);
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
                usleep(300000);
            }
        }

    } elseif ($action === 'register') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($fullname) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            // Validation Regex
            $plateRegex = '/^[A-Za-z]{1,3}\s*\d{1,4}\s*[A-Za-z]{0,3}\s*$/';
            $phoneRegex = '/^(\+62|0)8[1-9][0-9]{7,11}$/';
            $nameRegex  = '/^[a-zA-Z\s\.\,\']{3,50}$/';

            // Clean data for validation
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            $plate = strtoupper(trim($_POST['plate'] ?? ''));

            if (!preg_match($nameRegex, $fullname)) {
                $error = 'Full name must be 3-50 characters (letters only).';
            } elseif (!empty($phone) && !preg_match($phoneRegex, $cleanPhone)) {
                $error = 'Invalid Indonesian phone format (e.g. 0812...).';
            } elseif (!empty($plate) && !preg_match($plateRegex, $plate)) {
                $error = 'Invalid License Plate format (e.g. B 1234 ABC).';
            } else {
                // Check if email already exists
                $check = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'An account with this email already exists. Please sign in.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ins  = $pdo->prepare("INSERT INTO customers (full_name, email, phone, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $ins->execute([$fullname, $email, $cleanPhone, $hash]);
                    $new_id = $pdo->lastInsertId();

                    // If a plate was provided, associate it with this customer
                    if (!empty($plate)) {
                        // Ensure vehicle exists or create it
                        $vCheck = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
                        $vCheck->execute([$plate]);
                        $vExisting = $vCheck->fetch();

                        if ($vExisting) {
                            $upd = $pdo->prepare("UPDATE vehicle SET customer_id = ? WHERE vehicle_id = ? AND customer_id IS NULL");
                            $upd->execute([$new_id, $vExisting['vehicle_id']]);
                        } else {
                            $vIns = $pdo->prepare("INSERT INTO vehicle (plate_number, customer_id, vehicle_type) VALUES (?, ?, 'car')");
                            $vIns->execute([$plate, $new_id]);
                        }
                    }

                    session_regenerate_id(true);
                    $_SESSION['customer_id']    = $new_id;
                    $_SESSION['customer_name']  = $fullname;
                    $_SESSION['customer_email'] = $email;

                    header('Location: account.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login - Parkhere</title>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Logic & Tailwind -->
    <?php include 'includes/theme_init.php'; ?>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .auth-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        }

        .form-input {
            background: var(--bg-page);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            color: var(--text-primary);
        }

        .form-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .btn-brand {
            background: var(--brand);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-brand:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.2);
        }

        .btn-brand:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-inter text-primary antialiased relative">
    <!-- Navigation Shell -->
    <header class="w-full px-6 py-12 flex justify-center items-center z-50">
        <a href="home.php" class="flex items-center gap-3 group">
            <img src="assets/images/logo.png" alt="Parkhere" class="w-12 h-12 object-contain group-hover:scale-110 transition-transform duration-300">
            <span class="text-2xl font-manrope font-800 tracking-tight text-primary"><span class="text-brand">Park</span>here</span>
        </a>
    </header>

    <main class="flex-grow flex items-center justify-center px-4 pb-20 z-10">
        <div class="w-full max-w-[480px] auth-card rounded-[2.5rem] p-8 md:p-12 relative overflow-hidden">
            
            <!-- Error Messages -->
            <?php if (!empty($error)): ?>
                <div class="mb-8 bg-red-500/5 border border-red-500/20 text-red-600 p-4 rounded-2xl text-center text-sm font-semibold animate-pulse">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="text-center mb-10">
                <h1 id="auth-title" class="text-3xl font-manrope font-800 text-primary mb-2">Welcome Back</h1>
                <p id="auth-subtitle" class="text-secondary font-medium">Access your premium parking dashboard</p>
            </div>

            <!-- Auth Form -->
            <form id="auth-form" method="POST" action="auth.php" class="space-y-6">
                <input type="hidden" name="action" id="auth-action" value="login">
                <input type="hidden" name="redirect" value="<?= $redirect_to ?>">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>">
                <input type="hidden" name="plate" value="<?= htmlspecialchars($_GET['plate'] ?? '') ?>">

                <!-- Full Name Field (Hidden by default) -->
                <div id="field-fullname" class="space-y-2 hidden">
                    <label class="text-xs font-bold text-secondary uppercase tracking-widest block ml-1">Full Name</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-secondary group-focus-within:text-brand transition-colors"></i>
                        </div>
                        <input name="fullname" class="form-input w-full rounded-2xl py-4 pl-12 pr-4 font-semibold" 
                               placeholder="Full Name" type="text"
                               pattern="^[a-zA-Z\s\.\,\']{3,50}$"
                               title="Full name (3-50 characters, letters only)"/>
                    </div>
                </div>

                <!-- Email Field -->
                <div class="space-y-2">
                    <label class="text-xs font-bold text-secondary uppercase tracking-widest block ml-1">Email Address</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-envelope text-secondary group-focus-within:text-brand transition-colors"></i>
                        </div>
                        <input name="email" required class="form-input w-full rounded-2xl py-4 pl-12 pr-4 font-semibold" placeholder="name@email.com" type="email"/>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-xs font-bold text-secondary uppercase tracking-widest">Password</label>
                        <a id="forgot-link" class="text-xs font-bold text-brand hover:brightness-110 transition-all" href="#">Forgot?</a>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-secondary group-focus-within:text-brand transition-colors"></i>
                        </div>
                        <input name="password" id="password-input" required class="form-input w-full rounded-2xl py-4 pl-12 pr-12 font-semibold" placeholder="••••••••" type="password"/>
                        <div class="absolute inset-y-0 right-0 pr-5 flex items-center cursor-pointer text-secondary hover:text-primary transition-colors" onclick="togglePassword()">
                            <i id="password-toggle-icon" class="fa-solid fa-eye"></i>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button id="auth-submit-btn" class="btn-brand w-full py-4 rounded-2xl text-lg font-bold flex items-center justify-center gap-3 mt-4" type="submit">
                    <span>Sign In</span>
                    <i class="fa-solid fa-arrow-right text-sm"></i>
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-10 flex items-center">
                <div class="flex-grow border-t border-color"></div>
                <span class="flex-shrink mx-4 text-[10px] font-bold text-secondary uppercase tracking-[0.2em]">Social Login</span>
                <div class="flex-grow border-t border-color"></div>
            </div>

            <!-- Social Logins -->
            <div class="grid grid-cols-2 gap-4">
                <button class="flex items-center justify-center gap-3 bg-bg-page border border-color hover:border-brand transition-all text-primary font-bold py-3.5 rounded-2xl text-sm">
                    <i class="fa-brands fa-google text-brand"></i>
                    Google
                </button>
                <button class="flex items-center justify-center gap-3 bg-bg-page border border-color hover:border-brand transition-all text-primary font-bold py-3.5 rounded-2xl text-sm">
                    <i class="fa-brands fa-apple text-primary"></i>
                    Apple
                </button>
            </div>

            <!-- Footer CTA -->
            <div class="mt-10 text-center">
                <p class="text-secondary font-medium">
                    <span id="footer-text">Don't have an account?</span>
                    <a id="footer-link" class="text-brand font-bold hover:underline underline-offset-4 transition-all ml-1" href="javascript:toggleAuthMode()">Create Account</a>
                </p>
            </div>
        </div>
    </main>

    <!-- Legal Footer -->
    <footer class="w-full py-8 px-6 text-center z-10 border-t border-color bg-surface/50">
        <p class="text-[11px] font-bold text-secondary uppercase tracking-widest">&copy; 2026 Parkhere Technologies Inc. All rights reserved.</p>
    </footer>

    <script>
        let currentMode = 'login';

        // Auto-detect mode and pre-fill data from URL
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const action = params.get('action');
            const fullname = params.get('fullname');

            if (action === 'register') {
                toggleAuthMode();
            }

            if (fullname) {
                const fullnameInput = document.querySelector('input[name="fullname"]');
                if (fullnameInput) fullnameInput.value = fullname;
            }

            const email = params.get('email');
            if (email) {
                const emailInput = document.querySelector('input[name="email"]');
                if (emailInput) emailInput.value = email;
            }
        });

        function toggleAuthMode() {
            const title = document.getElementById('auth-title');
            const subtitle = document.getElementById('auth-subtitle');
            const action = document.getElementById('auth-action');
            const submitBtn = document.getElementById('auth-submit-btn');
            const footerText = document.getElementById('footer-text');
            const footerLink = document.getElementById('footer-link');
            const fullnameField = document.getElementById('field-fullname');
            const forgotLink = document.getElementById('forgot-link');

            if (currentMode === 'login') {
                currentMode = 'register';
                title.textContent = 'Create Account';
                subtitle.textContent = 'Join the future of premium parking';
                action.value = 'register';
                submitBtn.innerHTML = '<span>Create Account</span> <i class="fa-solid fa-user-plus text-sm"></i>';
                footerText.textContent = 'Already have an account?';
                footerLink.textContent = 'Sign In';
                fullnameField.classList.remove('hidden');
                fullnameField.querySelector('input').setAttribute('required', 'required');
                forgotLink.classList.add('hidden');
            } else {
                currentMode = 'login';
                title.textContent = 'Welcome Back';
                subtitle.textContent = 'Access your premium parking dashboard';
                action.value = 'login';
                submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right text-sm"></i>';
                footerText.textContent = "Don't have an account?";
                footerLink.textContent = 'Create Account';
                fullnameField.classList.add('hidden');
                fullnameField.querySelector('input').removeAttribute('required');
                forgotLink.classList.remove('hidden');
            }
        }

        function togglePassword() {
            const input = document.getElementById('password-input');
            const icon = document.getElementById('password-toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
