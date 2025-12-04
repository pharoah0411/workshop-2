<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Staff';
$username = $_SESSION['username'] ?? 'User';

$patients = [];
$medicines = [];
$error = '';

// 1. Fetch Data for Dropdowns
try {
    // FETCH PATIENTS (Name directly from PATIENT table as requested)
    $p_sql = "SELECT PATIENT_ID, NAME, IC_NO FROM PATIENT ORDER BY NAME";
    
    // FETCH MEDICINES
    $m_sql = "SELECT MEDICINE_ID, NAME, QUANTITY_IN_STOCK FROM MEDICINE WHERE QUANTITY_IN_STOCK > 0 ORDER BY NAME";

    if (isset($pdo) && $pdo instanceof PDO) {
        $patients = $pdo->query($p_sql)->fetchAll(PDO::FETCH_ASSOC);
        $medicines = $pdo->query($m_sql)->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        // Patients
        $res_p = sqlsrv_query($conn, $p_sql);
        if ($res_p) {
            while ($row = sqlsrv_fetch_array($res_p, SQLSRV_FETCH_ASSOC)) $patients[] = $row;
        }
        // Medicines
        $res_m = sqlsrv_query($conn, $m_sql);
        if ($res_m) {
            while ($row = sqlsrv_fetch_array($res_m, SQLSRV_FETCH_ASSOC)) $medicines[] = $row;
        }
    }
} catch (Exception $e) { 
    $error = "DB Error: " . $e->getMessage(); 
}

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id']);
    $pharmacist_id = $_SESSION['user_id'];
    $items = $_POST['meds'] ?? []; 

    if ($patient_id > 0 && !empty($items)) {
        try {
            // A. Insert Prescription Header
            $lastId = 0;
            if (isset($pdo)) {
                $stmt = $pdo->prepare("INSERT INTO PRESCRIPTION (PATIENT_ID, PHARMACIST_ID, DATE_ISSUED, STATUS) VALUES (?, ?, GETDATE(), 'Pending')");
                $stmt->execute([$patient_id, $pharmacist_id]);
                // Get ID (Specific to SQL Server PDO)
                $lastId = $pdo->lastInsertId(); 
                if(!$lastId) $lastId = $pdo->query("SELECT @@IDENTITY")->fetchColumn();
            } elseif (isset($conn)) {
                $sql = "INSERT INTO PRESCRIPTION (PATIENT_ID, PHARMACIST_ID, DATE_ISSUED, STATUS) VALUES (?, ?, GETDATE(), 'Pending'); SELECT SCOPE_IDENTITY() AS id";
                $res = sqlsrv_query($conn, $sql, [$patient_id, $pharmacist_id]);
                if ($res === false) throw new Exception(print_r(sqlsrv_errors(), true));
                sqlsrv_next_result($res); 
                $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
                $lastId = $row['id'];
            }

            // B. Insert Prescription Details
            if ($lastId) {
                foreach ($items as $item) {
                    $med_id = intval($item['id']);
                    $qty = intval($item['qty']);
                    $dose = trim($item['dose']);
                    $instr = trim($item['instr']);

                    if ($med_id > 0 && $qty > 0) {
                        if (isset($pdo)) {
                            $stmt = $pdo->prepare("INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$lastId, $med_id, $dose, $qty, $instr]);
                        } elseif (isset($conn)) {
                            $sql = "INSERT INTO PRESCRIPTION_DETAIL (PRESCRIPTION_ID, MEDICINE_ID, DOSAGE, QUANTITY, INSTRUCTION) VALUES (?, ?, ?, ?, ?)";
                            sqlsrv_query($conn, $sql, [$lastId, $med_id, $dose, $qty, $instr]);
                        }
                    }
                }
                header('Location: prescriptiondashboard.php');
                exit;
            }
        } catch (Exception $e) { $error = "Failed to create prescription: " . $e->getMessage(); }
    } else { $error = "Please select a patient and add at least one medicine."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Prescription</title>
    <style>
        /* Shared CSS */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI', Tahoma, sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px; }
        .container { max-width:900px; margin:0 auto; background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
        
        /* Nav Bar */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background: #1565c0; color: white; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; transition: opacity 0.2s; }
        .nav-links a:hover { opacity: 0.8; }
        .btn-logout { padding: 6px 12px; border: 1px solid white; border-radius: 6px; background: transparent; color: white; cursor: pointer; text-decoration: none; font-size: 0.9em; }
        .btn-logout:hover { background: rgba(255, 255, 255, 0.1); }

        .header { background:#e3f2fd; padding:20px; color:#0066ff; text-align:center; }
        .content { padding:30px; }
        .form-group { margin-bottom:15px; }
        label { display:block; margin-bottom:5px; font-weight:600; }
        select, input { width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; }
        
        /* Dynamic Row Styles */
        .med-row { display:flex; gap:10px; margin-bottom:10px; align-items:flex-start; background:#f9f9f9; padding:10px; border-radius:5px; border:1px solid #eee; }
        .med-row select { flex:2; }
        .med-row input { flex:1; }
        .remove-row { color:red; cursor:pointer; font-weight:bold; padding-top:10px; font-size:1.2em; }
        
        .btn { padding:12px 20px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; font-size:1em; font-weight:600; }
        .btn-add { background:#0066ff; margin-bottom:20px; width:100%; }
        .btn-cancel { background:#6c757d; text-decoration:none; display:inline-block; text-align:center;}
        .actions { display:flex; gap:10px; margin-top:20px; }
        .actions button { flex:1; }
        .actions a { width:auto; }
        .error-msg { background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> (<?php echo htmlspecialchars($userRole); ?>)
        </div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="medDirectory.php">📦 Medicines</a>
            <a href="prescriptiondashboard.php">📝 Prescriptions</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>

    <div class="container">
        <div class="header">
            <h1>➕ Issue New Prescription</h1>
        </div>
        <div class="content">
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Select Patient:</label>
                    <select name="patient_id" required>
                        <option value="">-- Choose Patient --</option>
                        <?php foreach($patients as $p): ?>
                            <option value="<?php echo $p['PATIENT_ID']; ?>">
                                <?php echo htmlspecialchars($p['NAME']) . " (IC: " . htmlspecialchars($p['IC_NO']) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label>Prescription Items:</label>
                <div id="med-container">
                    </div>
                
                <button type="button" class="btn btn-add" onclick="addRow()">+ Add Medicine Item</button>

                <div class="actions">
                    <button type="submit" class="btn">Create Prescription</button>
                    <a href="prescriptiondashboard.php" class="btn btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <template id="row-template">
        <div class="med-row">
            <select name="meds[INDEX][id]" required>
                <option value="">Select Medicine...</option>
                <?php foreach($medicines as $m): ?>
                    <option value="<?php echo $m['MEDICINE_ID']; ?>">
                        <?php echo htmlspecialchars($m['NAME']) . " (In Stock: " . $m['QUANTITY_IN_STOCK'] . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="meds[INDEX][dose]" placeholder="Dosage (e.g. 500mg)" required>
            <input type="number" name="meds[INDEX][qty]" placeholder="Qty" min="1" required style="max-width:80px;">
            <input type="text" name="meds[INDEX][instr]" placeholder="Instructions (e.g. 3x Daily)" required>
            <span class="remove-row" onclick="this.parentElement.remove()" title="Remove Item">×</span>
        </div>
    </template>

    <script>
        let index = 0;
        function addRow() {
            const container = document.getElementById('med-container');
            const template = document.getElementById('row-template').innerHTML;
            const html = template.replace(/INDEX/g, index++);
            const div = document.createElement('div');
            div.innerHTML = html;
            container.appendChild(div.firstElementChild);
        }
        // Initialize with one row
        window.onload = addRow;
    </script>
</body>
</html>