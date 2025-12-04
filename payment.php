<?php
// Database connection
$conn = pg_connect("host=localhost port=5432 dbname=Workshop user=postgres password=admin");

if (!$conn) {
    die("❌ Database Connection Failed: " . pg_last_error());
}

$message = "";

// Insert payment
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prescription_id = $_POST['prescription_id'];
    $total_amount = $_POST['total_amount'];

    $sql = "INSERT INTO public.payment (prescription_id, payment_date, total_amount)
            VALUES ($prescription_id, NOW(), $total_amount)";

    $result = pg_query($conn, $sql);

    if ($result) {
        $message = "Payment added successfully!";
    } else {
        $message = "Error: " . pg_last_error($conn);
    }
}

// Fetch all payments
$sql = "SELECT * FROM public.payment ORDER BY payment_id ASC";
$payments = pg_query($conn, $sql);

// Load HTML UI
include "payment.html";
