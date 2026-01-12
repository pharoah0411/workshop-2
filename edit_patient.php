<?php
require_once "auth_check.php";
requireRole('admin');
require_once "connection.php";
include "header.php";

$message = "";

/* =========================
   1️⃣ VALIDATE INPUT
========================= */
if (!isset($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing patient ID or source.</p>");
}

$patient_id = (int) $_GET['id'];
$db = $_GET['db'];

/* =========================
   2️⃣ LOAD PATIENT
========================= */
$patient = null;

try {

    /* ---------- POSTGRES ---------- */
    if ($db === 'Postgres') {
        $stmt = $pg_conn->prepare(
            'SELECT patient_id, name, gender, dob, address, ic_no
             FROM patient WHERE patient_id = :id'
        );
        $stmt->execute([':id' => $patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ---------- MYSQL (FIX 1: PATIENT) ---------- */
    if ($db === 'MySQL') {
        $stmt = $mysql_conn2->prepare(
            "SELECT patient_id, name, gender, dob, address, ic_no
             FROM PATIENT WHERE patient_id = ?"
        );
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    /* ---------- SQL SERVER ---------- */
    if ($db === 'SQL Server') {
        $stmt = $pdo->prepare(
            "SELECT patient_id, name, gender, dob, address, ic_no
             FROM patient WHERE patient_id = ?"
        );
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$patient) {
        die("<p style='color:red;'>Patient not found.</p>");
    }

} catch (Exception $e) {
    die("<p style='color:red;'>Error loading patient: " . htmlspecialchars($e->getMessage()) . "</p>");
}

/* =========================
   3️⃣ HANDLE UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name']);
    $gender  = trim($_POST['gender']);
    $dob     = trim($_POST['dob']);
    $address = trim($_POST['address']);
    $ic_no   = trim($_POST['ic_no']);

    try {

        /* ---------- POSTGRES ---------- */
        if ($db === 'Postgres') {
            $stmt = $pg_conn->prepare(
                'UPDATE patient
                 SET name=:n, gender=:g, dob=:d, address=:a, ic_no=:ic
                 WHERE patient_id=:id'
            );
            $stmt->execute([
                ':n'=>$name, ':g'=>$gender, ':d'=>$dob,
                ':a'=>$address, ':ic'=>$ic_no, ':id'=>$patient_id
            ]);
        }

        /* ---------- MYSQL (FIX 1) ---------- */
        if ($db === 'MySQL') {
            $stmt = $mysql_conn2->prepare(
                "UPDATE PATIENT
                 SET name=?, gender=?, dob=?, address=?, ic_no=?
                 WHERE patient_id=?"
            );
            $stmt->bind_param(
                "sssssi",
                $name, $gender, $dob, $address, $ic_no, $patient_id
            );
            $stmt->execute();
            $stmt->close();
        }

        /* ---------- SQL SERVER ---------- */
        if ($db === 'SQL Server') {
            $stmt = $pdo->prepare(
                "UPDATE patient
                 SET name=?, gender=?, dob=?, address=?, ic_no=?
                 WHERE patient_id=?"
            );
            $stmt->execute([
                $name, $gender, $dob, $address, $ic_no, $patient_id
            ]);
        }

        $message = "<p style='color:green;'>✅ Patient updated successfully!</p>";

    } catch (Exception $e) {
        $message = "<p style='color:red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<h1>Edit Patient</h1>
<?= $message ?>

<form method="POST" style="background:white; padding:20px; border-radius:10px; width:650px;">

    <label>Full Name</label>
    <input class="input-box" name="name" value="<?= htmlspecialchars($patient['name']) ?>" required><br><br>

    <label>Gender</label>
    <select class="input-box" name="gender">
        <option value="Male" <?= $patient['gender']=='Male'?'selected':'' ?>>Male</option>
        <option value="Female" <?= $patient['gender']=='Female'?'selected':'' ?>>Female</option>
    </select><br><br>

    <label>Date of Birth</label>
    <input type="date" class="input-box" name="dob" value="<?= $patient['dob'] ?>" required><br><br>

    <label>IC Number</label>
    <input class="input-box" name="ic_no" value="<?= htmlspecialchars($patient['ic_no']) ?>" required><br><br>

    <label>Address</label>
    <textarea class="input-box" name="address"><?= htmlspecialchars($patient['address']) ?></textarea><br><br>

    <button style="background:#0b2f6d; color:white; padding:10px 20px; border:0;">
        Save Changes
    </button>

    <a href="patient_list.php" style="margin-left:10px;">Cancel</a>
</form>

<style>
.input-box {
    width:100%;
    padding:10px;
    border-radius:5px;
    border:1px solid #ccc;
}
</style>

</div>
</body>
</html>
