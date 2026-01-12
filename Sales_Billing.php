<?php
// sales_billing.php
header('Content-Type: text/html; charset=utf-8');
require_once "connection.php"; 

$message = "";
$presc_id = filter_input(INPUT_GET, 'presc_id', FILTER_VALIDATE_INT);

$prescription_items = [];
$calculated_total = 0;
$patient_name = "N/A";

// --- 1. FETCH DATA FROM EXISTING CLINICAL TABLES ---
if ($presc_id && isset($mysql_conn2)) {
    try {
        $query = "SELECT p.NAME as PATIENT_NAME, m.NAME as MED_NAME, pd.QUANTITY, m.UNIT_PRICE 
                  FROM PRESCRIPTION pr
                  JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                  JOIN PRESCRIPTION_DETAIL pd ON pr.PRESCRIPTION_ID = pd.PRESCRIPTION_ID
                  JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID
                  WHERE pr.PRESCRIPTION_ID = ?";

        $stmt = $mysql_conn2->prepare($query);
        $stmt->bind_param("i", $presc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $prescription_items[] = $row;
            $patient_name = $row['PATIENT_NAME'];
            $calculated_total += ($row['QUANTITY'] * $row['UNIT_PRICE']);
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-warning'>⚠️ Fetch Error: " . $e->getMessage() . "</div>";
    }
}

// --- 2. UPDATE EXISTING PRESCRIPTION TABLE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalize_bill'])) {
    $p_id = $_POST['prescription_id'];
    $final_amt = $_POST['total_amount'];

    if ($p_id && isset($mysql_conn2)) {
        try {
            $sql = "UPDATE PRESCRIPTION SET STATUS = 'PAID', TOTAL_AMOUNT = ? WHERE PRESCRIPTION_ID = ?";
            $stmt = $mysql_conn2->prepare($sql);
            $stmt->bind_param("di", $final_amt, $p_id);
            
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ Prescription #$p_id marked as PAID. Total recorded: RM " . number_format($final_amt, 2) . "</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ Update Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales & Billing Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Matches your main Pharmacy Management System theme */
        body { 
            background-color: #0066ff; 
            min-height: 100vh;
            padding: 20px; 
            font-family: 'Segoe UI', sans-serif; 
        }

        /* Top Navigation Styling */
        .top-nav {
            display: flex;
            justify-content: flex-end;
            padding: 10px 20px;
            margin-bottom: 20px;
        }

        .btn-dashboard {
            background-color: white;
            color: #0066ff;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s;
        }

        .btn-dashboard:hover {
            background-color: #f0f0f0;
            color: #0056b3;
        }

        /* Invoice Container */
        .invoice-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            padding: 40px; 
            max-width: 900px; 
            margin: auto; 
        }

        .table thead { background-color: #2c3e50; color: white; }
        .grand-total { font-size: 1.5rem; color: #2ecc71; font-weight: bold; }
        
        /* Print adjustments */
        @media print {
            body { background: white; padding: 0; }
            .top-nav, .no-print { display: none !important; }
            .invoice-card { box-shadow: none; border: none; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="top-nav">
    <a href="user_management.php" class="nav-btn">👤 User Management</a>
    <a href="prescriptionDashboard.php" class="nav-btn">💊 Prescription</a>
    <a href="Sales_Billing.php" class="nav-btn active">💰 Sales & Billing</a>
    <a href="reportDashboard.php" class="nav-btn">📊 Report & Analysis</a>
    <a href="viewPrescription.php" class="nav-btn">📈 Prescription Management</a>
</div>

<div class="invoice-card">
    <div class="row mb-4">
        <div class="col-sm-6">
            <h2 class="text-uppercase fw-bold text-primary">Invoice</h2>
            <p class="text-muted">Prescription Record</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <td><?= htmlspecialchars($item['MED_NAME'] ?? '') ?></td>
            <small class="text-muted">Date: <?= date('d-m-Y') ?></small>
        </div>
    </div>

    <?= $message ?>

    <div class="mb-4">
        <label class="text-muted small text-uppercase">Billed To:</label>
        <h5 class="fw-bold"><?= htmlspecialchars($patient_name) ?></h5>
    </div>

    <table class="table table-hover mt-3">
        <thead>
            <tr>
                <th>Medicine</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($prescription_items)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No items found for this ID.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($prescription_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['MED_NAME']) ?></td>
                    <td class="text-center"><?= $item['QUANTITY'] ?></td>
                    <td class="text-end">RM <?= number_format($item['UNIT_PRICE'], 2) ?></td>
                    <td class="text-end">RM <?= number_format($item['QUANTITY'] * $item['UNIT_PRICE'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end fw-bold pt-4">Grand Total:</td>
                <td class="text-end pt-4 grand-total">RM <?= number_format($calculated_total, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-5 d-flex justify-content-between no-print">
        <button onclick="window.print()" class="btn btn-outline-primary px-4 shadow-sm">
            Print Invoice
        </button>
        <form method="POST">
            <input type="hidden" name="prescription_id" value="<?= $presc_id ?>">
            <input type="hidden" name="total_amount" value="<?= $calculated_total ?>">
            <button type="submit" name="finalize_bill" class="btn btn-success btn-lg px-5 shadow" <?= !$presc_id || empty($prescription_items) ? 'disabled' : '' ?>>
                Finalize & Collect Payment
            </button>
        </form>
    </div>
</div>

</body>
</html>