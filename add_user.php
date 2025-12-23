<?php
include "connection.php";
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
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $target_source = $_POST['source'] ?? 'Postgres';

    if ($username === '' || $password === '' || $role === '' ||
        $name === '' || $email === '' || $phone === '') {

        $message = "<div style='color:red; padding:10px;'>❌ All fields are required.</div>";

    } elseif (!isStrongPassword($password)) {

        $message = "<div style='color:red; padding:10px;'>
            ❌ Password does not meet security requirements.
        </div>";

    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $success_count = 0;
        $attempt_count = 0;
        $errors = [];

        /* =========================
           POSTGRESQL
        ========================= */
        if ($target_source === 'Postgres' || $target_source === 'All') {
            if ($pg_conn instanceof PDO) {
                $attempt_count++;
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

                    $success_count++;
                } catch (PDOException $e) {
                    $errors[] = "Postgres: " . $e->getMessage();
                }
            }
        }

        /* =========================
           MYSQL
        ========================= */
        if ($target_source === 'MySQL' || $target_source === 'All') {
            if ($mysql_conn2 instanceof mysqli) {
                $attempt_count++;
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
                    $success_count++;

                } catch (Exception $e) {
                    $errors[] = "MySQL: " . $e->getMessage();
                }
            }
        }

        /* =========================
           SQL SERVER
        ========================= */
        if ($target_source === 'SQLServer' || $target_source === 'All') {
            if ($pdo instanceof PDO) {
                $attempt_count++;
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

                    $success_count++;

                } catch (Exception $e) {
                    $errors[] = "SQL Server: " . $e->getMessage();
                }
            }
        }

        /* =========================
           FINAL MESSAGE
        ========================= */
        if ($success_count > 0) {
            $message = "<div style='color:green; padding:10px;'>
                ✅ User added successfully to $success_count database(s).
            </div>";
        } else {
            $message = "<div style='color:red; padding:10px;'>
                ❌ Failed to add user.<br>" . implode("<br>", $errors) . "
            </div>";
        }
    }
}
?>

<h1>Add New User</h1>

<?= $message ?>

<form method="POST" style="background:white; padding:20px; border-radius:10px; width:600px;">

    <label>Save to Database</label>
    <select name="source" class="input-box" required>
        <option value="Postgres">Postgres</option>
        <option value="MySQL">MySQL</option>
        <option value="SQLServer">SQL Server</option>
        <option value="All">All</option>
    </select><br><br>

    <label>Username</label>
    <input name="username" class="input-box" required><br><br>

    <label>Password</label>
    <input type="password" name="password" id="password"
           class="input-box" onkeyup="checkPasswordStrength()" required>

    <!-- PASSWORD RULES -->
    <div id="password-rules" style="margin-top:10px; font-size:14px;">
        <p id="length">❌ At least 8 characters</p>
        <p id="uppercase">❌ At least 1 uppercase letter</p>
        <p id="lowercase">❌ At least 1 lowercase letter</p>
        <p id="number">❌ At least 1 number</p>
        <p id="special">❌ At least 1 special character (@$!%*?&)</p>
    </div>
    <br>

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
        id="submitBtn"
        disabled
        style="background:#999; color:white; padding:10px 20px; border:0; cursor:not-allowed;">
        Add User
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

<script>
function checkPasswordStrength() {
    const password = document.getElementById("password").value;
    const submitBtn = document.getElementById("submitBtn");

    const rules = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password),
        special: /[@$!%*?&]/.test(password)
    };

    for (let rule in rules) {
        document.getElementById(rule).style.color = rules[rule] ? "green" : "red";
        document.getElementById(rule).innerHTML =
            (rules[rule] ? "✅ " : "❌ ") + document.getElementById(rule).innerText.slice(2);
    }

    if (Object.values(rules).every(Boolean)) {
        submitBtn.disabled = false;
        submitBtn.style.background = "#0b2f6d";
        submitBtn.style.cursor = "pointer";
    } else {
        submitBtn.disabled = true;
        submitBtn.style.background = "#999";
        submitBtn.style.cursor = "not-allowed";
    }
}
</script>
