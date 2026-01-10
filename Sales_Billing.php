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

// --- 2. UPDATE EXISTING PRESCRIPTION TABLE (FIXED CASE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalize_bill'])) {
    $p_id = $_POST['prescription_id'];
    $final_amt = $_POST['total_amount'];

    if ($p_id && isset($mysql_conn2)) {
        try {
            // FIXED: Using TOTAL_AMOUNT as per your check
            // If STATUS is also uppercase in your DB, you may want to use STATUS instead of status
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
        body { background: #f4f7f6; padding: 40px; font-family: 'Segoe UI', sans-serif; }
        .invoice-card { background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 40px; max-width: 800px; margin: auto; }
        .table thead { background-color: #2c3e50; color: white; }
        .grand-total { font-size: 1.5rem; color: #2ecc71; font-weight: bold; }
    </style>
</head>
<body>

<div class="invoice-card">
    <div class="row mb-4">
        <div class="col-sm-6">
            <h2 class="text-uppercase tracking-wider">Invoice</h2>
            <p class="text-muted">Prescription Record</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h4 class="mb-0">#<?= $presc_id ?></h4>
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
            <?php foreach ($prescription_items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['MED_NAME']) ?></td>
                <td class="text-center"><?= $item['QUANTITY'] ?></td>
                <td class="text-end">RM <?= number_format($item['UNIT_PRICE'], 2) ?></td>
                <td class="text-end">RM <?= number_format($item['QUANTITY'] * $item['UNIT_PRICE'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end fw-bold pt-4">Grand Total:</td>
                <td class="text-end pt-4 grand-total">RM <?= number_format($calculated_total, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-5 d-flex justify-content-between">
        <button onclick="window.print()" class="btn btn-outline-primary px-4">
            Print Invoice
        </button>
        <form method="POST">
            <input type="hidden" name="prescription_id" value="<?= $presc_id ?>">
            <input type="hidden" name="total_amount" value="<?= $calculated_total ?>">
            <button type="submit" name="finalize_bill" class="btn btn-success btn-lg px-5 shadow" <?= !$presc_id ? 'disabled' : '' ?>>
                Finalize & Collect Payment
            </button>
        </form>
    </div>
</div>

</body>
</html>