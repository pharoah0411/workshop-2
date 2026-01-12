<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$message = "";

// --- 1. HANDLE BILL FINALIZATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_bill') {
    $presc_id = intval($_POST['prescription_id']);
    $total_amt = floatval($_POST['total_amount']);
    $target_source = $_POST['source'] ?? '';
    $current_date = date('Y-m-d'); 

    if ($presc_id > 0) {
        try {
            $success = false;
            $sql_update = "UPDATE PRESCRIPTION SET STATUS = 'Completed' WHERE PRESCRIPTION_ID = ?";
            $sql_payment = "INSERT INTO PAYMENT (PRESCRIPTION_ID, PAYMENT_DATE, TOTAL_AMOUNT) VALUES (?, ?, ?)";

            if ($target_source === 'MySQL' && isset($mysql_conn2)) {
                $mysql_conn2->begin_transaction();
                $stmt1 = $mysql_conn2->prepare($sql_update);
                $stmt1->bind_param("i", $presc_id);
                $stmt1->execute();
                $stmt2 = $mysql_conn2->prepare($sql_payment);
                $stmt2->bind_param("isd", $presc_id, $current_date, $total_amt);
                $stmt2->execute();
                $mysql_conn2->commit();
                $success = true;
            }
            elseif (($target_source === 'Postgres' && isset($pg_conn)) || ($target_source === 'SQLServer' && isset($pdo))) {
                $conn = ($target_source === 'Postgres') ? $pg_conn : $pdo;
                $conn->beginTransaction();
                $conn->prepare($sql_update)->execute([$presc_id]);
                $conn->prepare($sql_payment)->execute([$presc_id, $current_date, $total_amt]);
                $conn->commit();
                $success = true;
            }

            if ($success) {
                $message = "<div class='alert-msg' style='background:#d4edda; color:#155724;'>✅ Success: Payment recorded. <a href='Sales_Billing.php?bill_id=$presc_id&source=$target_source&print=1' class='btn' style='background:#1565c0; margin-left:10px;'>Print Receipt Now</a></div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert-msg' style='background:#f8d7da; color:#721c24;'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. FETCH INVOICE/RECEIPT DETAILS ---
$invoice_items = [];
$selected_presc_id = filter_input(INPUT_GET, 'bill_id', FILTER_VALIDATE_INT);
$selected_source = $_GET['source'] ?? '';
$is_print_mode = isset($_GET['print']);
$patient_name = "N/A";
$grand_total = 0;

if ($selected_presc_id && $selected_source) {
    try {
        $invoice_sql = "SELECT m.NAME as MED_NAME, pd.QUANTITY, m.UNIT_PRICE, p.NAME as PT_NAME 
                        FROM PRESCRIPTION_DETAIL pd 
                        JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
                        JOIN PRESCRIPTION pr ON pd.PRESCRIPTION_ID = pr.PRESCRIPTION_ID
                        JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                        WHERE pd.PRESCRIPTION_ID = ?";

        if ($selected_source === 'MySQL' && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare($invoice_sql);
            $stmt->bind_param("i", $selected_presc_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $invoice_items[] = array_change_key_case($row, CASE_UPPER);
        } else {
            $conn_to_use = ($selected_source === 'Postgres') ? $pg_conn : $pdo;
            if ($conn_to_use) {
                $stmt = $conn_to_use->prepare($invoice_sql);
                $stmt->execute([$selected_presc_id]);
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $invoice_items[] = array_change_key_case($row, CASE_UPPER);
            }
        }

        if (!empty($invoice_items)) {
            $patient_name = $invoice_items[0]['PT_NAME'] ?? "Unknown Patient";
            foreach($invoice_items as $item) {
                $grand_total += (float)($item['QUANTITY'] ?? 0) * (float)($item['UNIT_PRICE'] ?? 0);
            }
        }
    } catch (Exception $e) { }
}

// --- 3. FETCH ALL BILLS (With Sorting & Search) ---
$search = $_GET['search'] ?? '';
$prescriptions = [];
$list_sql = "SELECT pr.PRESCRIPTION_ID, pr.STATUS, p.NAME AS PATIENT_NAME
             FROM PRESCRIPTION pr
             JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID";

try {
    if (isset($mysql_conn2)) {
        $res = $mysql_conn2->query($list_sql);
        if ($res) while ($row = $res->fetch_assoc()) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'MySQL'; $prescriptions[] = $r; }
    }
    if (isset($pg_conn)) {
        $stmt = $pg_conn->query($list_sql);
        if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'Postgres'; $prescriptions[] = $r; }
    }
    if (isset($pdo)) {
        $stmt = $pdo->query($list_sql);
        if ($stmt) foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'SQLServer'; $prescriptions[] = $r; }
    }
} catch (Exception $e) { }

// Search Filter
if ($search) {
    $prescriptions = array_filter($prescriptions, function($p) use ($search) {
        $q = strtolower($search);
        return (strpos(strtolower($p['PATIENT_NAME'] ?? ''), $q) !== false || strpos(strval($p['PRESCRIPTION_ID'] ?? ''), $q) !== false);
    });
}

// Custom Sort: Pending First, then Completed
usort($prescriptions, function($a, $b) {
    if ($a['STATUS'] === $b['STATUS']) {
        return (int)($b['PRESCRIPTION_ID'] ?? 0) - (int)($a['PRESCRIPTION_ID'] ?? 0);
    }
    return ($a['STATUS'] === 'Pending') ? -1 : 1;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales & Billing | Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 30px; }
        .top-nav { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#1565c0; color:white; margin-bottom:15px; border-radius:10px; max-width:1000px; margin: 0 auto 15px auto; }
        .nav-links a { color:white; text-decoration:none; margin-left:15px; font-weight:500; }
        .invoice-card { background: #f8f9fa; border: 2px solid #1565c0; border-radius: 12px; padding: 25px; margin-bottom: 30px; }
        .alert-msg { padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold; }
        .btn { padding:10px 15px; border-radius:6px; cursor:pointer; text-decoration:none; color:white; font-weight:600; border:none; display:inline-flex; align-items:center; gap:5px; }
        .search-bar { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; font-size: 1em; }
        table { width:100%; border-collapse:collapse; background: white; margin-top: 10px; }
        th, td { padding:12px 15px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#1565c0; color:white; }
        .src-badge { font-size:0.75em; padding:2px 6px; border-radius:4px; color:white; }
        .src-MySQL { background:#f39c12; } .src-Postgres { background:#3498db; } .src-SQLServer { background:#e74c3c; }
        
        /* Interactive Link Styling for the Name */
        .patient-link { color: #1565c0; text-decoration: none; font-weight: bold; transition: color 0.2s; }
        .patient-link:hover { color: #004ba0; text-decoration: underline; }

        @media print { .no-print { display: none; } body { background: white; padding: 0; } .container { box-shadow: none; border: none; max-width: 100%; } }
    </style>
</head>
<body <?= $is_print_mode ? 'onload="window.print()"' : '' ?>>

<div class="top-nav">
    <div>Welcome, <strong><?= htmlspecialchars($username ?? '') ?></strong></div>
    <div class="nav-links">
        <a href="dashboard.php">🏠 Home</a>
        <a href="user_management.php" class="nav-btn">👤 User Management</a>
        <a href="medDirectory.php" class="nav-btn">💊 Med Inventory</a>
        <span class="nav-btn active">💰 Sales & Billing</span>
        <a href="reports.php" class="nav-btn">📊 Report</a>
        <a href="viewPrescription.php" class="nav-btn">📈 Prescription Management</a>
    </div>
</div>

<div class="container">
    <div class="no-print" style="text-align: center; margin-bottom: 30px;">
        <h1 style="color:#1565c0;"><i class="fas fa-cash-register"></i> Sales & Billing Console</h1>
    </div>

    <div class="no-print"><?= $message ?></div>

    <?php if ($selected_presc_id): ?>
    <div class="invoice-card" id="printableInvoice">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div>
                <h2 style="color:#1565c0;"><?= $is_print_mode ? 'OFFICIAL RECEIPT' : 'Invoice Details' ?></h2>
                <p>Prescription #<?= htmlspecialchars($selected_presc_id ?? '') ?> (<?= htmlspecialchars($selected_source ?? '') ?>)</p>
            </div>
            <div style="text-align: right;">
                <p><strong>Date:</strong> <?= date('d-M-Y') ?></p>
            </div>
        </div>
        <hr style="margin: 15px 0;">
        <p><strong>Patient Name:</strong> <?= htmlspecialchars($patient_name ?? 'N/A') ?></p>
        
        <table>
            <thead><tr><th>Medicine</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php foreach($invoice_items as $item): 
                    $qty = $item['QUANTITY'] ?? 0;
                    $u_price = $item['UNIT_PRICE'] ?? 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['MED_NAME'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($qty) ?></td>
                    <td>RM <?= number_format((float)$u_price, 2) ?></td>
                    <td>RM <?= number_format((float)$qty * (float)$u_price, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" style="text-align:right; font-weight:bold;">Total Amount:</td><td style="font-size:1.4em; color:#28a745; font-weight:bold;">RM <?= number_format((float)$grand_total, 2) ?></td></tr>
            </tfoot>
        </table>

        <div class="no-print" style="margin-top:20px; display:flex; gap:10px;">
            <?php 
            // Check current status for action button logic
            $current_status = '';
            foreach($prescriptions as $p) {
                if($p['PRESCRIPTION_ID'] == $selected_presc_id && $p['SOURCE'] == $selected_source) {
                    $current_status = $p['STATUS'];
                    break;
                }
            }
            
            if (!$is_print_mode && $current_status === 'Pending'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="finalize_bill">
                    <input type="hidden" name="prescription_id" value="<?= htmlspecialchars($selected_presc_id ?? '') ?>">
                    <input type="hidden" name="source" value="<?= htmlspecialchars($selected_source ?? '') ?>">
                    <input type="hidden" name="total_amount" value="<?= htmlspecialchars($grand_total ?? 0) ?>">
                    <button type="submit" class="btn" style="background:#28a745;"><i class="fas fa-check-circle"></i> Mark as Paid</button>
                </form>
            <?php else: ?>
                <button onclick="window.print()" class="btn" style="background:#1565c0;"><i class="fas fa-print"></i> Print Receipt</button>
            <?php endif; ?>
            <a href="Sales_Billing.php" class="btn" style="background:#6c757d;">Back to List</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="no-print">
        <form method="GET">
            <input type="text" name="search" class="search-bar" placeholder="🔍 Search Patient Name or Prescription ID..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>

        <table>
            <thead>
                <tr><th>Source</th><th>ID</th><th>Patient Name</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($prescriptions as $p): $src=$p['SOURCE']; ?>
                <tr>
                    <td><span class="src-badge src-<?= htmlspecialchars($src ?? '') ?>"><?= htmlspecialchars($src ?? '') ?></span></td>
                    <td>#<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?></td>
                    <td>
                        <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>" class="patient-link">
                            <?= htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown') ?>
                        </a>
                    </td>
                    <td>
                        <span style="font-size:0.85em; font-weight:bold; color:<?= ($p['STATUS']=='Pending'?'#856404':'#155724') ?>;">
                            <?= htmlspecialchars($p['STATUS'] ?? '') ?>
                        </span>
                    </td>
                    <td>
                        <?php if(($p['STATUS'] ?? '') === 'Pending'): ?>
                            <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>" class="btn" style="background:#28a745; font-size:0.8em;">
                                <i class="fas fa-cash-register"></i> Bill Now
                            </a>
                        <?php else: ?>
                            <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>&print=1" class="btn" style="background:#6c757d; font-size:0.8em;">
                                <i class="fas fa-print"></i> Reprint Receipt
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>