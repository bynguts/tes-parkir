<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php'); exit;
}

require_once 'config/connection.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Invalid request payload. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $ip       = $_SERVER['REMOTE_ADDR'];
        $key      = 'login_fail_' . md5($ip);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'since' => time()];
        if (time() - $_SESSION[$key]['since'] > 300) {
            $_SESSION[$key] = ['count' => 0, 'since' => time()];
        }
        if ($_SESSION[$key]['count'] >= 5) {
            $remaining = 300 - (time() - $_SESSION[$key]['since']);
            $error = "Too many attempts. System locked for {$remaining}s.";
        } else {
            $stmt = $pdo->prepare("SELECT user_id, password_hash, role, full_name FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                unset($_SESSION[$key]);
                session_regenerate_id(true);
                $_SESSION['logged_in']        = true;
                $_SESSION['user_id']          = $user['user_id'];
                $_SESSION['username']         = $username;
                $_SESSION['role']             = $user['role'];
                $_SESSION['full_name']        = $user['full_name'];
                $_SESSION['last_regenerated'] = time();

                $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE user_id = ?")
                    ->execute([$user['user_id']]);

                header('Location: index.php'); exit;
            } else {
                $_SESSION[$key]['count']++;
                $error = 'Authentication failed: Invalid identity or key.';
                usleep(300000);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access — SmartParking</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: '#6366f1',
                    'brand-subtle': '#eef2ff',
                },
                fontFamily: {
                    'manrope': ['Manrope', 'sans-serif'],
                    'inter': ['Inter', 'sans-serif'],
                },
            }
        }
    }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; transition: all 0.3s ease; }
        body { background-color: var(--bg-page); color: var(--text-primary); }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.8s cubic-bezier(.16,1,.3,1) forwards; }
        .bento-card {
            background-color: var(--surface);
            border: 2px solid var(--border-color);
            box-shadow: 0 25px 50px -12px var(--shadow-color);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col items-center justify-center px-4 overflow-hidden relative">

<!-- LUXURY ACCENTS -->
<div class="fixed -top-24 -right-24 w-96 h-96 bg-brand/5 rounded-full blur-[120px] pointer-events-none"></div>
<div class="fixed -bottom-24 -left-24 w-96 h-96 bg-brand/5 rounded-full blur-[120px] pointer-events-none"></div>

<div class="w-full max-w-md fade-up z-10">

    <!-- BRAND ARCHITECTURE -->
    <div class="text-center mb-12">
        <div class="w-16 h-16 bg-brand rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-brand/20 ring-8 ring-brand/5 hover:scale-110 transition-transform cursor-default">
            <i class="fa-solid fa-square-p text-white text-3xl"></i>
        </div>
        <h1 class="font-manrope font-black text-4xl text-primary tracking-tight mb-2">SmartParking</h1>
        <div class="flex items-center justify-center gap-3">
            <div class="h-px w-8 bg-brand/20"></div>
            <p class="text-brand text-[10px] font-black uppercase tracking-[0.4em] font-inter">Security Protocol</p>
            <div class="h-px w-8 bg-brand/20"></div>
        </div>
    </div>

    <!-- LOGIN SURFACE -->
    <div class="bento-card rounded-[3rem] p-12 relative overflow-hidden group">
        <!-- Glossy overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-brand/[0.02] to-transparent pointer-events-none"></div>
        
        <div class="relative z-10">
            <div class="mb-10">
                <h2 class="font-manrope font-black text-2xl text-primary mb-1">Administrative Access</h2>
                <p class="text-tertiary text-[10px] font-bold uppercase tracking-widest">Enterprise Authentication Node</p>
            </div>

            <?php if ($error): ?>
            <div class="flex items-center gap-4 bg-rose-500/5 border border-rose-500/10 rounded-2xl p-5 mb-8 animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-500 shrink-0">
                    <i class="fa-solid fa-shield-xmark text-lg"></i>
                </div>
                <p class="text-rose-500 text-xs font-black font-inter leading-relaxed"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <?= csrf_field() ?>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-2">
                        <i class="fa-solid fa-user-shield text-[8px] text-brand"></i>
                        Identity
                    </label>
                    <input type="text" name="username"
                           class="w-full bg-surface-alt border-2 border-transparent focus:border-brand/30 rounded-2xl px-6 py-4.5 text-sm text-primary focus:outline-none transition-all font-inter font-bold placeholder-primary/20 shadow-inner"
                           placeholder="Enter username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-tertiary ml-2">
                        <i class="fa-solid fa-key-skeleton text-[8px] text-brand"></i>
                        Security Key
                    </label>
                    <div class="relative group/pass">
                        <input type="password" name="password" id="passInput"
                               class="w-full bg-surface-alt border-2 border-transparent focus:border-brand/30 rounded-2xl px-6 py-4.5 text-sm text-primary focus:outline-none transition-all font-inter font-bold placeholder-primary/20 shadow-inner"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" onclick="togglePass()" class="absolute right-5 top-1/2 -translate-y-1/2 text-tertiary hover:text-brand transition-colors p-2">
                            <i id="passIcon" class="fa-solid fa-eye-slash text-xs"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-brand hover:brightness-110 text-white font-black font-inter rounded-2xl uppercase tracking-[0.2em] text-[11px] py-5 transition-all mt-8 shadow-2xl shadow-brand/20 hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-3">
                    <span>Initialize Session</span>
                    <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- FOOTER ARCHITECTURE -->
    <div class="mt-12 flex flex-col items-center gap-6 opacity-40 hover:opacity-100 transition-opacity">
        <div class="flex items-center gap-4">
            <div class="h-px w-12 bg-primary/10"></div>
            <i class="fa-solid fa-microchip text-[10px]"></i>
            <div class="h-px w-12 bg-primary/10"></div>
        </div>
        <p class="text-center text-primary text-[10px] font-black font-inter uppercase tracking-[0.5em]">
            Precision Engineering Core — <?= date('Y') ?>
        </p>
    </div>
</div>

<script>
function togglePass() {
    const input = document.getElementById('passInput');
    const icon = document.getElementById('passIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}
</script>

</body>
</html>
