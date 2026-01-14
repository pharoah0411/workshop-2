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
            
            // Search SQL Server - FIXED: Use $pdo_sqlsrv
            if (!$patient && isset($pdo_sqlsrv)) {
                $stmt = $pdo_sqlsrv->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE IC_NO = ?");
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
        /* Medical Professional Color Scheme */
        :root {
            --dark-blue: #1c4966;
            --blue-medium: #2a5d7a;
            --blue-light: #e3f2fd;
            --blue-accent: #4a90e2;
            --cream-white: #f8fafc;
            --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50;
            --alert-red: #d9534f;
            --warning-orange: #f0ad4e;
            --success-green: #5cb85c;
            
            --sidebar-bg: var(--dark-blue);
            --sidebar-text: white;
            --main-bg: #f5f7fa;
            --card-bg: white;
            --border-color: #e1e8ed;
            --text-primary: var(--dark-grey);
            --text-secondary: var(--soft-grey);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Be Vietnam Pro", sans-serif;
            background: linear-gradient(135deg, #0d1b4e 0%, #1a2980 30%, #26d0ce 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-weight: 400;
            line-height: 1.5;
        }

        .portal-container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(28, 73, 102, 0.25);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header Section */
        .portal-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .portal-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
        }

        .health-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            border: 3px solid white;
        }

        .portal-header h1 {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .portal-header p {
            font-size: 1em;
            opacity: 0.9;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Content Area */
        .portal-content {
            padding: 40px 30px;
            min-height: 500px;
        }

        /* Login Form */
        .login-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .welcome-card {
            background: var(--blue-light);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .welcome-card h2 {
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-card p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .form-control:focus {
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 3px rgba(28, 73, 102, 0.15);
            outline: none;
        }

        .form-control:hover {
            border-color: var(--blue-medium);
        }

        /* Buttons */
        .btn {
            padding: 14px 30px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            box-shadow: 0 4px 15px rgba(28, 73, 102, 0.25);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--blue-medium), #1e5a7c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(28, 73, 102, 0.35);
        }

        .btn-secondary {
            background: var(--blue-light);
            color: var(--dark-blue);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: white;
            border-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-logout {
            background: var(--alert-red);
            color: white;
        }

        .btn-logout:hover {
            background: #c9302c;
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert-message {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Patient Dashboard */
        .patient-dashboard {
            width: 100%;
        }

        .welcome-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue-medium), var(--dark-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
            font-weight: 600;
            border: 3px solid var(--blue-light);
        }

        .patient-text h2 {
            color: var(--dark-blue);
            margin-bottom: 5px;
            font-size: 1.4em;
        }

        .patient-text p {
            color: var(--text-secondary);
            font-size: 0.9em;
        }

        /* Prescription Table */
        .prescription-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .table-header {
            background: var(--blue-light);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h3 {
            color: var(--dark-blue);
            font-size: 1.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
        }

        .prescription-table thead {
            background: #f8f9fa;
        }

        .prescription-table th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-grey);
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .prescription-table tbody tr {
            border-bottom: 1px solid #f1f3f4;
            transition: background 0.2s ease;
        }

        .prescription-table tbody tr:hover {
            background: #f8f9fa;
        }

        .prescription-table td {
            padding: 18px 20px;
            color: var(--text-primary);
            vertical-align: top;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc80;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4em;
            color: var(--blue-light);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark-grey);
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        /* Database Status */
        .database-status {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .db-status-item {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .db-online {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .db-offline {
            background: rgba(211, 47, 47, 0.1);
            color: #d32f2f;
            border: 1px solid rgba(211, 47, 47, 0.2);
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9em;
        }

        .footer-links a {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .footer-links a:hover {
            color: var(--blue-accent);
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .portal-container {
                max-width: 100%;
                border-radius: 12px;
            }
            
            .portal-header {
                padding: 30px 20px;
            }
            
            .portal-content {
                padding: 30px 20px;
            }
            
            .health-icon {
                width: 70px;
                height: 70px;
                font-size: 28px;
            }
            
            .portal-header h1 {
                font-size: 1.7em;
            }
            
            .welcome-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .prescription-table {
                display: block;
                overflow-x: auto;
            }
            
            .prescription-table th,
            .prescription-table td {
                padding: 12px 15px;
            }
            
            .database-status {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .portal-header {
                padding: 25px 15px;
            }
            
            .portal-content {
                padding: 25px 15px;
            }
            
            .patient-info {
                flex-direction: column;
                text-align: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="portal-container">
    <div class="portal-header">
        <div class="health-icon">
            <i class="fas fa-user-injured"></i>
        </div>
        <h1>PATIENT HEALTH PORTAL</h1>
        <p>Access your prescription history and medication information securely</p>
    </div>

    <div class="portal-content">
        <?php if (!isset($_SESSION['patient_id'])): ?>
            <!-- Login Form -->
            <div class="login-container">
                <div class="welcome-card">
                    <h2><i class="fas fa-lock"></i> Secure Patient Access</h2>
                    <p>Enter your IC Number to view your prescription history and medication records.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> IC Number
                        </label>
                        <input 
                            type="text" 
                            name="ic_no" 
                            class="form-control" 
                            placeholder="e.g. 990101-01-1234" 
                            required
                            autocomplete="off"
                        >
                        <small style="display: block; margin-top: 8px; color: var(--text-secondary);">
                            Enter your identification number as registered in our system
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Access My Records
                    </button>
                </form>

                <div class="database-status">
                    <span class="db-status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'db-online' : 'db-offline'; ?>">
                        <i class="fas fa-database"></i> MySQL
                    </span>
                    <span class="db-status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'db-online' : 'db-offline'; ?>">
                        <i class="fas fa-database"></i> PostgreSQL
                    </span>
                    <span class="db-status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'db-online' : 'db-offline'; ?>">
                        <i class="fas fa-database"></i> SQL Server
                    </span>
                </div>

                <div class="footer-links">
                    <a href="login.php">
                        <i class="fas fa-user-md"></i> Staff Login
                    </a>
                    <span>|</span>
                    <a href="index.php">
                        <i class="fas fa-home"></i> Return to Home
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Patient Dashboard -->
            <div class="patient-dashboard">
                <div class="welcome-bar">
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($_SESSION['patient_name'], 0, 1)); ?>
                        </div>
                        <div class="patient-text">
                            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['patient_name']); ?></h2>
                            <p>Patient ID: <?php echo $_SESSION['patient_id']; ?></p>
                        </div>
                    </div>
                    <a href="?logout=1" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </a>
                </div>

                <div class="prescription-table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-prescription"></i> Prescription History</h3>
                    </div>
                    
                    <?php if (empty($prescriptions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical"></i>
                            <h3>No Prescription History</h3>
                            <p>You don't have any prescription records in our system.</p>
                            <p style="font-size: 0.9em; margin-top: 10px;">If you believe this is an error, please contact the pharmacy.</p>
                        </div>
                    <?php else: ?>
                        <table class="prescription-table">
                            <thead>
                                <tr>
                                    <th>Date Issued</th>
                                    <th>Medicine</th>
                                    <th>Dosage & Quantity</th>
                                    <th>Instructions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('d M Y', strtotime($p['DATE_ISSUED'])); ?></strong><br>
                                        <small style="color: var(--text-secondary);">
                                            <?php echo date('h:i A', strtotime($p['DATE_ISSUED'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong style="color: var(--dark-blue);"><?php echo htmlspecialchars($p['MED_NAME']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($p['DOSAGE']); ?><br>
                                        <small style="color: var(--text-secondary);">
                                            Quantity: <strong><?php echo $p['QUANTITY']; ?> units</strong>
                                        </small>
                                    </td>
                                    <td>
                                        <small style="color: var(--text-primary);">
                                            <?php echo htmlspecialchars($p['INSTRUCTION']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php $status = strtolower($p['STATUS'] ?? 'pending'); ?>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <i class="fas fa-<?php echo $status === 'completed' ? 'check-circle' : 'clock'; ?>"></i>
                                            <?php echo strtoupper($status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="footer-links" style="margin-top: 40px;">
                    <p>
                        <i class="fas fa-shield-alt"></i> Your data is protected under HIPAA compliance standards
                    </p>
                    <p style="font-size: 0.8em; margin-top: 10px; color: var(--text-secondary);">
                        Last updated: <?php echo date('F j, Y'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Add animation to form focus
    document.addEventListener('DOMContentLoaded', function() {
        const icInput = document.querySelector('input[name="ic_no"]');
        if (icInput) {
            icInput.focus();
            
            // Add input masking for IC format
            icInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d-]/g, '');
                if (value.length > 6 && value.length <= 12) {
                    value = value.replace(/(\d{6})(\d{2})(\d{4})/, '$1-$2-$3');
                }
                e.target.value = value;
            });
        }
        
        // Add hover effect to table rows
        const tableRows = document.querySelectorAll('.prescription-table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    });
</script>
</body>
</html>