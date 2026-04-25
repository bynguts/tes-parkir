<?php
/**
 * login.php — standalone fallback login page (used when PHP serves directly)
 * When React is running, login is handled by /src/pages/Login.tsx
 */

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
        $error = 'Invalid request payload.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip  = $_SERVER['REMOTE_ADDR'];
        $key = 'login_fail_' . md5($ip);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'since' => time()];
        if (time() - $_SESSION[$key]['since'] > 300) $_SESSION[$key] = ['count' => 0, 'since' => time()];
        if ($_SESSION[$key]['count'] >= 5) {
            $remaining = 300 - (time() - $_SESSION[$key]['since']);
            $error = "Terlalu banyak percobaan. Tunggu {$remaining}s.";
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
                $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
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
        theme: { extend: {
            fontFamily: { manrope: ['Manrope', 'sans-serif'], inter: ['Inter', 'sans-serif'] },
            colors: { 'primary-fixed': '#0f172a' }
        }}
    }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Manrope', sans-serif; font-weight: 800; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24; vertical-align: middle; line-height:1; }
        body { background: linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:40px 36px; backdrop-filter:blur(16px); width:100%; max-width:400px; }
        input { background:rgba(255,255,255,0.04)!important; border:1px solid rgba(255,255,255,0.1)!important; color:#f8fafc!important; border-radius:10px; padding:11px 12px 11px 40px; width:100%; font-size:14px; outline:none; transition:border-color 150ms; }
        input:focus { border-color:rgba(34,197,94,0.5)!important; }
        input::placeholder { color:#334155; }
        .submit-btn { background:#22c55e; color:#fff; border:none; border-radius:10px; padding:12px; width:100%; font-family:'Manrope',sans-serif; font-weight:700; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity 150ms; }
        .submit-btn:hover { opacity:0.9; }
        label { display:block; color:#94a3b8; font-size:11px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#475569; font-size:18px; pointer-events:none; }
    </style>
</head>
<body>
<div class="login-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px">
        <div style="width:40px;height:40px;border-radius:10px;background:#0f172a;border:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center">
            <span class="material-symbols-outlined" style="color:#22c55e;font-size:20px">local_parking</span>
        </div>
        <div>
            <div style="font-family:Manrope,sans-serif;font-weight:800;color:#f8fafc;font-size:18px">Smart<span style="color:#94a3b8">Parking</span></div>
            <div style="font-size:10px;color:#475569;text-transform:uppercase;letter-spacing:.14em">Enterprise</div>
        </div>
    </div>

    <h1 style="color:#f8fafc;font-size:22px;margin-bottom:6px">Secure Access</h1>
    <p style="color:#64748b;font-size:13px;margin-bottom:28px">Masukkan kredensial akun Anda.</p>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:10px 14px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
        <span class="material-symbols-outlined" style="color:#ef4444;font-size:16px">error</span>
        <span style="color:#fca5a5;font-size:13px"><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" style="display:flex;flex-direction:column;gap:16px">
        <?= csrf_field() ?>
        <div>
            <label>Username</label>
            <div class="input-wrap">
                <span class="material-symbols-outlined input-icon">person</span>
                <input type="text" name="username" autocomplete="username" required>
            </div>
        </div>
        <div>
            <label>Password</label>
            <div class="input-wrap">
                <span class="material-symbols-outlined input-icon">lock</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </div>
        </div>
        <button type="submit" class="submit-btn" style="margin-top:8px">
            <span class="material-symbols-outlined" style="font-size:16px">shield</span>
            Login Workspace
        </button>
    </form>
</div>
</body>
</html>
