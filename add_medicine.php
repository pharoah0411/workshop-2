<?php
require_once 'connection.php';
require_once 'audit.php'; // Included for audit trail
session_start();

// 1. Determine if we are ADDING (id=0) or EDITING (id > 0)
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$source = $_REQUEST['source'] ?? ''; 
$med = null;
$isEdit = ($id > 0);

// Only redirect if it's an EDIT attempt but source is missing
if ($isEdit && empty($source)) {
    header('Location: medDirectory.php');
    exit;
}

// 2. Handle Form Submission (INSERT or UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetSource = $_POST['source']; // From the form
    $name = $_POST['name'];
    $category = $_POST['category'];
    $stock = intval($_POST['stock']);
    $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $price = floatval($_POST['price']);
    $supplier = $_POST['supplier'];
    $success = false;

    try {
        if ($isEdit) {
            // --- UPDATE LOGIC ---
            if ($targetSource === 'MySQL' && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?");
                $stmt->bind_param("ssisdsi", $name, $category, $stock, $expiry, $price, $supplier, $id);
                $success = $stmt->execute();
            } elseif ($targetSource === 'Postgres' && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
            } elseif ($targetSource === 'SQLServer' && isset($pdo)) {
                $stmt = $pdo->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
            }
            $action = "UPDATED";
        } else {
            // --- INSERT LOGIC ---
            if ($targetSource === 'MySQL' && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisds", $name, $category, $stock, $expiry, $price, $supplier);
                $success = $stmt->execute();
            } elseif ($targetSource === 'Postgres' && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:n, :c, :s, :e, :p, :sup)");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier]);
            } elseif ($targetSource === 'SQLServer' && isset($pdo)) {
                $stmt = $pdo->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:n, :c, :s, :e, :p, :sup)");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier]);
            }
            $action = "ADDED";
        }

        if ($success) {
            // Audit Log using Postgres connection
            if (isset($pg_conn)) {
                logAudit($pg_conn, $action, 'Medicine', "$action medicine '$name' in $targetSource database");
            }
            header('Location: medDirectory.php');
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 3. Fetch Data for Editing
if ($isEdit) {
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
}

$expiryDate = ($med && !empty($med['EXPIRY_DATE'])) ? date('Y-m-d', strtotime(is_object($med['EXPIRY_DATE']) ? $med['EXPIRY_DATE']->format('Y-m-d') : $med['EXPIRY_DATE'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEdit ? 'Edit' : 'Add'; ?> Medicine</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 40px; display: flex; justify-content: center; align-items: center; }
        .container { width: 100%; max-width: 600px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .header { background: #007bff; color: white; padding: 25px; text-align: center; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9em; color: #333; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        .price-wrapper { position: relative; }
        .price-wrapper::before { content: "RM"; position: absolute; left: 12px; top: 12px; font-weight: bold; color: #666; }
        .price-wrapper input { padding-left: 45px; }
        .btn-save { background: #007bff; color: white; width: 100%; padding: 15px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1em; transition: opacity 0.3s; }
        .btn-save:hover { opacity: 0.9; }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><?php echo $isEdit ? '✏️ Edit Medicine' : '💊 Add New Medicine'; ?></h1>
        </header>
        <div class="content">
            <?php if (isset($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Target Database</label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-control" value="<?php echo $source; ?>" readonly>
                        <input type="hidden" name="source" value="<?php echo $source; ?>">
                    <?php else: ?>
                        <select name="source" class="form-control" required>
                            <option value="">-- Select Database --</option>
                            <option value="MySQL">MySQL</option>
                            <option value="Postgres">PostgreSQL</option>
                            <option value="SQLServer">SQL Server</option>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Medicine Name</label>
                    <input name="name" class="form-control" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required placeholder="Enter medicine name">
                </div>

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

                <div class="form-group">
                    <label>Quantity in Stock</label>
                    <input name="stock" type="number" class="form-control" value="<?php echo $med['QUANTITY_IN_STOCK']??0; ?>" required>
                </div>

                <div class="form-group">
                    <label>Expiry Date</label>
                    <input name="expiryDate" type="date" class="form-control" value="<?php echo $expiryDate; ?>">
                </div>

                <div class="form-group">
                    <label>Unit Price</label>
                    <div class="price-wrapper">
                        <input name="price" type="number" step="0.01" class="form-control" value="<?php echo $med['UNIT_PRICE']??0; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Supplier Name</label>
                    <input name="supplier" class="form-control" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>" placeholder="Enter supplier name">
                </div>

                <button type="submit" class="btn-save">
                    <?php echo $isEdit ? 'Update Medicine' : 'Add to Inventory'; ?>
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="medDirectory.php" style="color: #666; text-decoration: none; font-size: 0.9em;">← Back to Directory</a>
            </div>
        </div>
    </div>
</body>
</html>