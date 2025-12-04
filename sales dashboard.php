<?php
require_once "connection.php";

if (!$pg_conn) {
    die("PostgreSQL connection failed.");
}

// Today Sales
$today = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE(sale_date) = CURRENT_DATE
")->fetch(PDO::FETCH_ASSOC);

// Month Sales
$month = $pg_conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total 
    FROM sales 
    WHERE DATE_TRUNC('month', sale_date) = DATE_TRUNC('month', CURRENT_DATE)
")->fetch(PDO::FETCH_ASSOC);

// Recent 10 Sales
$recent = $pg_conn->query("
    SELECT * FROM sales ORDER BY sale_date DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

include "sales_dashboard.html";
html>
