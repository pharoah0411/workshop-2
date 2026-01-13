<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$source = $_GET['source'] ?? 'MySQL'; 

$prescription = null;
$details = [];

if ($id > 0) {
    try {
        $conn = null;
        $db_type = ''; 
        
        // Use explicit aliases to ensure columns don't collide and are easily identified
        $sql = "SELECT pr.*, 
                       p.NAME AS PATIENT_NAME, p.IC_NO AS PATIENT_IC, p.ADDRESS AS PATIENT_ADDR,
                       u.NAME AS PHARMACIST_NAME
                FROM PRESCRIPTION pr
                JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                LEFT JOIN {USER_TABLE} u ON pr.PHARMACIST_ID = u.USER_ID
                WHERE pr.PRESCRIPTION_ID = ?";

        if ($source === 'MySQL' && isset($mysql_conn2)) {
            $conn = $mysql_conn2;
            $db_type = 'mysqli';
            $sql = str_replace('{USER_TABLE}', '`USER`', $sql);
        } elseif ($source === 'Postgres' && isset($pg_conn)) {
            $conn = $pg_conn;
            $db_type = 'pdo';
            $sql = str_replace('{USER_TABLE}', '"user"', $sql);
        } elseif ($source === 'SQLServer' && isset($pdo)) {
            $conn = $pdo;
            $db_type = 'pdo';
            $sql = str_replace('{USER_TABLE}', '[USER]', $sql);
        }

        if ($conn) {
            // 1. Fetch Header
            if ($db_type === 'mysqli') {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $prescription = $stmt->get_result()->fetch_assoc();
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($prescription) {
                // Force all keys to UPPERCASE for consistency across all DB types
                $prescription = array_change_key_case($prescription, CASE_UPPER);

                // 2. Fetch Details
                $d_sql = "SELECT pd.*, m.NAME AS MED_NAME 
                          FROM PRESCRIPTION_DETAIL pd 
                          JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
                          WHERE pd.PRESCRIPTION_ID = ?";
                
                if ($db_type === 'mysqli') {
                    $stmtD = $conn->prepare($d_sql);
                    $stmtD->bind_param("i", $id);
                    $stmtD->execute();
                    $resD = $stmtD->get_result();
                    while ($row = $resD->fetch_assoc()) {
                        $details[] = array_change_key_case($row, CASE_UPPER);
                    }
                } else {
                    $stmtD = $conn->prepare($d_sql);
                    $stmtD->execute([$id]);
                    while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
                        $details[] = array_change_key_case($row, CASE_UPPER);
                    }
                }
            }
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Prescription #<?php echo $id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background: #1565c0; color: white; border-radius: 10px; margin-bottom: 20px; }
        .nav-links { display: flex; gap: 15px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 8px 12px; border-radius: 5px; transition: 0.3s; }
        .nav-links a:hover { background: rgba(255, 255, 255, 0.2); }

        .paper-container { max-width: 850px; margin: 0 auto; }
        .paper { background: white; padding: 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; }
        .paper::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 8px; background: #0066ff; border-radius: 15px 15px 0 0; }
        
        .header-section { display: flex; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .source-badge { background: #e3f2fd; color: #1565c0; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        
        .status-badge { padding: 6px 15px; border-radius: 5px; color: white; font-weight: bold; font-size: 0.9em; }
        .status-PENDING { background: #f39c12; }
        .status-COMPLETED { background: #2ecc71; }

        .info-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; margin-bottom: 40px; }
        .info-group { margin-bottom: 15px; }
        .info-group label { display: block; color: #888; font-size: 0.75em; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .info-group div { font-size: 1.1em; color: #333; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; background: #f8f9fa; padding: 15px; border-bottom: 2px solid #ddd; color: #555; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #444; }

        .actions { margin-top: 40px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-print { background: #0066ff; color: white; }
        .btn-back { background: #f0f0f0; color: #555; border: 1px solid #ddd; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        @media print {
            .no-print, .top-nav { display: none !important; }
            body { background: white; padding: 0; }
            .paper { box-shadow: none; border: none; padding: 0; }
            .paper::before { display: none; }
        }
    </style>
</head>
<body>

<header class="top-nav no-print">
    <div>Logged in: <strong><?php echo htmlspecialchars($username ?? ''); ?></strong></div>
    <div class="nav-links">
        <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="prescriptionDashboard.php">📝 Prescriptions</a>
    </div>
</header>

<div class="paper-container">
    <div class="paper">
        <?php if ($prescription): ?>
            <div class="header-section">
                <div>
                    <h1 style="color:#333; margin-bottom:5px;">Prescription Record</h1>
                    <span class="source-badge">Database: <?php echo $source; ?></span>
                </div>
                <div>
                    <?php $status = strtoupper($prescription['STATUS'] ?? 'PENDING'); ?>
                    <span class="status-badge status-<?php echo $status; ?>">
                        <?php echo $status; ?>
                    </span>
                </div>
            </div>

            <div class="info-grid">
                <div>
                    <div class="info-group">
                        <label>Patient Details</label>
                        <div style="font-size: 1.4em; color: #0066ff;"><?php echo htmlspecialchars($prescription['PATIENT_NAME'] ?? 'N/A'); ?></div>
                        <div style="color: #666; margin-top:5px;">IC: <?php echo htmlspecialchars($prescription['PATIENT_IC'] ?? 'N/A'); ?></div>
                        <div style="color: #666;">Addr: <?php echo htmlspecialchars($prescription['PATIENT_ADDR'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                <div style="border-left: 2px solid #f0f0f0; padding-left: 30px;">
                    <div class="info-group">
                        <label>Prescription ID</label>
                        <div>#<?php echo $id; ?></div>
                    </div>
                    <div class="info-group">
                        <label>Issued On</label>
                        <div>
                            <?php 
                            $date = $prescription['DATE_ISSUED'] ?? '';
                            if (is_object($date)) {
                                echo $date->format('d M Y, h:i A');
                            } elseif (!empty($date)) {
                                echo date('d M Y, h:i A', strtotime($date));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-group">
                        <label>Pharmacist</label>
                        <div><?php echo htmlspecialchars($prescription['PHARMACIST_NAME'] ?? 'System User'); ?></div>
                    </div>
                </div>
            </div>

            <h3 style="color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">Medication (Rx)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th>Dosage</th>
                        <th>Instruction</th>
                        <th width="80">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($details)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No medication items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($details as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['MED_NAME'] ?? 'Unknown'); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['DOSAGE'] ?? 'As directed'); ?></td>
                            <td><?php echo htmlspecialchars($d['INSTRUCTION'] ?? '-'); ?></td>
                            <td><?php echo $d['QUANTITY'] ?? '0'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 50px; text-align: center; color: #aaa; font-size: 0.85em; border-top: 1px solid #eee; padding-top: 20px;">
                <p>This document was generated by the Unified Pharmacy Management System.</p>
            </div>

            <div class="actions no-print">
                <a href="prescriptionDashboard.php" class="btn btn-back">⬅ Back to List</a>
                <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Record</button>
            </div>

        <?php else: ?>
            <div style="text-align:center; padding: 50px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 4em; color: #f39c12; margin-bottom: 20px;"></i>
                <h2>Prescription Not Found</h2>
                <p>The record #<?php echo $id; ?> could not be located in the <?php echo $source; ?> database.</p>
                <a href="prescriptionDashboard.php" class="btn btn-back" style="display:inline-block; margin-top:20px;">Return to List</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>