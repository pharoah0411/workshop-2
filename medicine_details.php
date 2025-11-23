<?php 
require_once 'connection.php'; 

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$med = null;
$error = '';
$minStockDisplay = 50; // Default min stock for display

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

try {
    // FIX: Use MEDICINE_ID and all-caps column names
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME, STOCK_PRICE FROM MEDICINE WHERE MEDICINE_ID = ?";
    
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $med = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        $params = [$id];
        $res = sqlsrv_query($conn, $sql, $params);
        if ($res !== false) {
            $med = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        }
    } else {
        $error = 'No database connection available.';
    }
} catch (Exception $e) {
    $error = 'Failed to load details: ' . htmlspecialchars($e->getMessage());
}

if ($med) {
    // Handle date format for display
    $expiryDate = '';
    if (isset($med['EXPIRY_DATE'])) {
        if ($med['EXPIRY_DATE'] instanceof DateTime) {
            $expiryDate = $med['EXPIRY_DATE']->format('M j, Y');
        } elseif (is_string($med['EXPIRY_DATE'])) {
            $expiryDate = date('M j, Y', strtotime($med['EXPIRY_DATE']));
        }
    }
} else if (!isset($error)) {
    $error = 'Medicine not found.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medicine Details</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Arial;background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);min-height:100vh;padding:20px}
        .container{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)}
        .header{background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);color:#fff;padding:20px}
        .content{padding:20px}
        dl{max-width:600px}
        dt{font-weight:700;margin-top:8px}
        dd{margin-left:0;margin-bottom:8px}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
        .error-message { color: red; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🔎 Medicine Details</h1></header>
        <div class="content">
            <?php if (!empty($error)): ?>
                <p class="error-message">Error: <?php echo $error; ?></p>
            <?php elseif ($med): ?>
                <div id="details">
                    <dl>
                        <dt>ID</dt><dd><?php echo htmlspecialchars($med['MEDICINE_ID']); ?></dd>
                        <dt>Name</dt><dd><?php echo htmlspecialchars($med['NAME']); ?></dd>
                        <dt>Category</dt><dd><?php echo htmlspecialchars($med['CATEGORY_TYPE'] ?? 'N/A'); ?></dd>
                        <dt>Stock</dt><dd><?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']); ?></dd>
                        <dt>Min Stock (Simulated)</dt><dd><?php echo htmlspecialchars($minStockDisplay); ?></dd>
                        <dt>Expiry</dt><dd><?php echo htmlspecialchars($expiryDate ?: 'N/A'); ?></dd>
                        <dt>Price</dt><dd>$<?php echo number_format((float)($med['UNIT_PRICE'] ?? 0), 2); ?></dd>
                        <dt>Supplier</dt><dd><?php echo htmlspecialchars($med['SUPPLIER_NAME'] ?? 'N/A'); ?></dd>
                        <dt>Stock Price</dt><dd>$<?php echo number_format((float)($med['STOCK_PRICE'] ?? 0), 2); ?></dd>
                    </dl>
                </div>
                <p>
                    <a id="editLink" href="edit_medicine.php?id=<?php echo urlencode($med['MEDICINE_ID']); ?>">Edit</a> | 
                    <a class="back" href="medDirectory.php">Back to Directory</a>
                </p>
            <?php else: ?>
                <p class="error-message">Medicine details could not be loaded.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>