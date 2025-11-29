<?php
// DB connection
$conn = pg_connect("host=localhost port=5432 dbname=Workshop user=postgres password=admin");

$payment_id = $_GET['payment_id'];

// Get payment details
$sql = "SELECT * FROM public.payment WHERE payment_id = $payment_id";
$result = pg_query($conn, $sql);
$pay = pg_fetch_assoc($result);

// If no data found
if (!$pay) {
    die("Invoice not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice #<?= $pay['payment_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background: #f8f9fa; }
        .invoice-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 0 10px #ccc;
        }
        .header-title {
            color: #0066cc;
            font-size: 28px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="invoice-box mt-5">
    <div class="text-center mb-4">
        <div class="header-title">Pharmacy Invoice</div>
        <div>Official Receipt</div>
    </div>

    <hr>

    <p><strong>Invoice ID:</strong> <?= $pay['payment_id'] ?></p>
    <p><strong>Prescription ID:</strong> <?= $pay['prescription_id'] ?></p>
    <p><strong>Total Amount:</strong> RM <?= $pay['total_amount'] ?></p>
    <p><strong>Payment Date:</strong> <?= $pay['payment_date'] ?></p>

    <hr>

    <p class="text-center text-muted">Thank you for your payment!</p>

    <div class="text-center mt-3">
        <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
        <a href="payment.php" class="btn btn-secondary">Back</a>
    </div>
</div>

</body>
</html>
