<?php
require_once 'connection.php';

// Start session to manage user state
session_start();

$error = '';
$username = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// --- Handle POST Request for Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        $user = null;

        try {
            // ---------------------------------------------------------
            // 1. Universal PDO Check (SQL Server or MySQL via PDO)
            // ---------------------------------------------------------
            if (!$user && isset($pdo) && $pdo instanceof PDO) {
                try {
                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    // Standardize table name: Brackets for SQLSRV, Backticks for MySQL
                    $table = ($driver === 'sqlsrv') ? "[USER]" : "`user`";
                    
                    $stmt = $pdo->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM $table WHERE USERNAME = ?");
                    $stmt->execute([$username]);
                    $res = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($res) $user = array_change_key_case($res, CASE_UPPER);
                } catch (Exception $e) { /* Skip if this connection is offline */ }
            } 

            // ---------------------------------------------------------
            // 2. MySQL Secondary Checks (Farah's or other MySQL connections)
            // ---------------------------------------------------------
            $mysql_list = [];
            if (isset($mysql_conn) && $mysql_conn instanceof mysqli) $mysql_list[] = $mysql_conn;
            if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) $mysql_list[] = $mysql_conn2;

            foreach ($mysql_list as $m_conn) {
                if ($user) break;
                try {
                    $stmt = $m_conn->prepare("SELECT USER_ID, USERNAME, PASSWORD, ROLE FROM `user` WHERE USERNAME = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $res = $result->fetch_assoc();
                            $user = array_change_key_case($res, CASE_UPPER);
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) { /* Skip offline MySQL */ }
            }

            // ---------------------------------------------------------
            // 3. PostgreSQL Check
            // ---------------------------------------------------------
            if (!$user && isset($pg_conn) && $pg_conn instanceof PDO) {
                try {
                    $stmt = $pg_conn->prepare('SELECT "USER_ID", "USERNAME", "PASSWORD", "ROLE" FROM "USER" WHERE "USERNAME" = ?');
                    $stmt->execute([$username]);
                    $res = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($res) $user = array_change_key_case($res, CASE_UPPER);
                } catch (Exception $e) { /* Skip offline PostgreSQL */ }
            }

            // ---------------------------------------------------------
            // Final Authentication
            // ---------------------------------------------------------
            if ($user && $user['PASSWORD'] === $password) {
                $_SESSION['user_id'] = $user['USER_ID'];
                $_SESSION['username'] = $user['USERNAME'];
                $_SESSION['role'] = $user['ROLE'];
                header('Location: dashboard.php');
                exit;
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; display: flex; justify-content: center; align-items: center; }
        .container { max-width: 400px; width: 100%; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
        .header-content h1 { font-size: 1.6em; }
        .content { padding: 24px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
        .form-group input { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .form-actions { margin-top:16px; }
        .btn { width: 100%; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-size: 1em; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:#fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 102, 255, 0.4); }
        .error-message { color: red; background: #ffe0e0; border: 1px solid red; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: 600; text-align: center; }
    </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="header-content">
        <h1>🔑 Inventory Login</h1>
      </div>
    </header>

    <div class="content">
      <?php if (!empty($error)): ?>
        <p class="error-message"><?php echo $error; ?></p>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Log In</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>