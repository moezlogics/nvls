<?php
include '../db.php';

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

// Automatically create login_attempts table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    ip_address VARCHAR(45) PRIMARY KEY,
    attempts INT DEFAULT 0,
    last_attempt_time INT DEFAULT 0
)");

$ip_address = $_SERVER['REMOTE_ADDR'];
$error = '';
$lockout_duration = 2 * 60 * 60; // 2 hours in seconds
$max_attempts = 3;

// Check current lock status
$result = $conn->query("SELECT attempts, last_attempt_time FROM login_attempts WHERE ip_address = '$ip_address'");
$is_locked = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['attempts'] >= $max_attempts) {
        $time_passed = time() - $row['last_attempt_time'];
        if ($time_passed < $lockout_duration) {
            $is_locked = true;
            $time_left = ceil(($lockout_duration - $time_passed) / 60);
            $error = "Too many failed login attempts. You are locked out for $time_left minutes.";
        } else {
            // Lockout duration passed, reset attempts
            $conn->query("UPDATE login_attempts SET attempts = 0 WHERE ip_address = '$ip_address'");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_locked) {
    csrf_verify();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // DB Authentication
    $stmt = $conn->prepare("SELECT id, password, role FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result_user = $stmt->get_result();
    
    if ($result_user->num_rows > 0) {
        $user = $result_user->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Login successful, reset attempts
            $conn->query("DELETE FROM login_attempts WHERE ip_address = '$ip_address'");
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['admin_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            header("Location: dashboard.php");
            exit;
        }
    }
    
    // Failed attempt, update or insert into DB
        if ($result && $result->num_rows > 0) {
            $conn->query("UPDATE login_attempts SET attempts = attempts + 1, last_attempt_time = " . time() . " WHERE ip_address = '$ip_address'");
        } else {
            $conn->query("INSERT INTO login_attempts (ip_address, attempts, last_attempt_time) VALUES ('$ip_address', 1, " . time() . ")");
        }
        
        // Fetch new attempt count
        $result = $conn->query("SELECT attempts FROM login_attempts WHERE ip_address = '$ip_address'");
        $row = $result->fetch_assoc();
        $attempts_left = $max_attempts - $row['attempts'];
        
        if ($attempts_left > 0) {
            $error = "Invalid username or password. $attempts_left attempt(s) remaining.";
        } else {
            $is_locked = true;
            $error = "Too many failed login attempts. You are locked out for 120 minutes.";
        }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --adm-bg: #000000;
            --adm-glass: rgba(255, 255, 255, .04);
            --adm-glass-2: rgba(255, 255, 255, .08);
            --adm-border: rgba(255, 255, 255, .10);
            --adm-border-green: rgba(108, 200, 50, .18);
            --adm-text: #ffffff;
            --adm-muted: #c5cad4;
            --adm-accent: #6CC832;
            --adm-accent-2: #54a626;
            --adm-on-accent: #0c2104;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background-color: var(--adm-bg);
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--adm-bg) !important;
            color: var(--adm-text);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 40px 24px;
        }

        /* Ambient green glassmorphism glow */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(1100px 760px at 50% 26%, rgba(108, 200, 50, .15), rgba(108, 200, 50, .04) 36%, transparent 60%),
                radial-gradient(760px 520px at 85% 85%, rgba(108, 200, 50, .06), transparent 55%);
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 520px;
        }

        .glass-panel {
            background: rgba(16, 18, 14, 0.55);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--adm-border-green);
            border-radius: 16px;
            padding: 45px 50px;
            width: 100%;
            box-shadow: 0 0 50px rgba(108, 200, 50, 0.12), 0 20px 50px rgba(0, 0, 0, 0.6);
            transition: all 0.3s ease;
            animation: panelFadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @media (max-width: 576px) {
            body {
                padding: 20px 12px;
            }
            .glass-panel {
                padding: 30px 20px;
                border-radius: 12px;
            }
        }

        @keyframes panelFadeIn {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-title {
            font-weight: 800;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 22px;
        }

        .input-group-custom input {
            width: 100%;
            background: var(--adm-glass);
            border: 1px solid var(--adm-border);
            border-radius: 10px;
            padding: 13px 18px;
            color: #ffffff;
            font-size: 0.98rem;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .input-group-custom input.has-right-icon {
            padding-right: 46px;
        }
        
        .input-group-custom input::placeholder {
            color: rgba(255, 255, 255, 0.55);
        }

        .input-group-custom input:focus {
            background: var(--adm-glass-2);
            border-color: var(--adm-accent);
            box-shadow: 0 0 0 3px rgba(108, 200, 50, 0.22);
        }

        /* Prevent browser autofill background/text color overrides */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #161814 inset !important;
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .input-group-custom .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--adm-muted);
            cursor: pointer;
            transition: color 0.3s ease, transform 0.2s ease;
            z-index: 10;
            padding: 5px;
        }
        
        .input-group-custom .toggle-password:hover {
            color: var(--adm-accent);
            transform: translateY(-50%) scale(1.1);
        }

        .extra-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .form-check {
            display: flex;
            align-items: center;
            padding-left: 0;
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--adm-border);
            border-radius: 6px;
            width: 18px;
            height: 18px;
            margin-right: 8px;
            margin-top: 0;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .form-check-input:checked {
            background-color: var(--adm-accent);
            border-color: var(--adm-accent);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%230c2104' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e");
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(108, 200, 50, 0.2);
            border-color: var(--adm-accent);
        }
        
        .form-check-label {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--adm-accent), var(--adm-accent-2));
            color: var(--adm-on-accent);
            border: none;
            border-radius: 10px;
            padding: 13px;
            width: 100%;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(108, 200, 50, 0.3);
        }

        .btn-login:hover:not(:disabled) {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 200, 50, 0.45);
        }
        
        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            background: rgba(255, 255, 255, 0.08) !important;
            color: var(--adm-muted) !important;
            border: 1px solid var(--adm-border) !important;
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .alert-custom-danger {
            background: rgba(220, 38, 38, 0.14) !important;
            border: 1px solid rgba(220, 38, 38, 0.4) !important;
            color: #fca5a5 !important;
            border-radius: 10px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="glass-panel">
            <div class="text-center mb-4">
                <h2 class="login-title mb-1">Admin Portal</h2>
                <p class="text-muted small">Sign in to manage your platform</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-custom-danger border-0 p-3 mb-4">
                    <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div class="input-group-custom">
                    <input type="text" name="username" placeholder="Username" <?php echo $is_locked ? 'disabled' : 'required'; ?> autocomplete="username">
                </div>
                
                <div class="input-group-custom">
                    <input type="password" name="password" id="password" class="has-right-icon" placeholder="Password" <?php echo $is_locked ? 'disabled' : 'required'; ?> autocomplete="current-password">
                    <i class="fa-regular fa-eye-slash toggle-password" onclick="togglePassword()"></i>
                </div>
                
                <div class="extra-links">
                    <div class="form-check">
                        <input class="form-check-input shadow-none" type="checkbox" id="rememberMe" <?php echo $is_locked ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="rememberMe">
                            Remember me
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <?php echo $is_locked ? 'Locked Out' : 'Login'; ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            var pwd = document.getElementById('password');
            if(pwd.disabled) return;
            var icon = document.querySelector('.toggle-password');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>
