<?php
// medDirectory.php
require_once "session_check.php"; 
require_once 'connection.php';
require_once 'audit.php';

// Check if user is NOT logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Pharmacist';
$username = $_SESSION['username'] ?? 'User';

// --- Connection Status ---
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅ Connected" : "❌ Failed";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅ Connected" : "❌ Failed";
$status_sql = ((isset($pdo) && $pdo instanceof PDO) || (isset($conn) && $conn !== false)) ? "✅ Connected" : "❌ Failed";

$DAYS_EXPIRING_SOON = 30;

// --- 1. Handle Targeted Deletion (FIXED WITH CONSTRAINTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id_to_delete = intval($_POST['id']);
    $db_source = $_POST['db_source'] ?? ''; // Identify which DB this medicine came from
    $deleted = false;
    $errorMessage = "";
    
    if ($id_to_delete > 0) {
        try {
            /* --- TARGET: MySQL --- */
            if ($db_source === 'MYSQL' && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                // Delete related details first
                $stmt1 = $mysql_conn2->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE MEDICINE_ID = ?");
                $stmt1->bind_param("i", $id_to_delete);
                $stmt1->execute();
                
                $stmt2 = $mysql_conn2->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = ?");
                $stmt2->bind_param("i", $id_to_delete);
                $stmt2->execute();
                if ($stmt2->affected_rows > 0) $deleted = true;
            } 

            /* --- TARGET: PostgreSQL --- */
            elseif ($db_source === 'POSTGRES' && isset($pg_conn) && $pg_conn instanceof PDO) {
                $stmt1 = $pg_conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE MEDICINE_ID = :id");
                $stmt1->execute([':id' => $id_to_delete]);

                $stmt2 = $pg_conn->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = :id");
                $stmt2->execute([':id' => $id_to_delete]);
                if ($stmt2->rowCount() > 0) $deleted = true;
            } 

            /* --- TARGET: SQL Server --- */
            elseif ($db_source === 'SQLSRV' && isset($pdo) && $pdo instanceof PDO) {
                $stmt1 = $pdo->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE MEDICINE_ID = :id");
                $stmt1->execute([':id' => $id_to_delete]);

                $stmt2 = $pdo->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = :id");
                $stmt2->execute([':id' => $id_to_delete]);
                if ($stmt2->rowCount() > 0) $deleted = true;
            }

        } catch (Exception $e) {
            error_log("Delete Error: " . $e->getMessage());
            $errorMessage = "Could not delete: " . $e->getMessage();
        }
    }

    // Audit Trail: Log which specific database the deletion occurred in
    if ($deleted && isset($pg_conn) && $pg_conn instanceof PDO) {
        logAudit($pg_conn, 'DELETE', 'Medicine', "Deleted ID: $id_to_delete from source: $db_source");
    }

    header('Location: medDirectory.php' . ($errorMessage ? '?error=' . urlencode($errorMessage) : ''));
    exit;
}

// Get search/filter input
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// --- 2. Fetch Medicines with DB_SOURCE tags ---
$all_meds = [];
$sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";

try {
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $result = $mysql_conn2->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = 'MYSQL'; 
                $all_meds[] = $r;
            }
        }
    }
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->query($sql);
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = 'POSTGRES';
                $all_meds[] = $r;
            }
        }
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $r = array_change_key_case($r, CASE_UPPER);
                $r['DB_SOURCE'] = 'SQLSRV';
                $all_meds[] = $r;
            }
        }
    }
} catch (Exception $e) { error_log($e->getMessage()); }

// --- 3. Filter & Stats Logic ---
$totalMedicines = count($all_meds);
$lowStockCount = 0; $expiringCount = 0; $expiredCount = 0;
$now_ts = time();
$expiring_limit_ts = strtotime("+{$DAYS_EXPIRING_SOON} days");

foreach ($all_meds as &$m) {
    $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
    $minStock = 50; 
    $m['minStock'] = $minStock;
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
        if (strpos(strtolower($m['NAME'] ?? ''), $q) === false && strpos(strtolower($m['MEDICINE_ID'] ?? ''), $q) === false) return false;
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background: #1976d2; color: white; margin-bottom: 20px; border-radius: 8px; }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; }
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 40px 20px; text-align: center; }
        .status-bar { display: flex; justify-content: space-around; background: #333; color: white; padding: 10px; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 30px; background: #f8f9fa; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; display: flex; align-items: center; gap: 15px; }
        .stat-number { font-size: 2em; font-weight: bold; color: #0066ff; }
        .controls { display: flex; gap: 15px; padding: 20px 30px; background: white; align-items: center; }
        .btn-primary { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; }
        .medicines-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; padding: 30px; }
        .medicine-card { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: relative; }
        .medicine-name { font-size: 1.3em; font-weight: bold; }
        .db-tag { position: absolute; top: 10px; right: 10px; font-size: 0.7em; padding: 2px 6px; border-radius: 4px; background: #eee; color: #666; font-weight: bold; }
        .stock-good { background: #c8e6c9; color: #2e7d32; padding: 8px; border-radius: 6px; text-align: center; margin: 10px 0; }
        .stock-low { background: #ffe0b2; color: #e65100; padding: 8px; border-radius: 6px; text-align: center; margin: 10px 0; }
        .action-btn { padding: 8px 12px; border-radius: 6px; text-decoration: none; color: white; text-align: center; flex: 1; border: none; cursor: pointer; }
        .dropdown-menu { display: none; position: absolute; top: 100%; left: 0; background: white; min-width: 220px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; border: 1px solid #ddd; overflow: hidden; margin-top: 5px; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; color: #333; text-decoration: none; border-bottom: 1px solid #eee; }
        .dropdown-item:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="logout.php" style="border:1px solid white; padding:5px 10px; border-radius:5px;">Log Out</a>
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
            <div class="stat-card"><span>📦</span><div><h3>Total</h3><p class="stat-number"><?php echo count($all_meds); ?></p></div></div>
            <div class="stat-card"><span>⚠️</span><div><h3>Low Stock</h3><p class="stat-number"><?php echo $lowStockCount; ?></p></div></div>
            <div class="stat-card"><span>🔴</span><div><h3>Expiring</h3><p class="stat-number"><?php echo $expiringCount; ?></p></div></div>
            <div class="stat-card"><span>❌</span><div><h3>Expired</h3><p class="stat-number"><?php echo $expiredCount; ?></p></div></div>
        </div>

        <div class="controls">
            <a href="add_medicine.php" class="btn-primary">+ Add Medicine</a>

            <div style="position: relative; display: inline-block;">
                <button type="button" class="btn-primary" style="background: #28a745;" onclick="toggleExportMenu(event)">
                    📤 Export Inventory ▼
                </button>
                <div id="exportMenu" class="dropdown-menu">
                    <a href="export_medicine.php?type=excel" class="dropdown-item">📊 Excel Spreadsheet</a>
                    <a href="export_medicine.php?type=pdf" target="_blank" class="dropdown-item">📄 PDF Document</a>
                    <a href="export_medicine.php?type=print" target="_blank" class="dropdown-item">🖨️ Print List</a>
                </div>
            </div>

            <form method="GET" style="display:flex; gap:10px; flex:1">
                <input type="text" name="search" placeholder="Search ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #ddd;">
                <select name="filter" onchange="this.form.submit()" style="padding:10px; border-radius:8px; border:1px solid #ddd;">
                    <option value="all" <?php echo $filter_type=='all'?'selected':''; ?>>All</option>
                    <option value="low-stock" <?php echo $filter_type=='low-stock'?'selected':''; ?>>Low Stock</option>
                </select>
            </form>
        </div>

        <div class="medicines-list">
            <?php foreach($meds_to_display as $m): 
                $stock = (int)$m['QUANTITY_IN_STOCK'];
                $price = number_format((float)$m['UNIT_PRICE'], 2);
            ?>
                <div class="medicine-card">
                    <div class="db-tag"><?php echo $m['DB_SOURCE']; ?></div>
                    <div class="medicine-name"><?php echo htmlspecialchars($m['NAME']); ?></div>
                    <div style="color:#999; font-size:0.9em; margin-bottom:10px;">ID: <?php echo $m['MEDICINE_ID']; ?></div>
                    
                    <div style="display:flex; justify-content:space-between"><span>Stock:</span> <strong><?php echo $stock; ?></strong></div>
                    <div style="display:flex; justify-content:space-between"><span>Price:</span> <strong>RM <?php echo $price; ?></strong></div>
                    
                    <div class="<?php echo ($stock <= 50) ? 'stock-low' : 'stock-good'; ?>">
                        <?php echo ($stock <= 50) ? '⚠️ Low Stock' : '✅ In Stock'; ?>
                    </div>
                    
                    <?php 
                        $expiryStr = $m['EXPIRY_DATE'] ?? null;
                        $expiryTs = (!empty($expiryStr)) ? strtotime($expiryStr) : null;
                        $statusText = !empty($expiryStr) ? date('M d, Y', $expiryTs) : 'No Expiry';
                        $expiryStyle = "background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;";

                        if ($expiryTs) {
                            if ($expiryTs < $now_ts) {
                                $expiryStyle = "background: #ffebee; color: #c62828; border: 1px solid #ef9a9a;";
                                $statusText = "⚠️ EXPIRED: " . $statusText;
                            } elseif ($expiryTs <= $expiring_limit_ts) {
                                $expiryStyle = "background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80;";
                                $statusText = "🕒 EXPIRING: " . $statusText;
                            }
                        } else {
                            $expiryStyle = "background: #f5f5f5; color: #757575; border: 1px solid #e0e0e0;";
                        }
                    ?>

                    <div style="<?php echo $expiryStyle; ?> padding:8px; border-radius:6px; text-align:center; font-weight:600; font-size: 0.9em; margin-top: 10px;">
                        <?php echo $statusText; ?>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:15px;">
                        <a href="edit_medicine.php?id=<?php echo $m['MEDICINE_ID']; ?>" class="action-btn" style="background:#667eea;">✏️ Edit</a>
                        <a href="medicine_details.php?id=<?php echo $m['MEDICINE_ID']; ?>" class="action-btn" style="background:#4caf50;">ℹ️ Info</a>
                        
                        <form method="POST" style="flex:1" onsubmit="return confirm('Delete this record from <?php echo $m['DB_SOURCE']; ?>?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $m['MEDICINE_ID']; ?>">
                            <input type="hidden" name="db_source" value="<?php echo $m['DB_SOURCE']; ?>"> 
                            <button type="submit" class="action-btn" style="background:#ff6b6b; border:none; width:100%;">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function toggleExportMenu(event) {
        event.stopPropagation();
        const menu = document.getElementById('exportMenu');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    window.onclick = function(event) {
        if (!event.target.matches('.btn-primary')) {
            const menu = document.getElementById('exportMenu');
            if(menu) menu.style.display = 'none';
        }
    }
    </script>
</body>
</html>