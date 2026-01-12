<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$id = intval($_GET['id'] ?? 0);
$source = $_GET['source'] ?? '';
$error = '';

if ($id <= 0 || empty($source)) {
    header("Location: prescriptionDashboard.php");
    exit;
}

// Select Connection
$conn = null;
if ($source === 'MySQL') $conn = $mysql_conn2;
elseif ($source === 'Postgres') $conn = $pg_conn;
elseif ($source === 'SQLServer') $conn = $pdo;

if (!$conn) die("Connection to $source failed.");

// Helper: Parse Dosage "Choice (Custom)"
function parseDosage($string) {
    $parts = ['choice' => '1 Tablet', 'custom' => ''];
    if (preg_match('/^(.*) \((.*)\)$/', $string, $matches)) {
        $parts['choice'] = $matches[1];
        $parts['custom'] = $matches[2];
    } else {
        $parts['custom'] = $string; // Fallback
    }
    return $parts;
}

// Helper: Parse Instruction "Morning, Night - 1x Daily"
function parseInstruction($string) {
    $parts = ['timing' => [], 'freq' => '1x Daily'];
    $main = explode(' - ', $string);
    if (count($main) > 0) $parts['timing'] = explode(', ', $main[0]);
    if (count($main) > 1) $parts['freq'] = $main[1];
    return $parts;
}

$pres = null;
$items = [];
$medicines = [];

try {
    // 1. Fetch Header & Patient (Explicit columns to avoid naming collisions)
    $sqlH = "SELECT pr.STATUS, pr.DATE_ISSUED, p.NAME AS PATIENT_NAME 
             FROM PRESCRIPTION pr 
             JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID 
             WHERE pr.PRESCRIPTION_ID = ?";
             
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlH);
        $stmt->bind_param("i", $id); $stmt->execute();
        $pres = $stmt->get_result()->fetch_assoc();
    } else {
        $stmt = $conn->prepare($sqlH); $stmt->execute([$id]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$pres) die("Prescription #$id not found in $source.");
    $pres = array_change_key_case($pres, CASE_UPPER); // Standardize keys to uppercase

    // 2. Fetch Prescription Items
    $sqlD = "SELECT pd.MEDICINE_ID, pd.DOSAGE, pd.QUANTITY, pd.INSTRUCTION, m.NAME AS MED_NAME 
             FROM PRESCRIPTION_DETAIL pd 
             JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
             WHERE pd.PRESCRIPTION_ID = ?";
             
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlD);
        $stmt->bind_param("i", $id); $stmt->execute();
        $resD = $stmt->get_result();
        while($r = $resD->fetch_assoc()) $items[] = array_change_key_case($r, CASE_UPPER);
    } else {
        $stmt = $conn->prepare($sqlD); $stmt->execute([$id]);
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = array_change_key_case($r, CASE_UPPER);
    }

    // 3. Fetch All Medicines for Dropdowns
    $sqlM = "SELECT MEDICINE_ID, NAME FROM MEDICINE ORDER BY NAME";
    if ($source === 'MySQL') {
        $resM = $conn->query($sqlM);
        while($r = $resM->fetch_assoc()) $medicines[] = array_change_key_case($r, CASE_UPPER);
    } else {
        $stmtM = $conn->query($sqlM);
        while($r = $stmtM->fetch(PDO::FETCH_ASSOC)) $medicines[] = array_change_key_case($r, CASE_UPPER);
    }
} catch (Exception $e) { $error = "Fetch Error: " . $e->getMessage(); }

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $form_items = $_POST['meds'] ?? []; // Matches HTML input name

    try {
        if ($source === 'MySQL') {
            $conn->begin_transaction();
            // MySQLi Update
            $stmtUpd = $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?");
            $stmtUpd->bind_param("si", $status, $id); $stmtUpd->execute();
            
            $conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID=?")->bind_param("i", $id);
            $conn->query("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID=$id");

            foreach ($form_items as $item) {
                $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                $qty = intval($item['qty'] ?? 1);
                $mId = intval($item['med_id']);

                $stmt = $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)");
                $stmt->bind_param("iisis", $id, $mId, $dose, $qty, $instr); $stmt->execute();
            }
            $conn->commit();
        } else {
            $conn->beginTransaction();
            $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?")->execute([$status, $id]);
            $conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID=?")->execute([$id]);

            foreach ($form_items as $item) {
                $dose = ($item['dose_choice'] ?? '') . " (" . ($item['dose_custom'] ?? 'Std') . ")";
                $instr = implode(", ", $item['timing'] ?? []) . " - " . ($item['instr_freq'] ?? '');
                $qty = intval($item['qty'] ?? 1);
                $mId = intval($item['med_id']);

                $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)")
                     ->execute([$id, $mId, $dose, $qty, $instr]);
            }
            $conn->commit();
        }
        header("Location: prescriptionDashboard.php?success=1");
        exit;
    } catch(Exception $e){
        if ($source === 'MySQL') $conn->rollback(); else $conn->rollBack();
        $error = "Update Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Prescription | Pharmacy Intelligence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .top-nav { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#1565c0; color:white; margin-bottom:15px; border-radius:10px; }
        .header { background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 1px solid #eee; }
        .header h1 { color: #1565c0; margin-bottom: 5px; }
        .content { padding: 30px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        select, input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .item-row { background: #f1f8ff; padding: 20px; border-radius: 12px; border: 1px solid #cfe2ff; margin-bottom: 20px; position: relative; }
        .btn-update { background: #28a745; color: white; border: none; padding: 15px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1em; margin-top: 20px; }
        .btn-add { background: #0066ff; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; margin-bottom: 20px; }
        .remove-btn { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #dc3545; cursor: pointer; font-weight: bold; }
        .timing-box { display: flex; gap: 10px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #ddd; }
        .timing-box label { font-size: 12px; margin: 0; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .timing-box input { width: auto; }
    </style>
</head>
<body>

<header class="top-nav">
    <div>User: <strong><?= htmlspecialchars($username) ?></strong></div>
    <div style="display: flex; gap: 20px;">
        <a href="javascript:history.back()" style="color:white; text-decoration:none;">⬅️ Back</a>
        <a href="prescriptionDashboard.php" style="color:white; text-decoration:none;">🏠 Dashboard</a>
    </div>
</header>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-edit"></i> Edit Prescription #<?= $id ?></h1>
        <p>Source: <strong style="color: #1565c0;"><?= $source ?></strong> | Patient: <strong><?= htmlspecialchars($pres['PATIENT_NAME'] ?? 'Unknown') ?></strong></p>
    </div>

    <div class="content">
        <?php if($error): ?><div style="background:#f8d7da; padding:15px; border-radius:8px; margin-bottom:20px; color:#721c24;"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 20px;">
                <label>Prescription Status</label>
                <select name="status">
                    <option value="Pending" <?= ($pres['STATUS'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Completed" <?= ($pres['STATUS'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
            <h3><i class="fas fa-pills"></i> Medication Items</h3><br>

            <div id="med-items">
                <?php foreach($items as $idx => $item): 
                    $dParts = parseDosage($item['DOSAGE'] ?? '');
                    $iParts = parseInstruction($item['INSTRUCTION'] ?? '');
                ?>
                <div class="item-row">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Medicine</label>
                            <select name="meds[<?= $idx ?>][med_id]" required>
                                <?php foreach($medicines as $m): ?>
                                <option value="<?= $m['MEDICINE_ID'] ?>" <?= $m['MEDICINE_ID'] == ($item['MEDICINE_ID'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['NAME']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Dose</label>
                            <select name="meds[<?= $idx ?>][dose_choice]">
                                <option value="1 Tablet" <?= $dParts['choice'] == '1 Tablet' ? 'selected' : '' ?>>1 Tablet</option>
                                <option value="5ml" <?= $dParts['choice'] == '5ml' ? 'selected' : '' ?>>5ml</option>
                                <option value="1 Capsule" <?= $dParts['choice'] == '1 Capsule' ? 'selected' : '' ?>>1 Capsule</option>
                            </select>
                        </div>
                        <div><label>Custom</label><input type="text" name="meds[<?= $idx ?>][dose_custom]" value="<?= htmlspecialchars($dParts['custom']) ?>"></div>
                        <div><label>Qty</label><input type="number" name="meds[<?= $idx ?>][qty]" value="<?= $item['QUANTITY'] ?>" min="1"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div>
                            <label>Timing</label>
                            <div class="timing-box">
                                <label><input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="Morning" <?= in_array('Morning', $iParts['timing']) ? 'checked' : '' ?>> Morning</label>
                                <label><input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="Night" <?= in_array('Night', $iParts['timing']) ? 'checked' : '' ?>> Night</label>
                                <label><input type="checkbox" name="meds[<?= $idx ?>][timing][]" value="After Food" <?= in_array('After Food', $iParts['timing']) ? 'checked' : '' ?>> After Food</label>
                            </div>
                        </div>
                        <div>
                            <label>Frequency</label>
                            <select name="meds[<?= $idx ?>][instr_freq]">
                                <option value="1x Daily" <?= $iParts['freq'] == '1x Daily' ? 'selected' : '' ?>>1x Daily</option>
                                <option value="3x Daily" <?= $iParts['freq'] == '3x Daily' ? 'selected' : '' ?>>3x Daily</option>
                                <option value="Every 4 Hours" <?= $iParts['freq'] == 'Every 4 Hours' ? 'selected' : '' ?>>Every 4 Hours</option>
                                <option value="SOS" <?= $iParts['freq'] == 'SOS' ? 'selected' : '' ?>>SOS</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="addRow()" class="btn-add">+ Add Medication</button>
            <button type="submit" class="btn-update">💾 SAVE CHANGES</button>
        </form>
    </div>
</div>

<template id="row-tpl">
    <div class="item-row">
        <button type="button" class="remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label>Medicine</label>
                <select name="meds[IDX][med_id]" required>
                    <option value="">Select...</option>
                    <?php foreach($medicines as $m): ?>
                        <option value="<?= $m['MEDICINE_ID'] ?>"><?= htmlspecialchars($m['NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Dose</label>
                <select name="meds[IDX][dose_choice]">
                    <option value="1 Tablet">1 Tablet</option>
                    <option value="5ml">5ml</option>
                    <option value="1 Capsule">1 Capsule</option>
                </select>
            </div>
            <div><label>Custom</label><input type="text" name="meds[IDX][dose_custom]" placeholder="500mg"></div>
            <div><label>Qty</label><input type="number" name="meds[IDX][qty]" min="1" value="10"></div>
        </div>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
            <div>
                <label>Timing</label>
                <div class="timing-box">
                    <label><input type="checkbox" name="meds[IDX][timing][]" value="Morning"> Morning</label>
                    <label><input type="checkbox" name="meds[IDX][timing][]" value="Night"> Night</label>
                    <label><input type="checkbox" name="meds[IDX][timing][]" value="After Food"> After Food</label>
                </div>
            </div>
            <div>
                <label>Frequency</label>
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