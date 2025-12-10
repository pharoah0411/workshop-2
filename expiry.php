<?php 
require_once 'connection.php'; 

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$expiring_meds = [];
$now_ts = time();
$limit_ts = strtotime("+$days days");

try {
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE FROM MEDICINE";
    $all = [];

    // Fetch from all 3 DBs (using same logic as above)
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $res = $mysql_conn2->query($sql);
        if($res) while($r=$res->fetch_assoc()) $all[] = array_change_key_case($r, CASE_UPPER);
    }
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->query($sql);
        if($stmt) while($r=$stmt->fetch(PDO::FETCH_ASSOC)) $all[] = array_change_key_case($r, CASE_UPPER);
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        if($stmt) foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $all[] = array_change_key_case($r, CASE_UPPER);
    } elseif (isset($conn) && $conn !== false) {
        $stmt = sqlsrv_query($conn, $sql);
        if($stmt) while($r=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $all[] = array_change_key_case($r, CASE_UPPER);
    }

    // Filter
    foreach ($all as $m) {
        if (empty($m['EXPIRY_DATE'])) continue;
        $d_str = ($m['EXPIRY_DATE'] instanceof DateTime) ? $m['EXPIRY_DATE']->format('Y-m-d') : $m['EXPIRY_DATE'];
        $ts = strtotime($d_str);
        
        if ($ts > $now_ts && $ts <= $limit_ts) {
            $m['EXPIRY_DATE'] = $d_str;
            $expiring_meds[] = $m;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expiry Alerts</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box }
        body { font-family:'Segoe UI',sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px }
        .container { max-width:1100px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12) }
        .header { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; padding:20px }
        .content { padding:20px }
        .medicine-card { background:#fff; border:1px solid #eaeaea; border-radius:10px; padding:14px; margin-bottom:10px; }
        .warn { background:#ffebee; padding:5px 10px; border-radius:6px; color:#c62828; font-weight:700; display:inline-block; }
        a.back { display:inline-block; margin-top:12px; color:#0066ff; text-decoration:none; font-weight:bold; }
        input, button { padding:8px; border-radius:5px; border:1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🔴 Expiry Alerts</h1></header>
        <div class="content">
            <form style="margin-bottom:20px">
                <label>Show expiring within days:</label>
                <input type="number" name="days" value="<?php echo $days; ?>">
                <button type="submit">Filter</button>
            </form>

            <?php if (empty($expiring_meds)): ?>
                <p>No medicines expiring soon.</p>
            <?php else: ?>
                <?php foreach ($expiring_meds as $m): ?>
                <div class="medicine-card">
                    <h3><?php echo htmlspecialchars($m['NAME']); ?></h3>
                    <p>Expiry: <?php echo htmlspecialchars($m['EXPIRY_DATE']); ?></p>
                    <div class="warn">Expiring Soon</div>
                    <div style="margin-top:10px"><a href="edit_medicine.php?id=<?php echo urlencode($m['MEDICINE_ID']); ?>">Edit</a></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a class="back" href="medDirectory.php">← Back to Directory</a>
        </div>
    </div>
</body>
</html>