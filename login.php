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
    <title>Secure Access — Parking System Enterprise</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Global CSS -->
    <link rel="stylesheet" href="assets/css/premium.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1e2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Abstract Premium Background Elements */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: 0;
            pointer-events: none;
            animation: float 20s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .orb-1 { width: 400px; height: 400px; background: rgba(59, 130, 246, 0.5); top: -100px; left: -100px; }
        .orb-2 { width: 500px; height: 500px; background: rgba(139, 92, 246, 0.4); bottom: -150px; right: -100px; animation-delay: -5s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(100px, 50px) scale(1.2); }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
            padding: 20px;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, rgba(59, 130, 246, 0.5) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin: 0 auto 24px;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3), inset 0 2px 0 rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .form-control-custom {
            background: rgba(0, 0, 0, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            padding: 16px 20px 16px 50px !important;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control-custom:focus {
            background: rgba(0, 0, 0, 0.3) !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            transition: color 0.3s ease;
            z-index: 10;
        }

        .input-group-custom:focus-within .input-icon {
            color: var(--primary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);
            border: none;
            color: white;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
            text-transform: uppercase;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        .form-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<div class="bg-orb orb-1"></div>
<div class="bg-orb orb-2"></div>

<div class="login-wrapper">
    <div class="login-card">
        <div class="text-center mb-5">
            <div class="brand-icon">
                <i class="fas fa-parking"></i>
            </div>
            <h3 class="text-white fw-bold mb-1" style="letter-spacing: -0.5px;">Workspace Access</h3>
            <p class="text-muted small">Enter your corporate credentials to continue.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 12px; font-size: 14px;">
            <i class="fas fa-exclamation-circle fs-5 me-3"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            
            <div class="mb-4">
                <label class="form-label">Global Identity</label>
                <div class="position-relative input-group-custom">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control form-control-custom" 
                           placeholder="Enter your AD username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                </div>
            </div>
            
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Hash Key (Password)</label>
                </div>
                <div class="position-relative input-group-custom">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control form-control-custom" 
                           placeholder="••••••••" autocomplete="current-password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login w-100">
                Authenticate Session <i class="fas fa-arrow-right ms-2 opacity-75"></i>
            </button>
        </form>
    </div>
    
    <div class="text-center mt-4 text-muted" style="font-size: 12px; opacity: 0.6; letter-spacing: 1px;">
        <i class="fas fa-shield-alt me-1"></i> SECURE ENTERPRISE ENVIRONMENT &copy; <?= date('Y') ?>
    </div>
</div>

</body>
</html>
