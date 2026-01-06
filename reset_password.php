<?php
require_once "connection.php";
session_start();

$email = $_GET['email'] ?? '';
$error = '';
$success = '';

if ($email === '') {
    $error = "Invalid reset request.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email !== '') {

    $newPassword = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    // ✅ Backend strong password validation (MUST MATCH JS)
    if (
        strlen($newPassword) < 8 ||
        !preg_match('/[A-Z]/', $newPassword) ||
        !preg_match('/[a-z]/', $newPassword) ||
        !preg_match('/[0-9]/', $newPassword) ||
        !preg_match('/[\W]/', $newPassword)
    ) {
        $error = "Password does not meet security requirements.";
    }
    elseif ($newPassword !== $confirm) {
        $error = "Passwords do not match.";
    }
    else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = false;

        try {
            // 1️⃣ SQL Server
            if (!$updated && isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("UPDATE [USER] SET password = ? WHERE email = ?");
                if ($stmt->execute([$hashed, $email]) && $stmt->rowCount() > 0) {
                    $updated = true;
                }
            }

            // 2️⃣ MySQL
            if (!$updated && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $stmt = $mysql_conn2->prepare("UPDATE `USER` SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed, $email);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $updated = true;
                }
                $stmt->close();
            }

            // 3️⃣ PostgreSQL
            if (!$updated && isset($pg_conn) && $pg_conn instanceof PDO) {
                $stmt = $pg_conn->prepare('UPDATE "user" SET password = ? WHERE email = ?');
                $stmt->execute([$hashed, $email]);
                if ($stmt->rowCount() > 0) {
                    $updated = true;
                }
            }

            if ($updated) {
                $success = "Password reset successful. You may now log in.";
            } else {
                $error = "Email not found in system.";
            }

        } catch (Exception $e) {
            $error = "Reset error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            background: white;
            padding: 30px;
            width: 420px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.2);
        }
        h2 {
            text-align: center;
            color: #0066ff;
            margin-bottom: 20px;
        }
        .input-box {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }
        .rules {
            font-size: 14px;
            margin: 10px 0;
        }
        .rules span {
            display: block;
            color: red;
        }
        .rules span.valid {
            color: green;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #0066ff;
            color: white;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
        }
        .btn:disabled {
            background: #999;
            cursor: not-allowed;
        }
        .error {
            background: #ffe0e0;
            color: red;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: green;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }
        .back {
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>🔒 Reset Password</h2>

    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="password" name="password" id="password" class="input-box" placeholder="New Password" required>
        <input type="password" name="confirm_password" id="confirm" class="input-box" placeholder="Confirm Password" required>

        <div class="rules">
            <span id="len">❌ At least 8 characters</span>
            <span id="upper">❌ One uppercase letter</span>
            <span id="lower">❌ One lowercase letter</span>
            <span id="num">❌ One number</span>
            <span id="spec">❌ One special character</span>
            <span id="match">❌ Passwords match</span>
        </div>

        <button id="submitBtn" class="btn" disabled>Reset Password</button>
    </form>
    <?php endif; ?>

    <div class="back">
        <a href="login.php">← Back to Login</a>
    </div>
</div>

<script>
const password = document.getElementById("password");
const confirmP = document.getElementById("confirm");
const btn = document.getElementById("submitBtn");

function validate() {
    const val = password.value;

    const rules = {
        len: val.length >= 8,
        upper: /[A-Z]/.test(val),
        lower: /[a-z]/.test(val),
        num: /[0-9]/.test(val),
        spec: /[\W]/.test(val),
        match: val === confirmP.value && val !== ''
    };

    for (let id in rules) {
        document.getElementById(id).className = rules[id] ? "valid" : "";
        document.getElementById(id).innerText =
            (rules[id] ? "✔ " : "❌ ") + document.getElementById(id).innerText.replace(/^[✔❌]\s*/, '');
    }

    btn.disabled = !Object.values(rules).every(Boolean);
}

password.addEventListener("input", validate);
confirmP.addEventListener("input", validate);
</script>

</body>
</html>
