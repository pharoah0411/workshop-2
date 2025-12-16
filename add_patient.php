<?php
// CHANGE: Include the main multi-database connection file
require_once "connection.php";
include "header.php";

$message = "";

// Function to auto-generate username
function generateUsername($conn) {
    // This function will only work if $conn is set, which it isn't here,
    // so we'll simplify and use a time-based ID for multi-DB compatibility.
    return "patient" . time(); 
}

// Function to auto-generate password
function generatePassword() {
    return "PT" . rand(10000, 99999);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the selected target database
    $target_source = $_POST['source'] ?? 'Postgres'; // Default to Postgres if not set

    // Auto-generate login details
    $username = generateUsername(null);
    $password = generatePassword();

    // Patient details - REMOVED: email and phone
    $name     = trim($_POST['name']);
    $gender   = trim($_POST['gender']);
    $dob      = trim($_POST['dob']);
    $ic_no    = trim($_POST['ic_no']);
    $address  = trim($_POST['address']);

    $success_count = 0;
    $attempt_count = 0;
    $errors = [];

    // --- 1. MySQL #2 INSERT (Requires a transaction) ---
 // --- 1. MySQL #2 INSERT (FIXED) ---
$source = $_POST['source'] ?? '';

if (($source === "MySQL" || $source === "All") && $mysql_conn2 instanceof mysqli) {
    $attempt_count++;
    try {
        $mysql_conn2->begin_transaction();

        // USER (FIX: backticks + correct counters)
        $stmtUser = $mysql_conn2->prepare(
            "INSERT INTO `USER` (USERNAME, PASSWORD, ROLE) VALUES (?, ?, 'patient')"
        );
        $stmtUser->bind_param("ss", $username, $password);
        $stmtUser->execute();
        $userId = $mysql_conn2->insert_id;

        // PATIENT
        $stmtPatient = $mysql_conn2->prepare(
            "INSERT INTO PATIENT (USER_ID, NAME, GENDER, DOB, IC_NO, ADDRESS)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtPatient->bind_param(
            "isssss",
            $userId,
            $name,
            $gender,
            $dob,
            $ic_no,
            $address
        );
        $stmtPatient->execute();

        $mysql_conn2->commit();
        $success_count++;

    } catch (Exception $e) {
        $mysql_conn2->rollback();
        $errors[] = "MySQL: " . $e->getMessage();
    }
}


    // --- 2. PostgreSQL INSERT (Requires a transaction) ---
    if (($target_source === 'All' || $target_source === 'Postgres') && isset($pg_conn) && $pg_conn instanceof PDO) {
        $attempt_count++;
        try {
            $pg_conn->beginTransaction();

            // Insert only minimal data into user table
            $sqlUser = "INSERT INTO \"user\" (username, password, role)
                        VALUES (:u, :p, 'patient')
                        RETURNING user_id"; 

            $stmtUser = $pg_conn->prepare($sqlUser);
            $stmtUser->execute([':u'  => $username, ':p'  => $password]);
            $newUserId = $stmtUser->fetchColumn();

            // MODIFIED: Insert ONLY available columns (name, but NOT email/phone) into patient table
            $sqlPatient = "INSERT INTO patient (user_id, gender, dob, address, ic_no, name)
                           VALUES (:uid, :g, :dob, :addr, :ic, :n)";
            $stmtPatient = $pg_conn->prepare($sqlPatient);
            $stmtPatient->execute([
                ':uid'  => $newUserId,
                ':g'    => $gender,
                ':dob'  => $dob,
                ':addr' => $address,
                ':ic'   => $ic_no,
                ':n'    => $name // Only name is included
            ]);

            $pg_conn->commit();
            $success_count++;
        } catch (PDOException $e) {
            $pg_conn->rollBack();
            $errors[] = "Postgres: " . $e->getMessage();
        }
    }

    // --- 3. SQL Server INSERT (Requires a transaction) ---
    if ($target_source === 'All' || $target_source === 'SQLServer') {
        $attempt_count++;
        try {
            
            // MODIFIED: Insert ONLY available columns (name, but NOT email/phone) into patient table
            $patient_sql = "INSERT INTO patient (user_id, gender, dob, address, ic_no, name) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            if (isset($pdo) && $pdo instanceof PDO) {
                // PDO for SQL Server
                $pdo->beginTransaction();
                // Insert only minimal data into user table
                $stmtUser = $pdo->prepare("INSERT INTO [USER] (username, password, role) VALUES (?, ?, 'patient')");
                $stmtUser->execute([$username, $password]);
                $newUserId = $pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
                
                // MODIFIED: Execute with only available parameters
                $stmtPatient = $pdo->prepare($patient_sql);
                $stmtPatient->execute([$newUserId, $gender, $dob, $address, $ic_no, $name]);
                
                $pdo->commit();
                $success_count++;
            } elseif (isset($conn) && $conn !== false) {
                // Legacy SQLSRV Driver
                // Insert only minimal data into user table
                $user_sql = "INSERT INTO [USER] (username, password, role) 
                             VALUES (?, ?, 'patient'); SELECT SCOPE_IDENTITY() AS id;";
                $resUser = sqlsrv_query($conn, $user_sql, [$username, $password]);
                if ($resUser === false) throw new Exception(print_r(sqlsrv_errors(), true));
                sqlsrv_next_result($resUser);
                $row = sqlsrv_fetch_array($resUser, SQLSRV_FETCH_ASSOC);
                $newUserId = $row['id'];
                
                // MODIFIED: Execute with only available parameters
                $resPatient = sqlsrv_query($conn, $patient_sql, [$newUserId, $gender, $dob, $address, $ic_no, $name]);
                if ($resPatient === false) throw new Exception(print_r(sqlsrv_errors(), true));
                
                $success_count++;
            }
        } catch (Exception $e) { 
            if(isset($pdo) && $pdo instanceof PDO) $pdo->rollBack();
            $errors[] = "SQL Server: " . $e->getMessage();
        }
    }


    if ($success_count > 0) {
        $message = "
        <p style='color:green;'>
            Patient added successfully to **$success_count** out of **$attempt_count** database(s)!<br>
            <strong>Auto Login:</strong><br>
            Username: <b>$username</b><br>
            Password: <b>$password</b>
        </p>";
    } else {
        $error_details = implode('<br>', $errors);
        $message = "<p style='color:red;'>ERROR: Failed to add patient to any selected database.<br>Details: $error_details</p>";
    }
}
?>

<h1>Add New Patient</h1>

<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:650px;">

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

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
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

</div> </body>
</html>