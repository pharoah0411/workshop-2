<?php 
// PostgreSQL connection
$conn = pg_connect("host=localhost port=5432 dbname=pharmacy_db user=postgres password=YOURPASSWORD");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// If form submitted → insert payment
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prescription_id = $_POST['prescription_id'];
    $total_amount = $_POST['total_amount'];

    $sql = "INSERT INTO payment (prescription_id, payment_date, total_amount)
            VALUES ($prescription_id, NOW(), $total_amount)";

    $result = pg_query($conn, $sql);

    if ($result) {
        $message = "Payment recorded successfully!";
    } else {
        $message = "Error: " . pg_last_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Page</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #e6f0ff;
        }
        .card {
            border-radius: 12px;
        }
        .btn-primary {
            background-color: #0066cc;
            border: none;
        }
        .btn-primary:hover {
            background-color: #004999;
        }
        .table thead {
            background-color: #0066cc;
            color: white;
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <!-- Message -->
    <?php if (!empty($message)) { ?>
        <div class="alert alert-info text-center">
            <?= $message ?>
        </div>
    <?php } ?>

    <!-- Add Payment Form -->
    <div class="card shadow p-4 mb-4">
        <h2 class="text-center text-primary">Add Payment</h2>

        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Prescription ID</label>
                <input type="number" name="prescription_id" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Total Amount (RM)</label>
                <input type="text" name="total_amount" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Submit Payment</button>
        </form>
    </div>

    <!-- Payment List -->
    <div class="card shadow p-4">
        <h2 class="text-center text-primary mb-4">Payment Records</h2>

        <?php
        $sql = "SELECT pay.payment_id, pay.total_amount, pay.payment_date,
                       u.name AS patient_name
                FROM payment pay
                JOIN prescription pr ON pay.prescription_id = pr.prescription_id
                JOIN patient p ON pr.patient_id = p.patient_id
                JOIN users u ON p.user_id = u.user_id
                ORDER BY pay.payment_date DESC";

        $result = pg_query($conn, $sql);
        ?>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Patient Name</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
            <?php while($row = pg_fetch_assoc($result)) { ?>
                <tr>
                    <td><?= $row['payment_id'] ?></td>
                    <td><?= $row['patient_name'] ?></td>
                    <td>RM <?= $row['total_amount'] ?></td>
                    <td><?= $row['payment_date'] ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

    </div>
</div>

</body>
</html>

