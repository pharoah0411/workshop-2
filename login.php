<?php
require_once 'connection.php';
session_start();

$error = '';
$username = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {

        $user = null;
        $activeConn = null;
        $db_online_count = 0; // Initialize counter

        try {
            /* ===============================
               1️⃣ SQL SERVER (PDO) - FIXED: Use $pdo_sqlsrv instead of $pdo
            =============================== */
            if (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
                $db_online_count++;
                $stmt = $pdo_sqlsrv->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM [USER] WHERE USERNAME = ?");
                $stmt->execute([$username]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) { 
                    $user = array_change_key_case($res, CASE_UPPER); 
                    $activeConn = $pdo_sqlsrv; // FIXED: Use $pdo_sqlsrv
                }
            }

            /* ===============================
               2️⃣ MYSQL
            =============================== */
            if (!$user && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $db_online_count++;
                $stmt = $mysql_conn2->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM `USER` WHERE USERNAME = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $user = array_change_key_case($result->fetch_assoc(), CASE_UPPER);
                    $activeConn = $mysql_conn2;
                }
                $stmt->close();
            }

            /* ===============================
               3️⃣ POSTGRESQL
            =============================== */
            if (!$user && isset($pg_conn) && $pg_conn instanceof PDO) {
                $db_online_count++;
                $stmt = $pg_conn->prepare('SELECT user_id, username, password, role FROM "user" WHERE username = ?');
                $stmt->execute([$username]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) { 
                    $user = array_change_key_case($res, CASE_UPPER); 
                    $activeConn = $pg_conn;
                }
            }

            // --- Authentication Logic ---
            if ($db_online_count === 0) {
                $error = "System Error: No databases are currently reachable.";
            } elseif ($user) {
                $storedPass = $user['PASSWORD'];
                $authSuccess = false;

                // Authentication: Direct comparison or password_verify
                if ($password === $storedPass) {
                    $authSuccess = true;
                } elseif (password_verify($password, $storedPass)) {
                    $authSuccess = true;
                }

                if ($authSuccess) {

    // ✅ SAVE USER DATA TO SESSION
    $_SESSION['user_id']  = $user['USER_ID'];
    $_SESSION['username'] = $user['USERNAME'];
    $_SESSION['role']     = $user['ROLE'];

    // ✅ FORCE RESET LOGIC (new users / temp password)
    // If your add_user uses Temp@xxxx, this will work
    if (strpos($password, "Temp@1234!") === 0) {
        $_SESSION['force_reset'] = 1;
        header("Location: reset_password.php");
        exit;
    }

    // ✅ Audit Logging
    if (file_exists('audit.php')) {
        require_once 'audit.php';
        logAudit($activeConn, 'LOGIN', 'Authentication', 'User logged into the system');
    }

    header('Location: dashboard.php');
    exit;

} else {
    $error = "Invalid username or password.";
}
            } else {
                $error = "Invalid username or password.";
            }

        } catch (Exception $e) {
            $error = 'Login error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pharmacy System</title>
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
            max-width: 420px;
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
            margin-bottom: 8px;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.9em;
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

        /* Divider */
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: var(--soft-grey);
            font-size: 0.85em;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: var(--border-color);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
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
            <i class="fas fa-pills"></i>
        </div>
        <h1>PHARMACY SYSTEM</h1>
        <p>Professional Healthcare Management</p>
    </div>

    <div class="login-content">
        <?php if (!empty($error)): ?>
            <div class="alert-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input 
                    id="username" 
                    name="username" 
                    type="text" 
                    class="form-control" 
                    required 
                    value="<?php echo htmlspecialchars($username); ?>"
                    placeholder="Enter your username"
                    autocomplete="username"
                >
                <div class="form-focus-line"></div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="password-wrapper">
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        class="form-control" 
                        required
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="form-focus-line"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Log In
            </button>
        </form>

        <div class="footer-links">
            <a href="forgot_password.php">
                <i class="fas fa-key"></i> Forgot Password?
            </a>
        </div>

        <div class="divider">Patient Portal</div>

        <button type="button" class="btn btn-secondary" onclick="window.location.href='patient_portal.php'">
            <i class="fas fa-user-injured"></i> I'm a Patient
        </button>

        <!-- System Status - FIXED: Updated to use $pdo_sqlsrv -->
        <div class="system-status">
            <div class="status-title">
                <i class="fas fa-server"></i> System Status
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
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.toggle-password i');
        
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
    
    // Add animation on form submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('.btn-primary');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
        btn.disabled = true;
    });
    
    // Auto-focus username field
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('username').focus();
        
        // Add floating animation to header pattern
        const header = document.querySelector('.login-header');
        header.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            this.style.backgroundPosition = `${x * 20}px ${y * 20}px`;
        });
    });
    
    // Form validation
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        
        if (!username || !password) {
            e.preventDefault();
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert-message';
            alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please fill in both username and password fields.';
            
            const existingAlert = document.querySelector('.alert-message');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            form.insertBefore(alertDiv, form.firstChild);
            
            // Scroll to alert
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
</body>
</html>