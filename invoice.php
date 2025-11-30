<?php
$conn = pg_connect("host=localhost port=5432 dbname=Workshop user=postgres password=admin");

$payment_id = $_GET['payment_id'];

$sql = "SELECT * FROM public.payment WHERE payment_id = $payment_id";
$result = pg_query($conn, $sql);
$pay = pg_fetch_assoc($result);

if (!$pay) {
    die("Invoice not found.");
}

include "invoice_view.html";

