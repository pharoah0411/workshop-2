<?php
require_once 'connection.php';

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$source = $_REQUEST['source'] ?? ''; // Added Source tracking
$med = null;

if ($id <= 0 || empty($source)) {
    header('Location: medDirectory.php');
    exit;
}

// --- Targeted Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $stock = intval($_POST['stock']);
    $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $price = floatval($_POST['price']);
    $supplier = $_POST['supplier'];

    if ($source === 'MySQL' && isset($mysql_conn2)) {
        $stmt = $mysql_conn2->prepare("UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?");
        $stmt->bind_param("ssisdsi", $name, $category, $stock, $expiry, $price, $supplier, $id);
        $stmt->execute();
    } elseif ($source === 'Postgres' && isset($pg_conn)) {
        $stmt = $pg_conn->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
        $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
    } elseif ($source === 'SQLServer' && isset($pdo)) {
        $stmt = $pdo->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
        $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
    }

    header('Location: medDirectory.php');
    exit;
}

// --- Targeted Fetch ---
if ($source === 'MySQL' && isset($mysql_conn2)) {
    $stmt = $mysql_conn2->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
} elseif ($source === 'Postgres' && isset($pg_conn)) {
    $stmt = $pg_conn->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $med = array_change_key_case($row, CASE_UPPER);
} elseif ($source === 'SQLServer' && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $med = array_change_key_case($row, CASE_UPPER);
}

$expiryDate = ($med && !empty($med['EXPIRY_DATE'])) ? date('Y-m-d', strtotime(is_object($med['EXPIRY_DATE']) ? $med['EXPIRY_DATE']->format('Y-m-d') : $med['EXPIRY_DATE'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Medicine</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .header { background: #007bff; color: white; padding: 25px; text-align: center; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9em; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .price-wrapper { position: relative; }
        .price-wrapper::before { content: "RM"; position: absolute; left: 12px; top: 12px; font-weight: bold; color: #666; }
        .price-wrapper input { padding-left: 45px; }
        .btn-save { background: #007bff; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>✏️ Edit Medicine (<?php echo $source; ?>)</h1></header>
        <div class="content">
            <form method="POST">
                <input type="hidden" name="source" value="<?php echo $source; ?>">
                <div class="form-group"><label>Medicine Name</label><input name="name" class="form-control" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required></div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <?php $c = $med['CATEGORY_TYPE']??''; ?>
                        <option value="Tablet" <?php if($c=='Tablet') echo 'selected';?>>Tablet</option>
                        <option value="Capsule" <?php if($c=='Capsule') echo 'selected';?>>Capsule</option>
                        <option value="Syrup" <?php if($c=='Syrup') echo 'selected';?>>Syrup</option>
                        <option value="Other" <?php if($c=='Other') echo 'selected';?>>Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Stock</label><input name="stock" type="number" class="form-control" value="<?php echo $med['QUANTITY_IN_STOCK']??0; ?>"></div>
                <div class="form-group"><label>Expiry Date</label><input name="expiryDate" type="date" class="form-control" value="<?php echo $expiryDate; ?>"></div>
                <div class="form-group">
                    <label>Price</label>
                    <div class="price-wrapper"><input name="price" type="number" step="0.01" class="form-control" value="<?php echo $med['UNIT_PRICE']??0; ?>"></div>
                </div>
                <div class="form-group"><label>Supplier</label><input name="supplier" class="form-control" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>"></div>
                <button type="submit" class="btn-save">Update Medicine</button>
            </form>
        </div>
    </div>
</body>
</html>