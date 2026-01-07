<?php
require_once "auth_check.php";   // must be FIRST
requireRole('admin');            // admin only

require_once "connection.php";
include "header.php";

$message = "";

/* =========================
   STRONG PASSWORD CHECK (SERVER)
========================= */
function isStrongPassword($password) {
    return preg_match(
        '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/',
        $password
    );
}

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $role     = trim($_POST['role']);
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $source   = $_POST['source'] ?? 'Postgres';

    if ($username === '' || $role === '' || $name === '' || $email === '' || $phone === '') {

        $message = "<div style='color:red;'>❌ All fields are required.</div>";

    } else {

        /* =========================
           TEMP PASSWORD (NO DB CHANGE)
        ========================= */
        $tempPassword = "Temp@1234!";
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);


        $success = false;
        $errors  = [];

        /* =========================
           POSTGRESQL
        ========================= */
        if (($source === 'Postgres' || $source === 'All') && $pg_conn instanceof PDO) {
            try {
                $stmt = $pg_conn->prepare(
                    'INSERT INTO "user"
                     (username, password, role, name, email, phone)
                     VALUES (:u, :p, :r, :n, :e, :ph)'
                );
                $stmt->execute([
                    ':u'  => $username,
                    ':p'  => $hashedPassword,
                    ':r'  => $role,
                    ':n'  => $name,
                    ':e'  => $email,
                    ':ph' => $phone
                ]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = "Postgres: " . $e->getMessage();
            }
        }

        /* =========================
           MYSQL
        ========================= */
        if (($source === 'MySQL' || $source === 'All') && $mysql_conn2 instanceof mysqli) {
            try {
                $stmt = $mysql_conn2->prepare(
                    "INSERT INTO `USER`
                     (USERNAME, PASSWORD, ROLE, NAME, EMAIL, PHONE)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssssss",
                    $username,
                    $hashedPassword,
                    $role,
                    $name,
                    $email,
                    $phone
                );
                $stmt->execute();
                $stmt->close();
                $success = true;
            } catch (Exception $e) {
                $errors[] = "MySQL: " . $e->getMessage();
            }
        }

        /* =========================
           SQL SERVER
        ========================= */
        if (($source === 'SQLServer' || $source === 'All') && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO [USER]
                     (username, password, role, name, email, phone)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $username,
                    $hashedPassword,
                    $role,
                    $name,
                    $email,
                    $phone
                ]);
                $success = true;
            } catch (Exception $e) {
                $errors[] = "SQL Server: " . $e->getMessage();
            }
        }

        /* =========================
           FINAL MESSAGE
        ========================= */
        if ($success) {
            $message = "
            <div style='color:green; padding:10px;'>
                ✅ User created successfully.<br><br>
                <strong>Temporary Password:</strong><br>
                <code style='font-size:16px;'>$tempPassword</code><br><br>
                ⚠ User must change password on first login.
            </div>";
        } else {
            $message = "<div style='color:red;'>❌ Failed to add user.<br>"
                     . implode("<br>", $errors) . "</div>";
        }
    }
}
?>

<h1>Add New User</h1>

<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:600px;">

    <label>Save to Database</label>
    <select name="source" class="input-box">
        <option value="Postgres">Postgres</option>
        <option value="MySQL">MySQL</option>
        <option value="SQLServer">SQL Server</option>
        <option value="All">All</option>
    </select><br><br>

    <label>Username</label>
    <input name="username" class="input-box" required><br><br>

    <label>Role</label>
    <select name="role" class="input-box" required>
        <option value="">Select role</option>
        <option value="admin">Admin</option>
        <option value="pharmacist">Pharmacist</option>
    </select><br><br>

    <label>Full Name</label>
    <input name="name" class="input-box" required><br><br>

    <label>Email</label>
    <input type="email" name="email" class="input-box" required><br><br>

    <label>Phone</label>
    <input name="phone" class="input-box" required><br><br>

    <button type="submit"
        style="background:#0b2f6d; color:white; padding:10px 20px; border:0;">
        Create User
    </button>

    <a href="user_list.php" style="margin-left:10px;">Cancel</a>
</form>

<style>
.input-box {
    width:100%;
    padding:10px;
    border-radius:5px;
    border:1px solid #ccc;
}
</style>
