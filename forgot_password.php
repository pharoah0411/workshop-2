<?php
require_once 'connection.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if ($email === '') {
        $error = "Please enter your email address.";
    } else {
        $username = null;

        // PostgreSQL
        if (!$username && isset($pg_conn)) {
            $stmt = $pg_conn->prepare('SELECT username FROM "user" WHERE email = ?');
            $stmt->execute([$email]);
            $username = $stmt->fetchColumn();
        }

        // MySQL
        if (!$username && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare("SELECT username FROM `USER` WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($u);
            if ($stmt->fetch()) $username = $u;
            $stmt->close();
        }

        // SQL Server
        if (!$username && isset($pdo_sqlsrv)) {
            $stmt = $pdo_sqlsrv->prepare("SELECT username FROM [USER] WHERE email = ?");
            $stmt->execute([$email]);
            $username = $stmt->fetchColumn();
        }

        if ($username) {
            header("Location: reset_password.php?email=" . urlencode($email));
            exit;
        } else {
            $error = "Email address not found in any database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Pharmacy System</title>
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
            max-width: 450px;
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
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
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
            margin-bottom: 25px;
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

        /* Info Box */
        .info-box {
            background: var(--blue-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .info-box p {
            color: var(--dark-blue);
            font-size: 0.9em;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .info-box i {
            color: var(--dark-blue);
            margin-right: 8px;
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

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--blue-medium), #1e5a7c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(28, 73, 102, 0.35);
        }

        .btn-secondary {
            background: var(--blue-light);
            color: var(--dark-blue);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: white;
            border-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            gap: 20px;
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
            
            .footer-links {
                flex-direction: column;
                gap: 10px;
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
            <i class="fas fa-key"></i>
        </div>
        <h1>Reset Password</h1>
        <p>Pharmacy System - Account Recovery</p>
    </div>

    <div class="login-content">
        <?php if (!empty($error)): ?>
            <div class="alert-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> Enter the email address associated with your account. We'll send you instructions to reset your password.</p>
            <p><i class="fas fa-shield-alt"></i> Check all connected databases for your account.</p>
        </div>

        <form method="POST" id="forgotForm">
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input 
                    id="email" 
                    name="email" 
                    type="email" 
                    class="form-control" 
                    required 
                    placeholder="Enter your registered email address"
                    autocomplete="email"
                >
                <div class="form-focus-line"></div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div class="footer-links">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
            <a href="patient_portal.php">
                <i class="fas fa-user-injured"></i> Patient Portal
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
    </div>
</div>

<script>
    // Add animation on form submit
    document.getElementById('forgotForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        btn.disabled = true;
        
        // Re-enable button after 3 seconds if form doesn't submit
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 3000);
    });
    
    // Auto-focus email field
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('email').focus();
        
        // Add floating animation to header pattern
        const header = document.querySelector('.login-header');
        header.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            this.style.backgroundPosition = `${x * 20}px ${y * 20}px`;
        });
    });
    
    // Form validation
    const form = document.getElementById('forgotForm');
    form.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email) {
            e.preventDefault();
            showAlert('Please enter your email address.');
            return;
        }
        
        if (!emailRegex.test(email)) {
            e.preventDefault();
            showAlert('Please enter a valid email address.');
            return;
        }
    });
    
    function showAlert(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert-message';
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