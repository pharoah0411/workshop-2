<?php
// Ensure the connection script is loaded
require_once "connection.php";

if (!$pg_conn) {
    // If connection fails, stop execution and display an error
    die("PostgreSQL connection failed.");
}

// Today Sales: Calculates sum of sales amount for the current date
$today_sales_stmt = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE(sale_date) = CURRENT_DATE
");
$today = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);

// Month Sales: Calculates sum of sales amount for the current month
$month_sales_stmt = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE_TRUNC('month', sale_date) = DATE_TRUNC('month', CURRENT_DATE)
");
$month = $month_sales_stmt->fetch(PDO::FETCH_ASSOC);

// Recent 10 Sales: Fetches the 10 most recent sales transactions
$recent_sales_stmt = $pg_conn->query("
    SELECT * FROM sales ORDER BY sale_date DESC LIMIT 10
");
$recent = $recent_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the HTML file to display the data.
// The variables $today, $month, and $recent will be available inside this file.
include "sales_dashboard.html";

// Ensure the PHP block is properly closed
?>