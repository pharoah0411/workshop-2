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
    <title>Issue Prescription</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI', sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px; }
        .container { max-width:1000px; margin:0 auto; background:white; border-radius:15px; box-shadow:0 10px 40px rgba(0,0,0,0.2); overflow:hidden; }
        .top-nav { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#1565c0; color:white; margin-bottom:15px; border-radius:10px; }
        .header { background:#e3f2fd; padding:20px; color:#0066ff; text-align:center; border-bottom:1px solid #ddd; }
        .content { padding:30px; }
        label { display:block; margin-bottom:8px; font-weight:600; color:#333; }
        select, input { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .med-row { background:#f8f9fa; padding:20px; border-radius:10px; border:1px solid #dee2e6; margin-bottom:20px; position:relative; }
        .btn-submit { background:#28a745; color:white; padding:15px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; width:100%; font-size:1.1em; }
        .btn-print { background:#ff9800; color:white; padding:15px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; width:100%; display:block; text-align:center; text-decoration:none; margin-top:10px; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div>User: <strong><?= htmlspecialchars($username ?? '') ?></strong></div>
        <div style="display: flex; gap: 20px;">
            <a href="javascript:history.back()" style="color:white; text-decoration:none;">⬅️ Back</a>
            <a href="prescriptionDashboard.php" style="color:white; text-decoration:none;">🏠 Dashboard</a>
        </div>
    </header>

    <div class="container">
        <div class="header"><h1>➕ New Prescription</h1></div>
        <div class="content">
            <?php if($error): ?><div style="background:#f8d7da; padding:15px; border-radius:8px; margin-bottom:20px; color:#721c24;"><?= $error ?></div><?php endif; ?>
            
            <?php if($success_msg): ?>
                <script>alert("<?= $success_msg ?>");</script>
                <div style="background:#d4edda; padding:15px; border-radius:8px; text-align:center; font-weight:bold; margin-bottom:15px; color:#155724;">✅ <?= $success_msg ?></div>
                <a href="printLabel.php?id=<?= $last_id ?>&source=MySQL" class="btn-print">🖨️ PRINT MEDICATION LABELS</a>
                <div style="text-align:center; margin-top:20px;"><a href="createPrescription.php" style="color:#1565c0; font-weight:bold;">Issue Another</a></div>
            <?php else: ?>
                <form method="POST">
                    <div style="margin-bottom:20px;">
                        <label>Patient (Search Across Systems):</label>
                        <select name="patient_ic" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach($all_patients as $ic => $p): ?>
                                <option value="<?= htmlspecialchars($ic ?? '') ?>">
                                    <?= htmlspecialchars(($p['NAME'] ?? 'Unknown') . " — IC: " . ($ic ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="med-items"></div>
                    <button type="button" onclick="addRow()" style="background:#0066ff; color:white; padding:10px; border:none; border-radius:8px; cursor:pointer; margin-bottom:20px; width:100%;">+ Add Medicine</button>
                    <button type="submit" name="submit_presc" class="btn-submit">💾 SAVE PRESCRIPTION</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <template id="row-tpl">
        <div class="med-row">
            <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:5px; right:10px; background:none; border:none; color:red; font-weight:bold; cursor:pointer;">X</button>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label>Medicine</label>
                    <select name="meds[IDX][med_name]" required>
                        <option value="">Select...</option>
                        <?php foreach($all_medicines as $name => $m): ?>
                            <option value="<?= htmlspecialchars($name ?? '') ?>">
                                <?= htmlspecialchars($name ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Dose</label>
                    <select name="meds[IDX][dose_choice]">
                        <option value="1 Tablet">1 Tablet</option>
                        <option value="5ml">5ml</option>
                        <option value="1 Capsule">1 Capsule</option>
                    </select>
                </div>
                <div><label>Custom</label><input type="text" name="meds[IDX][dose_custom]" placeholder="500mg"></div>
                <div><label>Qty</label><input type="number" name="meds[IDX][qty]" min="1" value="10"></div>
            </div>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px;">
                <div><label>Timing</label>
                    <div style="display:flex; gap:10px; background:white; padding:10px; border-radius:8px; border:1px solid #ddd;">
                        <label style="font-size:12px;"><input type="checkbox" name="meds[IDX][timing][]" value="Morning"> Morning</label>
                        <label style="font-size:12px;"><input type="checkbox" name="meds[IDX][timing][]" value="Night"> Night</label>
                        <label style="font-size:12px;"><input type="checkbox" name="meds[IDX][timing][]" value="After Food"> After Food</label>
                    </div>
                </div>
                <div><label>Frequency</label>
                    <select name="meds[IDX][instr_freq]">
                        <option value="1x Daily">1x Daily</option>
                        <option value="3x Daily">3x Daily</option>
                        <option value="Every 4 Hours">Every 4 Hours</option>
                        <option value="SOS">SOS</option>
                    </select>
                </div>
            </div>
        </div>
    </template>

    <script>
        let idx = 0;
        function addRow() {
            const container = document.getElementById('med-items');
            const html = document.getElementById('row-tpl').innerHTML.replace(/IDX/g, idx++);
            const div = document.createElement('div');
            div.innerHTML = html;
            container.appendChild(div.firstElementChild);
        }
        window.onload = addRow;
    </script>
</body>
</html>