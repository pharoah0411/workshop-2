<?php
require_once 'connection.php';

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$med = null;
$error = '';

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

// --- Handle Update (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? null;
    $category = $_POST['category'] ?? null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : null;
    $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $supplier = $_POST['supplier'] ?? null;

    // 1. MySQL #2 Update (Fixed Table Name)
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        try {
            $stmt = $mysql_conn2->prepare("UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?");
            $stmt->bind_param("ssisdsi", $name, $category, $stock, $expiry, $price, $supplier, $id);
            $stmt->execute();
        } catch(Exception $e){}
    }
    
    // 2. PostgreSQL Update
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        try {
            $stmt = $pg_conn->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
            $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
        } catch(Exception $e){}
    }

    // 3. SQL Server Update
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
            $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
        } elseif (isset($conn) && $conn !== false) {
            $sql = "UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?";
            sqlsrv_query($conn, $sql, [$name, $category, $stock, $expiry, $price, $supplier, $id]);
        }
    } catch(Exception $e){}

    header('Location: medDirectory.php');
    exit;
}

// --- Fetch Current Details (GET) ---
try {
    // Try MySQL 2 (Fixed Table Name)
    if (!$med && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    }
    // Try Postgres
    if (!$med && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
    // Try SQL Server
    if (!$med) {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $med = array_change_key_case($row, CASE_UPPER);
        } elseif (isset($conn) && $conn !== false) {
            $res = sqlsrv_query($conn, "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?", [$id]);
            if ($res && $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) $med = array_change_key_case($row, CASE_UPPER);
        }
    }
} catch (Exception $e) { $error = $e->getMessage(); }

// Format Date
$expiryDate = '';
if ($med && !empty($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) $expiryDate = $med['EXPIRY_DATE']->format('Y-m-d');
    else $expiryDate = date('Y-m-d', strtotime($med['EXPIRY_DATE']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Medicine</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
    .content { padding: 24px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
    .form-group input { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
    .form-actions { display:flex; gap:10px; margin-top:16px; }
    .btn { padding:10px 16px; border-radius:8px; border:none; cursor:pointer; color:white; font-weight:bold; }
    .btn-primary { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); }
    .btn-secondary { background:#999; text-decoration:none; display:inline-block; text-align:center; }
  </style>
</head>
<body>
  <div class="container">
    <header class="header"><h1>✏️ Edit Medicine</h1></header>
    <div class="content">
      <?php if ($med): ?>
      <form method="POST">
        <div class="form-group"><label>ID (Read-only)</label><input value="<?php echo $id; ?>" disabled></div>
        <div class="form-group"><label>Name</label><input name="name" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required></div>
        <div class="form-group"><label>Category</label><input name="category" value="<?php echo htmlspecialchars($med['CATEGORY_TYPE']??''); ?>"></div>
        <div class="form-group"><label>Stock</label><input name="stock" type="number" value="<?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']??0); ?>"></div>
        <div class="form-group"><label>Expiry</label><input name="expiryDate" type="date" value="<?php echo $expiryDate; ?>"></div>
        <div class="form-group"><label>Price</label><input name="price" type="number" step="0.01" value="<?php echo htmlspecialchars($med['UNIT_PRICE']??0); ?>"></div>
        <div class="form-group"><label>Supplier</label><input name="supplier" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>"></div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn btn-secondary" href="medDirectory.php">Cancel</a>
        </div>
      </form>
      <?php else: ?>
        <p>Medicine not found.</p>
        <a href="medDirectory.php">Back</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>