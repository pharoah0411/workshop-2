<?php
require_once 'connection.php';
session_start();

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
        if (!$username && isset($pdo)) {
            $stmt = $pdo->prepare("SELECT username FROM [USER] WHERE email = ?");
            $stmt->execute([$email]);
            $username = $stmt->fetchColumn();
        }

        if ($username) {
            if ($username) {header("Location: reset_password.php?email=" . urlencode($email));
            exit;
}

        } else {
            $error = "Email address not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
    min-height: 100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.container {
    width:100%;
    max-width:420px;
    background:white;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
    overflow:hidden;
}

.header {
    background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
    color:white;
    padding:26px;
    text-align:center;
}

.header h2 {
    font-size:1.6em;
}

.content {
    padding:26px;
}

.form-group {
    margin-bottom:16px;
}

.form-group label {
    font-weight:600;
    margin-bottom:6px;
    display:block;
}

.form-group input {
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #ccc;
    font-size:1em;
}

.btn-primary {
    width:100%;
    padding:12px;
    background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);
    color:white;
    border:none;
    border-radius:8px;
    font-size:1em;
    font-weight:600;
    cursor:pointer;
}

.btn-primary:hover {
    opacity:0.9;
}

.error-message {
    background:#ffe0e0;
    border:1px solid red;
    color:red;
    padding:10px;
    border-radius:6px;
    margin-bottom:16px;
    text-align:center;
    font-weight:600;
}

.back-link {
    text-align:center;
    margin-top:16px;
}

.back-link a {
    color:#0066ff;
    font-weight:600;
    text-decoration:none;
}
</style>
</head>

<body>
<div class="container">
    <div class="header">
        <h2>🔐 Forgot Password</h2>
        <p style="margin-top:8px; font-size:0.95em;">
            Enter your registered email
        </p>
    </div>

    <div class="content">
        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@email.com" required>
            </div>

            <button class="btn-primary">Verify Email</button>
        </form>

        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
