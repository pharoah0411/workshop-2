<?php
require_once "connection.php";

if (!$pg_conn) {
    die("❌ PostgreSQL Connection Failed");
}

$payment_id = $_GET['payment_id'];

try {
    $stmt = $pg_conn->prepare("SELECT * FROM public.payment WHERE payment_id = :pid");
    $stmt->execute([":pid" => $payment_id]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pay) {
        die("Invoice not found.");
    }

} catch (PDOException $e) {
    die("Error retrieving invoice: " . $e->getMessage());
}

include "invoice_view.html";

