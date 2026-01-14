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

// 🔐 Logged in but forced to reset password
if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'Pharmacist';
$username = $_SESSION['username'] ?? 'User';

// --- Connection Status --- FIXED: Use $pdo_sqlsrv instead of $pdo
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅ Connected" : "❌ Failed";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅ Connected" : "❌ Failed";
$status_sql = (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? "✅ Connected" : "❌ Failed"; // FIXED HERE

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

            /* --- TARGET: SQL Server --- */ // FIXED: Use $pdo_sqlsrv instead of $pdo
            elseif ($db_source === 'SQLSRV' && isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
                $stmt1 = $pdo_sqlsrv->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE MEDICINE_ID = :id");
                $stmt1->execute([':id' => $id_to_delete]);

                $stmt2 = $pdo_sqlsrv->prepare("DELETE FROM MEDICINE WHERE MEDICINE_ID = :id");
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

// --- 2. Fetch Medicines with DB_SOURCE tags --- FIXED: Use $pdo_sqlsrv
$all_meds = [];
$sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";

try {
    // MySQL
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
    
    // PostgreSQL
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
    
    // SQL Server - FIXED: Use $pdo_sqlsrv
    if (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
        $stmt = $pdo_sqlsrv->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $r = array_change_key_case($r, CASE_UPPER);
                $r['DB_SOURCE'] = 'SQLSRV';
                $all_meds[] = $r;
            }
        }
    }
} catch (Exception $e) { 
    error_log($e->getMessage()); 
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM - Medicine Inventory</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme */
        :root {
            --dark-blue: #1c4966;
            --blue-medium: #2a5d7a;
            --blue-light: #e3f2fd;
            --blue-accent: #4a90e2;
            --cream-white: #f8fafc;
            --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50;
            --alert-red: #d9534f;
            --warning-orange: #f0ad4e;
            --success-green: #5cb85c;
            
            --sidebar-bg: var(--dark-blue);
            --sidebar-text: white;
            --main-bg: #f5f7fa;
            --card-bg: white;
            --border-color: #e1e8ed;
            --text-primary: var(--dark-grey);
            --text-secondary: var(--soft-grey);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Be Vietnam Pro", sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            font-weight: 400;
            line-height: 1.5;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            height: 92vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
            overflow: hidden;
            display: flex;
        }

        /* Sidebar - Match Dashboard Colors */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1c4966 0%, #143852 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 25px 0;
        }

        .pharmacy-logo {
            text-align: center;
            padding: 0 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pharmacy-logo h1 {
            font-size: 1.3em;
            font-weight: 600;
            color: white;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pharmacy-logo p {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 300;
        }

        .user-profile {
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, white, #e3f2fd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1c4966;
            font-weight: 600;
            font-size: 1.2em;
            border: 2px solid white;
        }

        .user-info {
            margin-left: 12px;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95em;
            margin-bottom: 3px;
        }

        .user-role {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        /* Navigation Menu */
        .nav-menu {
            flex: 1;
            padding: 25px 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }

        .nav-title {
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 6px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
            font-size: 0.9em;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--blue-accent);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
            font-weight: 500;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 1em;
        }

        .logout-btn {
            margin: 15px 20px 0;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: var(--alert-red);
            border-color: var(--alert-red);
            transform: translateY(-1px);
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f5f7fa;
        }

        /* Header */
        .main-header {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid #e4e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header-title h1 {
            font-size: 1.4em;
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header-title p {
            color: #6c757d;
            font-size: 0.85em;
            font-weight: 300;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            width: 280px;
            font-size: 0.9em;
            background: white;
            transition: all 0.3s ease;
            font-weight: 300;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-blue);
            font-size: 0.9em;
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background: #f5f7fa;
        }

        /* Database Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .status-number {
            font-size: 2em;
            font-weight: 700;
            color: var(--dark-blue);
            margin: 10px 0;
        }

        .status-label {
            color: #6c757d;
            font-size: 0.85em;
            font-weight: 400;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-blue);
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--dark-blue);
        }

        /* Search and Filter */
        .search-filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .search-form {
            flex: 1;
            display: flex;
            gap: 10px;
        }

        .search-form input[type="text"] {
            flex: 1;
            padding: 10px 15px 10px 40px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            font-size: 0.9em;
            background: white;
        }

        .search-form select {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            font-size: 0.9em;
            background: white;
            cursor: pointer;
        }

        /* Medicine Cards Grid */
        .medicine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .medicine-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .db-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 4px;
            background: #eee;
            color: #666;
            font-weight: bold;
            z-index: 1;
        }

        .medicine-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--dark-blue));
            color: white;
            padding: 20px;
        }

        .medicine-name {
            margin: 0;
            font-size: 1.2em;
            font-weight: 600;
        }

        .medicine-id {
            margin: 5px 0 0;
            font-size: 0.85em;
            opacity: 0.8;
        }

        .medicine-content {
            padding: 20px;
        }

        .medicine-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.85em;
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            font-size: 1.1em;
        }

        .medicine-category {
            margin-bottom: 15px;
        }

        .category-label {
            font-size: 0.85em;
            color: #6c757d;
        }

        .category-value {
            font-weight: 500;
        }

        .stock-status {
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            margin: 10px 0;
            font-weight: 500;
        }

        .stock-good {
            background: #d4edda;
            color: #155724;
        }

        .stock-low {
            background: #ffe0b2;
            color: #e65100;
        }

        .expiry-info {
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .expiry-valid {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .expiry-warning {
            background: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffcc80;
        }

        .expiry-expired {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .expiry-neutral {
            background: #f5f5f5;
            color: #757575;
            border: 1px solid #e0e0e0;
        }

        .medicine-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            text-align: center;
            font-size: 0.85em;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #667eea;
        }

        .btn-info {
            background: #4caf50;
        }

        .btn-delete {
            background: #ff6b6b;
            border: none;
            cursor: pointer;
        }

        /* Export Dropdown */
        .export-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-top: 5px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container {
                height: auto;
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 15px;
            }
            
            .nav-section {
                flex: 1;
                min-width: 200px;
                margin-bottom: 15px;
            }
            
            .main-content {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 20px;
            }
            
            .main-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .status-grid,
            .medicine-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons,
            .search-filter-container {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                padding: 15px 0;
            }
            
            .pharmacy-logo h1 {
                font-size: 1.1em;
            }
            
            .user-profile {
                padding: 15px;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .medicine-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="pharmacy-logo">
                <h1><i class="fas fa-pills"></i> PHARMACY SYSTEM</h1>
                <p>Professional Healthcare Management</p>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-title">NAVIGATION</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php" class="active"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ADMINISTRATION</div>
                    <ul class="nav-links">
                        <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                    </ul>
                </div>
            </nav>

            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>Medicine Inventory</h1>
                    <p>Monitor stock levels and expiry dates across all databases</p>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Database Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-number"><?php echo count($all_meds); ?></div>
                        <div class="status-label">Total Medicines</div>
                        <div style="font-size: 0.8em; color: #28a745; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> <?php echo $status_mysql2; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number"><?php echo $lowStockCount; ?></div>
                        <div class="status-label">Low Stock</div>
                        <div style="font-size: 0.8em; color: <?php echo $status_pg === '✅ Connected' ? '#28a745' : '#dc3545'; ?>; margin-top: 5px;">
                            <i class="<?php echo $status_pg === '✅ Connected' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?>"></i> <?php echo $status_pg; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number"><?php echo $expiringCount; ?></div>
                        <div class="status-label">Expiring Soon</div>
                        <div style="font-size: 0.8em; color: <?php echo $status_sql === '✅ Connected' ? '#28a745' : '#dc3545'; ?>; margin-top: 5px;">
                            <i class="<?php echo $status_sql === '✅ Connected' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?>"></i> <?php echo $status_sql; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number"><?php echo $expiredCount; ?></div>
                        <div class="status-label">Expired</div>
                        <div style="font-size: 0.8em; color: #28a745; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> Connected
                        </div>
                    </div>
                </div>

                <!-- Action Buttons and Search -->
                <div class="action-buttons">
                    <a href="add_medicine.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Medicine
                    </a>
                    
                    <div class="export-dropdown">
                        <button class="btn-secondary" onclick="toggleExportMenu(event)">
                            <i class="fas fa-file-export"></i> Export Inventory <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="exportMenu" class="dropdown-menu">
                            <a href="export_medicine.php?type=excel" class="dropdown-item">
                                <i class="fas fa-file-excel"></i> Excel Spreadsheet
                            </a>
                            <a href="export_medicine.php?type=pdf" target="_blank" class="dropdown-item">
                                <i class="fas fa-file-pdf"></i> PDF Document
                            </a>
                            <a href="export_medicine.php?type=print" target="_blank" class="dropdown-item">
                                <i class="fas fa-print"></i> Print List
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <form method="GET" class="search-filter-container">
                    <div class="search-form">
                        <input type="text" name="search" placeholder="Search by Medicine ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <select name="filter" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_type=='all'?'selected':''; ?>>All Medicines</option>
                            <option value="low-stock" <?php echo $filter_type=='low-stock'?'selected':''; ?>>Low Stock</option>
                            <option value="expiring" <?php echo $filter_type=='expiring'?'selected':''; ?>>Expiring Soon</option>
                            <option value="expired" <?php echo $filter_type=='expired'?'selected':''; ?>>Expired</option>
                        </select>
                    </div>
                </form>

                <!-- Medicine Cards -->
                <div class="medicine-grid">
                    <?php foreach($meds_to_display as $m): 
                        $stock = (int)$m['QUANTITY_IN_STOCK'];
                        $price = number_format((float)$m['UNIT_PRICE'], 2);
                        $expiryStr = $m['EXPIRY_DATE'] ?? null;
                        $expiryTs = (!empty($expiryStr)) ? strtotime($expiryStr) : null;
                        $statusText = !empty($expiryStr) ? date('M d, Y', $expiryTs) : 'No Expiry';
                        $expiryClass = 'expiry-neutral';
                        
                        if ($expiryTs) {
                            if ($expiryTs < $now_ts) {
                                $expiryClass = 'expiry-expired';
                                $statusText = "EXPIRED: " . date('M d, Y', $expiryTs);
                            } elseif ($expiryTs <= $expiring_limit_ts) {
                                $expiryClass = 'expiry-warning';
                                $statusText = "EXPIRING: " . date('M d, Y', $expiryTs);
                            } else {
                                $expiryClass = 'expiry-valid';
                                $statusText = "Expires: " . date('M d, Y', $expiryTs);
                            }
                        }
                    ?>
                        <div class="medicine-card">
                            <div class="db-tag"><?php echo $m['DB_SOURCE']; ?></div>
                            <div class="medicine-header">
                                <h3 class="medicine-name"><?php echo htmlspecialchars($m['NAME']); ?></h3>
                                <p class="medicine-id">ID: <?php echo $m['MEDICINE_ID']; ?></p>
                            </div>
                            <div class="medicine-content">
                                <div class="medicine-info-row">
                                    <div>
                                        <div class="info-label">Stock</div>
                                        <div class="info-value"><?php echo $stock; ?> units</div>
                                    </div>
                                    <div>
                                        <div class="info-label">Price</div>
                                        <div class="info-value">RM <?php echo $price; ?></div>
                                    </div>
                                </div>
                                
                                <div class="medicine-category">
                                    <div class="category-label">Category</div>
                                    <div class="category-value"><?php echo htmlspecialchars($m['CATEGORY_TYPE'] ?? 'N/A'); ?></div>
                                </div>
                                
                                <div class="stock-status <?php echo ($stock <= 50) ? 'stock-low' : 'stock-good'; ?>">
                                    <?php echo ($stock <= 50) ? '⚠️ Low Stock' : '✅ In Stock'; ?>
                                </div>
                                
                                <div class="expiry-info <?php echo $expiryClass; ?>">
                                    <?php echo $statusText; ?>
                                </div>
                                
                                <div class="medicine-actions">
                                    <a href="edit_medicine.php?id=<?php echo $m['MEDICINE_ID']; ?>" class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="medicine_details.php?id=<?php echo $m['MEDICINE_ID']; ?>" class="action-btn btn-info">
                                        <i class="fas fa-info-circle"></i> Info
                                    </a>
                                    <form method="POST" style="flex:1" onsubmit="return confirm('Delete this record from <?php echo $m['DB_SOURCE']; ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $m['MEDICINE_ID']; ?>">
                                        <input type="hidden" name="db_source" value="<?php echo $m['DB_SOURCE']; ?>"> 
                                        <button type="submit" class="action-btn btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($meds_to_display)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 10px; color: #6c757d;">
                            <i class="fas fa-pills" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i>
                            <h3>No medicines found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Export menu toggle
        function toggleExportMenu(event) {
            event.stopPropagation();
            const menu = document.getElementById('exportMenu');
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
        
        // Close menu when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.btn-secondary')) {
                const menu = document.getElementById('exportMenu');
                if(menu) menu.style.display = 'none';
            }
        }
        
        // Sidebar navigation active state
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                sessionStorage.setItem('activePage', this.getAttribute('href'));
            });
        });

        // Restore active page on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const activePage = sessionStorage.getItem('activePage') || 'medDirectory.php';
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                const linkPage = link.getAttribute('href');
                link.classList.remove('active');
                
                if (linkPage === currentPage || linkPage === activePage) {
                    link.classList.add('active');
                }
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        if (searchTerm) {
                            window.location.href = 'medDirectory.php?search=' + encodeURIComponent(searchTerm);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>