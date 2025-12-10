<?php
require_once "connection.php"; // Loads $pg_conn

// Check PostgreSQL connection
if (!$pg_conn) {
    die("❌ PostgreSQL Connection Failed");
}

$message = "";

// ===================== INSERT PAYMENT =====================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prescription_id = $_POST['prescription_id'];
    $total_amount = $_POST['total_amount'];

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
        $message = "Error: " . $e->getMessage();
    }
}

// ===================== FETCH ALL PAYMENTS =====================
try {
    $result = $pg_conn->query("SELECT * FROM public.payment ORDER BY payment_id ASC");
    $payments = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching payments: " . $e->getMessage());
}

// Load UI
include "paymentpage.html";
