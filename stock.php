<?php 
require_once 'connection.php'; 

$low_stock_meds = [];
$MIN_STOCK_THRESHOLD = 50; // Hardcoded minimum stock for check
$error = '';

try {
    // FIX: Use MEDICINE_ID and all-caps column names
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK FROM MEDICINE";
    $rows = [];

    if (isset($pdo) && $pdo !== null) {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn !== null) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
    } else {
        $error = 'No database connection available.';
    }

    // Filter results in PHP for low stock
    foreach ($rows as $m) {
        $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
        
        // Check if stock is low (using hardcoded threshold)
        if ($stock <= $MIN_STOCK_THRESHOLD) {
            // Inject the threshold for display purposes
            $m['minStock'] = $MIN_STOCK_THRESHOLD; 
            $low_stock_meds[] = $m;
        }
    }

} catch (Exception $e) {
    $error = 'Failed to load stock data: ' . htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Alerts</title>
    <style>
        /* Reused design */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Arial; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);min-height:100vh;padding:20px}
        .container{max-width:1100px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)}
        .header{background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);color:#fff;padding:20px}
        .content{padding:20px}
        .medicines-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
        .medicine-card{background:#fff;border:1px solid #eaeaea;border-radius:10px;padding:14px}
        .medicine-name{font-weight:700}
        .small{font-size:0.9rem;color:#555}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
        .low{background:#fff3e0;padding:8px;border-radius:6px;color:#e65100;margin-top:8px;font-weight:700}
        .critical{background:#ffcccc;padding:8px;border-radius:6px;color:#c62828;margin-top:8px;font-weight:700}
        .error-message { color: red; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>⚠️ Low Stock / Out of Stock</h1></header>
        <div class="content">
            <?php if (!empty($error)): ?>
                <p class="error-message">Error: <?php echo $error; ?></p>
            <?php endif; ?>
            <div id="list" class="medicines-list">
                <?php if (empty($low_stock_meds)): ?>
                    <p class="small">No low-stock medicines found (Threshold: <?php echo $MIN_STOCK_THRESHOLD; ?> units).</p>
                <?php else: ?>
                    <?php foreach ($low_stock_meds as $m): 
                        $id = htmlspecialchars($m['MEDICINE_ID']);
                        $name = htmlspecialchars($m['NAME']);
                        $category = htmlspecialchars($m['CATEGORY_TYPE'] ?? '');
                        $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
                        $minStock = (int)($m['minStock'] ?? $MIN_STOCK_THRESHOLD);
                        $statusClass = ($stock <= 0) ? 'critical' : 'low';
                        $statusText = ($stock <= 0) ? 'Out of stock' : "Low stock: {$stock} units";
                    ?>
                        <div class="medicine-card">
                            <div class="medicine-name"><?php echo $name; ?></div>
                            <div class="small">ID: <?php echo $id; ?> • <?php echo $category; ?></div>
                            <div style="margin-top:8px">Min Stock: <?php echo $minStock; ?></div>
                            <div class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></div>
                            <div style="margin-top:10px"><a href="edit_medicine.php?id=<?php echo urlencode($id); ?>">Edit</a></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <a class="back" href="medDirectory.php">← Back to Directory</a>
        </div>
    </div>
</body>
</html>