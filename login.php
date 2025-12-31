<?php
require_once 'connection.php';
session_start();

$error = '';
$username = '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        $user = null;
        $activeConn = null;
        $db_online_count = 0;

        // DEBUG: Log start
        error_log("=== LOGIN ATTEMPT ===");
        error_log("Username: $username");
        error_log("Password entered: $password");

        try {
            // 1. Check SQL Server (PDO)
            if (isset($pdo) && $pdo instanceof PDO) {
                $db_online_count++;
                error_log("SQL Server connection: ACTIVE");
                $stmt = $pdo->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM [USER] WHERE USERNAME = ?");
                $stmt->execute([$username]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) { 
                    $user = array_change_key_case($res, CASE_UPPER); 
                    $activeConn = $pdo;
                    error_log("Found user in SQL Server");
                }
            } else {
                error_log("SQL Server connection: INACTIVE");
            }

            // 2. Check MySQL
            if (!$user && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $db_online_count++;
                error_log("MySQL connection: ACTIVE");
                $stmt = $mysql_conn2->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM `USER` WHERE USERNAME = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $user = array_change_key_case($result->fetch_assoc(), CASE_UPPER);
                    $activeConn = $mysql_conn2;
                    error_log("Found user in MySQL: " . $user['USERNAME']);
                }
                $stmt->close();
            } else {
                error_log("MySQL connection: " . (isset($mysql_conn2) ? 'ACTIVE' : 'INACTIVE'));
            }

            // 3. Check PostgreSQL
            if (!$user && isset($pg_conn) && $pg_conn instanceof PDO) {
                $db_online_count++;
                error_log("PostgreSQL connection: ACTIVE");
                $stmt = $pg_conn->prepare('SELECT user_id, username, password, role FROM "user" WHERE username = ?');
                $stmt->execute([$username]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) { 
                    $user = array_change_key_case($res, CASE_UPPER); 
                    $activeConn = $pg_conn;
                    error_log("Found user in PostgreSQL");
                }
            } else {
                error_log("PostgreSQL connection: " . (isset($pg_conn) ? 'ACTIVE' : 'INACTIVE'));
            }

            error_log("Total DB connections: $db_online_count");
            error_log("User found: " . ($user ? 'YES' : 'NO'));

            // --- Authentication Logic ---
            if ($db_online_count === 0) {
                $error = "System Error: No databases are currently reachable.";
            } elseif ($user) {
                $storedPass = $user['PASSWORD'];
                $authSuccess = false;

                // DEBUG
                error_log("Stored password: '$storedPass'");
                error_log("Password length: " . strlen($storedPass));
                error_log("Entered password: '$password'");
                error_log("Entered password length: " . strlen($password));

                // SIMPLIFIED AUTH - JUST DIRECT COMPARISON
                if ($password === $storedPass) {
                    $authSuccess = true;
                    error_log("Password matches (direct comparison)");
                } else {
                    error_log("Password does NOT match (direct comparison)");
                    
                    // Try password_verify as fallback
                    if (password_verify($password, $storedPass)) {
                        $authSuccess = true;
                        error_log("Password matches (password_verify)");
                    }
                }

                if ($authSuccess) {
                    $_SESSION['user_id'] = $user['USER_ID'];
                    $_SESSION['username'] = $user['USERNAME'];
                    $_SESSION['role'] = $user['ROLE'];

                    error_log("Login SUCCESS for user: " . $user['USERNAME']);

                    if (file_exists('audit.php')) {
                        require_once 'audit.php';
                        logAudit($activeConn, 'LOGIN', 'Authentication', 'User logged into the system');
                    }
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = "Invalid username or password.";
                    error_log("Login FAILED - password mismatch");
                }
            } else {
                $error = "Invalid username or password.";
                error_log("Login FAILED - user not found");
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . htmlspecialchars($e->getMessage());
            error_log("Exception: " . $e->getMessage());
        }
        
        error_log("=== END LOGIN ATTEMPT ===");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; display: flex; justify-content: center; align-items: center; }
        .container { max-width: 400px; width: 100%; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
        .content { padding: 24px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
        .form-group input { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .btn-primary { width: 100%; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:#fff; font-weight: 500; }
        .error-message { color: red; background: #ffe0e0; border: 1px solid red; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: 600; text-align: center; }
    </style>
</head>
<body>
  <div class="container">
    <header class="header"><h1>🔑 Inventory Login</h1></header>
    <div class="content">
      <?php if (!empty($error)): ?><p class="error-message"><?php echo $error; ?></p><?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label>Username</label>
          <input name="username" type="text" required value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input name="password" type="password" required>
        </div>
        <button class="btn-primary" type="submit">Log In</button>
        <div style="text-align:center; margin-top:15px;">
        <a href="forgot_password.php"style="color:#0066ff; font-weight:600; text-decoration:none;"> Forgot Password?</a>
</div>
      </form>
    </div>
  </div>
</body>
</html>