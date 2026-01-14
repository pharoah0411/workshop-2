<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Pharmacist';
$id = intval($_GET['id'] ?? 0);
$source = $_GET['source'] ?? '';
$error = '';

if ($id <= 0 || empty($source)) {
    header("Location: prescriptionDashboard.php");
    exit;
}

/**
 * HELPER: Parse Dosage string back into components for form fields
 */
function parseDosage($doseStr) {
    $res = ['choice' => '', 'custom' => ''];
    if (preg_match('/^(.*?)\s\((.*?)\)$/', (string)$doseStr, $matches)) {
        $res['choice'] = $matches[1];
        $res['custom'] = $matches[2];
    } else {
        $res['choice'] = $doseStr;
    }
    return $res;
}

/**
 * HELPER: Parse Instruction string back into components for form fields
 */
function parseInstruction($instrStr) {
    $res = ['timing' => [], 'freq' => ''];
    $parts = explode(' - ', (string)$instrStr);
    if (count($parts) >= 2) {
        $res['timing'] = array_map('trim', explode(', ', $parts[0]));
        $res['freq'] = trim($parts[1]);
    } else {
        $res['freq'] = $instrStr;
    }
    return $res;
}

// Map of connections for easy switching
$dbConns = ['MySQL' => $mysql_conn2, 'Postgres' => $pg_conn, 'SQLServer' => $pdo_sqlsrv];
$conn = $dbConns[$source] ?? null;

if (!$conn) die("Connection to $source failed.");

/**
 * HELPER: Resolve Medicine ID in the Target Database
 */
function resolveMedicineId($conn, $source, $medicineName) {
    if (empty($medicineName)) return 0;
    $sqlCheck = "SELECT MEDICINE_ID FROM MEDICINE WHERE NAME = ?";
    $existingId = 0;

    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlCheck);
        $stmt->bind_param("s", $medicineName); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $existingId = $res['MEDICINE_ID'] ?? 0;
    } else {
        $stmt = $conn->prepare($sqlCheck); $stmt->execute([$medicineName]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $existingId = $res['MEDICINE_ID'] ?? $res['medicine_id'] ?? 0;
    }

    if ($existingId > 0) return $existingId;

    $sqlIns = "INSERT INTO MEDICINE (NAME, DESCRIPTION, PRICE) VALUES (?, 'Imported from other source', 0.00)";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlIns);
        $stmt->bind_param("s", $medicineName); $stmt->execute();
        return $conn->insert_id;
    } else {
        $conn->prepare($sqlIns)->execute([$medicineName]);
        return $conn->lastInsertId();
    }
}

/**
 * HELPER: Resolve Patient ID in the Target Database (Required for Migration)
 */
function resolvePatientId($conn, $source, $patientName) {
    if (empty($patientName)) return 0;
    $sqlCheck = "SELECT PATIENT_ID FROM PATIENT WHERE NAME = ?";
    $existingId = 0;
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlCheck);
        $stmt->bind_param("s", $patientName); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $existingId = $res['PATIENT_ID'] ?? 0;
    } else {
        $stmt = $conn->prepare($sqlCheck); $stmt->execute([$patientName]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $existingId = $res['PATIENT_ID'] ?? $res['patient_id'] ?? 0;
    }
    if ($existingId > 0) return $existingId;

    $sqlIns = "INSERT INTO PATIENT (NAME, EMAIL, PHONE) VALUES (?, 'imported@system.com', '000-0000')";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlIns);
        $stmt->bind_param("s", $patientName); $stmt->execute();
        return $conn->insert_id;
    } else {
        $conn->prepare($sqlIns)->execute([$patientName]);
        return $conn->lastInsertId();
    }
}

$pres = null;
$items = [];
$medicines = [];

try {
    // 1. Fetch Header
    $sqlH = "SELECT pr.STATUS, pr.DATE_ISSUED, p.NAME AS PATIENT_NAME FROM PRESCRIPTION pr JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID WHERE pr.PRESCRIPTION_ID = ?";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlH); $stmt->bind_param("i", $id); $stmt->execute();
        $pres = $stmt->get_result()->fetch_assoc();
    } else {
        $stmt = $conn->prepare($sqlH); $stmt->execute([$id]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($pres) $pres = array_change_key_case($pres, CASE_UPPER);

    // 2. Fetch Global Medicine List with Source Tracking
    $sqlM = "SELECT MEDICINE_ID, NAME FROM MEDICINE ORDER BY NAME";
    foreach ($dbConns as $dbName => $c) {
        if (!$c) continue;
        if ($c instanceof mysqli) {
            $res = $c->query($sqlM);
            while($r = $res ? $res->fetch_assoc() : null) $medicines[$r['NAME'] . '|' . $dbName] = ['NAME' => $r['NAME'], 'SOURCE' => $dbName];
        } else {
            $stmt = $c->query($sqlM);
            while($r = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null) {
                $name = $r['NAME'] ?? $r['name'];
                $medicines[$name . '|' . $dbName] = ['NAME' => $name, 'SOURCE' => $dbName];
            }
        }
    }
    ksort($medicines);
    
    // 3. Fetch Current Details
    $sqlD = "SELECT pd.DOSAGE, pd.QUANTITY, pd.INSTRUCTION, m.NAME AS MED_NAME FROM PRESCRIPTION_DETAIL pd JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID WHERE pd.PRESCRIPTION_ID = ?";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlD); $stmt->bind_param("i", $id); $stmt->execute();
        $resD = $stmt->get_result();
        while($r = $resD->fetch_assoc()) $items[] = array_change_key_case($r, CASE_UPPER);
    } else {
        $stmt = $conn->prepare($sqlD); $stmt->execute([$id]);
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = array_change_key_case($r, CASE_UPPER);
    }

} catch (Exception $e) { $error = "Fetch Error: " . $e->getMessage(); }

// Handle POST with Rule Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $form_items = $_POST['meds'] ?? [];
    
    // Determine Target Source from the first medication selected
    $firstMedRaw = $form_items[0]['med_name'] ?? '';
    $parts = explode('|', $firstMedRaw);
    $targetSource = $parts[1] ?? $source;

    try {
        if ($targetSource !== $source) {
            // RULE: Replace and migrate to another database
            $targetConn = $dbConns[$targetSource] ?? null;
            if (!$targetConn) throw new Exception("Target database $targetSource is unavailable.");

            if ($source === 'MySQL') $conn->begin_transaction(); else $conn->beginTransaction();
            if ($targetSource === 'MySQL') $targetConn->begin_transaction(); else $targetConn->beginTransaction();

            // 1. Resolve Patient in Target
            $newPatientId = resolvePatientId($targetConn, $targetSource, $pres['PATIENT_NAME'] ?? 'Unknown');
            
            // 2. Create New Prescription Header in Target
            $dateIssued = $pres['DATE_ISSUED'] ?? date('Y-m-d');
            $sqlH = "INSERT INTO PRESCRIPTION (PATIENT_ID, DATE_ISSUED, STATUS) VALUES (?, ?, ?)";
            if ($targetSource === 'MySQL') {
                $st = $targetConn->prepare($sqlH); $st->bind_param("iss", $newPatientId, $dateIssued, $status); $st->execute();
                $newId = $targetConn->insert_id;
            } else {
                $targetConn->prepare($sqlH)->execute([$newPatientId, $dateIssued, $status]);
                $newId = $targetConn->lastInsertId();
            }

            // 3. Insert Details in Target
            foreach ($form_items as $item) {
                $mParts = explode('|', $item['med_name'] ?? '');
                $mName = $mParts[0];
                $localMedId = resolveMedicineId($targetConn, $targetSource, $mName);
                if ($localMedId > 0) {
                    $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                    $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                    $qty = intval($item['qty'] ?? 1);
                    $sqlD = "INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)";
                    if ($targetSource === 'MySQL') {
                        $st = $targetConn->prepare($sqlD); $st->bind_param("iisis", $newId, $localMedId, $dose, $qty, $instr); $st->execute();
                    } else {
                        $targetConn->prepare($sqlD)->execute([$newId, $localMedId, $dose, $qty, $instr]);
                    }
                }
            }

            // 4. Remove Old Prescription from original database
            $delSql = "DELETE FROM PRESCRIPTION WHERE PRESCRIPTION_ID = " . $id;
            ($source === 'MySQL') ? $conn->query($delSql) : $conn->exec($delSql);

            if ($source === 'MySQL') $conn->commit(); else $conn->commit();
            if ($targetSource === 'MySQL') $targetConn->commit(); else $targetConn->commit();
            
            header("Location: prescriptionDashboard.php?success=Migrated to $targetSource");
            exit;

        } else {
            // Standard Update
            if ($source === 'MySQL') $conn->begin_transaction(); else $conn->beginTransaction();

            if ($source === 'MySQL') {
                $stmt = $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?");
                $stmt->bind_param("si", $status, $id); $stmt->execute();
            } else {
                $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?")->execute([$status, $id]);
            }
            
            $conn->query("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID = " . $id);

            foreach ($form_items as $item) {
                $mParts = explode('|', $item['med_name'] ?? '');
                $mName = $mParts[0];
                $localMedId = resolveMedicineId($conn, $source, $mName);

                if ($localMedId > 0) {
                    $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                    $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                    $qty = intval($item['qty'] ?? 1);

                    if ($source === 'MySQL') {
                        $stmt = $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)");
                        $stmt->bind_param("iisis", $id, $localMedId, $dose, $qty, $instr); $stmt->execute();
                    } else {
                        $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)")
                             ->execute([$id, $localMedId, $dose, $qty, $instr]);
                    }
                }
            }
            if ($source === 'MySQL') $conn->commit(); else $conn->commit();
            header("Location: prescriptionDashboard.php?success=1");
            exit;
        }
    } catch(Exception $e){
        if ($source === 'MySQL') $conn->rollback(); else $conn->rollBack();
        $error = "Update/Migration Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription | PHARMACY SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Be Vietnam Pro", sans-serif; background: var(--main-bg); color: var(--text-primary); min-height: 100vh; display: flex; font-weight: 400; line-height: 1.5; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #1c4966 0%, #143852 100%); color: white; display: flex; flex-direction: column; padding: 25px 0; height: 100vh; position: fixed; left: 0; top: 0; }
        .pharmacy-logo { text-align: center; padding: 0 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.15); }
        .pharmacy-logo h1 { font-size: 1.3em; font-weight: 600; color: white; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .pharmacy-logo p { font-size: 0.8em; color: rgba(255, 255, 255, 0.85); font-weight: 300; }
        .user-profile { padding: 20px; display: flex; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.15); }
        .user-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, white, #e3f2fd); display: flex; align-items: center; justify-content: center; color: #1c4966; font-weight: 600; font-size: 1.2em; border: 2px solid white; }
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
        .main-content { flex: 1; margin-left: 260px; display: flex; flex-direction: column; min-height: 100vh; background: #f5f7fa; }
        .main-header { padding: 20px 30px; background: white; border-bottom: 1px solid #e4e7eb; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .header-title h1 { font-size: 1.4em; color: var(--dark-blue); font-weight: 600; margin-bottom: 4px; }
        .header-title p { color: #6c757d; font-size: 0.85em; font-weight: 300; }
        .content-wrapper { flex: 1; padding: 30px; overflow-y: auto; }
        .prescription-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium)); color: white; padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1.5em; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; }
        .source-badge { background: rgba(255, 255, 255, 0.2); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.85em; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-body { padding: 30px; }
        .alert-message { background: #f8d7da; color: #721c24; border-left: 4px solid var(--alert-red); padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; font-size: 0.9em; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 25px; }
        .form-label { display: block; margin-bottom: 10px; color: var(--dark-blue); font-weight: 600; font-size: 0.95em; display: flex; align-items: center; gap: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.95em; transition: all 0.3s ease; background: white; font-family: "Be Vietnam Pro", sans-serif; }
        .form-control:focus { border-color: var(--dark-blue); box-shadow: 0 0 0 3px rgba(28, 73, 102, 0.15); outline: none; }
        select.form-control { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%231c4966' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; background-size: 12px; appearance: none; }
        .item-row { background: #f8f9fa; padding: 25px; border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px; position: relative; }
        .remove-btn { position: absolute; top: 15px; right: 15px; background: var(--alert-red); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .remove-btn:hover { background: #c9302c; transform: scale(1.1); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .timing-box { background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 10px; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .action-buttons { display: flex; gap: 15px; margin-top: 30px; padding-top: 25px; border-top: 1px solid #e9ecef; }
        .btn { padding: 12px 25px; border-radius: 8px; font-weight: 600; font-size: 0.95em; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease; text-decoration: none; }
        .btn-primary { background: var(--dark-blue); color: white; }
        .btn-secondary { background: white; color: var(--dark-blue); border: 1px solid var(--border-color); }
        .btn-success { background: var(--success-green); color: white; }
        .btn-add { background: var(--blue-accent); color: white; width: 100%; margin-top: 10px; margin-bottom: 20px; }
        @media (max-width: 768px) { .sidebar { position: static; width: 100%; height: auto; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
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
                    <li><a href="prescriptionDashboard.php" class="active"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
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
        </nav>
        <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</button>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div class="header-title">
                <h1>Edit Prescription</h1>
                <p>Update prescription details and medication information</p>
            </div>
            <div class="header-actions">
                <a href="prescriptionDashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
        </header>

        <div class="content-wrapper">
            <div class="prescription-card">
                <div class="card-header">
                    <div>
                        <h2><i class="fas fa-edit"></i> Edit Prescription #<?php echo $id; ?></h2>
                        <div style="display: flex; align-items: center; margin-top: 10px; gap: 10px;">
                            <span class="source-badge"><i class="fas fa-database"></i> <?php echo $source; ?> Database</span>
                            <span style="color: rgba(255, 255, 255, 0.9);"><i class="fas fa-user-injured"></i> <?php echo htmlspecialchars($pres['PATIENT_NAME'] ?? 'Unknown'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert-message"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tasks"></i> Prescription Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Pending" <?= ($pres['STATUS'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Completed" <?= ($pres['STATUS'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>

                        <hr style="margin: 30px 0; border: none; border-top: 1px solid #e9ecef;">
                        <h3 style="color: var(--dark-blue); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-prescription-bottle-alt"></i> Medication Items</h3>

                        <div class="med-items-container" id="med-items">
                            <?php foreach($items as $idx => $item): 
                                $dParts = parseDosage($item['DOSAGE'] ?? '');
                                $iParts = parseInstruction($item['INSTRUCTION'] ?? '');
                            ?>
                            <div class="item-row">
                                <button type="button" class="remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                                <div class="form-grid">
                                    <div>
                                        <label class="form-label">Medicine</label>
                                        <select name="meds[<?= $idx ?>][med_name]" class="form-control" required>
                                            <option value="">Select Medicine...</option>
                                            <?php foreach($medicines as $mKey => $m): ?>
                                            <option value="<?= htmlspecialchars($mKey) ?>" <?= ($item['MED_NAME'] == $m['NAME'] && $m['SOURCE'] == $source) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['NAME']) ?> (<?= $m['SOURCE'] ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Dosage</label>
                                        <select name="meds[<?= $idx ?>][dose_choice]" class="form-control">
                                            <option value="1 Tablet" <?= $dParts['choice'] == '1 Tablet' ? 'selected' : '' ?>>1 Tablet</option>
                                            <option value="5ml" <?= $dParts['choice'] == '5ml' ? 'selected' : '' ?>>5ml</option>
                                            <option value="1 Capsule" <?= $dParts['choice'] == '1 Capsule' ? 'selected' : '' ?>>1 Capsule</option>
                                            <option value="10ml" <?= $dParts['choice'] == '10ml' ? 'selected' : '' ?>>10ml</option>
                                            <option value="2 Tablets" <?= $dParts['choice'] == '2 Tablets' ? 'selected' : '' ?>>2 Tablets</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Custom Dosage</label>
                                        <input type="text" name="meds[<?= $idx ?>][dose_custom]" class="form-control" value="<?= htmlspecialchars($dParts['custom']) ?>" placeholder="e.g., 500mg">
                                    </div>
                                    <div>
                                        <label class="form-label">Quantity</label>
                                        <input type="number" name="meds[<?= $idx ?>][qty]" class="form-control" value="<?= $item['QUANTITY'] ?>" min="1" max="100" required>
                                    </div>
                                </div>
                                <div style="margin-top: 20px;">
                                    <label class="form-label">Administration Timing</label>
                                    <div class="timing-box">
                                        <div class="checkbox-group">
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="Morning" id="morning_<?= $idx ?>" <?= in_array('Morning', $iParts['timing']) ? 'checked' : '' ?>>
                                                <label for="morning_<?= $idx ?>">Morning</label>
                                            </div>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="Afternoon" id="afternoon_<?= $idx ?>" <?= in_array('Afternoon', $iParts['timing']) ? 'checked' : '' ?>>
                                                <label for="afternoon_<?= $idx ?>">Afternoon</label>
                                            </div>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="Night" id="night_<?= $idx ?>" <?= in_array('Night', $iParts['timing']) ? 'checked' : '' ?>>
                                                <label for="night_<?= $idx ?>">Night</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 20px;">
                                    <label class="form-label">Frequency</label>
                                    <select name="meds[<?= $idx ?>][instr_freq]" class="form-control">
                                        <option value="1x Daily" <?= $iParts['freq'] == '1x Daily' ? 'selected' : '' ?>>1x Daily</option>
                                        <option value="2x Daily" <?= $iParts['freq'] == '2x Daily' ? 'selected' : '' ?>>2x Daily</option>
                                        <option value="3x Daily" <?= $iParts['freq'] == '3x Daily' ? 'selected' : '' ?>>3x Daily</option>
                                        <option value="SOS" <?= $iParts['freq'] == 'SOS' ? 'selected' : '' ?>>SOS (As Needed)</option>
                                    </select>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" onclick="addRow()" class="btn btn-add"><i class="fas fa-plus"></i> Add New Medication Item</button>
                        <div class="action-buttons">
                            <a href="prescriptionDashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <template id="row-tpl">
        <div class="item-row">
            <button type="button" class="remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            <div class="form-grid">
                <div>
                    <label class="form-label">Medicine</label>
                    <select name="meds[IDX][med_name]" class="form-control" required>
                        <option value="">Select Medicine...</option>
                        <?php foreach($medicines as $mKey => $m): ?>
                        <option value="<?= htmlspecialchars($mKey) ?>"><?= htmlspecialchars($m['NAME']) ?> (<?= $m['SOURCE'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Dosage</label>
                    <select name="meds[IDX][dose_choice]" class="form-control">
                        <option value="1 Tablet">1 Tablet</option><option value="5ml">5ml</option><option value="1 Capsule">1 Capsule</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Custom Dosage</label>
                    <input type="text" name="meds[IDX][dose_custom]" class="form-control" placeholder="e.g., 500mg">
                </div>
                <div>
                    <label class="form-label">Quantity</label>
                    <input type="number" name="meds[IDX][qty]" class="form-control" min="1" max="100" value="10" required>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <label class="form-label">Administration Timing</label>
                <div class="timing-box">
                    <div class="checkbox-group">
                        <div class="checkbox-item"><input type="checkbox" name="meds[IDX][timing][]" value="Morning" id="morning_IDX"><label for="morning_IDX">Morning</label></div>
                        <div class="checkbox-item"><input type="checkbox" name="meds[IDX][timing][]" value="Afternoon" id="afternoon_IDX"><label for="afternoon_IDX">Afternoon</label></div>
                        <div class="checkbox-item"><input type="checkbox" name="meds[IDX][timing][]" value="Night" id="night_IDX"><label for="night_IDX">Night</label></div>
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <label class="form-label">Frequency</label>
                <select name="meds[IDX][instr_freq]" class="form-control">
                    <option value="1x Daily">1x Daily</option><option value="2x Daily">2x Daily</option><option value="SOS">SOS</option>
                </select>
            </div>
        </div>
    </template>

    <script>
        let idx = <?= count($items) ?>;
        function addRow() {
            const container = document.getElementById('med-items');
            const html = document.getElementById('row-tpl').innerHTML.replace(/IDX/g, idx++);
            const div = document.createElement('div');
            div.innerHTML = html;
            container.appendChild(div.firstElementChild);
        }
    </script>
</body>
</html>