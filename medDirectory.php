<?php
session_start();

// Authentication Check: Redirects non-logged-in users
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// User details for display
$userRole = $_SESSION['role'] ?? 'Pharmacist';
$username = $_SESSION['username'] ?? 'User';

require_once 'connection.php';

// Define constants for calculations
$DAYS_EXPIRING_SOON = 30;

// --- 1. Handle Deletion POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id_to_delete = intval($_POST['id']);
    if ($id_to_delete > 0) {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                // FIX: Use MEDICINE_ID
                $stmt = $pdo->prepare('DELETE FROM Medicine WHERE MEDICINE_ID = :id');
                $stmt->execute([':id' => $id_to_delete]);
            } elseif (isset($conn)) {
                // FIX: Use MEDICINE_ID
                $sql = 'DELETE FROM Medicine WHERE MEDICINE_ID = ?';
                sqlsrv_query($conn, $sql, [$id_to_delete]);
            }
        } catch (Exception $e) {
            // Error handling placeholder
        }
    }
    // Redirect to prevent form resubmission and clear the POST state
    header('Location: medDirectory.php');
    exit;
}

// Get input from query parameters for search/filter
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// --- 2. Fetch All Medicines from DB ---
$all_meds = [];
$connection_error_message = ''; // Variable for error reporting

// Inject default minStock value (50) as the DB table likely doesn't have this column
$min_stock_defaults = [ 
    'default' => 50 
];

try {
    // FIX: Use MEDICINE_ID in the SELECT statement
    $sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, SUPPLIER_NAME, UNIT_PRICE, STOCK_PRICE FROM Medicine";
    
    if (isset($pdo) && $pdo !== null) {
        // PDO fetch logic
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!empty($r['EXPIRY_DATE'])) {
                $d = $r['EXPIRY_DATE'];
                if ($d instanceof DateTime) $r['EXPIRY_DATE'] = $d->format('Y-m-d');
                else $r['EXPIRY_DATE'] = date('Y-m-d', strtotime($r['EXPIRY_DATE']));
            }
            $id_key = $r['MEDICINE_ID'] ?? null;
            $r['minStock'] = $min_stock_defaults[(string)$id_key] ?? $min_stock_defaults['default'];
            $all_meds[] = $r;
        }
    } elseif (isset($conn) && $conn !== null) {
        // SQLSRV fetch logic with explicit error checking
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            $sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
            $error_msg = 'SQL Query Failed.';
            if ($sqlsrv_errors) {
                // Display the specific SQL Server error
                $error_msg .= ' Details: ' . print_r($sqlsrv_errors, true);
            }
            $connection_error_message = '❌ DATABASE QUERY FAILED: ' . $error_msg;
        } else {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($r['EXPIRY_DATE']) && $r['EXPIRY_DATE'] instanceof DateTime) {
                    $r['EXPIRY_DATE'] = $r['EXPIRY_DATE']->format('Y-m-d');
                }
                $id_key = $r['MEDICINE_ID'] ?? null;
                $r['minStock'] = $min_stock_defaults[(string)$id_key] ?? $min_stock_defaults['default'];
                $all_meds[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
    } else {
        $connection_error_message = '⚠️ DATABASE CONNECTION FAILED. Check credentials in connection.php.';
    }
} catch (Exception $e) {
    $all_meds = [];
    $connection_error_message = '❌ PHP EXCEPTION: ' . htmlspecialchars($e->getMessage());
}

// --- 3. Calculate Stats from all_meds (for dashboard) ---
$now_ts = time();
$expiring_limit_ts = strtotime("+{$DAYS_EXPIRING_SOON} days");
$totalMedicines = count($all_meds);
$lowStockCount = 0;
$expiringCount = 0;
$expiredCount = 0;

foreach ($all_meds as $m) {
    $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
    $minStock = (int)($m['minStock'] ?? 0);
    $expiry = !empty($m['EXPIRY_DATE']) ? strtotime($m['EXPIRY_DATE']) : null;
    
    if ($stock <= $minStock) {
        $lowStockCount++;
    }

    if ($expiry) {
        if ($expiry < $now_ts) {
            $expiredCount++;
        } elseif ($expiry > $now_ts && $expiry <= $expiring_limit_ts) {
            $expiringCount++;
        }
    }
}

// --- 4. Apply Filter and Search to get the list to display ---
$meds_to_display = array_filter($all_meds, function($m) use ($search_query, $filter_type, $now_ts, $expiring_limit_ts) {
    // Search filter
    if ($search_query !== '') {
        $q = strtolower($search_query);
        $name = strtolower($m['NAME'] ?? '');
        $id = strtolower($m['MEDICINE_ID'] ?? '');
        $category = strtolower($m['CATEGORY_TYPE'] ?? '');
        if (strpos($name, $q) === false && strpos($id, $q) === false && strpos($category, $q) === false) {
            return false;
        }
    }

    // Category filter
    $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
    $minStock = (int)($m['minStock'] ?? 0);
    $expiry = !empty($m['EXPIRY_DATE']) ? strtotime($m['EXPIRY_DATE']) : null;

    switch ($filter_type) {
        case 'low-stock':
            return $stock <= $minStock;
        case 'expiring':
            return $expiry && $expiry > $now_ts && $expiry <= $expiring_limit_ts;
        case 'expired':
            return $expiry && $expiry < $now_ts;
        case 'all':
        default:
            return true;
    }
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory Management</title>
    <style>
        /* ADDED CSS for Top Navigation */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            background: #1976d2; /* Darker blue */
            color: white;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .user-info {
            font-size: 0.9em;
        }

        .btn-logout {
            padding: 6px 12px;
            border: 1px solid white;
            border-radius: 6px;
            background: transparent;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Existing CSS continues below */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 40px 20px; text-align: center; }
        .header-content h1 { font-size: 2.5em; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2); }
        .subtitle { font-size: 1.1em; opacity: 0.9; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 30px; background: #f8f9fa; border-bottom: 1px solid #e0e0e0; }
        .stat-card { display: flex; align-items: center; gap: 15px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15); }
        .stat-icon { font-size: 3em; min-width: 60px; text-align: center; }
        .stat-icon.total { background: #e3f2fd; padding: 10px; border-radius: 50%; }
        .stat-icon.low-stock { background: #fff3e0; padding: 10px; border-radius: 50%; }
        .stat-icon.expiring { background: #ffebee; padding: 10px; border-radius: 50%; }
        .stat-icon.expired { background: #fce4ec; padding: 10px; border-radius: 50%; }
        .stat-details h3 { font-size: 0.9em; color: #666; margin-bottom: 5px; }
        .stat-number { font-size: 2em; font-weight: bold; color: #0066ff; }
        .controls { display: flex; gap: 15px; padding: 20px 30px; flex-wrap: wrap; align-items: center; background: white; border-bottom: 1px solid #e0e0e0; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 1em; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 102, 255, 0.4); }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-secondary:hover { background: #d0d0d0; }
        .search-bar { flex: 1; min-width: 200px; }
        .search-input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; transition: border-color 0.3s ease; }
        .search-input:focus { outline: none; border-color: #0066ff; box-shadow: 0 0 5px rgba(0, 102, 255, 0.3); }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; cursor: pointer; transition: border-color 0.3s ease; }
        .filter-select:focus { outline: none; border-color: #0066ff; }
        .medicines-section { padding: 30px; }
        .medicines-section h2 { color: #333; margin-bottom: 20px; font-size: 1.8em; }
        .medicines-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .medicine-card { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; transition: all 0.3s ease; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .medicine-card:hover { box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15); transform: translateY(-5px); border-color: #0066ff; }
        .medicine-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .medicine-name { font-size: 1.3em; font-weight: bold; color: #333; }
        .medicine-id { font-size: 0.85em; color: #999; margin-top: 5px; }
        .medicine-category { display: inline-block; background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; margin-top: 10px; }
        .medicine-details { margin: 15px 0; padding: 15px 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; font-size: 0.95em; }
        .detail-label { color: #666; font-weight: 500; }
        .detail-value { color: #333; font-weight: 600; }
        .stock-indicator { padding: 8px 12px; border-radius: 6px; font-weight: 600; text-align: center; margin: 10px 0; }
        .stock-good { background: #c8e6c9; color: #2e7d32; }
        .stock-low { background: #ffe0b2; color: #e65100; }
        .stock-critical { background: #ffcccc; color: #c62828; }
        .expiry-status { padding: 8px 12px; border-radius: 6px; font-weight: 600; text-align: center; margin: 10px 0; }
        .expiry-good { background: #c8e6c9; color: #2e7d32; }
        .expiry-warning { background: #ffe0b2; color: #e65100; }
        .expiry-expired { background: #ffcccc; color: #c62828; }
        .medicine-actions { display: flex; gap: 10px; margin-top: 15px; }
        .action-btn { flex: 1; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; transition: all 0.3s ease; }
        .edit-btn { background: #667eea; color: white; }
        .edit-btn:hover { background: #5568d3; }
        .delete-btn { background: #ff6b6b; color: white; }
        .delete-btn:hover { background: #ee5a52; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state-icon { font-size: 4em; margin-bottom: 20px; }
        .empty-state h3 { font-size: 1.5em; margin-bottom: 10px; color: #666; }
        .error-message { background: #ffdddd; color: #cc0000; padding: 15px; border: 1px solid #cc0000; margin: 20px; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div class="user-info">
            Welcome, **<?php echo htmlspecialchars($username); ?>** (<?php echo htmlspecialchars($userRole); ?>)
        </div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="stock.php">⚠️ Stock Alerts</a>
            <a href="expiry.php">🔴 Expiry Alerts</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>
    
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>🏥 Medicine Inventory Management</h1>
                <p class="subtitle">Monitor stock levels and expiry dates</p>
            </div>
        </header>
        
        <?php if (!empty($connection_error_message)): ?>
            <div class="error-message"><?php echo $connection_error_message; ?></div>
        <?php endif; ?>

        <section class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon total">📦</div>
                <div class="stat-details">
                    <h3>Total Medicines</h3>
                    <p class="stat-number" id="totalMedicines"><?php echo $totalMedicines; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon low-stock">⚠️</div>
                <div class="stat-details">
                    <h3>Low Stock</h3>
                    <p class="stat-number" id="lowStockCount"><?php echo $lowStockCount; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon expiring">🔴</div>
                <div class="stat-details">
                    <h3>Expiring Soon</h3>
                    <p class="stat-number" id="expiringCount"><?php echo $expiringCount; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon expired">❌</div>
                <div class="stat-details">
                    <h3>Expired</h3>
                    <p class="stat-number" id="expiredCount"><?php echo $expiredCount; ?></p>
                </div>
            </div>
        </section>

        <section class="controls">
            <a href="add_medicine.php" class="btn btn-primary" id="addMedicineBtn">+ Add Medicine</a>
            
            <form method="GET" action="medDirectory.php" id="filterForm" style="display:contents;">
                <div class="search-bar">
                    <input 
                        type="text" 
                        id="searchInput" 
                        name="search"
                        placeholder="Search medicines by name or ID..."
                        class="search-input"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        onchange="this.form.submit()"
                    >
                </div>
                <select id="filterSelect" name="filter" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Medicines</option>
                    <option value="low-stock" <?php echo $filter_type === 'low-stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="expiring" <?php echo $filter_type === 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                    <option value="expired" <?php echo $filter_type === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
                <button type="submit" style="display:none;"></button>
            </form>
        </section>

        <section class="medicines-section">
            <h2>Medicine List</h2>
            <div class="medicines-list" id="medicinesList">
                <?php if (!empty($meds_to_display)): ?>
                    <?php foreach ($meds_to_display as $m):
                        $id = htmlspecialchars($m['MEDICINE_ID'] ?? '');
                        $name = htmlspecialchars($m['NAME'] ?? '');
                        $category = htmlspecialchars($m['CATEGORY_TYPE'] ?? '');
                        $stock = (int)($m['QUANTITY_IN_STOCK'] ?? 0);
                        $minStock = (int)($m['minStock'] ?? 0);
                        $unitPrice = (float)($m['UNIT_PRICE'] ?? 0);
                        $price = number_format($unitPrice, 2);
                        $expiry = !empty($m['EXPIRY_DATE']) ? $m['EXPIRY_DATE'] : null;

                        // stock status
                        $stockClass = 'stock-good'; $stockText = "✅ In Stock ({$stock} units)";
                        if ($stock === 0) { $stockClass='stock-critical'; $stockText='❌ Out of Stock'; }
                        elseif ($stock < $minStock) { $stockClass='stock-low'; $stockText = "⚠️ Low Stock ({$stock} units)"; }
                        
                        // expiry status
                        $expiryClass = 'expiry-good'; $expiryText = '—';
                        if ($expiry) {
                            $ed = strtotime($expiry);
                            $now = time();
                            if ($ed < $now) { $expiryClass='expiry-expired'; $expiryText='❌ EXPIRED'; }
                            else { 
                                $days = ceil(($ed - $now) / (60*60*24)); 
                                if ($days <= $DAYS_EXPIRING_SOON) { 
                                    $expiryClass='expiry-warning'; $expiryText = "⚠️ Expiring in {$days} days"; 
                                } else { 
                                    $expiryClass='expiry-good'; 
                                    $expiryText = date('M j, Y', $ed); 
                                } 
                            }
                        }
                    ?>
                        <div class="medicine-card">
                            <div class="medicine-header">
                                <div>
                                    <div class="medicine-name"><?php echo $name; ?></div>
                                    <div class="medicine-id">ID: <?php echo $id; ?></div>
                                </div>
                            </div>
                            <span class="medicine-category"><?php echo $category; ?></span>
                            <div class="medicine-details">
                                <div class="detail-row"><span class="detail-label">Stock:</span><span class="detail-value"><?php echo $stock; ?> units</span></div>
                                <div class="detail-row"><span class="detail-label">Min Stock:</span><span class="detail-value"><?php echo $minStock; ?> units</span></div>
                                <div class="detail-row"><span class="detail-label">Price:</span><span class="detail-value">$<?php echo $price; ?></span></div>
                                <div class="detail-row"><span class="detail-label">Inventory Value:</span><span class="detail-value">$<?php echo number_format($stock * $unitPrice, 2); ?></span></div>
                            </div>
                            <div class="stock-indicator <?php echo $stockClass; ?>"><?php echo $stockText; ?></div>
                            <div class="expiry-status <?php echo $expiryClass; ?>">Expires: <?php echo $expiryText; ?></div>
                            <div class="medicine-actions">
                                <a class="action-btn edit-btn" href="edit_medicine.php?id=<?php echo urlencode($id); ?>">✏️ Edit</a>
                                <a class="action-btn" style="background:#4caf50;color:#fff;border-radius:6px;padding:8px 12px;text-decoration:none" href="medicine_details.php?id=<?php echo urlencode($id); ?>">ℹ️ Details</a>
                                
                                <form method="POST" action="medDirectory.php" style="display:inline; flex:1" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($name); ?> (ID: <?php echo htmlspecialchars($id); ?>)?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                                    <button class="action-btn delete-btn" type="submit">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-state-icon">📦</div><h3>No medicines found</h3><p>Try adjusting your search or filter criteria</p></div>
                <?php endif; ?>
            </div>
        </section>

    </div>
    
    <script>
        document.getElementById('filterSelect').onchange = function() {
            this.form.submit();
        };
        document.getElementById('searchInput').onchange = function() {
            this.form.submit();
        };
    </script>
</body>
</html>