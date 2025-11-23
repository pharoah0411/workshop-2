<?php
require_once 'connection.php';

// --- Handle POST Request for Adding Medicine ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate input using ALL-CAPS column names
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $minStock = isset($_POST['minStock']) ? intval($_POST['minStock']) : 0; // Note: minStock is not a DB column but collected for future use/consistency
    $expiry = isset($_POST['expiryDate']) && $_POST['expiryDate'] !== '' ? $_POST['expiryDate'] : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null; // Assuming a supplier field might exist/be needed

    if ($name === '') {
        // Simple client-side validation is better, but this handles required fields server-side
        $error = "Medicine Name is required.";
    } else {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:name, :category, :stock, :expiry, :unitPrice, :supplier)");
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':stock' => $stock,
                    ':expiry' => $expiry,
                    ':unitPrice' => $price,
                    ':supplier' => $supplier
                ]);
            } elseif (isset($conn)) {
                $sql = "INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$name, $category, $stock, $expiry, $price, $supplier];
                $res = sqlsrv_query($conn, $sql, $params);
                if ($res === false) {
                    throw new Exception('Insert failed: ' . print_r(sqlsrv_errors(), true));
                }
            } else {
                $error = 'No database connection available.';
            }
        } catch (Exception $e) {
            $error = 'Failed to add medicine: ' . htmlspecialchars($e->getMessage());
        }

        if (!isset($error)) {
            // Redirect back to the directory on success
            header('Location: medDirectory.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Medicine</title>
  <style>
    /* Reused design from medDirectory */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
    .header-content h1 { font-size: 1.6em; }
    .content { padding: 24px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
    .form-group input, .form-group select { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
    .form-actions { display:flex; gap:10px; margin-top:16px; }
    .btn { padding:10px 16px; border-radius:8px; border:none; cursor:pointer; }
    .btn-primary { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:#fff; }
    .btn-secondary { background:#e0e0e0; }
    a.back { display:inline-block; margin-top:12px; color:#0066ff; }
    .error-message { color: red; margin-bottom: 15px; font-weight: bold; }
  </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="header-content">
        <h1>🏥 Add Medicine</h1>
      </div>
    </header>

    <div class="content">
      <?php if (isset($error)): ?>
        <p class="error-message">Error: <?php echo $error; ?></p>
      <?php endif; ?>

      <form id="form" method="POST" action="add_medicine.php">
        <div class="form-group">
          <label for="name">Medicine Name</label>
          <input id="name" name="name" required value="<?php echo htmlspecialchars($name ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="category">Category (Type)</label>
          <input id="category" name="category" value="<?php echo htmlspecialchars($category ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="stock">Stock Quantity</label>
          <input id="stock" name="stock" type="number" min="0" value="<?php echo htmlspecialchars($stock ?? 0); ?>">
        </div>
        <div class="form-group">
          <label for="minStock">Minimum Stock Level</label>
          <input id="minStock" name="minStock" type="number" min="0" value="<?php echo htmlspecialchars($minStock ?? 0); ?>">
        </div>
        <div class="form-group">
          <label for="expiryDate">Expiry Date</label>
          <input id="expiryDate" name="expiryDate" type="date" value="<?php echo htmlspecialchars($expiry ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="price">Price per Unit</label>
          <input id="price" name="price" type="number" step="0.01" value="<?php echo htmlspecialchars($price ?? 0.00); ?>">
        </div>
        <div class="form-group">
          <label for="supplier">Supplier Name</label>
          <input id="supplier" name="supplier" value="<?php echo htmlspecialchars($supplier ?? ''); ?>">
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Add Medicine</button>
          <a class="btn btn-secondary" href="medDirectory.php">Cancel</a>
        </div>
      </form>

      <a class="back" href="medDirectory.php">← Back to Directory</a>
    </div>
  </div>
</body>
</html>