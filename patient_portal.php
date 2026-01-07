<?php
require_once 'connection.php';
session_start();

$error = '';
$patient = null;
$prescriptions = [];

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['patient_id']);
    unset($_SESSION['patient_name']);
    header('Location: patient_portal.php');
    exit;
}

// 1. Handle Login (IC Number Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ic_no'])) {
    $ic_no = trim($_POST['ic_no']);
    
    if (empty($ic_no)) {
        $error = "Please enter your IC Number.";
    } else {
        try {
            /* Search across all 3 databases for the IC Number */
            
            // Search MySQL
            if (!$patient && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE IC_NO = ?");
                $stmt->bind_param("s", $ic_no);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) $patient = array_change_key_case($row, CASE_UPPER);
            }
            
            // Search PostgreSQL
            if (!$patient && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("SELECT patient_id, name FROM patient WHERE ic_no = ?");
                $stmt->execute([$ic_no]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $patient = array_change_key_case($row, CASE_UPPER);
            }
            
            // Search SQL Server
            if (!$patient && isset($pdo)) {
                $stmt = $pdo->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE IC_NO = ?");
                $stmt->execute([$ic_no]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $patient = array_change_key_case($row, CASE_UPPER);
            }

            if ($patient) {
                $_SESSION['patient_id'] = $patient['PATIENT_ID'];
                $_SESSION['patient_name'] = $patient['NAME'] ?: 'Valued Patient';
            } else {
                $error = "No records found for IC: " . htmlspecialchars($ic_no);
            }
        } catch (Exception $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}

// 2. Fetch Prescriptions if Logged In
if (isset($_SESSION['patient_id'])) {
    $p_id = $_SESSION['patient_id'];
    $sql = "SELECT p.PRESCRIPTION_ID, p.DATE_ISSUED, p.STATUS, 
                   pd.DOSAGE, pd.QUANTITY, pd.INSTRUCTION, 
                   m.NAME as MED_NAME
            FROM PRESCRIPTION p
            JOIN PRESCRIPTION_DETAIL pd ON p.PRESCRIPTION_ID = pd.PRESCRIPTION_ID
            JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID
            WHERE p.PATIENT_ID = ?
            ORDER BY p.DATE_ISSUED DESC";

    try {
        // Collect from all active databases
        if (isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare($sql);
            $stmt->bind_param("i", $p_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $prescriptions[] = array_change_key_case($row, CASE_UPPER);
        }
        if (isset($pg_conn)) {
            $stmt = $pg_conn->prepare($sql);
            $stmt->execute([$p_id]);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $prescriptions[] = array_change_key_case($row, CASE_UPPER);
        }
        if (isset($pdo)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$p_id]);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $prescriptions[] = array_change_key_case($row, CASE_UPPER);
        }
    } catch (Exception $e) { /* Error handling */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Portal</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 40px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: #1976d2; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .login-box { max-width: 400px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 8px; background: #0066ff; color: white; font-weight: 600; cursor: pointer; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f5f5f5; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; }
        .bg-pending { background: #ffa000; }
        .bg-completed { background: #2e7d32; }
        .welcome-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <h1>🏥 Patient Prescription Portal</h1>
    </header>

    <div class="content">
        <?php if (!isset($_SESSION['patient_id'])): ?>
            <div class="login-box">
                <p style="text-align:center; margin-bottom:20px; color:#666;">Enter your IC Number to view your history</p>
                <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>IC Number</label>
                        <input name="ic_no" placeholder="e.g. 990101-01-1234" required>
                    </div>
                    <button type="submit" class="btn">View My Prescriptions</button>
                    <div style="text-align:center; margin-top:15px;">
                        <a href="login.php" style="color:#0066ff; text-decoration:none;">Staff Login</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="welcome-bar">
                <h2>Welcome, <?= htmlspecialchars($_SESSION['patient_name']) ?></h2>
                <a href="?logout=1" style="color:#d32f2f; text-decoration:none; font-weight:600;">Log Out</a>
            </div>

            <?php if (empty($prescriptions)): ?>
                <p style="text-align:center; padding:40px; color:#666;">No prescription history found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medicine</th>
                            <th>Dosage/Qty</th>
                            <th>Instructions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $p): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($p['DATE_ISSUED'])) ?></td>
                            <td><strong><?= htmlspecialchars($p['MED_NAME']) ?></strong></td>
                            <td><?= htmlspecialchars($p['DOSAGE']) ?> (x<?= $p['QUANTITY'] ?>)</td>
                            <td><small><?= htmlspecialchars($p['INSTRUCTION']) ?></small></td>
                            <td>
                                <span class="status-badge bg-<?= strtolower($p['STATUS']) ?>">
                                    <?= strtoupper($p['STATUS']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>