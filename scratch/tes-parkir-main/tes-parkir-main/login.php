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
            $error = "Terlalu banyak percobaan. Sistem terkunci selama {$remaining}s.";
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
                $error = 'Otentikasi gagal: Identitas atau key tidak valid.';
                usleep(300000);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access — SmartParking</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0">

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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.6s cubic-bezier(.16,1,.3,1) forwards; }
    </style>
</head>

<body class="min-h-screen bg-[#f2f4f7] flex items-center justify-center px-4">

<!-- Asymmetric background accent -->
<div class="fixed top-0 right-0 w-[600px] h-[600px] bg-slate-900 rounded-bl-[120px] opacity-[0.04] pointer-events-none"></div>
<div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-slate-900 rounded-tr-[80px] opacity-[0.03] pointer-events-none"></div>

<div class="w-full max-w-sm fade-up">

    <!-- Brand mark -->
    <div class="text-center mb-10">
        <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-white text-xl">local_parking</span>
        </div>
        <h1 class="font-manrope font-extrabold text-2xl text-slate-900">SmartParking</h1>
        <p class="text-slate-400 text-sm mt-1 font-inter">Enterprise Management System</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm p-8">

        <h2 class="font-manrope font-bold text-xl text-slate-900 mb-1">Sign in</h2>
        <p class="text-slate-400 text-xs font-inter mb-6">Enter your corporate credentials to continue.</p>

        <?php if ($error): ?>
        <div class="flex items-start gap-3 bg-red-50 rounded-xl p-4 mb-5">
            <span class="material-symbols-outlined text-red-500 text-lg mt-0.5">error</span>
            <p class="text-red-700 text-sm font-inter leading-snug"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Username</label>
                <input type="text" name="username"
                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all font-inter font-medium placeholder-slate-400"
                       placeholder="your.username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 font-inter mb-2">Password</label>
                <input type="password" name="password"
                       class="w-full bg-slate-100 border-none rounded-full px-5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all font-inter font-medium"
                       placeholder="••••••••" autocomplete="current-password" required>
            </div>

            <button type="submit"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold font-inter rounded-xl uppercase tracking-widest text-xs py-3.5 transition-all mt-2 shadow-sm">
                Authenticate Session
            </button>
        </form>
    </div>

    <p class="text-center text-slate-400 text-[10px] font-inter mt-6 uppercase tracking-widest">
        Secure Enterprise Environment © <?= date('Y') ?>
    </p>
</div>

</body>
</html>
