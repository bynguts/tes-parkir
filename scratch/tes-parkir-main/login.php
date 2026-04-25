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
    <title>Enterprise Login — SmartParking</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: '#6366f1',
                    'brand-dark': '#4f46e5',
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
        :root {
            --brand-primary: #6366f1;
            --brand-deep: #4338ca;
            --slate-900: #0f172a;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --slate-50: #f8fafc;
        }
        
        * { font-family: 'Inter', sans-serif; transition: all 0.3s ease; }
        
        .brand-side {
            background: radial-gradient(circle at top right, var(--brand-primary), var(--brand-deep));
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.4;
        }

        .floating-orb {
            position: absolute;
            width: 600px;
            height: 600px;
            background: var(--brand-primary);
            filter: blur(120px);
            opacity: 0.3;
            border-radius: 50%;
            pointer-events: none;
        }

        .form-side {
            background-color: white;
        }

        input:focus {
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .animate-slide { animation: slideIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>

<body class="min-h-screen bg-white">

<div class="flex min-h-screen">
    <!-- LEFT SIDE: BRANDING -->
    <div class="hidden lg:flex lg:w-1/2 brand-side flex-col justify-between p-20 relative">
        <div class="floating-orb -top-40 -left-40"></div>
        <div class="floating-orb -bottom-40 -right-40" style="background: white; opacity: 0.1;"></div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-4 group">
                <div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-2xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-square-p text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-manrope font-black text-white tracking-tight">SmartParking</h1>
            </div>
        </div>

        <div class="relative z-10 max-w-md">
            <h2 class="text-6xl font-manrope font-black text-white leading-tight tracking-tighter mb-8">
                Advanced Urban Mobility Core.
            </h2>
            <div class="space-y-6">
                <div class="flex items-start gap-5">
                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center border border-white/20 shrink-0 mt-1">
                        <i class="fa-solid fa-shield-check text-white text-xs"></i>
                    </div>
                    <p class="text-white/70 text-sm font-medium leading-relaxed">
                        Securely manage your parking assets with enterprise-grade encryption and real-time synchronization.
                    </p>
                </div>
                <div class="flex items-start gap-5">
                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center border border-white/20 shrink-0 mt-1">
                        <i class="fa-solid fa-chart-line text-white text-xs"></i>
                    </div>
                    <p class="text-white/70 text-sm font-medium leading-relaxed">
                        Access deep analytics and automated reporting for maximum operational efficiency.
                    </p>
                </div>
            </div>
        </div>

        <div class="relative z-10">
            <p class="text-white/40 text-[10px] font-black uppercase tracking-[0.5em]">
                Precision Infrastructure Engineering &copy; <?= date('Y') ?>
            </p>
        </div>
    </div>

    <!-- RIGHT SIDE: LOGIN FORM -->
    <div class="w-full lg:w-1/2 form-side flex flex-col items-center justify-center p-8 lg:p-24 relative overflow-hidden">
        <!-- Background accents for mobile -->
        <div class="lg:hidden absolute -top-40 -right-40 w-80 h-80 bg-brand/5 rounded-full blur-3xl"></div>
        <div class="lg:hidden absolute -bottom-40 -left-40 w-80 h-80 bg-brand/5 rounded-full blur-3xl"></div>

        <div class="w-full max-w-[420px] animate-slide">
            <!-- Mobile Brand Header -->
            <div class="lg:hidden flex flex-col items-center mb-12">
                <div class="w-16 h-16 bg-brand rounded-2xl flex items-center justify-center shadow-2xl mb-6">
                    <i class="fa-solid fa-square-p text-white text-3xl"></i>
                </div>
                <h1 class="text-3xl font-manrope font-black text-[#0f172a] tracking-tight">SmartParking</h1>
            </div>

            <div class="mb-12">
                <h2 class="text-4xl font-manrope font-black text-[#0f172a] tracking-tight mb-3">Sign In</h2>
                <p class="text-slate-500 font-medium">Please enter your credentials to access the administrative console.</p>
            </div>

            <?php if ($error): ?>
            <div class="flex items-center gap-4 bg-red-50 border border-red-100 rounded-2xl p-5 mb-8">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-red-600 shrink-0">
                    <i class="fa-solid fa-shield-xmark text-lg"></i>
                </div>
                <p class="text-red-700 text-[13px] font-bold leading-relaxed"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <?= csrf_field() ?>

                <div class="space-y-3">
                    <label class="block text-[11px] font-black uppercase tracking-[0.15em] text-slate-500 ml-1">
                        Professional Identity
                    </label>
                    <div class="relative group">
                        <i class="fa-solid fa-user absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand transition-colors"></i>
                        <input type="text" name="username"
                               class="w-full bg-slate-50 border-2 border-slate-100 focus:border-brand/40 focus:bg-white rounded-[1.25rem] pl-14 pr-6 py-5 text-sm font-bold text-[#0f172a] focus:outline-none transition-all placeholder:text-slate-400"
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between ml-1">
                        <label class="block text-[11px] font-black uppercase tracking-[0.15em] text-slate-500">
                            Access Key
                        </label>
                    </div>
                    <div class="relative group">
                        <i class="fa-solid fa-key absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand transition-colors"></i>
                        <input type="password" name="password" id="passInput"
                               class="w-full bg-slate-50 border-2 border-slate-100 focus:border-brand/40 focus:bg-white rounded-[1.25rem] pl-14 pr-16 py-5 text-sm font-bold text-[#0f172a] focus:outline-none transition-all placeholder:text-slate-400"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" onclick="togglePass()" class="absolute right-6 top-1/2 -translate-y-1/2 text-slate-400 hover:text-brand p-2">
                            <i id="passIcon" class="fa-solid fa-eye-slash text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-3 ml-1 pt-2">
                    <input type="checkbox" id="remember" class="w-5 h-5 rounded-md border-2 border-slate-200 text-brand focus:ring-brand focus:ring-offset-0 transition-all cursor-pointer">
                    <label for="remember" class="text-sm font-semibold text-slate-600 cursor-pointer select-none">Remember this session</label>
                </div>

                <button type="submit"
                        class="w-full bg-[#0f172a] hover:bg-black text-white font-black rounded-2xl uppercase tracking-[0.2em] text-[11px] py-6 transition-all mt-6 shadow-2xl shadow-slate-200 flex items-center justify-center gap-3 group">
                    <span>Initialize Interface</span>
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="mt-20 flex items-center justify-center gap-8 opacity-20 hover:opacity-50 transition-opacity">
                <i class="fa-solid fa-shield-halved text-xl"></i>
                <i class="fa-solid fa-microchip text-xl"></i>
                <i class="fa-solid fa-fingerprint text-xl"></i>
            </div>
        </div>
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
