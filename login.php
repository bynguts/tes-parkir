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

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    'manrope': ['Manrope', 'sans-serif'],
                    'inter': ['Inter', 'sans-serif'],
                },
            }
        }
    }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.6s cubic-bezier(.16,1,.3,1) forwards; }
        h1, h2 { font-family: 'Manrope', sans-serif; font-weight: 800; }
    </style>
</head>

<body class="min-h-screen bg-slate-50 flex items-center justify-center px-4 font-inter antialiased">

<!-- Asymmetric background accents -->
<div class="fixed top-0 right-0 w-[600px] h-[600px] bg-slate-900 rounded-full opacity-[0.02] -mr-32 -mt-32 pointer-events-none"></div>
<div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-slate-900 rounded-full opacity-[0.015] -ml-20 -mb-20 pointer-events-none"></div>

<div class="w-full max-w-sm fade-up">

    <!-- Brand mark -->
    <div class="text-center mb-10">
        <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-2xl shadow-slate-900/20 ring-4 ring-white">
            <i class="fa-solid fa-square-p text-white text-2xl"></i>
        </div>
        <h1 class="font-manrope font-extrabold text-3xl text-slate-900 tracking-tight">SmartParking</h1>
        <p class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-[0.2em] mt-2 font-inter">Enterprise System</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-3xl ring-1 ring-slate-900/5 shadow-[0_20px_50px_rgba(15,23,42,0.04)] p-10 relative overflow-hidden">
        <!-- Subtle glass reflection -->
        <div class="absolute -top-24 -left-24 w-48 h-48 bg-slate-900/[0.02] rounded-full blur-3xl"></div>
        
        <div class="relative z-10">
            <h2 class="font-manrope font-extrabold text-2xl text-slate-900 mb-1">Sign in</h2>
            <p class="text-slate-900/40 text-[11px] font-extrabold uppercase tracking-widest font-inter mb-8">Secure Corporate Access</p>

            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50/10 border border-red-500/20 rounded-2xl p-4 mb-6">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-sm mt-1"></i>
                <p class="text-red-700 text-xs font-bold font-inter leading-snug"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter mb-2 ml-1">Username</label>
                    <input type="text" name="username"
                           class="w-full bg-slate-50 border border-slate-900/5 rounded-2xl px-5 py-4 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all font-inter font-semibold placeholder-slate-900/20"
                           placeholder="admin.id"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-900/40 font-inter mb-2 ml-1">Password</label>
                    <input type="password" name="password"
                           class="w-full bg-slate-50 border border-slate-900/5 rounded-2xl px-5 py-4 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all font-inter font-semibold placeholder-slate-900/20"
                           placeholder="••••••••" autocomplete="current-password" required>
                </div>

                <button type="submit"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-extrabold font-inter rounded-2xl uppercase tracking-[0.15em] text-[11px] py-4.5 transition-all mt-6 shadow-xl shadow-slate-900/20 hover:scale-[1.02] active:scale-[0.98]">
                    Authenticate
                </button>
            </form>
        </div>
    </div>

    <div class="mt-10 flex flex-col items-center gap-4">
        <div class="h-px w-12 bg-slate-900/10"></div>
        <p class="text-center text-slate-900/30 text-[10px] font-extrabold font-inter uppercase tracking-[0.3em]">
            Precision Engineering © <?= date('Y') ?>
        </p>
    </div>
</div>

</body>
</html>
