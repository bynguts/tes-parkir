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
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Simple rate limit: max 5 attempts per 5 minutes per IP
        $ip       = $_SERVER['REMOTE_ADDR'];
        $key      = 'login_fail_' . md5($ip);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'since' => time()];
        if (time() - $_SESSION[$key]['since'] > 300) {
            $_SESSION[$key] = ['count' => 0, 'since' => time()];
        }
        if ($_SESSION[$key]['count'] >= 5) {
            $remaining = 300 - (time() - $_SESSION[$key]['since']);
            $error = "Too many failed attempts. Try again in {$remaining}s.";
        } else {
            $stmt = $pdo->prepare("SELECT user_id, password_hash, role, full_name FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Success — reset rate limit & regenerate session
                unset($_SESSION[$key]);
                session_regenerate_id(true);
                $_SESSION['logged_in']        = true;
                $_SESSION['user_id']          = $user['user_id'];
                $_SESSION['username']         = $username;
                $_SESSION['role']             = $user['role'];
                $_SESSION['full_name']        = $user['full_name'];
                $_SESSION['last_regenerated'] = time();

                // Update last login
                $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE user_id = ?")
                    ->execute([$user['user_id']]);

                header('Location: index.php'); exit;
            } else {
                $_SESSION[$key]['count']++;
                $error = 'Username atau password salah.';
                // Constant-time delay to prevent timing attacks
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
    <title>Login — Parking System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#0d0d0d;--panel:#141414;--border:#2a2a2a;--accent:#f5c518;--text:#e8e8e8;--muted:#666;--danger:#e03c3c;--input-bg:#1c1c1c}
        html,body{height:100%;background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;overflow:hidden}
        .bg-grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px);background-size:48px 48px;opacity:.4}
        .bg-glow{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 50% 60%,rgba(245,197,24,.07) 0%,transparent 70%)}
        .wrapper{position:relative;z-index:1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:420px;background:var(--panel);border:1px solid var(--border);padding:48px 40px 44px;position:relative;animation:slideUp .5s cubic-bezier(.16,1,.3,1) forwards;opacity:0}
        .card::before,.card::after{content:'';position:absolute;width:14px;height:14px;border-color:var(--accent);border-style:solid}
        .card::before{top:-1px;left:-1px;border-width:2px 0 0 2px}
        .card::after{bottom:-1px;right:-1px;border-width:0 2px 2px 0}
        @keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
        .header{text-align:center;margin-bottom:36px}
        .logo-icon{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border:2px solid var(--accent);background:rgba(245,197,24,.06);margin-bottom:20px;font-size:26px}
        .system-label{font-family:'DM Mono',monospace;font-size:10px;letter-spacing:.25em;text-transform:uppercase;color:var(--accent);margin-bottom:8px}
        h1{font-family:'Bebas Neue',sans-serif;font-size:42px;letter-spacing:.04em;color:var(--text);line-height:1}
        .subtitle{margin-top:8px;font-size:13px;color:var(--muted)}
        .divider{width:100%;height:1px;background:linear-gradient(90deg,transparent,var(--border),transparent);margin-bottom:28px}
        .field{margin-bottom:18px}
        label{display:block;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
        .input-wrap{position:relative}
        .input-wrap svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--muted);pointer-events:none;transition:color .2s}
        input{width:100%;background:var(--input-bg);border:1px solid var(--border);color:var(--text);font-family:'DM Mono',monospace;font-size:14px;padding:13px 14px 13px 42px;outline:none;transition:border-color .2s;-webkit-appearance:none;border-radius:0}
        input::placeholder{color:#3a3a3a}
        input:focus{border-color:var(--accent);background:#1f1f1f}
        input:focus~svg,.input-wrap:focus-within svg{color:var(--accent)}
        .error-box{display:flex;align-items:center;gap:10px;background:rgba(224,60,60,.08);border:1px solid rgba(224,60,60,.3);padding:11px 14px;margin-bottom:20px;font-size:13px;color:#ff7070;animation:shake .35s ease}
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}
        .btn-login{width:100%;margin-top:8px;padding:15px;background:var(--accent);color:#0d0d0d;border:none;font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.12em;cursor:pointer;position:relative;overflow:hidden;transition:background .2s,transform .1s}
        .btn-login:hover{background:#ffd332}
        .btn-login:active{transform:scale(.99)}
        .footer-note{margin-top:28px;text-align:center;font-family:'DM Mono',monospace;font-size:10px;letter-spacing:.12em;color:#333;text-transform:uppercase}
        .status-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#2ecc71;margin-right:6px;animation:pulse 2s infinite;vertical-align:middle}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
    </style>
</head>
<body>
<div class="bg-grid"></div>
<div class="bg-glow"></div>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <div class="logo-icon">🅿</div>
            <div class="system-label">Authorized Personnel Only</div>
            <h1>Parking System</h1>
            <p class="subtitle">Enter credentials to continue</p>
        </div>
        <div class="divider"></div>
        <?php if ($error): ?>
        <div class="error-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username" placeholder="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
            </div>
            <button type="submit" class="btn-login">ENTER SYSTEM</button>
        </form>
        <div class="footer-note"><span class="status-dot"></span>System Online — © 2026 Parking System</div>
    </div>
</div>
</body>
</html>
