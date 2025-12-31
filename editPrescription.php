<?php
require_once 'session_check.php';
require_once 'connection.php';

$id = intval($_GET['id'] ?? 0);
$source = $_GET['source'] ?? '';

$pres = null;

try {
    if ($source === 'MySQL' && isset($mysql_conn2)) {
        $stmt = $mysql_conn2->prepare("SELECT * FROM PRESCRIPTION WHERE PRESCRIPTION_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pres = $stmt->get_result()->fetch_assoc();
    }

    if ($source === 'Postgres' && isset($pg_conn)) {
        $stmt = $pg_conn->prepare("SELECT * FROM PRESCRIPTION WHERE PRESCRIPTION_ID = :id");
        $stmt->execute([':id' => $id]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($source === 'SQLServer' && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM PRESCRIPTION WHERE PRESCRIPTION_ID = :id");
        $stmt->execute([':id' => $id]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

if (!$pres) die("Prescription not found");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];

    try {
        if ($source === 'MySQL' && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?");
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
        }

        if ($source === 'Postgres' && isset($pg_conn)) {
            $stmt = $pg_conn->prepare("UPDATE PRESCRIPTION SET STATUS=:status WHERE PRESCRIPTION_ID=:id");
            $stmt->execute([':status'=>$status, ':id'=>$id]);
        }

        if ($source === 'SQLServer' && isset($pdo)) {
            $stmt = $pdo->prepare("UPDATE PRESCRIPTION SET STATUS=:status WHERE PRESCRIPTION_ID=:id");
            $stmt->execute([':status'=>$status, ':id'=>$id]);
        }
    } catch(Exception $e){}

    header("Location: prescriptiondashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Prescription</title>
</head>
<body>

<h2>Edit Prescription #<?= $id ?> (<?= htmlspecialchars($source) ?>)</h2>

<form method="POST">

<label>Status</label><br>
<select name="status">
<option value="Pending" <?= $pres['STATUS']=='Pending'?'selected':'' ?>>Pending</option>
<option value="Completed" <?= $pres['STATUS']=='Completed'?'selected':'' ?>>Completed</option>
</select>

<br><br>

<button type="submit">Save</button>
<a href="prescriptiondashboard.php">Cancel</a>

</form>

</body>
</html>
