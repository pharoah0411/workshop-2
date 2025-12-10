<?php
// new_sale.php

// Ensure PDO connection setup is included
require_once "connection.php";

if (!$pg_conn) {
    // If connection fails, set a dummy value and message to prevent fatal errors
    $message = "❌ PostgreSQL Connection Failed. Please check connection.php.";
    $products = [];
}

$message = "";

// 1. Fetch available products (e.g., medicine) for the selection dropdown
if ($pg_conn) { // Only attempt DB operations if connection is successful
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8"> 
    <title>New Sale Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Styling to match the Sales & Billing Dashboard page */
        body { 
            background-color: #0066ff; /* Solid blue background */
            min-height: 100vh;
        } 
        
        .container {
            max-width: 900px; /* Slightly smaller container for a form page */
            margin: 0 auto;
            background: white;
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            padding-bottom: 20px; 
            margin-top: 50px !important; 
            margin-bottom: 50px !important; 
        }

        /* Styling for the requested header */
        .header-box {
            background: linear-gradient(to right, #0056b3, #0099ff);
            color: white; 
            padding: 25px 30px; 
            margin: 20px; 
            margin-bottom: 20px; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25); 
            position: relative; 
            top: -10px;
        }
        
        .header-box h2 {
            color: white !important;
            font-weight: 700; 
            font-size: 2.0rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            margin: 0;
            text-align: center; 
        }
        
        .card { 
            border-radius: 12px; 
            border: none; 
        }
        .btn-success { background-color: #28a745; border-color: #28a745; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .table tfoot td { font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">

    <div class="header-box">
        <h2 style="margin: 0;">🛒 New Sales Transaction</h2>
    </div>

    <div style="padding: 0 20px 20px 20px;">
    
        <?php if (!empty($message)) { ?>
            <div class="alert alert-info text-center">
                <?= $message ?>
            </div>
        <?php } ?>

        <form method="POST" action="new_sale.php" id="saleForm">
            
            <div class="card shadow p-4 mb-4">
                <h4>Customer Details</h4>
                <div class="mb-3">
                    <label for="patient_name" class="form-label">Patient Name</label>
                    <input type="text" name="patient_name" id="patient_name" class="form-control" required>
                </div>
            </div>

            <div class="card shadow p-4 mb-4">
                <h4>Add Items</h4>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="product_select" class="form-label">Select Item</label>
                        <select id="product_select" class="form-select">
                            <option value="">-- Choose Medicine --</option>
                            <?php foreach ($products as $product) { ?>
                                <option 
                                    value="<?= $product['product_id'] ?>" 
                                    data-price="<?= $product['price'] ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-stock="<?= $product['stock_quantity'] ?>"
                                >
                                    <?= htmlspecialchars($product['name']) ?> (RM <?= number_format($product['price'], 2) ?> | Stock: <?= $product['stock_quantity'] ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" id="quantity" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-success w-100" onclick="addItem()">Add</button>
                    </div>
                </div>
            </div>

            <div class="card shadow p-4">
                <h4>Sale Items</h4>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price (RM)</th>
                            <th class="text-end">Subtotal (RM)</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items_tbody">
                        </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>GRAND TOTAL:</strong></td>
                            <td class="text-end" id="grand_total_cell">RM 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                
                <input type="hidden" name="final_total" id="final_total" value="0.00">
                <input type="hidden" name="items_data" id="items_data">

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">Finalize Sale & Checkout</button>
                <a href="sales_billing.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
            </div>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Global array to hold line items data
    let currentSaleItems = [];

    /**
     * Adds an item to the sale items list.
     */
    function addItem() {
        const select = document.getElementById('product_select');
        const qtyInput = document.getElementById('quantity');

        const productId = select.value;
        const selectedOption = select.options[select.selectedIndex];
        
        // Basic Validation
        if (!productId || qtyInput.value < 1) {
            alert("Please select a valid item and quantity.");
            return;
        }

        const qty = parseInt(qtyInput.value);
        const price = parseFloat(selectedOption.dataset.price);
        const name = selectedOption.dataset.name;
        const subtotal = qty * price;
        const stock = parseInt(selectedOption.dataset.stock);

        // Stock Validation
        if (qty > stock) {
            alert(`Error: Only ${stock} units of ${name} in stock.`);
            return;
        }

        // Check if item already exists in the list
        const existingItemIndex = currentSaleItems.findIndex(item => item.product_id == productId);

        if (existingItemIndex > -1) {
            // If exists, update quantity
            const newQty = currentSaleItems[existingItemIndex].qty + qty;
            if (newQty > stock) {
                alert(`Error: Adding ${qty} exceeds the total stock of ${stock}.`);
                return;
            }
            currentSaleItems[existingItemIndex].qty = newQty;
            currentSaleItems[existingItemIndex].subtotal = newQty * price;

        } else {
            // If new, add item
            currentSaleItems.push({
                product_id: productId,
                name: name,
                qty: qty,
                price: price,
                subtotal: subtotal
            });
        }
        
        renderItems();
        select.value = ""; // Reset dropdown
        qtyInput.value = 1; // Reset quantity
    }

    /**
     * Removes an item from the sale items list by product ID.
     */
    function removeItem(productId) {
        currentSaleItems = currentSaleItems.filter(item => item.product_id != productId);
        renderItems();
    }

    /**
     * Renders the item list table and updates the totals.
     */
    function renderItems() {
        const tbody = document.getElementById('items_tbody');
        tbody.innerHTML = '';
        let grandTotal = 0;

        currentSaleItems.forEach(item => {
            const row = tbody.insertRow();
            
            // Item Name
            row.insertCell().textContent = item.name;
            
            // Quantity
            const qtyCell = row.insertCell();
            qtyCell.textContent = item.qty;
            qtyCell.classList.add('text-end');
            
            // Price
            const priceCell = row.insertCell();
            priceCell.textContent = item.price.toFixed(2);
            priceCell.classList.add('text-end');
            
            // Subtotal
            const subtotalCell = row.insertCell();
            subtotalCell.textContent = item.subtotal.toFixed(2);
            subtotalCell.classList.add('text-end');
            
            // Action Button
            const actionCell = row.insertCell();
            actionCell.classList.add('text-center');
            const button = document.createElement('button');
            button.className = 'btn btn-danger btn-sm';
            button.textContent = 'X';
            button.onclick = () => removeItem(item.product_id);
            actionCell.appendChild(button);

            grandTotal += item.subtotal;
        });

        // Update Grand Total in Footer
        document.getElementById('grand_total_cell').textContent = `RM ${grandTotal.toFixed(2)}`;
        
        // Update Hidden Inputs for PHP Submission
        document.getElementById('final_total').value = grandTotal.toFixed(2);
        document.getElementById('items_data').value = JSON.stringify(currentSaleItems);
    }

    // Optional: Pre-submission check
    document.getElementById('saleForm').onsubmit = function() {
        if (currentSaleItems.length === 0) {
            alert("Please add at least one item to the sale before submitting.");
            return false;
        }
        return true;
    };
</script>

</body>
</html>