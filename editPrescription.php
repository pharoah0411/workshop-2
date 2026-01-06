<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$id = intval($_GET['id'] ?? 0);
$source = $_GET['source'] ?? '';
$error = '';
$success = '';

if ($id <= 0 || empty($source)) {
    header("Location: prescriptiondashboard.php");
    exit;
}

// Select Connection
$conn = null;
if ($source === 'MySQL') $conn = $mysql_conn2;
elseif ($source === 'Postgres') $conn = $pg_conn;
elseif ($source === 'SQLServer') $conn = $pdo;

if (!$conn) die("Connection to $source failed.");

// Fetch Existing Data
$pres = null;
$items = [];
$medicines = [];

try {
    // 1. Fetch Header & Patient
    $sqlH = "SELECT pr.*, p.NAME as PATIENT_NAME FROM PRESCRIPTION pr 
             JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID WHERE pr.PRESCRIPTION_ID = ?";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlH);
        $stmt->bind_param("i", $id); $stmt->execute();
        $pres = $stmt->get_result()->fetch_assoc();
    } else {
        $stmt = $conn->prepare($sqlH); $stmt->execute([$id]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$pres) die("Prescription not found.");
    $pres = array_change_key_case($pres, CASE_UPPER);

    // 2. Fetch Details
    $sqlD = "SELECT pd.*, m.NAME as MED_NAME FROM PRESCRIPTION_DETAIL pd 
             JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID WHERE pd.PRESCRIPTION_ID = ?";
    if ($source === 'MySQL') {
        $stmt = $conn->prepare($sqlD);
        $stmt->bind_param("i", $id); $stmt->execute();
        $resD = $stmt->get_result();
        while($r = $resD->fetch_assoc()) $items[] = array_change_key_case($r, CASE_UPPER);
    } else {
        $stmt = $conn->prepare($sqlD); $stmt->execute([$id]);
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = array_change_key_case($r, CASE_UPPER);
    }

    // 3. Fetch All Medicines (for dropdowns)
    $sqlM = "SELECT MEDICINE_ID, NAME FROM MEDICINE ORDER BY NAME";
    if ($source === 'MySQL') {
        $resM = $conn->query($sqlM);
        while($r = $resM->fetch_assoc()) $medicines[] = array_change_key_case($r, CASE_UPPER);
    } else {
        $stmtM = $conn->query($sqlM);
        while($r = $stmtM->fetch(PDO::FETCH_ASSOC)) $medicines[] = array_change_key_case($r, CASE_UPPER);
    }
} catch (Exception $e) { $error = $e->getMessage(); }

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $form_items = $_POST['items'] ?? [];

    try {
        if ($source === 'MySQL') {
            $conn->begin_transaction();
            $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?")->execute([$status, $id]);
            $conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID=?")->execute([$id]);
            foreach ($form_items as $item) {
                $stmt = $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)");
                $stmt->execute([$id, $item['med_id'], $item['dosage'], $item['qty'], $item['instr']]);
            }
            $conn->commit();
        } else {
            $conn->beginTransaction();
            $conn->prepare("UPDATE PRESCRIPTION SET STATUS=? WHERE PRESCRIPTION_ID=?")->execute([$status, $id]);
            $conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID=?")->execute([$id]);
            foreach ($form_items as $item) {
                $conn->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?,?,?,?,?)")
                     ->execute([$id, $item['med_id'], $item['dosage'], $item['qty'], $item['instr']]);
            }
            $conn->commit();
        }
        header("Location: prescriptiondashboard.php?success=1");
        exit;
    } catch(Exception $e){
        if($source === 'MySQL') $conn->rollback(); else $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Prescription | Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .header { background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 1px solid #eee; }
        .header h1 { color: #1565c0; margin-bottom: 5px; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        select, input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .item-row { background: #f1f8ff; padding: 20px; border-radius: 12px; border: 1px solid #cfe2ff; margin-bottom: 15px; position: relative; }
        .btn-update { background: #28a745; color: white; border: none; padding: 15px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1em; }
        .btn-cancel { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #666; font-weight: 500; }
        .alert { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-edit"></i> Edit Prescription #<?= $id ?></h1>
        <p>Source: <strong style="color: #1565c0;"><?= $source ?></strong> | Patient: <strong><?= htmlspecialchars($pres['PATIENT_NAME'] ?? '') ?></strong></p>
    </div>

    <div class="content">
        <?php if($error): ?><div class="alert"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Pending" <?= $pres['STATUS'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Completed" <?= $pres['STATUS'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
            <h3><i class="fas fa-pills"></i> Medication Items</h3><br>

            <div id="med-list">
                <?php foreach($items as $idx => $item): ?>
                <div class="item-row">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Medicine</label>
                            <select name="items[<?= $idx ?>][med_id]">
                                <?php foreach($medicines as $m): ?>
                                <option value="<?= $m['MEDICINE_ID'] ?>" <?= $m['MEDICINE_ID'] == $item['MEDICINE_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['NAME']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Quantity</label>
                            <input type="number" name="items[<?= $idx ?>][qty]" value="<?= $item['QUANTITY'] ?>" min="1">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Dosage</label>
                            <input type="text" name="items[<?= $idx ?>][dosage]" value="<?= htmlspecialchars($item['DOSAGE'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Instruction</label>
                            <input type="text" name="items[<?= $idx ?>][instr]" value="<?= htmlspecialchars($item['INSTRUCTION'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-update"><i class="fas fa-save"></i> Save Changes</button>
            <a href="prescriptiondashboard.php" class="btn-cancel">Cancel and Exit</a>
        </form>
    </div>
</div>

</body>
</html>