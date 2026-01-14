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
            /* FIX: Strip all dashes from user input for comparison.
               This ensures that "990101-01-1234" and "990101011234" are treated the same.
            */
            $ic_no_clean = str_replace('-', '', $ic_no);
            
            /* Search across all 3 databases using REPLACE to ignore dashes in the DB column */
            
            // Search MySQL
            if (!$patient && isset($mysql_conn2)) {
                // Use REPLACE(IC_NO, '-', '') to match the cleaned input
                $stmt = $mysql_conn2->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE REPLACE(IC_NO, '-', '') = ?");
                $stmt->bind_param("s", $ic_no_clean);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) $patient = array_change_key_case($row, CASE_UPPER);
            }
            
            // Search PostgreSQL
            if (!$patient && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("SELECT patient_id, name FROM patient WHERE REPLACE(ic_no, '-', '') = ?");
                $stmt->execute([$ic_no_clean]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $patient = array_change_key_case($row, CASE_UPPER);
            }
            
            // Search SQL Server - FIXED: Use $pdo_sqlsrv
            if (!$patient && isset($pdo_sqlsrv)) {
                $stmt = $pdo_sqlsrv->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE REPLACE(IC_NO, '-', '') = ?");
                $stmt->execute([$ic_no_clean]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $patient = array_change_key_case($row, CASE_UPPER);
            }

            if ($patient) {
                $_SESSION['patient_id'] = $patient['PATIENT_ID'];
                $_SESSION['patient_name'] = $patient['NAME']; 
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
        if (isset($pdo_sqlsrv)) {
            $stmt = $pdo_sqlsrv->prepare($sql);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal | PHARMACY SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Design preserved as requested */
        :root {
            --dark-blue: #1c4966; --blue-medium: #2a5d7a; --blue-light: #e3f2fd;
            --blue-accent: #4a90e2; --cream-white: #f8fafc; --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50; --alert-red: #d9534f; --warning-orange: #f0ad4e; --success-green: #5cb85c;
            --sidebar-bg: var(--dark-blue); --sidebar-text: white; --main-bg: #f5f7fa;
            --card-bg: white; --border-color: #e1e8ed; --text-primary: var(--dark-grey); --text-secondary: var(--soft-grey);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Be Vietnam Pro", sans-serif; background: linear-gradient(135deg, #0d1b4e 0%, #1a2980 30%, #26d0ce 100%); background-attachment: fixed; color: var(--text-primary); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .portal-container { width: 100%; max-width: 1200px; background: white; border-radius: 16px; box-shadow: 0 15px 40px rgba(28, 73, 102, 0.25); overflow: hidden; animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .portal-header { background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium)); color: white; padding: 40px 30px; text-align: center; position: relative; overflow: hidden; }
        .health-icon { width: 80px; height: 80px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; border: 3px solid white; }
        .portal-header h1 { font-size: 2em; font-weight: 700; margin-bottom: 10px; }
        .portal-content { padding: 40px 30px; min-height: 500px; }
        .login-container { max-width: 500px; margin: 0 auto; }
        .welcome-card { background: var(--blue-light); border-radius: 12px; padding: 30px; margin-bottom: 30px; text-align: center; border: 1px solid var(--border-color); }
        .form-group { margin-bottom: 25px; }
        .form-label { display: block; margin-bottom: 10px; color: var(--dark-blue); font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid var(--border-color); border-radius: 10px; }
        .btn { padding: 14px 30px; border-radius: 10px; cursor: pointer; text-decoration: none; font-weight: 600; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium)); color: white; box-shadow: 0 4px 15px rgba(28, 73, 102, 0.25); }
        .btn-logout { background: var(--alert-red); color: white; }
        .alert-message { background: #f8d7da; color: #721c24; border-left: 4px solid var(--alert-red); padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; }
        .welcome-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: white; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08); }
        .patient-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-medium), var(--dark-blue)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5em; border: 3px solid var(--blue-light); }
        .prescription-table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); }
        .table-header { background: var(--blue-light); padding: 20px; border-bottom: 1px solid var(--border-color); }
        .prescription-table { width: 100%; border-collapse: collapse; }
        .prescription-table th { padding: 18px 20px; text-align: left; font-weight: 600; border-bottom: 2px solid #e9ecef; }
        .prescription-table td { padding: 18px 20px; vertical-align: top; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .database-status { display: flex; justify-content: center; gap: 15px; margin-top: 30px; }
        .db-status-item { padding: 8px 15px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
        .db-online { background: rgba(46, 125, 50, 0.1); color: #2e7d32; }
        .db-offline { background: rgba(211, 47, 47, 0.1); color: #d32f2f; }
        .footer-links { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); }
    </style>
</head>
<body>

<div class="portal-container">
    <div class="portal-header">
        <div class="health-icon"><i class="fas fa-user-injured"></i></div>
        <h1>PATIENT HEALTH PORTAL</h1>
        <p>Access your prescription history and medication information securely</p>
    </div>

    <div class="portal-content">
        <?php if (!isset($_SESSION['patient_id'])): ?>
            <div class="login-container">
                <div class="welcome-card">
                    <h2><i class="fas fa-lock"></i> Secure Patient Access</h2>
                    <p>Enter your IC Number to view your records.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-message"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> IC Number</label>
                        <input type="text" name="ic_no" class="form-control" placeholder="e.g. 990101-01-1234" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-sign-in-alt"></i> Access My Records</button>
                </form>

                <div class="database-status">
                    <span class="db-status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'db-online' : 'db-offline'; ?>">MySQL</span>
                    <span class="db-status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'db-online' : 'db-offline'; ?>">PostgreSQL</span>
                    <span class="db-status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'db-online' : 'db-offline'; ?>">SQL Server</span>
                </div>
                <div class="footer-links"><a href="login.php"><i class="fas fa-user-md"></i> Staff Login</a></div>
            </div>
        <?php else: ?>
            <div class="patient-dashboard">
                <div class="welcome-bar">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="patient-avatar"><?php echo strtoupper(substr($_SESSION['patient_name'], 0, 1)); ?></div>
                        <div>
                            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['patient_name']); ?></h2>
                        </div>
                    </div>
                    <a href="?logout=1" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                </div>

                <div class="prescription-table-container">
                    <div class="table-header"><h3><i class="fas fa-prescription"></i> Prescription History</h3></div>
                    <?php if (empty($prescriptions)): ?>
                        <div style="text-align: center; padding: 60px;"><h3>No Records Found</h3></div>
                    <?php else: ?>
                        <table class="prescription-table">
                            <thead><tr><th>Date</th><th>Medicine</th><th>Dosage</th><th>Instructions</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($prescriptions as $p): ?>
                                <tr>
                                    <td><strong><?php echo date('d M Y', strtotime($p['DATE_ISSUED'])); ?></strong></td>
                                    <td><strong style="color: var(--dark-blue);"><?php echo htmlspecialchars($p['MED_NAME']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['DOSAGE']); ?><br><small>Qty: <?php echo $p['QUANTITY']; ?></small></td>
                                    <td><small><?php echo htmlspecialchars($p['INSTRUCTION']); ?></small></td>
                                    <td>
                                        <?php $status = strtolower($p['STATUS'] ?? 'pending'); ?>
                                        <span class="status-badge status-<?php echo $status; ?>"><?php echo strtoupper($status); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const icInput = document.querySelector('input[name="ic_no"]');
        if (icInput) {
            icInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d-]/g, '');
                if (value.length > 6 && value.length <= 12) {
                    value = value.replace(/(\d{6})(\d{2})(\d{4})/, '$1-$2-$3');
                }
                e.target.value = value;
            });
        }
    });
</script>
</body>
</html>