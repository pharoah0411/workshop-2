<?php 
include "db_connect.php";

// Insert into PostgreSQL
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prescription_id = $_POST['prescription_id'];
    $total_amount = $_POST['total_amount'];

    $sql = "INSERT INTO public.payment (prescription_id, payment_date, total_amount)
            VALUES ($prescription_id, NOW(), $total_amount)";

    $result = pg_query($conn, $sql);

    if ($result) {
        echo "<p>Payment added successfully!</p>";
    } else {
        echo "<p>Error: " . pg_last_error($conn) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<body>

<h2>Add Payment</h2>

<form method="POST">
    Prescription ID: <br>
    <input type="number" name="prescription_id" required><br><br>

    Total Amount (RM): <br>
    <input type="text" name="total_amount" required><br><br>

    <button type="submit">Add Payment</button>
</form>

<hr>

<h2>Payment Records</h2>

<?php
$sql = "SELECT * FROM public.payment ORDER BY payment_id ASC";
$result = pg_query($conn, $sql);

echo "<table border='1' cellpadding='6'>
        <tr>
            <th>ID</th>
            <th>Prescription ID</th>
            <th>Total Amount</th>
            <th>Date</th>
        </tr>";

while ($row = pg_fetch_assoc($result)) {
    echo "<tr>
            <td>".$row['payment_id']."</td>
            <td>".$row['prescription_id']."</td>
            <td>RM ".$row['total_amount']."</td>
            <td>".$row['payment_date']."</td>
          </tr>";
}

echo "</table>";
?>

</body>
</html>
