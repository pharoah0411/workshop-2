<?php
require_once 'connection.php';
require_once 'audit.php';

$error = '';

// --- Handle POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $expiry = isset($_POST['expiryDate']) && $_POST['expiryDate'] !== '' ? $_POST['expiryDate'] : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
    
    // NEW: Get the selected target database
    $target_source = $_POST['source'] ?? 'MySQL'; 

    if ($name === '') {
        $error = "Medicine Name is required.";
    } else {
        
        // 1. MySQL #2 INSERT
        if (($target_source === 'All' || $target_source === 'MySQL') && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
            try {
                $stmt = $mysql_conn2->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisds", $name, $category, $stock, $expiry, $price, $supplier);
                $stmt->execute();
            } catch (Exception $e) { /* Ignore */ }
        }

        // 2. PostgreSQL INSERT
        if (($target_source === 'All' || $target_source === 'Postgres') && isset($pg_conn) && $pg_conn instanceof PDO) {
            try {
                $stmt = $pg_conn->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:name, :category, :stock, :expiry, :price, :supplier)");
                $stmt->execute([':name'=>$name, ':category'=>$category, ':stock'=>$stock, ':expiry'=>$expiry, ':price'=>$price, ':supplier'=>$supplier]);
            } catch (Exception $e) { /* Ignore */ }
        }

        // 3. SQL Server INSERT
        if ($target_source === 'All' || $target_source === 'SQLServer') {
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:name, :category, :stock, :expiry, :price, :supplier)");
                    $stmt->execute([':name'=>$name, ':category'=>$category, ':stock'=>$stock, ':expiry'=>$expiry, ':price'=>$price, ':supplier'=>$supplier]);
                } elseif (isset($conn) && $conn !== false) {
                    $sql = "INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [$name, $category, $stock, $expiry, $price, $supplier];
                    sqlsrv_query($conn, $sql, $params);
                }
            } catch (Exception $e) { /* Ignore */ }
          } 
            // --- NEW AUDIT LOG CODE START ---
            // Identify which connection to use for logging the audit
            $activeConn = $pdo ?? $mysql_conn2 ?? $pg_conn;
            if ($activeConn) {
              logAudit($activeConn, 'ADD', 'Inventory', "Added medicine: $name to $target_source database");
            }
            // --- NEW AUDIT LOG CODE END ---
            
        header('Location: medDirectory.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Medicine</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
    .content { padding: 24px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
    .form-group input, .form-group select { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
    .form-actions { display:flex; gap:10px; margin-top:16px; }
    .btn { padding:10px 16px; border-radius:8px; border:none; cursor:pointer; color:white; font-weight:bold; }
    .btn-primary { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); }
    .btn-secondary { background:#999; text-decoration:none; display:inline-block; text-align:center; }
    .error-message { color: red; margin-bottom: 15px; font-weight: bold; }
  </style>
</head>
<body>
  <div class="container">
    <header class="header"><h1>🏥 Add Medicine</h1></header>
    <div class="content">
      <?php if ($error): ?>
        <p class="error-message">Error: <?php echo $error; ?></p>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
            <label>Save to Database:</label>
            <select name="source">
                <option value="MySQL">MySQL Only</option>
                <option value="Postgres">Postgres Only</option>
                <option value="SQLServer">SQL Server Only</option>
                <option value="All">All Databases</option>
            </select>
        </div>

        <div class="form-group"><label>Medicine Name</label><input name="name" required></div>
        <div class="form-group"><label>Category</label><input name="category"></div>
        <div class="form-group"><label>Stock Quantity</label><input name="stock" type="number" min="0"></div>
        <div class="form-group"><label>Expiry Date</label><input name="expiryDate" type="date"></div>
        <div class="form-group"><label>Price per Unit</label><input name="price" type="number" step="0.01"></div>
        <div class="form-group"><label>Supplier Name</label><input name="supplier"></div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Add Medicine</button>
          <a class="btn btn-secondary" href="medDirectory.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>