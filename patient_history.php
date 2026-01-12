<?php
require_once "auth_check.php";
requireRole('admin');

require_once "connection.php";
include "header.php";

$message = "";

// ---------------------
// 1) Validate Inputs
// ---------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing patient ID or database source.</p><a href='patient_list.php'>Back</a>");
}

$patient_id = (int)$_GET['id'];
$db = trim($_GET['db']); // "Postgres", "MySQL", "SQL Server"

// We'll store patient & history here
$patient = null;
$historyList = [];

// ---------------------
// 2) FETCH PATIENT (by DB)
// ---------------------
try {

    /* =========================
       POSTGRESQL
    ========================= */
    if ($db === "Postgres" && isset($pg_conn) && $pg_conn instanceof PDO) {

        // patient name is inside patient table in your latest design
        $stmt = $pg_conn->prepare('SELECT patient_id, name FROM patient WHERE patient_id = ?');
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) die("<p style='color:red;'>Patient not found in Postgres.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $pg_conn->prepare('INSERT INTO medical_history (patient_id, description) VALUES (?, ?)');
                $ins->execute([$patient_id, $description]);
                $message = "<p style='color:green;'>New history record added!</p>";
            }
        }

        // Fetch history list
        $stmtH = $pg_conn->prepare('SELECT history_id, description FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC');
        $stmtH->execute([$patient_id]);
        $historyList = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
       MYSQL
    ========================= */
    elseif ($db === "MySQL" && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {

        // patient name is inside PATIENT table
        $stmt = $mysql_conn2->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE PATIENT_ID = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $patient = $res->fetch_assoc();
        $stmt->close();

        if (!$patient) die("<p style='color:red;'>Patient not found in MySQL.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $mysql_conn2->prepare("INSERT INTO MEDICAL_HISTORY (PATIENT_ID, DESCRIPTION) VALUES (?, ?)");
                $ins->bind_param("is", $patient_id, $description);
                $ins->execute();
                $ins->close();
                $message = "<p style='color:green;'>New history record added!</p>";
            }
        }

        // Fetch history list
        $stmtH = $mysql_conn2->prepare("SELECT HISTORY_ID, DESCRIPTION FROM MEDICAL_HISTORY WHERE PATIENT_ID = ? ORDER BY HISTORY_ID DESC");
        $stmtH->bind_param("i", $patient_id);
        $stmtH->execute();
        $historyList = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtH->close();
    }

    /* =========================
       SQL SERVER
    ========================= */
    elseif ($db === "SQL Server" && isset($pdo) && $pdo instanceof PDO) {

        $stmt = $pdo->prepare("SELECT patient_id, name FROM patient WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) die("<p style='color:red;'>Patient not found in SQL Server.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $pdo->prepare("INSERT INTO medical_history (patient_id, description) VALUES (?, ?)");
                $ins->execute([$patient_id, $description]);
                $message = "<p style='color:green;'>New history record added!</p>";
            }
        }

        // Fetch history list
        $stmtH = $pdo->prepare("SELECT history_id, description FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC");
        $stmtH->execute([$patient_id]);
        $historyList = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }

    else {
        die("<p style='color:red;'>Invalid database selected or connection not available.</p><a href='patient_list.php'>Back</a>");
    }

} catch (Exception $e) {
    die("<p style='color:red;'>System error: " . htmlspecialchars($e->getMessage()) . "</p><a href='patient_list.php'>Back</a>");
}

// Normalize name key (MySQL uses NAME)
$patientName = $patient['name'] ?? $patient['NAME'] ?? 'Patient';

?>

<h1>Medical History for <?= htmlspecialchars($patientName) ?> <span style="font-size:14px;color:#666;">(<?= htmlspecialchars($db) ?>)</span></h1>

<?= $message ?>

<!-- Add New History Form -->
<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:650px; margin-bottom:25px;">

    <h3>Add New History Record</h3>

    <textarea name="description" rows="3" class="input-box" required
              placeholder="Enter medical note..."></textarea><br><br>

    <button type="submit"
            style="padding:10px 20px; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; border:0; border-radius:8px;">
        Add Record
    </button>

    <a href="patient_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:8px; margin-left:10px; text-decoration:none;">
        Back
    </a>
</form>

<!-- History Table -->
<table border="0" cellpadding="10" cellspacing="0"
       style="width: 100%; background:white; border-radius:10px;">

    <tr style="background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white;">
        <th>ID</th>
        <th>Description</th>
    </tr>

    <?php if (count($historyList) === 0): ?>
        <tr>
            <td colspan="2" style="text-align:center; padding:20px;">
                No medical history recorded.
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($historyList as $h): ?>
            <tr style="border-bottom:1px solid #ccc;">
                <td><?= htmlspecialchars($h['history_id'] ?? $h['HISTORY_ID']) ?></td>
                <td><?= nl2br(htmlspecialchars($h['description'] ?? $h['DESCRIPTION'])) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<style>
.input-box {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
</style>

</div>
</body>
</html>
