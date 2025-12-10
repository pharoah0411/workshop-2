<?php
// new_sale.php

require_once "connection.php";

if (!$pg_conn) {
    die("❌ PostgreSQL Connection Failed");
}

$message = "";

// 1. Fetch available products (e.g., medicine) for the selection dropdown
try {
    $products_stmt = $pg_conn->query("
        SELECT product_id, name, price, stock_quantity 
        FROM products 
        ORDER BY name
    ");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching products: " . $e->getMessage();
    $products = [];
}

// 2. Process Sale Submission (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patient_name'])) {
    
    // Get main sale details
    $patient_name = filter_input(INPUT_POST, 'patient_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $total_amount = filter_input(INPUT_POST, 'final_total', FILTER_VALIDATE_FLOAT);
    
    // Get line items array (JSON string from JavaScript, then decoded)
    $items_json = $_POST['items_data'] ?? '[]';
    $items = json_decode($items_json, true);

    if (empty($patient_name) || $total_amount === false || $total_amount <= 0 || empty($items)) {
        $message = "Error: Invalid patient name, total amount, or no items selected.";
    } else {
        // Start Transaction
        $pg_conn->beginTransaction();
        
        try {
            // A. Insert into 'sales' table
            $sale_stmt = $pg_conn->prepare("
                INSERT INTO sales (patient_name, total_amount, sale_date)
                VALUES (:patient_name, :total_amount, NOW())
                RETURNING sale_id;
            ");
            $sale_stmt->execute([
                ":patient_name" => $patient_name,
                ":total_amount" => $total_amount
            ]);
            $sale_id = $sale_stmt->fetchColumn(); // Get the new sale_id

            // B. Insert line items into 'sale_items' table and update inventory
            $item_stmt = $pg_conn->prepare("
                INSERT INTO sale_items (sale_id, product_id, medicine_name, quantity, price, subtotal)
                VALUES (:sale_id, :product_id, :medicine_name, :quantity, :price, :subtotal)
            ");
            $stock_update_stmt = $pg_conn->prepare("
                UPDATE products SET stock_quantity = stock_quantity - :qty WHERE product_id = :pid
            ");

            foreach ($items as $item) {
                // Insert item details
                $item_stmt->execute([
                    ":sale_id" => $sale_id,
                    ":product_id" => $item['product_id'],
                    ":medicine_name" => $item['name'],
                    ":quantity" => $item['qty'],
                    ":price" => $item['price'],
                    ":subtotal" => $item['subtotal']
                ]);

                // Update product inventory
                $stock_update_stmt->execute([
                    ":qty" => $item['qty'],
                    ":pid" => $item['product_id']
                ]);
            }

            // Commit Transaction
            $pg_conn->commit();
            $message = "✅ Sale recorded successfully! Sale ID: " . $sale_id;

        } catch (PDOException $e) {
            // Rollback on error
            $pg_conn->rollBack();
            $message = "Error recording sale: " . $e->getMessage();
        }
    }
}

// Load UI
include "new_sale.html";
?>