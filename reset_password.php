<?php
require_once 'connection.php';
session_start();

$error = '';
$success = '';

/* =============================
   STRONG PASSWORD CHECK (SERVER)
============================= */
function isStrongPassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password);
}

/* ==========================================================
   ACCESS CONTROL
   - Case A: Logged-in forced reset -> use SESSION user_id
   - Case B: Forgot password -> allow using GET email param
========================================================== */
$resetEmail = trim($_GET['email'] ?? '');

if (!isset($_SESSION['user_id'])) {

    // Forgot password must come with email
    if ($resetEmail === '') {
        header("Location: login.php");
        exit;
    }

    // Look up user by email (store which DB found)
    $found = false;
    $_SESSION['reset_email'] = $resetEmail;
    $_SESSION['reset_db'] = null;

    // Postgres
    if (!$found && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare('SELECT user_id FROM "user" WHERE email = ?');
        $stmt->execute([$resetEmail]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            $_SESSION['reset_user_id'] = (int)$uid;
            $_SESSION['reset_db'] = 'Postgres';
            $found = true;
        }
    }

    // MySQL
    if (!$found && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT USER_ID FROM `USER` WHERE EMAIL = ?");
        $stmt->bind_param("s", $resetEmail);
        $stmt->execute();
        $stmt->bind_result($uid);
        if ($stmt->fetch()) {
            $_SESSION['reset_user_id'] = (int)$uid;
            $_SESSION['reset_db'] = 'MySQL';
            $found = true;
        }
        $stmt->close();
    }

    // SQL Server
    if (!$found && isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
        $stmt = $pdo_sqlsrv->prepare("SELECT user_id FROM [USER] WHERE email = ?");
        $stmt->execute([$resetEmail]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            $_SESSION['reset_user_id'] = (int)$uid;
            $_SESSION['reset_db'] = 'SQLServer';
            $found = true;
        }
    }

    if (!$found) {
        // Email not found -> back to forgot password
        header("Location: forgot_password.php?notfound=1");
        exit;
    }
}

/* =============================
   HANDLE PASSWORD RESET
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirm === '') {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!isStrongPassword($password)) {
        $error = "Password does not meet security requirements.";
    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updated = false;

        try {

            /* ===========================
               CASE A: LOGGED IN USER
            ============================ */
            if (isset($_SESSION['user_id'])) {

                $userId = (int)$_SESSION['user_id'];

                // Postgres
                if (!$updated && isset($pg_conn) && $pg_conn instanceof PDO) {
                    $stmt = $pg_conn->prepare('UPDATE "user" SET password = ? WHERE user_id = ?');
                    $stmt->execute([$hashedPassword, $userId]);
                    $updated = true;
                }

                // MySQL
                if (!$updated && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                    $stmt = $mysql_conn2->prepare("UPDATE `USER` SET PASSWORD = ? WHERE USER_ID = ?");
                    $stmt->bind_param("si", $hashedPassword, $userId);
                    $stmt->execute();
                    $updated = true;
                }

                // SQL Server
                if (!$updated && isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
                    $stmt = $pdo_sqlsrv->prepare("UPDATE [USER] SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    $updated = true;
                }
            }

            /* ===========================
               CASE B: FORGOT PASSWORD
            ============================ */
            else {

                $userId = (int)($_SESSION['reset_user_id'] ?? 0);
                $db     = $_SESSION['reset_db'] ?? '';

                if ($userId <= 0 || $db === '') {
                    throw new Exception("Reset session invalid. Please try again.");
                }

                if ($db === 'Postgres' && isset($pg_conn) && $pg_conn instanceof PDO) {
                    $stmt = $pg_conn->prepare('UPDATE "user" SET password = ? WHERE user_id = ?');
                    $stmt->execute([$hashedPassword, $userId]);
                    $updated = true;
                }

                if ($db === 'MySQL' && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                    $stmt = $mysql_conn2->prepare("UPDATE `USER` SET PASSWORD = ? WHERE USER_ID = ?");
                    $stmt->bind_param("si", $hashedPassword, $userId);
                    $stmt->execute();
                    $updated = true;
                }

                if ($db === 'SQLServer' && isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
                    $stmt = $pdo_sqlsrv->prepare("UPDATE [USER] SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    $updated = true;
                }
            }

            if ($updated) {
                // clear reset flags
                unset($_SESSION['force_reset']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_db']);

                $success = "Password updated successfully! Redirecting to login...";
                header("refresh:2; url=login.php");

            } else {
                $error = "Failed to update password. Please try again.";
            }

        } catch (Exception $e) {
            $error = "System error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Pharmacy System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme with Dark Blue */
        :root {
            --dark-blue: #1c4966;
            --blue-medium: #2a5d7a;
            --blue-light: #e3f2fd;
            --blue-accent: #4a90e2;
            --cream-white: #f8fafc;
            --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50;
            --alert-red: #d9534f;
            --warning-orange: #f0ad4e;
            --success-green: #5cb85c;
            
            --sidebar-bg: var(--dark-blue);
            --sidebar-text: white;
            --main-bg: var(--cream-white);
            --card-bg: white;
            --border-color: #e1e8ed;
            --text-primary: var(--dark-grey);
            --text-secondary: var(--soft-grey);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Be Vietnam Pro", sans-serif;
            background: linear-gradient(135deg, #0d1b4e 0%, #1a2980 30%, #26d0ce 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-weight: 400;
            line-height: 1.5;
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(28, 73, 102, 0.25);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-30px, -30px) rotate(360deg); }
        }

        .pharmacy-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            border: 3px solid white;
        }

        .login-header h1 {
            font-size: 1.8em;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .login-header p {
            font-size: 0.95em;
            opacity: 0.9;
            font-weight: 300;
        }

        .login-content {
            padding: 35px 30px;
        }

        /* Alert Messages */
        .alert-message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.4s ease;
        }

        .alert-message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
        }

        .alert-message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-message i {
            font-size: 1.1em;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--blue-accent);
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            background: white;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .form-control:focus {
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 3px rgba(28, 73, 102, 0.15);
            outline: none;
        }

        .form-control:hover {
            border-color: var(--blue-medium);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--soft-grey);
            cursor: pointer;
            font-size: 1.1em;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--dark-blue);
        }

        /* Password Requirements */
        .password-requirements {
            background: var(--blue-light);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid var(--border-color);
        }

        .requirements-title {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: var(--dark-grey);
        }

        .requirement i {
            width: 16px;
            text-align: center;
        }

        .requirement.valid {
            color: var(--success-green);
        }

        .requirement.invalid {
            color: var(--alert-red);
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-weight: 600;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            font-family: "Be Vietnam Pro", sans-serif;
            margin-bottom: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            box-shadow: 0 4px 15px rgba(28, 73, 102, 0.25);
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--blue-medium), #1e5a7c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(28, 73, 102, 0.35);
        }

        .btn-primary:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .footer-links a {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--blue-accent);
            text-decoration: underline;
        }

        /* System Status */
        .system-status {
            background: var(--blue-light);
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            border: 1px solid var(--border-color);
        }

        .status-title {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .status-item {
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            background: white;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-online {
            color: #2e7d32;
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .status-offline {
            color: #c62828;
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                border-radius: 12px;
            }
            
            .login-header {
                padding: 25px 20px;
            }
            
            .login-content {
                padding: 25px 20px;
            }
            
            .pharmacy-icon {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .login-header h1 {
                font-size: 1.5em;
            }
            
            .form-control {
                padding: 12px 14px;
            }
            
            .btn {
                padding: 14px;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for form focus */
        .form-control:focus + .form-focus-line {
            width: 100%;
            opacity: 1;
        }

        .form-focus-line {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--dark-blue), var(--blue-accent));
            transition: all 0.3s ease;
            opacity: 0;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <div class="pharmacy-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h1>Create New Password</h1>
        <p>Pharmacy System - Password Reset</p>
    </div>

    <div class="login-content">
        <?php if (!empty($error)): ?>
            <div class="alert-message error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php else: ?>
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-key"></i> New Password
                    </label>
                    <div class="password-wrapper">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            class="form-control" 
                            required
                            placeholder="Enter new password"
                            onkeyup="validatePassword()"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="form-focus-line"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-key"></i> Confirm Password
                    </label>
                    <div class="password-wrapper">
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            class="form-control" 
                            required
                            placeholder="Confirm new password"
                            onkeyup="validatePassword()"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="form-focus-line"></div>
                    </div>
                </div>

                <div class="password-requirements">
                    <div class="requirements-title">
                        <i class="fas fa-shield-alt"></i> Password Requirements
                    </div>
                    <div class="requirement" id="req-length">
                        <i class="fas fa-times" id="icon-length"></i>
                        <span>At least 8 characters long</span>
                    </div>
                    <div class="requirement" id="req-upper">
                        <i class="fas fa-times" id="icon-upper"></i>
                        <span>Contains uppercase letter (A-Z)</span>
                    </div>
                    <div class="requirement" id="req-lower">
                        <i class="fas fa-times" id="icon-lower"></i>
                        <span>Contains lowercase letter (a-z)</span>
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-times" id="icon-number"></i>
                        <span>Contains number (0-9)</span>
                    </div>
                    <div class="requirement" id="req-special">
                        <i class="fas fa-times" id="icon-special"></i>
                        <span>Contains special character (@$!%*?&)</span>
                    </div>
                    <div class="requirement" id="req-match">
                        <i class="fas fa-times" id="icon-match"></i>
                        <span>Passwords match</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>

            <div class="footer-links">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>

            <!-- System Status -->
            <div class="system-status">
                <div class="status-title">
                    <i class="fas fa-server"></i> Database Status
                </div>
                <div class="status-grid">
                    <div class="status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'status-online' : 'status-offline'; ?>">
                        MySQL
                    </div>
                    <div class="status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        SQL Server
                    </div>
                    <div class="status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        PostgreSQL
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const passwordInput = document.getElementById(fieldId);
        const toggleIcon = document.querySelector(`#${fieldId} + .toggle-password i`);
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Validate password requirements
    function validatePassword() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        const requirements = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[@$!%*?&]/.test(password),
            match: password === confirmPassword && password !== ''
        };
        
        // Update requirement indicators
        Object.keys(requirements).forEach(req => {
            const icon = document.getElementById(`icon-${req}`);
            const element = document.getElementById(`req-${req}`);
            
            if (requirements[req]) {
                icon.className = 'fas fa-check';
                element.classList.remove('invalid');
                element.classList.add('valid');
            } else {
                icon.className = 'fas fa-times';
                element.classList.remove('valid');
                element.classList.add('invalid');
            }
        });
        
        // Enable/disable submit button
        const allValid = Object.values(requirements).every(Boolean);
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = !allValid;
    }
    
    // Add animation on form submit
    document.getElementById('resetForm')?.addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
    });
    
    // Auto-focus password field
    document.addEventListener('DOMContentLoaded', function() {
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.focus();
        }
        
        // Add floating animation to header pattern
        const header = document.querySelector('.login-header');
        header.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            this.style.backgroundPosition = `${x * 20}px ${y * 20}px`;
        });
        
        // Initial validation
        validatePassword();
    });
    
    // Form validation
    const form = document.getElementById('resetForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!password || !confirmPassword) {
                e.preventDefault();
                showAlert('Please fill in both password fields.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match.');
                return;
            }
        });
    }
    
    function showAlert(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert-message error';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
        
        const existingAlert = document.querySelector('.alert-message');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const content = document.querySelector('.login-content');
        content.insertBefore(alertDiv, content.querySelector('form'));
        
        // Scroll to alert
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>
</body>
</html>