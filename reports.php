<?php
require_once "session_check.php";
require_once 'connection.php';

// Check if user is NOT logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Force reset password check
if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'User';

// Initialize variables - will SUM from all databases
$totalUsers = 0;
$totalPatients = 0;
$totalPrescriptions = 0;
$lowStock = 0;
$todaysSales = 0.00;
$monthlySales = 0.00;
$salesDays = [];
$salesTotals = [];
$medNames = [];
$medCounts = [];
$stockNames = [];
$stockQty = [];

// Arrays to track data from each database
$db_results = [
    'sqlsrv' => ['connected' => false, 'data' => []],
    'pgsql' => ['connected' => false, 'data' => []],
    'mysql' => ['connected' => false, 'data' => []]
];

// ===========================
// QUERY SQL SERVER DATABASE
// ===========================
if ($pdo_sqlsrv) {
    try {
        $db_results['sqlsrv']['connected'] = true;
        
        // 1. TOTAL USERS
        $stmt = $pdo_sqlsrv->query('SELECT COUNT(*) AS total FROM [USER]');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['users'] = $result['total'];
        $totalUsers += $result['total'];
        
        // 2. TOTAL PATIENTS
        $stmt = $pdo_sqlsrv->query('SELECT COUNT(*) AS total FROM patient');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['patients'] = $result['total'];
        $totalPatients += $result['total'];
        
        // 3. TOTAL PRESCRIPTIONS
        $stmt = $pdo_sqlsrv->query('SELECT COUNT(*) AS total FROM prescription');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['prescriptions'] = $result['total'];
        $totalPrescriptions += $result['total'];
        
        // 4. LOW STOCK MEDICINES
        $stmt = $pdo_sqlsrv->query("SELECT COUNT(*) AS total FROM medicine WHERE quantity_in_stock < 20");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['low_stock'] = $result['total'];
        $lowStock += $result['total'];
        
        // 5. TODAY'S SALES
        $stmt = $pdo_sqlsrv->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE CAST(payment_date AS DATE) = CAST(GETDATE() AS DATE)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['today_sales'] = $result['total'];
        $todaysSales += $result['total'];
        
        // 6. MONTHLY SALES
        $stmt = $pdo_sqlsrv->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE MONTH(payment_date) = MONTH(GETDATE()) AND YEAR(payment_date) = YEAR(GETDATE())");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['monthly_sales'] = $result['total'];
        $monthlySales += $result['total'];
        
        // 7. DAILY SALES CHART DATA (Current month)
        $stmt = $pdo_sqlsrv->query("SELECT DAY(payment_date) as day, SUM(total_amount) as total FROM payment WHERE MONTH(payment_date) = MONTH(GETDATE()) AND YEAR(payment_date) = YEAR(GETDATE()) GROUP BY DAY(payment_date), payment_date ORDER BY payment_date");
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['sales_days'] = $salesData;
        
        // 8. TOP 5 MEDICINES
        $stmt = $pdo_sqlsrv->query("SELECT TOP 5 m.name, COUNT(pd.medicine_id) AS times_prescribed FROM prescription_detail pd JOIN medicine m ON pd.medicine_id = m.medicine_id GROUP BY m.name, m.medicine_id ORDER BY times_prescribed DESC");
        $medData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['top_meds'] = $medData;
        
        // 9. STOCK DATA
        $stmt = $pdo_sqlsrv->query("SELECT TOP 5 name, quantity_in_stock FROM medicine WHERE quantity_in_stock > 0 ORDER BY quantity_in_stock ASC");
        $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['sqlsrv']['data']['stock'] = $stockData;
        
    } catch (Exception $e) {
        $db_results['sqlsrv']['error'] = $e->getMessage();
    }
}

// ===========================
// QUERY POSTGRESQL DATABASE
// ===========================
if ($pg_conn) {
    try {
        $db_results['pgsql']['connected'] = true;
        
        // 1. TOTAL USERS
        $stmt = $pg_conn->query('SELECT COUNT(*) AS total FROM "user"');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['users'] = $result['total'];
        $totalUsers += $result['total'];
        
        // 2. TOTAL PATIENTS
        $stmt = $pg_conn->query('SELECT COUNT(*) AS total FROM patient');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['patients'] = $result['total'];
        $totalPatients += $result['total'];
        
        // 3. TOTAL PRESCRIPTIONS
        $stmt = $pg_conn->query('SELECT COUNT(*) AS total FROM prescription');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['prescriptions'] = $result['total'];
        $totalPrescriptions += $result['total'];
        
        // 4. LOW STOCK MEDICINES
        $stmt = $pg_conn->query("SELECT COUNT(*) AS total FROM medicine WHERE quantity_in_stock < 20");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['low_stock'] = $result['total'];
        $lowStock += $result['total'];
        
        // 5. TODAY'S SALES
        $stmt = $pg_conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE DATE(payment_date) = CURRENT_DATE");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['today_sales'] = $result['total'];
        $todaysSales += $result['total'];
        
        // 6. MONTHLY SALES
        $stmt = $pg_conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE EXTRACT(MONTH FROM payment_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM payment_date) = EXTRACT(YEAR FROM CURRENT_DATE)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['monthly_sales'] = $result['total'];
        $monthlySales += $result['total'];
        
        // 7. DAILY SALES CHART DATA (Current month)
        $stmt = $pg_conn->query("SELECT EXTRACT(DAY FROM payment_date) as day, SUM(total_amount) as total FROM payment WHERE EXTRACT(MONTH FROM payment_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM payment_date) = EXTRACT(YEAR FROM CURRENT_DATE) GROUP BY EXTRACT(DAY FROM payment_date), payment_date ORDER BY payment_date");
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['sales_days'] = $salesData;
        
        // 8. TOP 5 MEDICINES
        $stmt = $pg_conn->query("SELECT m.name, COUNT(pd.medicine_id) AS times_prescribed FROM prescription_detail pd JOIN medicine m ON pd.medicine_id = m.medicine_id GROUP BY m.name, m.medicine_id ORDER BY times_prescribed DESC LIMIT 5");
        $medData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['top_meds'] = $medData;
        
        // 9. STOCK DATA
        $stmt = $pg_conn->query("SELECT name, quantity_in_stock FROM medicine WHERE quantity_in_stock > 0 ORDER BY quantity_in_stock ASC LIMIT 5");
        $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db_results['pgsql']['data']['stock'] = $stockData;
        
    } catch (Exception $e) {
        $db_results['pgsql']['error'] = $e->getMessage();
    }
}

// ===========================
// QUERY MYSQL DATABASE
// ===========================
if ($mysql_conn2) {
    try {
        $db_results['mysql']['connected'] = true;
        
        // 1. TOTAL USERS
        $result = $mysql_conn2->query('SELECT COUNT(*) AS total FROM `user`');
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['users'] = $row['total'];
        $totalUsers += $row['total'];
        
        // 2. TOTAL PATIENTS
        $result = $mysql_conn2->query('SELECT COUNT(*) AS total FROM patient');
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['patients'] = $row['total'];
        $totalPatients += $row['total'];
        
        // 3. TOTAL PRESCRIPTIONS
        $result = $mysql_conn2->query('SELECT COUNT(*) AS total FROM prescription');
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['prescriptions'] = $row['total'];
        $totalPrescriptions += $row['total'];
        
        // 4. LOW STOCK MEDICINES
        $result = $mysql_conn2->query("SELECT COUNT(*) AS total FROM medicine WHERE quantity_in_stock < 20");
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['low_stock'] = $row['total'];
        $lowStock += $row['total'];
        
        // 5. TODAY'S SALES
        $result = $mysql_conn2->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE DATE(payment_date) = CURDATE()");
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['today_sales'] = $row['total'];
        $todaysSales += $row['total'];
        
        // 6. MONTHLY SALES
        $result = $mysql_conn2->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
        $row = $result->fetch_assoc();
        $db_results['mysql']['data']['monthly_sales'] = $row['total'];
        $monthlySales += $row['total'];
        
        // 7. DAILY SALES CHART DATA (Current month)
        $result = $mysql_conn2->query("SELECT DAY(payment_date) as day, SUM(total_amount) as total FROM payment WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) GROUP BY DAY(payment_date), payment_date ORDER BY payment_date");
        $salesData = [];
        while($row = $result->fetch_assoc()) {
            $salesData[] = $row;
        }
        $db_results['mysql']['data']['sales_days'] = $salesData;
        
        // 8. TOP 5 MEDICINES
        $result = $mysql_conn2->query("SELECT m.name, COUNT(pd.medicine_id) AS times_prescribed FROM prescription_detail pd JOIN medicine m ON pd.medicine_id = m.medicine_id GROUP BY m.name, m.medicine_id ORDER BY times_prescribed DESC LIMIT 5");
        $medData = [];
        while($row = $result->fetch_assoc()) {
            $medData[] = $row;
        }
        $db_results['mysql']['data']['top_meds'] = $medData;
        
        // 9. STOCK DATA
        $result = $mysql_conn2->query("SELECT name, quantity_in_stock FROM medicine WHERE quantity_in_stock > 0 ORDER BY quantity_in_stock ASC LIMIT 5");
        $stockData = [];
        while($row = $result->fetch_assoc()) {
            $stockData[] = $row;
        }
        $db_results['mysql']['data']['stock'] = $stockData;
        
    } catch (Exception $e) {
        $db_results['mysql']['error'] = $e->getMessage();
    }
}

// ===========================
// COMBINE DATA FOR CHARTS
// ===========================

// ===========================
// MONTHLY SALES DATA (31 days)
// ===========================
$currentMonth = date('n'); // Current month (1-12)
$currentYear = date('Y'); // Current year
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);

// Initialize array for all days of the month
$monthlySalesData = array_fill(1, $daysInMonth, 0);

// Combine sales data from all databases for the CURRENT MONTH
foreach (['sqlsrv', 'pgsql', 'mysql'] as $dbType) {
    if (isset($db_results[$dbType]['data']['sales_days'])) {
        foreach ($db_results[$dbType]['data']['sales_days'] as $sale) {
            // Only use data from current month
            if (isset($sale['day']) && is_numeric($sale['day']) && $sale['day'] >= 1 && $sale['day'] <= $daysInMonth) {
                $day = (int)$sale['day'];
                $total = floatval($sale['total']);
                $monthlySalesData[$day] += $total;
            }
        }
    }
}

// Create labels for all days of the month (1 January, 2 January...)
$salesDays = [];
$salesTotals = [];
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$currentMonthName = $monthNames[$currentMonth - 1];

for ($day = 1; $day <= $daysInMonth; $day++) {
    $salesDays[] = $day . ' ' . $currentMonthName;
    $salesTotals[] = $monthlySalesData[$day];
}

// Combine top medicines from all databases
$allMedData = [];
foreach (['sqlsrv', 'pgsql', 'mysql'] as $dbType) {
    if (isset($db_results[$dbType]['data']['top_meds'])) {
        foreach ($db_results[$dbType]['data']['top_meds'] as $med) {
            $name = $med['name'];
            $count = intval($med['times_prescribed']);
            
            if (!isset($allMedData[$name])) {
                $allMedData[$name] = 0;
            }
            $allMedData[$name] += $count;
        }
    }
}

// Sort medicines by count and take top 5
arsort($allMedData);
$count = 0;
foreach ($allMedData as $name => $times) {
    if ($count >= 5) break;
    $medNames[] = $name;
    $medCounts[] = $times;
    $count++;
}

// Combine stock data from all databases
$allStockData = [];
foreach (['sqlsrv', 'pgsql', 'mysql'] as $dbType) {
    if (isset($db_results[$dbType]['data']['stock'])) {
        foreach ($db_results[$dbType]['data']['stock'] as $stock) {
            $name = $stock['name'];
            $qty = intval($stock['quantity_in_stock']);
            
            if (!isset($allStockData[$name])) {
                $allStockData[$name] = 0;
            }
            $allStockData[$name] += $qty;
        }
    }
}

// Sort stock data by quantity and take lowest 5
asort($allStockData);
$count = 0;
foreach ($allStockData as $name => $qty) {
    if ($count >= 5) break;
    $stockNames[] = $name;
    $stockQty[] = $qty;
    $count++;
}

// Count connected databases
$connectedDbs = 0;
$connectedDbs += $db_results['sqlsrv']['connected'] ? 1 : 0;
$connectedDbs += $db_results['pgsql']['connected'] ? 1 : 0;
$connectedDbs += $db_results['mysql']['connected'] ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM REPORTS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Medical Professional Color Scheme with Dark Blue */
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
            --main-bg: var(--cream-white);
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

        /* Sidebar - Dark Blue */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--dark-blue) 0%, #143852 100%);
            color: var(--sidebar-text);
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
            background: linear-gradient(135deg, white, var(--blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-blue);
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
        }

        /* Header */
        .main-header {
            padding: 20px 35px;
            background: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 1.4em;
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header-title p {
            color: var(--text-secondary);
            font-size: 0.9em;
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
            border: 1px solid var(--border-color);
            border-radius: 20px;
            width: 280px;
            font-size: 0.9em;
            background: var(--blue-light);
            transition: all 0.3s ease;
            font-weight: 300;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
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
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .welcome-text h2 {
            font-size: 1.5em;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .welcome-text p {
            font-size: 0.95em;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Database Connection Status */
        .db-info {
            background: linear-gradient(135deg, rgba(28, 73, 102, 0.1), rgba(42, 93, 122, 0.1));
            border-left: 4px solid var(--dark-blue);
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 0.9em;
        }

        /* Database Status Cards */
        .db-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .db-status-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        }

        .db-status-card.connected {
            border-color: var(--success-green);
            background: rgba(92, 184, 92, 0.05);
        }

        .db-status-card.disconnected {
            border-color: var(--soft-grey);
            opacity: 0.7;
        }

        .db-status-icon {
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .db-status-card.connected .db-status-icon {
            color: var(--success-green);
        }

        .db-status-card.disconnected .db-status-icon {
            color: var(--soft-grey);
        }

        .db-status-name {
            font-weight: 600;
            font-size: 1em;
            margin-bottom: 5px;
        }

        .db-status-text {
            font-size: 0.85em;
            color: var(--text-secondary);
        }

        /* Charts Section */
        .charts-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2em;
            color: var(--dark-blue);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        .chart-container h3 {
            color: var(--dark-blue);
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 15px;
            color: var(--dark-blue);
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--dark-blue);
            margin: 8px 0;
        }

        .stat-card.sales .stat-number {
            color: var(--success-green);
        }

        .stat-card.patients .stat-number {
            color: var(--blue-accent);
        }

        .stat-card.stock .stat-number {
            color: var(--warning-orange);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9em;
            font-weight: 400;
            margin-bottom: 8px;
        }

        .stat-subtext {
            color: var(--soft-grey);
            font-size: 0.8em;
            font-weight: 300;
        }

        /* Error Message */
        .error-message {
            background-color: #fee;
            color: var(--alert-red);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid var(--alert-red);
            font-size: 0.9em;
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
            
            .charts-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .db-status-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .welcome-section {
                padding: 20px;
            }
            
            .welcome-text h2 {
                font-size: 1.3em;
            }
            
            .chart-container {
                padding: 20px;
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
            
            .section-title {
                font-size: 1.1em;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .db-status-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-wrapper {
                height: 250px;
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
                    <div class="nav-title">Medical Operations</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php"><i class="fas fa-home nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">Administration</div>
                    <ul class="nav-links">
                        <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="reports.php" class="active"><i class="fas fa-chart-bar nav-icon"></i>Reports & Analytics</a></li>
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
                    <h1>Reports & Analytics</h1>
                    <p>Pharmacy Performance Dashboard - <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search reports...">
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Welcome Section -->
                <section class="welcome-section">
                    <div class="welcome-text">
                        <h2>Multi-Database Analytics Dashboard</h2>
                        <p>Combined data from all connected databases for comprehensive insights</p>
                    </div>
                </section>

                <!-- Database Connection Status -->
                <div class="db-info">
                    <i class="fas fa-network-wired"></i> 
                    <strong>Multi-Database Integration Active</strong> - 
                    <?php echo $connectedDbs; ?> of 3 databases connected
                </div>

                <!-- Database Status Cards -->
                <div class="db-status-grid">
                    <div class="db-status-card <?php echo $db_results['sqlsrv']['connected'] ? 'connected' : 'disconnected'; ?>">
                        <div class="db-status-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="db-status-name">SQL Server</div>
                        <div class="db-status-text">
                            <?php 
                            echo $db_results['sqlsrv']['connected'] ? 
                                'Connected ✓' : 
                                'Not Connected ✗';
                            ?>
                        </div>
                        <?php if ($db_results['sqlsrv']['connected'] && isset($db_results['sqlsrv']['data']['users'])): ?>
                        <div style="font-size: 0.8em; margin-top: 5px; color: var(--dark-blue);">
                            Users: <?php echo $db_results['sqlsrv']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="db-status-card <?php echo $db_results['pgsql']['connected'] ? 'connected' : 'disconnected'; ?>">
                        <div class="db-status-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="db-status-name">PostgreSQL</div>
                        <div class="db-status-text">
                            <?php 
                            echo $db_results['pgsql']['connected'] ? 
                                'Connected ✓' : 
                                'Not Connected ✗';
                            ?>
                        </div>
                        <?php if ($db_results['pgsql']['connected'] && isset($db_results['pgsql']['data']['users'])): ?>
                        <div style="font-size: 0.8em; margin-top: 5px; color: var(--dark-blue);">
                            Users: <?php echo $db_results['pgsql']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="db-status-card <?php echo $db_results['mysql']['connected'] ? 'connected' : 'disconnected'; ?>">
                        <div class="db-status-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="db-status-name">MySQL</div>
                        <div class="db-status-text">
                            <?php 
                            echo $db_results['mysql']['connected'] ? 
                                'Connected ✓' : 
                                'Not Connected ✗';
                            ?>
                        </div>
                        <?php if ($db_results['mysql']['connected'] && isset($db_results['mysql']['data']['users'])): ?>
                        <div style="font-size: 0.8em; margin-top: 5px; color: var(--dark-blue);">
                            Users: <?php echo $db_results['mysql']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Charts Section (NOW AT THE TOP) -->
                <section class="charts-section">
                    <h2 class="section-title"><i class="fas fa-chart-bar"></i> Combined Analytics from All Databases</h2>
                    
                    <div class="charts-grid">
                        <!-- Sales Chart -->
                        <div class="chart-container">
                            <h3><i class="fas fa-chart-line"></i> Combined Daily Sales (<?php echo $currentMonthName; ?>)</h3>
                            <div class="chart-wrapper">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Top Medicines Chart -->
                        <div class="chart-container">
                            <h3><i class="fas fa-pills"></i> Top Prescribed Medicines (All DBs)</h3>
                            <div class="chart-wrapper">
                                <canvas id="medChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Stock Chart -->
                        <div class="chart-container" style="grid-column: span 2;">
                            <h3><i class="fas fa-boxes"></i> Lowest Stock Medicines (All DBs)</h3>
                            <div class="chart-wrapper">
                                <canvas id="stockChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Statistics Cards (MOVED TO BOTTOM) -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $totalUsers; ?></div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                    
                    <div class="stat-card patients">
                        <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
                        <div class="stat-number"><?php echo $totalPatients; ?></div>
                        <div class="stat-label">Total Patients</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                        <div class="stat-number"><?php echo $totalPrescriptions; ?></div>
                        <div class="stat-label">Total Prescriptions</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                    
                    <div class="stat-card stock">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-number"><?php echo $lowStock; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                    
                    <div class="stat-card sales">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-number">RM <?php echo number_format($todaysSales, 2); ?></div>
                        <div class="stat-label">Total Today's Sales</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                    
                    <div class="stat-card sales">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-number">RM <?php echo number_format($monthlySales, 2); ?></div>
                        <div class="stat-label">Total Monthly Sales</div>
                        <div class="stat-subtext">Summed from all databases</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar navigation active state
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                sessionStorage.setItem('activePage', this.getAttribute('href'));
            });
        });

        // Restore active page on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const activePage = sessionStorage.getItem('activePage') || 'reports.php';
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                const linkPage = link.getAttribute('href');
                link.classList.remove('active');
                
                if (linkPage === currentPage || linkPage === activePage) {
                    link.classList.add('active');
                }
            });
        });

        // VIBRANT COLOR CHARTS
        // Sales Chart with colorful gradient - NOW SHOWS FULL MONTH DATES
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(76, 175, 80, 0.8)');   // Bright Green
        gradient.addColorStop(0.5, 'rgba(33, 150, 243, 0.6)'); // Bright Blue
        gradient.addColorStop(1, 'rgba(76, 175, 80, 0.1)');
        
        // Truncate long labels for better display
        const salesLabels = <?php echo json_encode($salesDays); ?>;
        const truncatedLabels = salesLabels.map(label => {
            // Show shorter version for crowded displays
            if (salesLabels.length > 15) {
                const parts = label.split(' ');
                return parts[0]; // Just show day number
            }
            return label;
        });
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: truncatedLabels,
                datasets: [{
                    label: 'Combined Sales (RM)',
                    data: <?php echo json_encode($salesTotals); ?>,
                    borderColor: '#4CAF50', // Bright Green
                    backgroundColor: gradient,
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2196F3', // Blue points
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: salesLabels.length > 15 ? 3 : 6,
                    pointHoverRadius: salesLabels.length > 15 ? 6 : 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            maxRotation: salesLabels.length > 15 ? 45 : 0,
                            minRotation: salesLabels.length > 15 ? 45 : 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return salesLabels[context.dataIndex] + ': RM ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Top Medicines Chart with rainbow colors
        const medCtx = document.getElementById('medChart').getContext('2d');
        new Chart(medCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($medNames); ?>,
                datasets: [{
                    label: 'Times Prescribed',
                    data: <?php echo json_encode($medCounts); ?>,
                    backgroundColor: [
                        '#FF5252', // Red
                        '#FF4081', // Pink
                        '#E040FB', // Purple
                        '#7C4DFF', // Deep Purple
                        '#536DFE', // Indigo
                        '#448AFF', // Blue
                        '#40C4FF', // Light Blue
                        '#18FFFF', // Cyan
                        '#64FFDA', // Teal
                        '#69F0AE'  // Green
                    ],
                    borderColor: '#1c4966',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Stock Chart with vibrant colors
        const stockCtx = document.getElementById('stockChart').getContext('2d');
        new Chart(stockCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($stockNames); ?>,
                datasets: [{
                    data: <?php echo json_encode($stockQty); ?>,
                    backgroundColor: [
                        '#FF5252', // Red
                        '#FF9800', // Orange
                        '#FFEB3B', // Yellow
                        '#4CAF50', // Green
                        '#2196F3', // Blue
                        '#9C27B0', // Purple
                        '#E91E63', // Pink
                        '#00BCD4', // Cyan
                        '#8BC34A', // Light Green
                        '#FF5722'  // Deep Orange
                    ],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                cutout: '55%'
            }
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    alert(`Search function would search across all databases for: "${searchTerm}"`);
                }
            }
        });

        // Update time display
        function updateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.header-title p');
            if (timeElement) {
                timeElement.textContent = `Pharmacy Performance Dashboard - ${now.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}`;
            }
        }

        // Initialize
        updateTime();
    </script>
</body>
</html>