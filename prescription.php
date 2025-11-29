<?php
session_start();
// FIXED: Changed from 'db_connection.php' to 'connection.php'
require_once 'connection.php'; 

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Staff';
$username = $_SESSION['username'] ?? 'User';
$prescription_id = $_GET['id'] ?? 0;
$prescription = null;
$items = [];
$error = '';

if ($prescription_id) {
    try {
        // 1. FETCH PRESCRIPTION HEADER (Joined with Patient info)
        $sql_head = "SELECT 
                        PR.PRESCRIPTION_ID, PR.DATE_ISSUED, PR.STATUS, 
                        P.NAME AS PATIENT_NAME, P.IC_NO, P.ADDRESS
                     FROM PRESCRIPTION PR
                     JOIN PATIENT P ON PR.PATIENT_ID = P.PATIENT_ID
                     WHERE PR.PRESCRIPTION_ID = ?";

        // 2. FETCH PRESCRIPTION ITEMS (Joined with Medicine info)
        $sql_items = "SELECT 
                        PD.DOSAGE, PD.QUANTITY, PD.INSTRUCTION,
                        M.NAME AS MED_NAME
                      FROM PRESCRIPTION_DETAIL PD
                      JOIN MEDICINE M ON PD.MEDICINE_ID = M.MEDICINE_ID
                      WHERE PD.PRESCRIPTION_ID = ?";

        if (isset($pdo) && $pdo instanceof PDO) {
            // PDO Version
            $stmt = $pdo->prepare($sql_head);
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prescription) {
                $stmt = $pdo->prepare($sql_items);
                $stmt->execute([$prescription_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif (isset($conn)) {
            // SQL Server Version
            $res = sqlsrv_query($conn, $sql_head, [$prescription_id]);
            if ($res) $prescription = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

            if ($prescription) {
                $res_items = sqlsrv_query($conn, $sql_items, [$prescription_id]);
                if ($res_items) {
                    while ($row = sqlsrv_fetch_array($res_items, SQLSRV_FETCH_ASSOC)) {
                        $items[] = $row;
                    }
                }
            }
        }

        if (!$prescription) {
            $error = "Prescription not found.";
        }

    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
} else {
    $error = "No Prescription ID provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription #<?php echo $prescription_id; ?></title>
    <style>
        /* Shared CSS from your previous code */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI', Tahoma, sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px; }
        .container { max-width:900px; margin:0 auto; background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
        
        /* Nav Bar */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background: #1565c0; color: white; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; transition: opacity 0.2s; }
        .nav-links a:hover { opacity: 0.8; }
        .btn-logout { padding: 6px 12px; border: 1px solid white; border-radius: 6px; background: transparent; color: white; cursor: pointer; text-decoration: none; font-size: 0.9em; }

        .header { background:#e3f2fd; padding:20px; color:#0066ff; text-align:center; border-bottom: 1px solid #ddd; }
        .content { padding:40px; }
        
        /* NEW STYLES FOR VIEW PAGE */
        .rx-header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; border-bottom: 2px dashed #eee; padding-bottom: 20px; }
        .info-box h3 { color: #555; font-size: 0.9em; text-transform: uppercase; margin-bottom: 8px; }
        .info-box p { font-size: 1.1em; color: #000; font-weight: 500; margin-bottom: 5px; }
        .status-badge { display:inline-block; padding: 5px 10px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Dispensed { background: #d4edda; color: #155724; }

        table.rx-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.rx-table th { text-align: left; background: #f8f9fa; padding: 12px; border-bottom: 2px solid #ddd; color: #495057; }
        table.rx-table td { padding: 12px; border-bottom: 1px solid #eee; }
        table.rx-table tr:last-child td { border-bottom: none; }
        
        .rx-logo { font-size: 3em; color: #0066ff; font-weight: bold; font-family: 'Times New Roman', serif; float: right; opacity: 0.2; }

        .actions { margin-top: 30px; display: flex; gap: 10px; justify-content: center; }
        .btn { padding:12px 25px; border:none; border-radius:5px; cursor:pointer; font-size:1em; font-weight:600; text-decoration:none; display:inline-block; }
        .btn-print { background: #0066ff; color: white; }
        .btn-back { background: #6c757d; color: white; }
        
        .error-msg { background:#f8d7da; color:#721c24; padding:20px; text-align: center; border-radius:5px; }

        /* PRINT SETTINGS: Hides blue background and nav when printing */
        @media print {
            body { background: white; padding: 0; }
            .top-nav, .actions, .btn-logout { display: none !important; }
            .container { box-shadow: none; border: 1px solid #ccc; max-width: 100%; width: 100%; border-radius: 0; }
            .header { background: white; border-bottom: 2px solid #000; color: black; }
        }
    </style>
</head>
<body>

    <header class="top-nav">
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="prescriptiondashboard.php">📝 Prescriptions</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>

    <div class="container">
        <?php if($error): ?>
            <div class="content">
                <div class="error-msg">
                    <h2>⚠️ Error</h2>
                    <p><?php echo $error; ?></p>
                    <br>
                    <a href="prescriptiondashboard.php" class="btn btn-back">Go Back</a>
                </div>
            </div>
        <?php else: ?>

            <div class="header">
                <h1>OFFICIAL PRESCRIPTION</h1>
                <p style="color:#666; font-size:0.9em; margin-top:5px;">ID: #<?php echo str_pad($prescription['PRESCRIPTION_ID'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>

            <div class="content">
                <div class="rx-header-grid">
                    <div class="info-box">
                        <h3>Patient Details</h3>
                        <p><?php echo htmlspecialchars($prescription['PATIENT_NAME']); ?></p>
                        <p style="font-size:0.9em; color:#666;">IC: <?php echo htmlspecialchars($prescription['IC_NO']); ?></p>
                        <p style="font-size:0.9em; color:#666;">Address: <?php echo htmlspecialchars($prescription['ADDRESS'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="info-box" style="text-align:right;">
                        <span class="rx-logo">Rx</span>
                        <h3>Prescription Details</h3>
                        <p>Date: <?php 
                            $date = $prescription['DATE_ISSUED'];
                            // Handle SQL Server DateTime object or String
                            if($date instanceof DateTime) echo $date->format('d M Y'); 
                            else echo date('d M Y', strtotime($date));
                        ?></p>
                        <p>Status: <span class="status-badge status-<?php echo $prescription['STATUS']; ?>"><?php echo $prescription['STATUS']; ?></span></p>
                    </div>
                </div>

                <h3>Medication List</h3>
                <table class="rx-table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Dosage</th>
                            <th>Instruction</th>
                            <th style="text-align:center;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['MED_NAME']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['DOSAGE']); ?></td>
                            <td><?php echo htmlspecialchars($item['INSTRUCTION']); ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($item['QUANTITY']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($items)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#999;">No medicines listed.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:40px; border-top:1px solid #eee; padding-top:20px; display:flex; justify-content:space-between;">
                    <div style="font-size:0.8em; color:#888;">
                        <p>General Hospital Pharmacy System</p>
                        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="border-bottom:1px solid #000; width:200px; height:30px; margin-bottom:5px;"></div>
                        <p style="font-size:0.8em;">Pharmacist Signature</p>
                    </div>
                </div>

                <div class="actions">
                    <button onclick="window.print()" class="btn btn-print">🖨️ Print Prescription</button>
                    <a href="prescriptiondashboard.php" class="btn btn-back">Back to List</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>