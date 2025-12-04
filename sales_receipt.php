<?php
require_once "connection.php";

$sale_id = $_GET['sale_id'];

$stmt = $pg_conn->prepare("SELECT * FROM sales WHERE sale_id = :sid");
$stmt->execute([":sid" => $sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

$itemStmt = $pg_conn->prepare("SELECT * FROM sale_items WHERE sale_id = :sid");
$itemStmt->execute([":sid" => $sale_id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

include "sales_receipt.html";
