<?php
session_start();

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Pharmacist';
$username = $_SESSION['username'] ?? 'User';

require_once 'connection.php';

// --- Connection Status ---
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅ Connected" : "❌ Failed";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅ Connected" : "❌ Failed";
$status_sql = ((isset($pdo) && $pdo instanceof PDO) || (isset($conn) && $conn !== false)) ? "✅ Connected" : "❌ Failed";

$DAYS_EXPIRING_SOON = 30;

// --- 1. Handle Deletion (FIXED TABLE NAME) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id_to_delete = intval($_POST['id']);
    if ($id_to_delete > 0) {
        try {
            // MySQL 2 (Fixed: MEDICINE)
            if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $stmt = $mysql_conn2->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = ?");
                $stmt->bind_param("i", $id_to_delete);
                $stmt->execute();
            } 
            // PostgreSQL (Fixed: MEDICINE)
            if (isset($pg_conn) && $pg_conn instanceof PDO) {
                $stmt = $pg_conn->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = :id");
                $stmt->execute([':id' => $id_to_delete]);
            } 
            // SQL Server (Fixed: MEDICINE)
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = :id");
                $stmt->execute([':id' => $id_to_delete]);
            } elseif (isset($conn) && $conn !== false) {
                $sql = "DELETE FROM MEDICINE WHERE MEDICINE_ID = ?";
                sqlsrv_query($conn, $sql, [$id_to_delete]);
            }
        } catch (Exception $e) { /* Ignore errors */ }
    }
    header('Location: medDirectory.php');
    exit;
}

// Get input
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// --- 2. Fetch Medicines from ALL 3 Sources ---
$all_meds = [];
$sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";

try {
    // 1. MySQL #2
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $result = $mysql_conn2->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                if (!empty($r['EXPIRY_DATE'])) $r['EXPIRY_DATE'] = date('Y-m-d', strtotime($r['EXPIRY_DATE']));
                $r['minStock'] = 50; 
                $all_meds[] = $r;
            }
        }
    }
    // 2. PostgreSQL
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->query($sql);
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                if (!empty($r['EXPIRY_DATE'])) $r['EXPIRY_DATE'] = date('Y-m-d', strtotime($r['EXPIRY_DATE']));
                $r['minStock'] = 50;
                $all_meds[] = $r;
            }
        }
    }
    // 3. SQL Server
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $r = array_change_key_case($r, CASE_UPPER);
                if (!empty($r['EXPIRY_DATE'])) {
                    $d = $r['EXPIRY_DATE'];
                    $r['EXPIRY_DATE'] = ($d instanceof DateTime) ? $d->format('Y-m-d') : date('Y-m-d', strtotime($r['EXPIRY_DATE']));
                }
                $r['minStock'] = 50;
                $all_meds[] = $r;
            }
        }
    } elseif (isset($conn) && $conn !== false) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                 if (!empty($r['EXPIRY_DATE']) && $r['EXPIRY_DATE'] instanceof DateTime) {
                    $r['EXPIRY_DATE'] = $r['EXPIRY_DATE']->format('Y-m-d');
                }
                $r['minStock'] = 50;
                $all_meds[] = $r;
            }
        }
    }
} catch (Exception $e) {}

// --- 3. Filter & Stats ---
$totalMedicines = count($all_meds);
$lowStockCount = 0; $expiringCount = 0; $expiredCount = 0;
$now_ts = time();
$expiring_limit_ts = strtotime("+{$DAYS_EXPIRING_SOON} days");

foreach ($all_meds as &$m) {
    $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
    $minStock = 50; $m['minStock'] = $minStock;
    $expiryStr = $m['EXPIRY_DATE'] ?? null;
    $expiry = (!empty($expiryStr)) ? strtotime($expiryStr) : null;
    
    if ($stock <= $minStock) $lowStockCount++;
    if ($expiry) {
        if ($expiry < $now_ts) $expiredCount++;
        elseif ($expiry > $now_ts && $expiry <= $expiring_limit_ts) $expiringCount++;
    }
}
unset($m);

$meds_to_display = array_filter($all_meds, function($m) use ($search_query, $filter_type, $now_ts, $expiring_limit_ts) {
    if ($search_query !== '') {
        $q = strtolower($search_query);
        $name = strtolower($m['NAME'] ?? '');
        $id = strtolower(strval($m['MEDICINE_ID'] ?? ''));
        if (strpos($name, $q) === false && strpos($id, $q) === false) return false;
    }
    $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
    $expiry = !empty($m['EXPIRY_DATE']) ? strtotime($m['EXPIRY_DATE']) : null;
    switch ($filter_type) {
        case 'low-stock': return $stock <= $m['minStock'];
        case 'expiring': return $expiry && $expiry > $now_ts && $expiry <= $expiring_limit_ts;
        case 'expired': return $expiry && $expiry < $now_ts;
        default: return true;
    }
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medicine Inventory</title>
    <style>
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background: #1976d2; color: white; margin-bottom: 20px; border-radius: 8px; }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; }
        .btn-logout { padding: 6px 12px; border: 1px solid white; border-radius: 6px; background: transparent; color: white; cursor: pointer; text-decoration: none; font-size: 0.9em; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 40px 20px; text-align: center; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .status-bar { display: flex; justify-content: space-around; background: #333; color: white; padding: 10px; margin-bottom: 20px; border-radius: 8px; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 30px; background: #f8f9fa; }
        .stat-card { display: flex; align-items: center; gap: 15px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 3em; padding: 10px; border-radius: 50%; }
        .stat-number { font-size: 2em; font-weight: bold; color: #0066ff; }
        .controls { display: flex; gap: 15px; padding: 20px 30px; background: white; align-items: center; }
        .medicines-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; padding: 30px; }
        .medicine-card { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .medicine-name { font-size: 1.3em; font-weight: bold; }
        .stock-indicator, .expiry-status { padding: 8px; border-radius: 6px; text-align: center; margin: 10px 0; font-weight: 600; }
        .stock-good, .expiry-good { background: #c8e6c9; color: #2e7d32; }
        .stock-low, .expiry-warning { background: #ffe0b2; color: #e65100; }
        .stock-critical, .expiry-expired { background: #ffcccc; color: #c62828; }
        .action-btn { padding: 8px 12px; border-radius: 6px; text-decoration: none; display: inline-block; cursor: pointer; border: none; font-size: 0.9em; flex: 1; text-align: center; }
        .edit-btn { background: #667eea; color: white; }
        .delete-btn { background: #ff6b6b; color: white; }
        .medicine-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn-primary { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; }
        input, select { padding: 10px; border-radius: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="stock.php">⚠️ Stock Alerts</a>
            <a href="expiry.php">🔴 Expiry Alerts</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>
    <div class="container">
        <header class="header">
            <h1>🏥 Medicine Inventory</h1>
            <p>Monitor stock levels and expiry dates</p>
        </header>
        <div class="status-bar">
            <div>MySQL 2: <?php echo $status_mysql2; ?></div>
            <div>Postgres: <?php echo $status_pg; ?></div>
            <div>SQL Server: <?php echo $status_sql; ?></div>
        </div>
        
        <div class="dashboard-stats">
            <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd">📦</div><div><h3>Total</h3><p class="stat-number"><?php echo $totalMedicines; ?></p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fff3e0">⚠️</div><div><h3>Low Stock</h3><p class="stat-number"><?php echo $lowStockCount; ?></p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#ffebee">🔴</div><div><h3>Expiring</h3><p class="stat-number"><?php echo $expiringCount; ?></p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fce4ec">❌</div><div><h3>Expired</h3><p class="stat-number"><?php echo $expiredCount; ?></p></div></div>
        </div>

        <div class="controls">
            <a href="add_medicine.php" class="btn-primary">+ Add Medicine</a>
            <form method="GET" style="display:flex; gap:10px; flex:1">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex:1" onchange="this.form.submit()">
                <select name="filter" onchange="this.form.submit()">
                    <option value="all" <?php if($filter_type=='all') echo 'selected'; ?>>All</option>
                    <option value="low-stock" <?php if($filter_type=='low-stock') echo 'selected'; ?>>Low Stock</option>
                    <option value="expiring" <?php if($filter_type=='expiring') echo 'selected'; ?>>Expiring</option>
                    <option value="expired" <?php if($filter_type=='expired') echo 'selected'; ?>>Expired</option>
                </select>
            </form>
        </div>

        <div class="medicines-list">
            <?php if(empty($meds_to_display)): ?>
                <div style="grid-column:1/-1; text-align:center; padding:40px; color:#999;"><h3>No medicines found</h3></div>
            <?php else: ?>
                <?php foreach($meds_to_display as $m): 
                    $stock=(int)$m['QUANTITY_IN_STOCK']; 
                    $price=number_format((float)$m['UNIT_PRICE'], 2);
                    
                    $stockClass = ($stock==0) ? 'stock-critical' : (($stock<=50) ? 'stock-low' : 'stock-good');
                    $stockText = ($stock==0) ? 'Out of Stock' : (($stock<=50) ? 'Low Stock' : 'In Stock');
                    
                    $expiryClass='expiry-good'; $expiryText='—';
                    if(!empty($m['EXPIRY_DATE'])){
                        $ed=strtotime($m['EXPIRY_DATE']);
                        if($ed<$now_ts){ $expiryClass='expiry-expired'; $expiryText='Expired'; }
                        elseif($ed<=$expiring_limit_ts){ $expiryClass='expiry-warning'; $expiryText='Expiring Soon'; }
                        else{ $expiryText=date('M j, Y', $ed); }
                    }
                ?>
                <div class="medicine-card">
                    <div class="medicine-name"><?php echo htmlspecialchars($m['NAME']); ?></div>
                    <div style="color:#999; font-size:0.9em">ID: <?php echo htmlspecialchars($m['MEDICINE_ID']); ?></div>
                    <div style="margin:10px 0; border-top:1px solid #eee; padding-top:10px">
                        <div style="display:flex; justify-content:space-between"><span>Stock:</span> <strong><?php echo $stock; ?></strong></div>
                        <div style="display:flex; justify-content:space-between"><span>Price:</span> <strong>$<?php echo $price; ?></strong></div>
                    </div>
                    <div class="stock-indicator <?php echo $stockClass; ?>"><?php echo $stockText; ?></div>
                    <div class="expiry-status <?php echo $expiryClass; ?>"><?php echo $expiryText; ?></div>
                    <div class="medicine-actions">
                        <a href="edit_medicine.php?id=<?php echo urlencode($m['MEDICINE_ID']); ?>" class="action-btn edit-btn">✏️ Edit</a>
                        <a href="medicine_details.php?id=<?php echo urlencode($m['MEDICINE_ID']); ?>" class="action-btn" style="background:#4caf50; color:white">ℹ️ Info</a>
                        <form method="POST" style="flex:1" onsubmit="return confirm('Delete this medicine?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($m['MEDICINE_ID']); ?>">
                            <button type="submit" class="action-btn delete-btn" style="width:100%">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>