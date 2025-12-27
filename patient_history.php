<?php
include "connection.php";
include "header.php";

$message = "";

// ---------------------
// 1. Get patient ID
// ---------------------
if (!isset($_GET['id'])) {
    die("<p style='color:red;'>No patient selected.</p>");
}

$patient_id = $_GET['id'];

// ---------------------
// 2. Fetch patient basic info
// ---------------------
$sqlPatient = "
SELECT p.patient_id, u.name
FROM patient p
JOIN \"user\" u ON p.user_id = u.user_id
WHERE p.patient_id = :pid
";

$stmt = $conn->prepare($sqlPatient);
$stmt->execute([':pid' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("<p style='color:red;'>Patient not found.</p>");
}

// ---------------------
// 3. Add new history entry
// ---------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $description = trim($_POST["description"]);

    if ($description !== "") {
        $sqlInsert = "
            INSERT INTO medical_history (patient_id, description)
            VALUES (:pid, :desc)
        ";

        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([
            ':pid'  => $patient_id,
            ':desc' => $description
        ]);

        $message = "<p style='color:green;'>New history record added!</p>";
    }
}

// ---------------------
// 4. Fetch all medical history
// ---------------------
$sqlHistory = "
SELECT history_id, description
FROM medical_history
WHERE patient_id = :pid
ORDER BY history_id DESC
";

$stmtH = $conn->prepare($sqlHistory);
$stmtH->execute([':pid' => $patient_id]);
$historyList = $stmtH->fetchAll(PDO::FETCH_ASSOC);

?>

<h1>Medical History for <?= htmlspecialchars($patient['name']) ?></h1>

<?= $message ?>

<!-- Add New History Form -->
<form method="POST" 
      style="background:white; padding:20px; border-radius:10px; width:650px; margin-bottom:25px;">

    <h3>Add New History Record</h3>

    <textarea name="description" rows="3" class="input-box" required
              placeholder="Enter medical note..."></textarea><br><br>

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Add Record
    </button>

    <a href="patient_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:5px; margin-left:10px; text-decoration:none;">
        Back
    </a>
</form>

<!-- History Table -->
<table border="0" cellpadding="10" cellspacing="0"
       style="width: 100%; background:white; border-radius:10px;">
    
    <tr style="background:#0b2f6d; color:white;">
        <th>ID</th>
        <th>Description</th>
    </tr>

    <?php foreach ($historyList as $h): ?>
        <tr style="border-bottom:1px solid #ccc;">
            <td><?= $h['history_id'] ?></td>
            <td><?= nl2br(htmlspecialchars($h['description'])) ?></td>
        </tr>
    <?php endforeach; ?>

    <?php if (count($historyList) === 0): ?>
        <tr>
            <td colspan="2" style="text-align:center; padding:20px;">
                No medical history recorded.
            </td>
        </tr>
    <?php endif; ?>
</table>

<style>
.input-box {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
</style>

</div> <!-- end content -->
</body>
</html>
