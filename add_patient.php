<?php
require_once "connection.php";
include "header.php";

$message = "";

// Function to auto-generate username
function generateUsername() {
    return "patient" . time();
}

// Function to auto-generate password
function generatePassword() {
    return "PT" . rand(10000, 99999);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $target_source = $_POST['source'] ?? 'Postgres';

    // Still generated (required for USER table), but NOT displayed
    $username = generateUsername();
    $password = generatePassword();

    // Patient details
    $name     = trim($_POST['name']);
    $gender   = trim($_POST['gender']);
    $dob      = trim($_POST['dob']);
    $ic_no    = trim($_POST['ic_no']);
    $address  = trim($_POST['address']);

    $success_count = 0;
    $attempt_count = 0;
    $errors = [];

    /* =========================
       1) MYSQL
    ========================= */
    if (($target_source === "MySQL" || $target_source === "All") && $mysql_conn2 instanceof mysqli) {
        $attempt_count++;
        try {
            $mysql_conn2->begin_transaction();

            // USER
            $stmtUser = $mysql_conn2->prepare(
                "INSERT INTO `USER` (USERNAME, PASSWORD, ROLE) VALUES (?, ?, 'patient')"
            );
            $stmtUser->bind_param("ss", $username, $password);
            $stmtUser->execute();
            $userId = $mysql_conn2->insert_id;
            $stmtUser->close();

            // PATIENT
            $stmtPatient = $mysql_conn2->prepare(
                "INSERT INTO PATIENT (USER_ID, NAME, GENDER, DOB, IC_NO, ADDRESS)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtPatient->bind_param("isssss", $userId, $name, $gender, $dob, $ic_no, $address);
            $stmtPatient->execute();
            $stmtPatient->close();

            $mysql_conn2->commit();
            $success_count++;

        } catch (Exception $e) {
            $mysql_conn2->rollback();
            $errors[] = "MySQL: " . $e->getMessage();
        }
    }

    /* =========================
       2) POSTGRESQL
    ========================= */
    if (($target_source === "Postgres" || $target_source === "All") && $pg_conn instanceof PDO) {
        $attempt_count++;
        try {
            $pg_conn->beginTransaction();

            // USER
            $stmtUser = $pg_conn->prepare(
                'INSERT INTO "user" (username, password, role)
                 VALUES (:u, :p, \'patient\')
                 RETURNING user_id'
            );
            $stmtUser->execute([':u' => $username, ':p' => $password]);
            $newUserId = $stmtUser->fetchColumn();

            // PATIENT
            $stmtPatient = $pg_conn->prepare(
                'INSERT INTO patient (user_id, gender, dob, address, ic_no, name)
                 VALUES (:uid, :g, :dob, :addr, :ic, :n)'
            );
            $stmtPatient->execute([
                ':uid'  => $newUserId,
                ':g'    => $gender,
                ':dob'  => $dob,
                ':addr' => $address,
                ':ic'   => $ic_no,
                ':n'    => $name
            ]);

            $pg_conn->commit();
            $success_count++;

        } catch (Exception $e) {
            if ($pg_conn->inTransaction()) $pg_conn->rollBack();
            $errors[] = "Postgres: " . $e->getMessage();
        }
    }

    /* =========================
       3) SQL SERVER (PDO)
    ========================= */
    if (($target_source === "SQLServer" || $target_source === "All") && $pdo instanceof PDO) {
        $attempt_count++;
        try {
            $pdo->beginTransaction();

            // USER
            $stmtUser = $pdo->prepare("INSERT INTO [USER] (username, password, role) VALUES (?, ?, 'patient')");
            $stmtUser->execute([$username, $password]);

            $newUserId = $pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();

            // PATIENT
            $stmtPatient = $pdo->prepare(
                "INSERT INTO patient (user_id, gender, dob, address, ic_no, name)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtPatient->execute([$newUserId, $gender, $dob, $address, $ic_no, $name]);

            $pdo->commit();
            $success_count++;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "SQL Server: " . $e->getMessage();
        }
    }

    /* =========================
       FINAL MESSAGE (NO USER/PASS)
    ========================= */
    if ($success_count > 0) {
        $message = "
        <div style='color:green; font-weight:700;'>
            ✅ Patient added successfully to <b>$success_count</b> out of <b>$attempt_count</b> database(s)!
        </div>";
    } else {
        $message = "<div style='color:red; font-weight:700;'>
            ❌ ERROR: Failed to add patient.<br>" . implode("<br>", array_map("htmlspecialchars", $errors)) . "
        </div>";
    }
}
?>

<h1>Add New Patient</h1>

<?= $message ?>

<form method="POST" style="background:white; padding:20px; border-radius:10px; width:650px;">

    <h3>Database Selection</h3>
    <label><strong>Save to Database:</strong></label><br>
    <select name="source" class="input-box" required>
        <option value="Postgres" selected>Postgres Only</option>
        <option value="MySQL">MySQL Only</option>
        <option value="SQLServer">SQL Server Only</option>
        <option value="All">All Databases</option>
    </select>
    <br><br>

    <h3>Personal Information</h3>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required class="input-box"><br><br>

    <label><strong>Gender:</strong></label><br>
    <select name="gender" class="input-box" required>
        <option value="">Select gender...</option>
        <option value="Male" <?= (($_POST['gender'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= (($_POST['gender'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
    </select>
    <br><br>

    <label><strong>Date of Birth:</strong></label><br>
    <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required class="input-box"><br><br>

    <label><strong>IC Number:</strong></label><br>
    <input type="text" name="ic_no" value="<?= htmlspecialchars($_POST['ic_no'] ?? '') ?>" required class="input-box"><br><br>

    <label><strong>Address:</strong></label><br>
    <textarea name="address" rows="3" class="input-box"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea><br><br>

    <button type="submit" style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Add Patient
    </button>

    <a href="patient_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:5px; margin-left:10px; text-decoration:none;">
        Cancel
    </a>

</form>

<style>
.input-box {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
</style>

</div>
</body>
</html>
