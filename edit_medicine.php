<?php
require_once 'connection.php';

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$med = null;
$error = '';

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

// --- Handle POST Request for Updating Medicine ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect updated input
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
    $category = isset($_POST['category']) ? trim($_POST['category']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : null;
    $expiry = isset($_POST['expiryDate']) && $_POST['expiryDate'] !== '' ? $_POST['expiryDate'] : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
    $minStock = isset($_POST['minStock']) ? intval($_POST['minStock']) : null; // Not a DB column but included for UI consistency

    if ($name === null || $name === '') {
         $error = "Medicine Name is required.";
    } else {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $fields = [];
                $params = [':id' => $id];
                
                // Build the SET clause using ALL-CAPS column names
                if ($name !== null) { $fields[] = 'NAME = :name'; $params[':name'] = $name; }
                if ($category !== null) { $fields[] = 'CATEGORY_TYPE = :category'; $params[':category'] = $category; }
                if ($stock !== null) { $fields[] = 'QUANTITY_IN_STOCK = :stock'; $params[':stock'] = $stock; }
                // EXPIRY_DATE can be set to NULL if an empty string is passed
                $fields[] = 'EXPIRY_DATE = :expiry'; $params[':expiry'] = $expiry;
                if ($price !== null) { $fields[] = 'UNIT_PRICE = :price'; $params[':price'] = $price; }
                if ($supplier !== null) { $fields[] = 'SUPPLIER_NAME = :supplier'; $params[':supplier'] = $supplier; }
                // STOCK_PRICE is missing in form, but adding it for completeness if the logic supported it:
                // if ($stockPrice !== null) { $fields[] = 'STOCK_PRICE = :stockPrice'; $params[':stockPrice'] = $stockPrice; } 

                if (!empty($fields)) {
                    // FIX: Use MEDICINE_ID in WHERE clause
                    $sql = 'UPDATE MEDICINE SET ' . implode(', ', $fields) . ' WHERE MEDICINE_ID = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
            } elseif (isset($conn)) {
                // SQLSRV update logic uses positional parameters (?)
                $sets = [];
                $params = [];
                
                // Build the SET clause using ALL-CAPS column names
                if ($name !== null) { $sets[] = 'NAME = ?'; $params[] = $name; }
                if ($category !== null) { $sets[] = 'CATEGORY_TYPE = ?'; $params[] = $category; }
                if ($stock !== null) { $sets[] = 'QUANTITY_IN_STOCK = ?'; $params[] = $stock; }
                $sets[] = 'EXPIRY_DATE = ?'; $params[] = $expiry;
                if ($price !== null) { $sets[] = 'UNIT_PRICE = ?'; $params[] = $price; }
                if ($supplier !== null) { $sets[] = 'SUPPLIER_NAME = ?'; $params[] = $supplier; }

                if (!empty($sets)) {
                    // FIX: Use MEDICINE_ID in WHERE clause
                    $sql = 'UPDATE MEDICINE SET ' . implode(', ', $sets) . ' WHERE MEDICINE_ID = ?';
                    $params[] = $id;
                    $res = sqlsrv_query($conn, $sql, $params);
                    if ($res === false) throw new Exception('Update failed: ' . print_r(sqlsrv_errors(), true));
                }
            } else {
                $error = 'No database connection available.';
            }
        } catch (Exception $e) {
            $error = 'Failed to update medicine: ' . htmlspecialchars($e->getMessage());
        }

        if (!isset($error)) {
            // Redirect back to the directory on success
            header('Location: medDirectory.php');
            exit;
        }
    }
}

// --- Fetch Current Medicine Details for Form Population ---
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // FIX: Use MEDICINE_ID in SELECT
        $stmt = $pdo->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME, STOCK_PRICE FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $med = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        // FIX: Use MEDICINE_ID in SELECT
        $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME, STOCK_PRICE FROM MEDICINE WHERE MEDICINE_ID = ?";
        $params = [$id];
        $res = sqlsrv_query($conn, $sql, $params);
        if ($res !== false) {
            $med = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $error = 'Failed to load details: ' . htmlspecialchars($e->getMessage());
}

if (!$med && !isset($error)) {
    $error = 'Medicine not found.';
}

// Handle date format for form input and handle DateTime objects from SQLSRV
$expiryDate = '';
if (isset($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) {
        $expiryDate = $med['EXPIRY_DATE']->format('Y-m-d');
    } elseif (is_string($med['EXPIRY_DATE'])) {
        $expiryDate = date('Y-m-d', strtotime($med['EXPIRY_DATE']));
    }
}

// Fallback for minStock if not loaded from DB (using the default of 50)
$minStockDisplay = $minStock ?? 50; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Medicine</title>
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
        <h1>✏️ Edit Medicine</h1>
      </div>
    </header>

    <div class="content">
        <?php if (!empty($error)): ?>
            <p class="error-message">Error: <?php echo $error; ?></p>
        <?php elseif (!$med): ?>
            <p class="error-message">Medicine details could not be loaded.</p>
        <?php endif; ?>

      <form id="form" method="POST" action="edit_medicine.php?id=<?php echo urlencode($id); ?>">
        <div class="form-group">
          <label for="id">Medicine ID</label>
          <input id="id" name="id" disabled value="<?php echo htmlspecialchars($id); ?>">
        </div>
        <div class="form-group">
          <label for="name">Medicine Name</label>
          <input id="name" name="name" required value="<?php echo htmlspecialchars($med['NAME'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="category">Category</label>
          <input id="category" name="category" value="<?php echo htmlspecialchars($med['CATEGORY_TYPE'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="stock">Stock Quantity</label>
          <input id="stock" name="stock" type="number" min="0" value="<?php echo htmlspecialchars($med['QUANTITY_IN_STOCK'] ?? 0); ?>">
        </div>
        <div class="form-group">
          <label for="minStock">Minimum Stock Level</label>
          <input id="minStock" name="minStock" type="number" min="0" value="<?php echo htmlspecialchars($minStockDisplay); ?>">
        </div>
        <div class="form-group">
          <label for="expiryDate">Expiry Date</label>
          <input id="expiryDate" name="expiryDate" type="date" value="<?php echo htmlspecialchars($expiryDate); ?>">
        </div>
        <div class="form-group">
          <label for="price">Price per Unit</label>
          <input id="price" name="price" type="number" step="0.01" value="<?php echo htmlspecialchars($med['UNIT_PRICE'] ?? 0.00); ?>">
        </div>
        <div class="form-group">
          <label for="supplier">Supplier Name</label>
          <input id="supplier" name="supplier" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME'] ?? ''); ?>">
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn btn-secondary" href="medDirectory.php">Cancel</a>
        </div>
      </form>

      <a class="back" href="medDirectory.php">← Back to Directory</a>
    </div>
  </div>
</body>
</html>