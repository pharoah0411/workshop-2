<?php
// sales_billing.php - This file must be executed by the PHP interpreter (i.e., accessed via http://localhost/...)

header('Content-Type: text/html; charset=utf-8'); // Add this line
require_once "connection.php";

// ... rest of your PHP code
?>

require_once "connection.php";

if (!$pg_conn) {
    die("❌ PostgreSQL Connection Failed");
}

$message = "";

// ===================== A. PAYMENT INSERT LOGIC =====================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] == 'payment_insert') {
    $prescription_id = filter_input(INPUT_POST, 'prescription_id', FILTER_VALIDATE_INT);
    $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);

    if ($prescription_id === false || $total_amount === false || $prescription_id <= 0 || $total_amount <= 0) {
        $message = "Error: Invalid Prescription ID or Total Amount.";
    } else {
        try {
            $stmt = $pg_conn->prepare("
                INSERT INTO public.payment (prescription_id, payment_date, total_amount)
                VALUES (:prescription_id, NOW(), :total_amount)
            ");
            $stmt->execute([
                ":prescription_id" => $prescription_id,
                ":total_amount" => $total_amount
            ]);
            $message = "Payment added successfully!";
        } catch (PDOException $e) {
            $message = "Error adding payment: " . $e->getMessage();
        }
    }
}

// ===================== B. SALES DASHBOARD DATA FETCH =====================

// Today Sales
$today_sales_stmt = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE(sale_date) = CURRENT_DATE
");
$today = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);

// Month Sales
$month_sales_stmt = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE_TRUNC('month', sale_date) = DATE_TRUNC('month', CURRENT_DATE)
");
$month = $month_sales_stmt->fetch(PDO::FETCH_ASSOC);

// Recent 10 Sales
$recent_sales_stmt = $pg_conn->query("
    SELECT * FROM sales ORDER BY sale_date DESC LIMIT 10
");
$recent = $recent_sales_stmt->fetchAll(PDO::FETCH_ASSOC);


// ===================== C. ALL PAYMENTS DATA FETCH =====================
try {
    $result_payments = $pg_conn->query("SELECT * FROM public.payment ORDER BY payment_id DESC");
    $payments = $result_payments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    $payments = []; 
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales and Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #87d2f0; }
        .card { border-radius: 12px; border: none; }
        .nav-link.active { background-color: #0066cc !important; color: white !important; }
        .nav-link { color: #0066cc; }
    </style>
</head>

<body>

<div class="container mt-5">

    <h2 class="text-dark mb-4">📊 Sales and Billing Control Panel</h2>

    <?php if (!empty($message)) { ?>
        <div class="alert alert-info text-center">
            <?= $message ?>
        </div>
    <?php } ?>

    <ul class="nav nav-tabs mb-4" id="salesBillingTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">
                Sales Dashboard
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">
                Payment & Billing
            </button>
        </li>
    </ul>

    <div class="tab-content" id="salesBillingTabContent">

        <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card p-3 shadow-sm">
                        <h5>📅 Today's Sales</h5>
                        <h3>RM <?= number_format($today['total'], 2) ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 shadow-sm">
                        <h5>🗓 This Month's Sales</h5>
                        <h3>RM <?= number_format($month['total'], 2) ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="new_sale.php" class="btn btn-primary btn-lg w-100 shadow-sm" style="height: 100%;">+ New Sale / Transaction</a>
                </div>
            </div>

            <div class="card shadow p-4 mt-4">
                <h4>Recent Sales Transactions</h4>
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row) { ?>
                            <tr>
                                <td><?= $row['sale_id'] ?></td>
                                <td><?= $row['patient_name'] ?></td>
                                <td>RM <?= number_format($row['total_amount'], 2) ?></td>
                                <td><?= $row['sale_date'] ?></td>
                                <td><a href="sales_receipt.php?sale_id=<?= $row['sale_id'] ?>" class="btn btn-success btn-sm">View</a></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
            
            <div class="card shadow p-4 mb-4">
                <h3 class="text-center text-primary">💵 Submit New Payment</h3>

                <form method="POST" action="sales_billing.php" class="mt-4">
                    <input type="hidden" name="form_type" value="payment_insert"> <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prescription ID</label>
                            <input type="number" name="prescription_id" class="form-control" required min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount (RM)</label>
                            <input type="number" step="0.01" name="total_amount" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mt-2">Submit Payment</button>
                </form>
            </div>

            <div class="card shadow p-4">
                <h3 class="text-center text-primary mb-4">Payment Records</h3>

                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Prescription ID</th>
                            <th>Total Amount</th>
                            <th>Date</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($payments as $row) { ?>
                        <tr>
                            <td><?= $row['payment_id'] ?></td>
                            <td><?= $row['prescription_id'] ?></td>
                            <td>RM <?= number_format($row['total_amount'], 2) ?></td>
                            <td><?= $row['payment_date'] ?></td>
                            <td>
                                <a href="invoice.php?payment_id=<?= $row['payment_id'] ?>" 
                                   class="btn btn-success btn-sm">Generate Invoice</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>