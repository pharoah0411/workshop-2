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
    if (!$found && isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT user_id FROM [USER] WHERE email = ?");
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
                if (!$updated && isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("UPDATE [USER] SET password = ? WHERE user_id = ?");
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

                if ($db === 'SQLServer' && isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("UPDATE [USER] SET password = ? WHERE user_id = ?");
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
<title>Reset Password</title>

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.container {
    max-width:420px;
    width:100%;
    background:white;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
    overflow:hidden;
}

.header {
    background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);
    color:white;
    padding:26px;
    text-align:center;
}

.content { padding:26px; }

.form-group { margin-bottom:14px; }

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

#password-rules p { font-size:13px; margin-top:4px; }

.btn-primary {
    width:100%;
    padding:12px;
    background:#999;
    color:white;
    border:none;
    border-radius:8px;
    font-weight:600;
    cursor:not-allowed;
    transition: 0.2s;
}

.btn-primary.enabled {
    background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);
    cursor:pointer;
}

.error-message {
    background:#ffe0e0;
    border:1px solid red;
    color:red;
    padding:10px;
    border-radius:6px;
    margin-bottom:14px;
    text-align:center;
    font-weight:600;
}

.success-message {
    background:#e0ffe0;
    border:1px solid green;
    color:green;
    padding:10px;
    border-radius:6px;
    margin-bottom:14px;
    text-align:center;
    font-weight:600;
}
</style>
</head>

<body>
<div class="container">
    <div class="header">
        <h2>🔐 Reset Password</h2>
        <p style="margin-top:6px;">You must set a new secure password</p>
    </div>

    <div class="content">

        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" id="password" onkeyup="checkPassword()" required>
            </div>

            <div id="password-rules">
                <p id="length">❌ At least 8 characters</p>
                <p id="upper">❌ Uppercase letter</p>
                <p id="lower">❌ Lowercase letter</p>
                <p id="number">❌ Number</p>
                <p id="special">❌ Special character</p>
            </div>

            <div class="form-group" style="margin-top:10px;">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button id="submitBtn" class="btn-primary" disabled>
                Reset Password
            </button>
        </form>

    </div>
</div>

<script>
function checkPassword() {
    const p = document.getElementById("password").value;

    const rules = {
        length: p.length >= 8,
        upper: /[A-Z]/.test(p),
        lower: /[a-z]/.test(p),
        number: /\d/.test(p),
        special: /[@$!%*?&]/.test(p)
    };

    let valid = true;

    // Update UI (without duplicating text)
    document.getElementById("length").style.color  = rules.length  ? "green" : "red";
    document.getElementById("upper").style.color   = rules.upper   ? "green" : "red";
    document.getElementById("lower").style.color   = rules.lower   ? "green" : "red";
    document.getElementById("number").style.color  = rules.number  ? "green" : "red";
    document.getElementById("special").style.color = rules.special ? "green" : "red";

    document.getElementById("length").innerHTML  = (rules.length  ? "✅" : "❌") + " At least 8 characters";
    document.getElementById("upper").innerHTML   = (rules.upper   ? "✅" : "❌") + " Uppercase letter";
    document.getElementById("lower").innerHTML   = (rules.lower   ? "✅" : "❌") + " Lowercase letter";
    document.getElementById("number").innerHTML  = (rules.number  ? "✅" : "❌") + " Number";
    document.getElementById("special").innerHTML = (rules.special ? "✅" : "❌") + " Special character";

    valid = Object.values(rules).every(Boolean);

    const btn = document.getElementById("submitBtn");
    btn.disabled = !valid;
    btn.classList.toggle("enabled", valid);
}
</script>
</body>
</html>
