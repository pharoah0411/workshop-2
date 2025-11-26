<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pharmacy_db"; // CHANGE THIS

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<?php include "db_connect.php"; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Payment</title>
</head>
<body>

<h2>Add Payment</h2>

<form action="payment_process.php" method="POST">

    <label>Prescription ID:</label>
    <input type="number" name="prescription_id" required><br><br>

    <label>Total Amount (RM):</label>
    <input type="text" name="total_amount" required><br><br>

    <button type="submit">Submit Payment</button>
</form>

</body>
</html>

<?php
include "db_connect.php";

$prescription_id = $_POST['prescription_id'];
$total_amount = $_POST['total_amount'];

$sql = "INSERT INTO payment (prescription_id, payment_date, total_amount)
        VALUES ('$prescription_id', NOW(), '$total_amount')";

if ($conn->query($sql) === TRUE) {
    echo "Payment recorded successfully!<br>";
    echo "<a href='payment_list.php'>View Payments</a>";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>

<?php
include "db_connect.php";

$sql = "SELECT pay.payment_id, pay.total_amount, pay.payment_date,
               u.name AS patient_name
        FROM payment pay
        JOIN prescription pr ON pay.prescription_id = pr.prescription_id
        JOIN patient p ON pr.patient_id = p.patient_id
        JOIN users u ON p.user_id = u.user_id
        ORDER BY pay.payment_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment List</title>
</head>
<body>

<h2>Payment Records</h2>

<table border="1" cellpadding="10">
    <tr>
        <th>Payment ID</th>
        <th>Patient Name</th>
        <th>Total Amount</th>
        <th>Date</th>
        <th>View</th>
    </tr>

    <?php while($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?= $row['payment_id'] ?></td>
        <td><?= $row['patient_name'] ?></td>
        <td>RM <?= $row['total_amount'] ?></td>
        <td><?= $row['payment_date'] ?></td>
        <td><a href="payment_view.php?id=<?= $row['payment_id'] ?>">View</a></td>
    </tr>
    <?php } ?>

</table>

</body>
</html>


