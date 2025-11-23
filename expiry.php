<?php 
require_once 'connection.php'; 

$days_filter = isset($_GET['days']) ? intval($_GET['days']) : 30;
$expiring_meds = [];
$error = '';

$now_ts = time();
$expiring_limit_ts = strtotime("+{$days_filter} days");

try {
    // FIX: Use MEDICINE_ID and all-caps column names
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE FROM MEDICINE";
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

    // Filter results in PHP for expiring stock
    foreach ($rows as $m) {
        $expiry_date_val = $m['EXPIRY_DATE'] ?? null;
        if (!$expiry_date_val) continue;

        if ($expiry_date_val instanceof DateTime) {
            $expiry_ts = $expiry_date_val->getTimestamp();
            $expiry_date_str = $expiry_date_val->format('Y-m-d');
        } else {
            $expiry_ts = strtotime($expiry_date_val);
            $expiry_date_str = date('Y-m-d', $expiry_ts);
        }

        // Check if expiring (not expired, but within the limit)
        if ($expiry_ts > $now_ts && $expiry_ts <= $expiring_limit_ts) {
            $m['EXPIRY_DATE'] = $expiry_date_str;
            $expiring_meds[] = $m;
        }
    }

} catch (Exception $e) {
    $error = 'Failed to load expiry data: ' . htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expiring Medicines</title>
    <style>
        /* Reused design */
        * { margin:0; padding:0; box-sizing:border-box }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px }
        .container { max-width:1100px; margin:0 auto; background:white; border-radius:12px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12) }
        .header { background: linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; padding:20px }
        .header h1{font-size:1.6rem}
        .content { padding:20px }
        .controls { display:flex; gap:8px; align-items:center; margin-bottom:12px }
        input[type=number]{width:90px;padding:8px;border:1px solid #ddd;border-radius:8px}
        .medicines-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
        .medicine-card{background:#fff;border:1px solid #eaeaea;border-radius:10px;padding:14px}
        .medicine-name{font-weight:700}
        .small{font-size:0.9rem;color:#555}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
        .error-message { color: red; margin-bottom: 15px; font-weight: bold; }
        .btn { padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; font-weight: 500;}
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🕒 Expiring Soon</h1></header>
        <div class="content">
            <?php if (!empty($error)): ?>
                <p class="error-message">Error: <?php echo $error; ?></p>
            <?php endif; ?>

            <form method="GET" action="expiry.php" class="controls">
                <label>Show expiring within <input id="days" name="days" type="number" value="<?php echo htmlspecialchars($days_filter); ?>"></label>
                <button id="filter" class="btn" type="submit">Filter</button>
            </form>

            <div id="list" class="medicines-list">
                <?php if (empty($expiring_meds)): ?>
                    <p class="small">No medicines expiring within the next <?php echo $days_filter; ?> days.</p>
                <?php else: ?>
                    <?php foreach ($expiring_meds as $m): 
                        $id = htmlspecialchars($m['MEDICINE_ID']);
                        $name = htmlspecialchars($m['NAME']);
                        $category = htmlspecialchars($m['CATEGORY_TYPE'] ?? '');
                        $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
                        $expiryDate = htmlspecialchars($m['EXPIRY_DATE']);
                    ?>
                        <div class="medicine-card">
                            <div class="medicine-name"><?php echo $name; ?></div>
                            <div class="small">ID: <?php echo $id; ?> • <?php echo $category; ?></div>
                            <div style="margin-top:8px">Stock: <?php echo $stock; ?></div>
                            <div class="small">Expiry: <?php echo date('M j, Y', strtotime($expiryDate)); ?></div>
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