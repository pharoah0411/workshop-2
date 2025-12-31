<?php
require_once 'session_check.php';
require_once 'connection.php'; 

// Use the source passed from the dashboard, default to MySQL if not provided
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$source = $_GET['source'] ?? 'MySQL'; 

$items = [];
$patient_name = "";

if ($id > 0) {
    try {
        // We use a JOIN to get the MEDICINE NAME while filtering by PRESCRIPTION_ID
        $sql = "SELECT m.NAME as medicine_name, pd.DOSAGE, pd.QUANTITY, pd.INSTRUCTION, p.NAME as patient_name 
                FROM PRESCRIPTION_DETAIL pd
                JOIN PRESCRIPTION pr ON pd.PRESCRIPTION_ID = pr.PRESCRIPTION_ID
                JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID
                WHERE pd.PRESCRIPTION_ID = ?";

        if ($source === 'MySQL' && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
                $patient_name = $row['patient_name'];
            }
        } elseif ($source === 'Postgres' && isset($pg_conn)) {
            $stmt = $pg_conn->prepare($sql);
            $stmt->execute([$id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[] = $row;
                $patient_name = $row['patient_name'];
            }
        } elseif ($source === 'SQLServer' && isset($pdo)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[] = $row;
                $patient_name = $row['patient_name'];
            }
        }
    } catch (Exception $e) {
        $error = "Print Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Medication Labels</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .no-print { background: #1565c0; color: white; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-print { padding: 12px 30px; font-weight: bold; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 5px; }
        
        .label-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; justify-content: center; }
        
        /* Medicine Sticker Layout */
        .sticker { 
            background: white; border: 2px solid #333; padding: 20px; 
            width: 380px; height: 220px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .header-text { font-size: 0.9em; font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 5px; color: #555; }
        .med-display { font-size: 1.6em; font-weight: 900; color: #1565c0; margin: 10px 0; text-transform: uppercase; }
        .instruction-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 5px; font-weight: 700; color: #856404; text-align: center; }

        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .label-grid { display: block; }
            .sticker { box-shadow: none; border: 1px solid #000; margin-bottom: 10px; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ START PRINTING</button>
        <a href="prescriptionDashboard.php" style="color:white; margin-left: 20px; text-decoration:none; border:1px solid white; padding:10px; border-radius:5px;">Dashboard</a>
    </div>

    <div class="label-grid">
        <?php if (empty($items)): ?>
            <p style="text-align: center; grid-column: span 2;">No prescription data found. Check your database connection.</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="sticker">
                    <div class="header-text">PATIENT: <?php echo htmlspecialchars($patient_name ?? ''); ?></div>
                    
                    <div class="med-display"><?php echo htmlspecialchars($item['medicine_name'] ?? ''); ?></div>
                    
                    <div style="font-weight: 600;">
                        Qty: <?php echo htmlspecialchars($item['QUANTITY'] ?? ''); ?> — 
                        Dose: <?php echo htmlspecialchars($item['DOSAGE'] ?? ''); ?>
                    </div>
                    
                    <div class="instruction-box">
                        <?php echo htmlspecialchars($item['INSTRUCTION'] ?? ''); ?>
                    </div>
                    
                    <div style="font-size: 0.75em; color: #888; display: flex; justify-content: space-between;">
                        <span>Date: <?php echo date('d/m/Y'); ?></span>
                        <span>Pharmacy Central System</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>