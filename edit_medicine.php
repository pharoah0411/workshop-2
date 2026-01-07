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

    // 1. MySQL #2 Update
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
    if (!$med && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    }
    if (!$med && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
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

// Format Date for input[type="date"]
$expiryDate = '';
if ($med && !empty($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) $expiryDate = $med['EXPIRY_DATE']->format('Y-m-d');
    else $expiryDate = date('Y-m-d', strtotime($med['EXPIRY_DATE']));
}

// Function to handle dropdown selection
function isSelected($current, $target) {
    return (trim($current) === $target) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Medicine | Pharmacy System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
            background: #f0f2f5; 
            min-height: 100vh; 
            padding: 40px 20px;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }
        .header { 
            background: linear-gradient(135deg, #0052cc 0%, #007bff 100%); 
            color: white; 
            padding: 25px; 
            text-align: center; 
        }
        .header h1 { font-size: 1.5em; text-transform: uppercase; letter-spacing: 1px; }
        
        .content { padding: 30px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            color: #495057; 
            font-weight: 600; 
            margin-bottom: 8px; 
            font-size: 0.9em;
        }
        .form-control { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            font-size: 1em;
            background-color: #fff;
        }
        .form-control:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }

        /* Price Input Styling */
        .price-input-wrapper { position: relative; display: flex; align-items: center; }
        .currency-prefix { position: absolute; left: 12px; color: #6c757d; font-weight: bold; }
        .price-input-wrapper input { padding-left: 45px; }

        .form-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 30px; }
        .btn { 
            padding: 12px; border-radius: 8px; border: none; cursor: pointer; 
            font-weight: bold; text-decoration: none; text-align: center; font-size: 1em;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>✏️ Edit Medicine</h1></header>
        <div class="content">
            <?php if ($med): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Medicine ID (Locked)</label>
                    <input class="form-control" value="#<?php echo $id; ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Medicine Name</label>
                    <input name="name" class="form-control" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <?php $currCat = $med['CATEGORY_TYPE'] ?? ''; ?>
                    <select name="category" class="form-control">
                        <option value="">-- Select Category --</option>
                        <option value="Tablet" <?php echo isSelected($currCat, 'Tablet'); ?>>Tablet</option>
                        <option value="Capsule" <?php echo isSelected($currCat, 'Capsule'); ?>>Capsule</option>
                        <option value="Syrup" <?php echo isSelected($currCat, 'Syrup'); ?>>Syrup</option>
                        <option value="Ointment" <?php echo isSelected($currCat, 'Ointment'); ?>>Ointment / Cream</option>
                        <option value="Injection" <?php echo isSelected($currCat, 'Injection'); ?>>Injection</option>
                        <option value="Drops" <?php echo isSelected($currCat, 'Drops'); ?>>Drops (Eye/Ear)</option>
                        <option value="Inhaler" <?php echo isSelected($currCat, 'Inhaler'); ?>>Inhaler</option>
                        <option value="Other" <?php echo isSelected($currCat, 'Other'); ?>>Other</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input name="stock" type="number" class="form-control" min="0" value="<?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']??0); ?>">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input name="expiryDate" type="date" class="form-control" value="<?php echo $expiryDate; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Price per Unit</label>
                    <div class="price-input-wrapper">
                        <span class="currency-prefix">RM</span>
                        <input name="price" type="number" step="0.01" class="form-control" value="<?php echo htmlspecialchars($med['UNIT_PRICE']??0); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Supplier Name</label>
                    <input name="supplier" class="form-control" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>">
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Update Medicine</button>
                    <a class="btn btn-secondary" href="medDirectory.php">Cancel</a>
                </div>
            </form>
            <?php else: ?>
                <div style="text-align: center;">
                    <p style="color: #666; margin-bottom: 20px;">Medicine record not found.</p>
                    <a class="btn btn-primary" href="medDirectory.php">Return to Directory</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>