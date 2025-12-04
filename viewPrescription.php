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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$prescription = null;
$details = [];

if ($id > 0) {
    try {
        // Fetch Header - Patient Name from PATIENT table, Pharmacist from USER table
        $sql = "SELECT pr.*, 
                       p.NAME AS PATIENT_NAME, p.IC_NO, p.ADDRESS,
                       pharm_user.NAME AS PHARMACIST_NAME
                FROM PRESCRIPTION pr
                JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                LEFT JOIN [USER] pharm_user ON pr.PHARMACIST_ID = pharm_user.USER_ID
                WHERE pr.PRESCRIPTION_ID = ?";
        
        if (isset($pdo)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (isset($conn)) {
            $res = sqlsrv_query($conn, $sql, [$id]);
            if ($res !== false) $prescription = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        }

        // Fetch Items - Using MEDICINE_ID and NAME from MEDICINE table
        if ($prescription) {
            $d_sql = "SELECT pd.*, m.NAME AS MED_NAME 
                      FROM PRESCRIPTION_DETAIL pd 
                      JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
                      WHERE pd.PRESCRIPTION_ID = ?";
            if (isset($pdo)) {
                $stmt = $pdo->prepare($d_sql);
                $stmt->execute([$id]);
                $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (isset($conn)) {
                $res = sqlsrv_query($conn, $d_sql, [$id]);
                if ($res !== false) {
                    while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) $details[] = $r;
                }
            }
        }
    } catch (Exception $e) { /* Error handled silently for view page */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #<?php echo $id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f7f6; padding: 0; margin: 0; }
        
        /* Top Nav (Screen Only) */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background: #1565c0; color: white; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; }
        .btn-logout { padding: 6px 12px; border: 1px solid white; border-radius: 6px; background: transparent; color: white; cursor: pointer; text-decoration: none; font-size: 0.9em; }

        /* Paper Layout */
        .paper-container { padding: 40px; }
        .paper { max-width: 800px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-top: 8px solid #0066ff; border-radius: 4px; }
        
        h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; font-size: 1.8em; }
        
        .status-badge { font-size: 0.5em; padding: 5px 10px; border-radius: 4px; vertical-align: middle; color: white; }
        .status-Pending { background-color: #f0ad4e; }
        .status-Completed { background-color: #28a745; }
        .status-Cancelled { background-color: #d9534f; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-group { margin-bottom: 10px; }
        .info-group strong { display: block; color: #666; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-group span { font-size: 1.1em; color: #333; font-weight: 600; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; border: 1px solid #eee; }
        th { text-align: left; background: #f8f9fa; color: #333; padding: 12px; border-bottom: 2px solid #ddd; }
        td { border-bottom: 1px solid #eee; padding: 12px; }
        
        .footer-note { text-align: center; color: #888; font-size: 0.9em; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; }

        .actions { margin-top: 20px; text-align: right; }
        .btn { padding: 10px 20px; text-decoration: none; background: #6c757d; color: white; border-radius: 5px; display: inline-block; font-weight: 500; }
        .btn-print { background: #0066ff; margin-right: 10px; }
        
        @media print {
            .top-nav, .actions, .no-print { display: none; }
            body { background: white; padding: 0; }
            .paper-container { padding: 0; }
            .paper { box-shadow: none; border: none; max-width: 100%; padding: 0; }
        }
    </style>
</head>
<body>
    <header class="top-nav no-print">
        <div class="user-info">
            Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="prescriptiondashboard.php">📝 Prescriptions</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>

    <div class="paper-container">
        <div class="paper">
            <?php if ($prescription): ?>
                <h1>
                    Prescription Record
                    <span class="status-badge status-<?php echo $prescription['STATUS']; ?>"><?php echo strtoupper($prescription['STATUS']); ?></span>
                </h1>
                
                <div class="info-grid">
                    <div>
                        <div class="info-group">
                            <strong>Patient Name</strong>
                            <span><?php echo htmlspecialchars($prescription['PATIENT_NAME']); ?></span>
                        </div>
                        <div class="info-group">
                            <strong>IC No.</strong>
                            <span><?php echo htmlspecialchars($prescription['IC_NO']); ?></span>
                        </div>
                        <div class="info-group">
                            <strong>Address</strong>
                            <span><?php echo htmlspecialchars($prescription['ADDRESS']); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="info-group">
                            <strong>Prescription ID</strong>
                            <span>#<?php echo $prescription['PRESCRIPTION_ID']; ?></span>
                        </div>
                        <div class="info-group">
                            <strong>Date Issued</strong>
                            <span><?php echo is_string($prescription['DATE_ISSUED']) ? $prescription['DATE_ISSUED'] : $prescription['DATE_ISSUED']->format('d M Y, h:i A'); ?></span>
                        </div>
                        <div class="info-group">
                            <strong>Pharmacist</strong>
                            <span><?php echo htmlspecialchars($prescription['PHARMACIST_NAME']); ?></span>
                        </div>
                    </div>
                </div>

                <h3>Rx Details</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Instruction</th>
                            <th width="80">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['MED_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($d['DOSAGE']); ?></td>
                            <td><?php echo htmlspecialchars($d['INSTRUCTION']); ?></td>
                            <td><?php echo $d['QUANTITY']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="footer-note">
                    <p>This is a computer-generated document. No signature is required.</p>
                </div>

                <div class="actions no-print">
                    <button onclick="window.print()" class="btn btn-print">🖨️ Print</button>
                    <a href="prescriptiondashboard.php" class="btn">Back to List</a>
                </div>

            <?php else: ?>
                <div style="text-align:center; padding: 50px;">
                    <h2>Prescription not found</h2>
                    <p>The requested record does not exist or has been deleted.</p>
                    <a href="prescriptiondashboard.php" class="btn" style="margin-top:20px;">Return to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>