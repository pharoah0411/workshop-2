<?php 
require_once 'connection.php'; 

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$med = null;
$error = '';

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

try {
    // 1. Try MySQL 2
    // FIX: Changed table name to MEDICINE (All Caps)
    if (!$med && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    }
    // 2. Try Postgres
    if (!$med && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
    // 3. Try SQL Server
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
} catch (Exception $e) {
    $error = 'Failed to load details: ' . htmlspecialchars($e->getMessage());
}

// Date Formatting
$expiryDate = 'N/A';
if ($med && !empty($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) $expiryDate = $med['EXPIRY_DATE']->format('M j, Y');
    else $expiryDate = date('M j, Y', strtotime($med['EXPIRY_DATE']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medicine Details</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box }
        body { font-family:'Segoe UI',Tahoma,Arial; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px }
        .container { max-width:900px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12) }
        .header { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:#fff; padding:20px }
        .content { padding:20px }
        dl { max-width:600px }
        dt { font-weight:700; margin-top:12px; color:#555; }
        dd { margin-left:0; margin-bottom:8px; font-size:1.1em; color:#000; }
        a.back { display:inline-block; margin-top:20px; color:#0066ff; text-decoration:none; font-weight:bold; }
        .btn-edit { background:#667eea; color:white; padding:8px 15px; border-radius:5px; text-decoration:none; margin-right:10px; }
        .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🔎 Medicine Details</h1></header>
        <div class="content">
            <?php if ($med): ?>
                <dl>
                    <dt>ID</dt><dd><?php echo htmlspecialchars($med['MEDICINE_ID']); ?></dd>
                    <dt>Name</dt><dd><?php echo htmlspecialchars($med['NAME']); ?></dd>
                    <dt>Category</dt><dd><?php echo htmlspecialchars($med['CATEGORY_TYPE'] ?? 'N/A'); ?></dd>
                    <dt>Stock</dt><dd><?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']); ?></dd>
                    <dt>Expiry</dt><dd><?php echo htmlspecialchars($expiryDate); ?></dd>
                    <dt>Price</dt><dd>$<?php echo number_format((float)($med['UNIT_PRICE'] ?? 0), 2); ?></dd>
                    <dt>Supplier</dt><dd><?php echo htmlspecialchars($med['SUPPLIER_NAME'] ?? 'N/A'); ?></dd>
                </dl>
                <div style="margin-top:20px;">
                    <a class="btn-edit" href="edit_medicine.php?id=<?php echo urlencode($med['MEDICINE_ID']); ?>">✏️ Edit Medicine</a>
                    <a class="back" href="medDirectory.php">← Back to Directory</a>
                </div>
            <?php else: ?>
                <p>Medicine not found.</p>
                <?php if($error) echo "<p class='error'>$error</p>"; ?>
                <a class="back" href="medDirectory.php">Back to Directory</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>