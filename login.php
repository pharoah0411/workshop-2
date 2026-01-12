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
               1️⃣ SQL SERVER (PDO)
            =============================== */
            if (isset($pdo) && $pdo instanceof PDO) {
                $db_online_count++;
                $stmt = $pdo->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM [USER] WHERE USERNAME = ?");
                $stmt->execute([$username]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) { 
                    $user = array_change_key_case($res, CASE_UPPER); 
                    $activeConn = $pdo;
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
    <title>User Login</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0052cc, #007bff);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin: 0; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            font-weight: 600; 
            margin-bottom: 8px; 
            display: block; 
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        .form-group input:focus {
            border-color: #0066ff;
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #0066ff, #0099ff);
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 15px;
            transition: opacity 0.3s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-patient {
            background: #f8f9fa;
            color: #0066ff;
            border: 2px solid #0066ff;
        }
        .btn-patient:hover { background: #eef2ff; }
        .error-message {
            background: #ffebeb;
            border: 1px solid #ff5a5a;
            color: #d8000c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .footer-links {
            text-align: center;
            margin-top: 10px;
        }
        .footer-links a {
            color: #0066ff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .footer-links a:hover { text-decoration: underline; }
        .divider {
            text-align: center;
            margin: 15px 0;
            position: relative;
            color: #888;
            font-size: 14px;
        }
        .divider::before, .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #ddd;
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <h1>🔑 Inventory Login</h1>
    </header>

    <div class="content">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required value="<?php echo htmlspecialchars($username); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button type="submit" class="btn">Log In</button>
        </form>

        <div class="footer-links">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <div class="divider">OR</div>

        <button type="button" class="btn btn-patient" onclick="window.location.href='patient_portal.php'">
            I'm a Patient
        </button>
    </div>
</div>

</body>
</html>