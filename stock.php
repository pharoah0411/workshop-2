<?php 
require_once 'connection.php'; 

$low_stock_meds = [];
$MIN_STOCK = 50;

try {
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK FROM MEDICINE";

    // 1. MySQL #2
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $result = $mysql_conn2->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) $low_stock_meds[] = array_change_key_case($row, CASE_UPPER);
        }
    }
    // 2. Postgres
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->query($sql);
        if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $low_stock_meds[] = array_change_key_case($row, CASE_UPPER);
    }
    // 3. SQL Server
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $low_stock_meds[] = array_change_key_case($r, CASE_UPPER);
        }
    } elseif (isset($conn) && $conn !== false) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $low_stock_meds[] = array_change_key_case($row, CASE_UPPER);
    }

    // Filter for low stock
    $low_stock_meds = array_filter($low_stock_meds, function($m) use ($MIN_STOCK) {
        return (int)($m['QUANTITY_IN_STOCK'] ?? 0) <= $MIN_STOCK;
    });

} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Alerts</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box }
        body { font-family:'Segoe UI',sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px }
        .container { max-width:1100px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12) }
        .header { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; padding:20px }
        .content { padding:20px }
        .medicine-card { background:#fff; border:1px solid #eaeaea; border-radius:10px; padding:14px; margin-bottom:10px; }
        .low { background:#fff3e0; padding:5px 10px; border-radius:6px; color:#e65100; font-weight:700; display:inline-block; }
        .critical { background:#ffcccc; padding:5px 10px; border-radius:6px; color:#c62828; font-weight:700; display:inline-block; }
        a.back { display:inline-block; margin-top:12px; color:#0066ff; text-decoration:none; font-weight:bold; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>⚠️ Low Stock Alerts</h1></header>
        <div class="content">
            <?php if (empty($low_stock_meds)): ?>
                <p>No low stock alerts.</p>
            <?php else: ?>
                <?php foreach ($low_stock_meds as $m): 
                    $stock = (int)$m['QUANTITY_IN_STOCK'];
                    $class = ($stock==0) ? 'critical' : 'low';
                    $text = ($stock==0) ? 'Out of Stock' : "Low Stock: $stock";
                ?>
                <div class="medicine-card">
                    <h3><?php echo htmlspecialchars($m['NAME']); ?></h3>
                    <p>ID: <?php echo htmlspecialchars($m['MEDICINE_ID']); ?></p>
                    <div class="<?php echo $class; ?>"><?php echo $text; ?></div>
                    <div style="margin-top:10px"><a href="edit_medicine.php?id=<?php echo urlencode($m['MEDICINE_ID']); ?>">Edit</a></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a class="back" href="medDirectory.php">← Back to Directory</a>
        </div>
    </div>
</body>
</html>