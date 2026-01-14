<?php
require_once 'session_check.php'; 
require_once 'connection.php'; 

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Pharmacist';
$error = '';
$success_msg = '';
$last_ids = []; // Changed to array to track multiple IDs if necessary
$final_sources = []; // Changed to array

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

    // SQLServer -> Postgres -> MySQL (MySQL takes priority for duplicate names)
    if (isset($pdo_sqlsrv)) $fetchAcross($pdo_sqlsrv, 'pdo', 'SQLServer');
    if (isset($pg_conn)) $fetchAcross($pg_conn, 'pdo', 'Postgres');
    if (isset($mysql_conn2)) $fetchAcross($mysql_conn2, 'mysql', 'MySQL');

    ksort($all_patients);
} catch (Exception $e) { $error = "Fetch Error: " . $e->getMessage(); }

// 2. DYNAMIC INSERT LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_presc'])) {
    $patient_ic = trim($_POST['patient_ic'] ?? '');
    $items = $_POST['meds'] ?? [];

    if (!empty($patient_ic) && !empty($items)) {
        // GROUP ITEMS BY THEIR SOURCE DATABASE
        $groupedItems = [];
        foreach ($items as $item) {
            $mName = strtoupper(trim($item['med_name'] ?? ''));
            if (isset($all_medicines[$mName])) {
                $source = $all_medicines[$mName]['DB_SOURCE'];
                $groupedItems[$source][] = $item;
            }
        }

        try {
            foreach ($groupedItems as $target_source => $sourceItems) {
                $target_conn = null;
                if ($target_source === 'MySQL') $target_conn = $mysql_conn2;
                elseif ($target_source === 'Postgres') $target_conn = $pg_conn;
                elseif ($target_source === 'SQLServer') $target_conn = $pdo_sqlsrv;

                if (!$target_conn) continue;

                // C. SYNC PHARMACIST (Current Session Username) TO TARGET DB
                $sqlPharmacistId = null;
                if ($target_source === 'MySQL') {
                    $st = $target_conn->prepare("SELECT USER_ID FROM `USER` WHERE USERNAME = ?");
                    $st->bind_param("s", $username); $st->execute();
                    $sqlPharmacistId = $st->get_result()->fetch_assoc()['USER_ID'] ?? null;
                } else {
                    $tableName = ($target_source === 'SQLServer') ? "[USER]" : "\"user\"";
                    $st = $target_conn->prepare("SELECT USER_ID FROM $tableName WHERE USERNAME = ?");
                    $st->execute([$username]);
                    $sqlPharmacistId = $st->fetchColumn() ?: null;
                }

                if (!$sqlPharmacistId) {
                $role = $_SESSION['role'] ?? 'Pharmacist';
                $tempPass = password_hash('SyncPassword123!', PASSWORD_DEFAULT); 
                    if ($target_source === 'MySQL') {
                        $insP = $target_conn->prepare("INSERT INTO `USER` (USERNAME, PASSWORD, ROLE) VALUES (?, ?, ?)");
                        $insP->bind_param("sss", $username, $tempPass, $role); $insP->execute();
                        $sqlPharmacistId = $target_conn->insert_id;
                    } else {
                        $tableName = ($target_source === 'SQLServer') ? "[USER]" : "\"user\"";
                        $insP = $target_conn->prepare("INSERT INTO $tableName (username, password, role) VALUES (?, ?, ?)");
                        $insP->execute([$username, $tempPass, $role]);
                        $sqlPharmacistId = $target_conn->lastInsertId();
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
                    $sqlPatientId = $st->fetchColumn() ?: null;
                }

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
                    }
                }
                
                if (!$sqlPatientId || !$sqlPharmacistId) {
                    throw new Exception("Synchronization failed for $target_source.");
                }

                // E. FINAL INSERT INTO SPECIFIC DB
                if ($target_source === 'MySQL') {
                    $target_conn->begin_transaction();
                    $insH = $target_conn->prepare("INSERT INTO PRESCRIPTION (PATIENT_ID, PHARMACIST_ID, DATE_ISSUED, STATUS) VALUES (?, ?, NOW(), 'Pending')");
                    $insH->bind_param("ii", $sqlPatientId, $sqlPharmacistId); $insH->execute();
                    $lastId = $target_conn->insert_id;

                    foreach ($sourceItems as $item) {
                        $mName = strtoupper(trim($item['med_name']));
                        $mId = $all_medicines[$mName]['MEDICINE_ID']; // Correct local ID
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
                    // Modified to insert the synced pharmacist user ID for PostgreSQL and SQL Server
                    $insH->execute([$sqlPatientId, $sqlPharmacistId]);
                    $lastId = $target_conn->lastInsertId();

                    foreach ($sourceItems as $item) {
                        $mName = strtoupper(trim($item['med_name']));
                        $mId = $all_medicines[$mName]['MEDICINE_ID']; // Correct local ID
                        $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                        $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                        $qty = intval($item['qty'] ?? 1);
                        $insD = $target_conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?, ?, ?, ?, ?)");
                        $insD->execute([$lastId, $mId, $dose, $qty, $instr]);
                    }
                    $target_conn->commit();
                }
                $last_ids[] = $lastId;
                $final_sources[] = $target_source;
            }
            $success_msg = "Prescriptions saved across " . count($groupedItems) . " system(s) successfully!";
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
            display: flex; flex-direction: column; padding: 25px 0;
        }

        .pharmacy-logo {
            text-align: center; padding: 0 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pharmacy-logo h1 {
            font-size: 1.3em; font-weight: 600; color: white; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .pharmacy-logo p {
            font-size: 0.8em; color: rgba(255, 255, 255, 0.85); font-weight: 300;
        }

        .user-profile {
            padding: 20px; display: flex; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, white, var(--blue-light)); display: flex; align-items: center; justify-content: center; color: var(--dark-blue); font-weight: 600; font-size: 1.2em; border: 2px solid white;
        }

        .user-info { margin-left: 12px; }
        .user-name { font-weight: 500; font-size: 0.95em; margin-bottom: 3px; }
        .user-role { font-size: 0.8em; color: rgba(255, 255, 255, 0.9); background: rgba(255, 255, 255, 0.15); padding: 3px 8px; border-radius: 10px; display: inline-block; }

        .nav-menu { flex: 1; padding: 25px 0; overflow-y: auto; }
        .nav-section { margin-bottom: 25px; padding: 0 20px; }
        .nav-title { font-size: 0.75em; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.7); margin-bottom: 12px; font-weight: 500; }
        .nav-links { list-style: none; }
        .nav-links li { margin-bottom: 6px; }
        .nav-links a { display: flex; align-items: center; color: rgba(255, 255, 255, 0.9); text-decoration: none; padding: 10px 12px; border-radius: 8px; transition: all 0.2s ease; border-left: 2px solid transparent; font-size: 0.9em; }
        .nav-links a:hover { background: rgba(255, 255, 255, 0.1); color: white; border-left-color: var(--blue-accent); }
        .nav-links a.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: white; font-weight: 500; }
        .nav-icon { width: 20px; text-align: center; margin-right: 10px; font-size: 1em; }

        .logout-btn { margin: 15px 20px 0; padding: 12px; background: rgba(255, 255, 255, 0.15); color: white; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 0.9em; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .logout-btn:hover { background: var(--alert-red); border-color: var(--alert-red); transform: translateY(-1px); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .main-header { padding: 20px 35px; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .header-title h1 { font-size: 1.4em; color: var(--dark-blue); font-weight: 600; margin-bottom: 4px; }
        .header-title p { color: var(--text-secondary); font-size: 0.9em; font-weight: 300; }
        .content-wrapper { flex: 1; padding: 30px; overflow-y: auto; background: var(--main-bg); }

        .alert-message { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-size: 0.95em; }
        .alert-message.success { background: #d4edda; color: #155724; border-left: 4px solid var(--success-green); }
        .alert-message.error { background: #f8d7da; color: #721c24; border-left: 4px solid var(--alert-red); }

        .form-container { background: white; border-radius: 12px; box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08); overflow: hidden; margin-bottom: 30px; border: 1px solid var(--border-color); }
        .form-section { padding: 25px 30px; border-bottom: 1px solid var(--border-color); }
        .form-section-header { display: flex; align-items: center; margin-bottom: 20px; color: var(--dark-blue); }
        .form-section-header h3 { font-size: 1.1em; font-weight: 600; margin-left: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; color: var(--dark-grey); font-weight: 500; font-size: 0.9em; }
        .form-control, .form-select { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95em; background: var(--cream-white); transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--dark-blue); box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1); background: white; }

        .medicine-row { background: var(--blue-light); padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid var(--border-color); position: relative; }
        .remove-row { position: absolute; top: 10px; right: 10px; background: var(--alert-red); color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 15px; }
        .timing-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; }
        .timing-options { display: flex; gap: 10px; flex-wrap: wrap; background: white; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); }
        .timing-option { display: flex; align-items: center; gap: 5px; font-size: 0.85em; }

        .btn { padding: 12px 25px; border-radius: 8px; font-weight: 600; font-size: 0.95em; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; }
        .btn-primary { background: var(--dark-blue); color: white; }
        .btn-success { background: var(--success-green); color: white; }
        .btn-secondary { background: white; color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-add { width: 100%; margin-bottom: 20px; background: var(--blue-medium); color: white; }
        .btn-print { background: var(--warning-orange); color: white; width: 100%; padding: 14px; margin-top: 10px; }

        .database-status { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-item { padding: 8px 15px; border-radius: 20px; font-size: 0.85em; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .status-online { background: rgba(46, 125, 50, 0.1); color: #2e7d32; border: 1px solid rgba(46, 125, 50, 0.2); }
        .status-offline { background: rgba(211, 47, 47, 0.1); color: #d32f2f; border: 1px solid rgba(211, 47, 47, 0.2); }

        @media (max-width: 1200px) { .dashboard-container { height: auto; flex-direction: column; } .sidebar { width: 100%; } .form-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .form-grid, .timing-grid { grid-template-columns: 1fr; } .btn { width: 100%; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="pharmacy-logo">
                <h1><i class="fas fa-pills"></i> PHARMACY SYSTEM</h1>
                <p>Professional Healthcare Management</p>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
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
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</button>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <div class="header-title"><h1>Issue New Prescription</h1><p>Create prescription for patient across all databases</p></div>
                <div class="header-actions"><a href="prescriptionDashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
            </header>

            <div class="content-wrapper">
                <div class="database-status">
                    <span class="status-item <?php echo (isset($pg_conn)) ? 'status-online' : 'status-offline'; ?>"><i class="fas fa-database"></i> PostgreSQL: Connected</span>
                    <span class="status-item <?php echo (isset($mysql_conn2)) ? 'status-online' : 'status-offline'; ?>"><i class="fas fa-database"></i> MySQL: Connected</span>
                    <span class="status-item <?php echo (isset($pdo_sqlsrv)) ? 'status-online' : 'status-offline'; ?>"><i class="fas fa-database"></i> SQL Server: Connected</span>
                </div>

                <?php if (!empty($error)): ?><div class="alert-message error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!empty($success_msg)): ?>
                    <div class="alert-message success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_msg); ?></div>
                    <div class="form-container">
                        <div class="form-section" style="text-align: center;">
                            <?php foreach($last_ids as $idx => $lid): ?>
                                <a href="printLabel.php?id=<?php echo $lid; ?>&source=<?php echo htmlspecialchars($final_sources[$idx]); ?>" class="btn btn-print">
                                    <i class="fas fa-print"></i> Print Labels (<?php echo $final_sources[$idx]; ?> #<?php echo $lid; ?>)
                                </a>
                            <?php endforeach; ?>
                            <a href="createPrescription.php" class="btn btn-primary" style="margin-top: 15px;"><i class="fas fa-prescription"></i> Issue Another</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" id="prescriptionForm">
                        <div class="form-container">
                            <div class="form-section">
                                <div class="form-section-header"><i class="fas fa-user-injured"></i><h3>Patient Information</h3></div>
                                <div class="form-group">
                                    <label class="form-label">Search Patient <span style="color:red;">*</span></label>
                                    <select name="patient_ic" class="form-select" required>
                                        <option value="">-- Select Patient --</option>
                                        <?php foreach($all_patients as $ic => $p): ?>
                                            <option value="<?php echo htmlspecialchars($ic); ?>"><?php echo htmlspecialchars($p['NAME'] . " — IC: " . $ic); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-section">
                                <div class="form-section-header"><i class="fas fa-pills"></i><h3>Medications</h3></div>
                                <div id="med-items"></div>
                                <button type="button" onclick="addMedicineRow()" class="btn btn-add"><i class="fas fa-plus-circle"></i> Add Medicine</button>
                            </div>
                            <div class="form-section"><button type="submit" name="submit_presc" class="btn btn-success"><i class="fas fa-save"></i> Save Prescription</button></div>
                        </div>
                    </form>

                    <template id="medicine-row-template">
                        <div class="medicine-row">
                            <button type="button" class="remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                            <div class="form-grid">
                                <div class="form-group"><label class="form-label">Medicine</label>
                                    <select name="meds[IDX][med_name]" class="form-select" required>
                                        <option value="">Select...</option>
                                        <?php foreach($all_medicines as $name => $m): ?><option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label class="form-label">Dose</label>
                                    <select name="meds[IDX][dose_choice]" class="form-select"><option value="1 Tablet">1 Tablet</option><option value="5ml">5ml</option><option value="1 Capsule">1 Capsule</option></select>
                                </div>
                                <div class="form-group"><label class="form-label">Custom Dose</label><input type="text" name="meds[IDX][dose_custom]" class="form-control" placeholder="e.g., 500mg"></div>
                                <div class="form-group"><label class="form-label">Qty</label><input type="number" name="meds[IDX][qty]" class="form-control" min="1" value="10"></div>
                            </div>
                            <div class="timing-grid">
                                <div class="form-group"><label class="form-label">Timing</label>
                                    <div class="timing-options">
                                        <label class="timing-option"><input type="checkbox" name="meds[IDX][timing][]" value="Morning"> Morning</label>
                                        <label class="timing-option"><input type="checkbox" name="meds[IDX][timing][]" value="Night"> Night</label>
                                        <label class="timing-option"><input type="checkbox" name="meds[IDX][timing][]" value="After Food"> After Food</label>
                                    </div>
                                </div>
                                <div class="form-group"><label class="form-label">Frequency</label>
                                    <select name="meds[IDX][instr_freq]" class="form-select"><option value="1x Daily">1x Daily</option><option value="3x Daily">3x Daily</option><option value="SOS">SOS</option></select>
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
        function removeRow(button) { button.closest('.medicine-row').remove(); }
        window.onload = addMedicineRow;
    </script>
</body>
</html>