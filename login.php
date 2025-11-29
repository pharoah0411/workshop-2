<?php
session_start();

// Database connection
$host = "localhost";
$port = "5432";
$dbname = "Workshop";
$user = "postgres";
$password = "admin";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle login
$message = "";
$message_type = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM \"user\" WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        if ($password_input === $user_data['password']) {
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['role'] = $user_data['role'];

            $message = "Login successful! Redirecting...";
            $message_type = "success";

            header("refresh:1; url=user_management.php");
        } else {
            $message = "Invalid password!";
            $message_type = "error";
        }
    } else {
        $message = "Username not found!";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Pharmacy System</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #d6f5e6, #c2e9fb);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 350px;
            background: white;
            padding: 35px 30px;
            border-radius: 12px;
            box-shadow: 0px 6px 20px rgba(0,0,0,0.15);
            text-align: center;
        }

        .login-card h2 {
            margin-bottom: 10px;
            color: #1e665d;
        }

        .login-card p.subtitle {
            margin-top: -5px;
            margin-bottom: 20px;
            color: #555;
            font-size: 14px;
        }

        .message {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .error { background: #ffdddd; color: #c62828; border: 1px solid #e57373; }
        .success { background: #ddffdd; color: #2e7d32; border: 1px solid #81c784; }

        input[type=text], input[type=password] {
            width: 100%;
            padding: 12px;
            margin: 10px 0 15px 0;
            border: 1px solid #aaa;
            border-radius: 6px;
            font-size: 15px;
        }

        input[type=submit] {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
            font-weight: bold;
        }

        input[type=submit]:hover {
            background: #1e874b;
        }
    </style>
</head>

<body>

<div class="login-card">
    <h2>Pharmacy System</h2>
    <p class="subtitle">Please login to continue</p>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <label style="float:left;font-size:14px;color:#333;">Username</label>
        <input type="text" name="username" required>

        <label style="float:left;font-size:14px;color:#333;">Password</label>
        <input type="password" name="password" required>

        <input type="submit" name="login" value="Login">
    </form>
</div>

</body>
</html>
