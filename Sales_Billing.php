<?php
require_once "connection.php";

if (!$pg_conn) {
    die("❌ PostgreSQL Connection Failed");
}

$message = "";

// ===================== A. PAYMENT INSERT LOGIC (from payment.php) =====================
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

// ===================== B. SALES DASHBOARD DATA FETCH (from sales_dashboard.php) =====================

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


// ===================== C. ALL PAYMENTS DATA FETCH (from payment.php) =====================
try {
    $result_payments = $pg_conn->query("SELECT * FROM public.payment ORDER BY payment_id DESC");
    $payments = $result_payments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Note: Don't die, just set payments to empty array if fetch fails
    error_log("Error fetching payments: " . $e->getMessage());
    $payments = []; 
}

// NOTE: No need to include a separate HTML file now.
?>