<?php
require_once 'session_check.php'; 
require_once 'connection.php'; 

$username = $_SESSION['username'] ?? 'User';
$error = '';
$success_msg = '';
$last_id = null;
$final_source = '';

$all_patients = [];
$all_medicines = [];

// 1. AGGREGATED FETCH: Store the 'SOURCE' for every item
try {
    $fetchAcross = function($conn, $type, $sourceName) use (&$all_patients, &$all_medicines) {
        $p_sql = "SELECT PATIENT_ID, NAME, IC_NO FROM PATIENT";
        $m_sql = "SELECT MEDICINE_ID, NAME, QUANTITY_IN_STOCK FROM MEDICINE";

        if ($type === 'mysql' && $conn instanceof mysqli) {
            $res = $conn->query($p_sql);
            if ($res) while ($row = $res->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = $sourceName;
                $all_patients[trim($r['IC_NO'] ?? '')] = $r; 
            }
            $res_m = $conn->query($m_sql);
            if ($res_m) while ($row = $res_m->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = $sourceName;
                $all_medicines[strtoupper(trim($r['NAME'] ?? ''))] = $r; 
            }
        } elseif ($type === 'pdo' && $conn instanceof PDO) {
            $stmt = $conn->query($p_sql);
            if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = $sourceName;
                $all_patients[trim($r['IC_NO'] ?? '')] = $r;
            }
            $stmt_m = $conn->query($m_sql);
            if ($stmt_m) while ($row = $stmt_m->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = $sourceName;
                $all_medicines[strtoupper(trim($r['NAME'] ?? ''))] = $r;
            }
        }
    };

    if (isset($mysql_conn2)) $fetchAcross($mysql_conn2, 'mysql', 'MySQL');
    if (isset($pg_conn)) $fetchAcross($pg_conn, 'pdo', 'Postgres');
    if (isset($pdo)) $fetchAcross($pdo, 'pdo', 'SQLServer');

    ksort($all_patients);
} catch (Exception $e) { $error = "Fetch Error: " . $e->getMessage(); }

// 2. DYNAMIC INSERT LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_presc'])) {
    $patient_ic = trim($_POST['patient_ic'] ?? '');
    $items = $_POST['meds'] ?? [];

    if (!empty($patient_ic) && !empty($items)) {
        try {
            // A. Determine Target Database based on the FIRST medicine selected
            $first_med_name = strtoupper(trim($items[0]['med_name'] ?? ''));
            if (!isset($all_medicines[$first_med_name])) {
                throw new Exception("Medicine not found in any system.");
            }
            
            $target_source = $all_medicines[$first_med_name]['DB_SOURCE'];
            $final_source = $target_source;

            // B. Establish Target Connection
            $target_conn = null;
            if ($target_source === 'MySQL') $target_conn = $mysql_conn2;
            elseif ($target_source === 'Postgres') $target_conn = $pg_conn;
            elseif ($target_source === 'SQLServer') $target_conn = $pdo;

           // C. SYNC PHARMACIST TO TARGET DB
$sqlPharmacistId = null;

// Search for the current pharmacist in the target database
if ($target_source === 'MySQL') {
    $st = $target_conn->prepare("SELECT USER_ID FROM `USER` WHERE USERNAME = ?");
    $st->bind_param("s", $username); $st->execute();
    $sqlPharmacistId = $st->get_result()->fetch_assoc()['USER_ID'] ?? null;
} else {
    $tableName = ($target_source === 'SQLServer') ? "[USER]" : "\"user\"";
    $st = $target_conn->prepare("SELECT USER_ID FROM $tableName WHERE USERNAME = ?");
    $st->execute([$username]);
    $sqlPharmacistId = $st->fetchColumn();
}

// 🚀 FIX: If Pharmacist is missing in the Target DB, create them automatically
if (!$sqlPharmacistId) {
    $role = $_SESSION['role'] ?? 'Pharmacist';
    // Use the same password for consistency (or a temporary one)
    $tempPass = 'SyncPassword123!'; 

    if ($target_source === 'MySQL') {
        $insP = $target_conn->prepare("INSERT INTO `USER` (USERNAME, PASSWORD, ROLE) VALUES (?, ?, ?)");
        $insP->bind_param("sss", $username, $tempPass, $role);
        $insP->execute();
        $sqlPharmacistId = $target_conn->insert_id;
    } else {
        $tableName = ($target_source === 'SQLServer') ? "[USER]" : "\"user\"";
        $insP = $target_conn->prepare("INSERT INTO $tableName (username, password, role) VALUES (?, ?, ?)");
        $insP->execute([$username, $tempPass, $role]);
        $sqlPharmacistId = ($target_source === 'SQLServer') 
            ? $target_conn->query("SELECT SCOPE_IDENTITY()")->fetchColumn() 
            : $target_conn->lastInsertId();
    }
}
            // D. SYNC PATIENT TO TARGET DB
            $sqlPatientId = null;
            if ($target_source === 'MySQL') {
                $st = $target_conn->prepare("SELECT PATIENT_ID FROM PATIENT WHERE IC_NO = ?");
                $st->bind_param("s", $patient_ic); $st->execute();
                $sqlPatientId = $st->get_result()->fetch_assoc()['PATIENT_ID'] ?? null;
            } else {
                $st = $target_conn->prepare("SELECT PATIENT_ID FROM PATIENT WHERE IC_NO = ?");
                $st->execute([$patient_ic]);
                $sqlPatientId = $st->fetchColumn();
            }

            // If missing in Target, insert them from the master list
            if (!$sqlPatientId && isset($all_patients[$patient_ic])) {
                $pData = $all_patients[$patient_ic];
                if ($target_source === 'MySQL') {
                    $ins = $target_conn->prepare("INSERT INTO PATIENT (NAME, IC_NO) VALUES (?, ?)");
                    $ins->bind_param("ss", $pData['NAME'], $pData['IC_NO']); $ins->execute();
                    $sqlPatientId = $target_conn->insert_id;
                } else {
                    $ins = $target_conn->prepare("INSERT INTO PATIENT (NAME, IC_NO) VALUES (?, ?)");
                    $ins->execute([$pData['NAME'], $pData['IC_NO']]);
                    $sqlPatientId = $target_conn->lastInsertId();
                    if ($target_source === 'SQLServer') $sqlPatientId = $target_conn->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
                }
            }

            // E. FINAL INSERT
            if ($target_source === 'MySQL') {
                $target_conn->begin_transaction();
                $insH = $target_conn->prepare("INSERT INTO PRESCRIPTION (PATIENT_ID, PHARMACIST_ID, DATE_ISSUED, STATUS) VALUES (?, ?, NOW(), 'Pending')");
                $insH->bind_param("ii", $sqlPatientId, $sqlPharmacistId); $insH->execute();
                $lastId = $target_conn->insert_id;

                foreach ($items as $item) {
                    $mName = strtoupper(trim($item['med_name']));
                    $mId = $all_medicines[$mName]['MEDICINE_ID'];
                    $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                    $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                    $qty = intval($item['qty'] ?? 1);
                    $insD = $target_conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?, ?, ?, ?, ?)");
                    $insD->bind_param("iisis", $lastId, $mId, $dose, $qty, $instr); $insD->execute();
                }
                $target_conn->commit();
            } else {
                $target_conn->beginTransaction();
                $date_func = ($target_source === 'Postgres') ? "CURRENT_TIMESTAMP" : "GETDATE()";
                $insH = $target_conn->prepare("INSERT INTO PRESCRIPTION (PATIENT_ID, PHARMACIST_ID, DATE_ISSUED, STATUS) VALUES (?, ?, $date_func, 'Pending')");
                $insH->execute([$sqlPatientId, $sqlPharmacistId]);
                $lastId = ($target_source === 'SQLServer') ? $target_conn->query("SELECT SCOPE_IDENTITY()")->fetchColumn() : $target_conn->lastInsertId();

                foreach ($items as $item) {
                    $mName = strtoupper(trim($item['med_name']));
                    $mId = $all_medicines[$mName]['MEDICINE_ID'];
                    $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                    $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                    $qty = intval($item['qty'] ?? 1);
                    $insD = $target_conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?, ?, ?, ?, ?)");
                    $insD->execute([$lastId, $mId, $dose, $qty, $instr]);
                }
                $target_conn->commit();
            }

            $success_msg = "Prescription Saved to $target_source Successfully!";
            $last_id = $lastId;
        } catch (Exception $e) {
            $error = "Save Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Prescription | Pharmacy System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme with Dark Blue */
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
            background: var(--main-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            font-weight: 400;
            line-height: 1.5;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            height: 92vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
            overflow: hidden;
            display: flex;
        }

        /* Sidebar - Dark Blue */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--dark-blue) 0%, #143852 100%);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 25px 0;
        }

        .pharmacy-logo {
            text-align: center;
            padding: 0 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pharmacy-logo h1 {
            font-size: 1.3em;
            font-weight: 600;
            color: white;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pharmacy-logo p {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 300;
        }

        .user-profile {
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, white, var(--blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1.2em;
            border: 2px solid white;
        }

        .user-info {
            margin-left: 12px;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95em;
            margin-bottom: 3px;
        }

        .user-role {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        /* Navigation Menu */
        .nav-menu {
            flex: 1;
            padding: 25px 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }

        .nav-title {
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 6px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
            font-size: 0.9em;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--blue-accent);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
            font-weight: 500;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 1em;
        }

        .logout-btn {
            margin: 15px 20px 0;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: var(--alert-red);
            border-color: var(--alert-red);
            transform: translateY(-1px);
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .main-header {
            padding: 20px 35px;
            background: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 1.4em;
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header-title p {
            color: var(--text-secondary);
            font-size: 0.9em;
            font-weight: 300;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Alert Messages */
        .alert-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95em;
        }

        .alert-message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        .alert-message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
        }

        .alert-message i {
            margin-right: 10px;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .form-section {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: var(--dark-blue);
        }

        .form-section-header h3 {
            font-size: 1.1em;
            font-weight: 600;
            margin-left: 10px;
        }

        .form-section-header i {
            color: var(--dark-blue);
            font-size: 1.2em;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-grey);
            font-weight: 500;
            font-size: 0.9em;
        }

        .form-label .required {
            color: var(--alert-red);
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            background: var(--cream-white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95em;
            background: var(--cream-white);
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
        }

        /* Medicine Row */
        .medicine-row {
            background: var(--blue-light);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .remove-row {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--alert-red);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
        }

        .remove-row:hover {
            background: #c9302c;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .timing-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .timing-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .timing-option {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85em;
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(28, 73, 102, 0.2);
        }

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #4cae4c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(92, 184, 92, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--dark-blue);
        }

        .btn-add {
            width: 100%;
            margin-bottom: 20px;
            background: var(--blue-medium);
            color: white;
            padding: 14px;
        }

        .btn-add:hover {
            background: var(--dark-blue);
        }

        .btn-print {
            background: var(--warning-orange);
            color: white;
            width: 100%;
            padding: 14px;
            margin-top: 10px;
        }

        .btn-print:hover {
            background: #eea236;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 173, 78, 0.2);
        }

        /* Database Status */
        .database-status {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .status-item {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-online {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .status-offline {
            background: rgba(211, 47, 47, 0.1);
            color: #d32f2f;
            border: 1px solid rgba(211, 47, 47, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container {
                height: auto;
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 15px;
            }
            
            .nav-section {
                flex: 1;
                min-width: 200px;
                margin-bottom: 15px;
            }
            
            .main-content {
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 20px;
            }
            
            .main-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .timing-grid {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                padding: 15px 0;
            }
            
            .pharmacy-logo h1 {
                font-size: 1.1em;
            }
            
            .user-profile {
                padding: 15px;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .form-section {
                padding: 15px;
            }
            
            .database-status {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="pharmacy-logo">
                <h1><i class="fas fa-pills"></i> PHARMACY SYSTEM</h1>
                <p>Professional Healthcare Management</p>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-title">NAVIGATION</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ADMINISTRATION</div>
                    <ul class="nav-links">
                        <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ACCOUNT</div>
                    <ul class="nav-links">
                        <li><a href="profile.php"><i class="fas fa-user-cog nav-icon"></i>Profile Settings</a></li>
                    </ul>
                </div>
            </nav>

            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>Issue New Prescription</h1>
                    <p>Create prescription for patient across all databases</p>
                </div>
                <div class="header-actions">
                    <a href="prescriptionDashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Database Status -->
                <div class="database-status">
                    <span class="status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> PostgreSQL: <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                    <span class="status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> MySQL: <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'Connected' : 'Offline'; ?>
                    </span>
                    <span class="status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> SQL Server: <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert-message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_msg)): ?>
                    <div class="alert-message success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                    
                    <div class="form-container">
                        <div class="form-section" style="text-align: center;">
                            <a href="printLabel.php?id=<?php echo $last_id; ?>&source=<?php echo htmlspecialchars($final_source); ?>" class="btn btn-print">
                                <i class="fas fa-print"></i> Print Medication Labels
                            </a>
                            <a href="createPrescription.php" class="btn btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-prescription"></i> Issue Another Prescription
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" id="prescriptionForm">
                        <div class="form-container">
                            <!-- Patient Information -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="fas fa-user-injured"></i>
                                    <h3>Patient Information</h3>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-search"></i> Search Patient (Across All Databases) <span class="required">*</span>
                                    </label>
                                    <select name="patient_ic" class="form-select" required>
                                        <option value="">-- Select Patient --</option>
                                        <?php foreach($all_patients as $ic => $p): ?>
                                            <option value="<?php echo htmlspecialchars($ic ?? ''); ?>">
                                                <?php echo htmlspecialchars(($p['NAME'] ?? 'Unknown') . " — IC: " . ($ic ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Medicines Section -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="fas fa-pills"></i>
                                    <h3>Medications</h3>
                                </div>
                                <div id="med-items">
                                    <!-- Medicine rows will be added here by JavaScript -->
                                </div>
                                <button type="button" onclick="addMedicineRow()" class="btn btn-add">
                                    <i class="fas fa-plus-circle"></i> Add Medicine
                                </button>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-section">
                                <button type="submit" name="submit_presc" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Prescription
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Medicine Row Template -->
                    <template id="medicine-row-template">
                        <div class="medicine-row">
                            <button type="button" class="remove-row" onclick="removeRow(this)">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Medicine <span class="required">*</span></label>
                                    <select name="meds[IDX][med_name]" class="form-select" required>
                                        <option value="">Select Medicine...</option>
                                        <?php foreach($all_medicines as $name => $m): ?>
                                            <option value="<?php echo htmlspecialchars($name ?? ''); ?>">
                                                <?php echo htmlspecialchars($name ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Dose</label>
                                    <select name="meds[IDX][dose_choice]" class="form-select">
                                        <option value="1 Tablet">1 Tablet</option>
                                        <option value="5ml">5ml</option>
                                        <option value="1 Capsule">1 Capsule</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Custom Dose</label>
                                    <input type="text" name="meds[IDX][dose_custom]" class="form-control" placeholder="e.g., 500mg">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="meds[IDX][qty]" class="form-control" min="1" value="10">
                                </div>
                            </div>
                            <div class="timing-grid">
                                <div class="form-group">
                                    <label class="form-label">Timing</label>
                                    <div class="timing-options">
                                        <label class="timing-option">
                                            <input type="checkbox" name="meds[IDX][timing][]" value="Morning"> Morning
                                        </label>
                                        <label class="timing-option">
                                            <input type="checkbox" name="meds[IDX][timing][]" value="Night"> Night
                                        </label>
                                        <label class="timing-option">
                                            <input type="checkbox" name="meds[IDX][timing][]" value="After Food"> After Food
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Frequency</label>
                                    <select name="meds[IDX][instr_freq]" class="form-select">
                                        <option value="1x Daily">1x Daily</option>
                                        <option value="3x Daily">3x Daily</option>
                                        <option value="Every 4 Hours">Every 4 Hours</option>
                                        <option value="SOS">SOS</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        let medicineIndex = 0;
        
        function addMedicineRow() {
            const container = document.getElementById('med-items');
            const template = document.getElementById('medicine-row-template');
            const html = template.innerHTML.replace(/IDX/g, medicineIndex++);
            const div = document.createElement('div');
            div.innerHTML = html;
            container.appendChild(div.firstElementChild);
        }
        
        function removeRow(button) {
            button.closest('.medicine-row').remove();
        }
        
        // Add first medicine row on page load
        window.onload = addMedicineRow;
        
        // Form validation
        document.getElementById('prescriptionForm')?.addEventListener('submit', function(e) {
            const patientSelect = document.querySelector('select[name="patient_ic"]');
            const medicineRows = document.querySelectorAll('.medicine-row');
            
            if (!patientSelect.value) {
                e.preventDefault();
                alert('Please select a patient.');
                patientSelect.focus();
                return;
            }
            
            if (medicineRows.length === 0) {
                e.preventDefault();
                alert('Please add at least one medicine.');
                return;
            }
            
            // Validate each medicine row
            let valid = true;
            medicineRows.forEach(row => {
                const medicineSelect = row.querySelector('select[name*="med_name"]');
                if (!medicineSelect.value) {
                    valid = false;
                    medicineSelect.style.borderColor = 'red';
                } else {
                    medicineSelect.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please select a medicine for all medication rows.');
            }
        });
    </script>
</body>
</html>