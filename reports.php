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
// COMBINE DATA FOR CHARTS AND REPORTS
// ===========================

// Monthly sales data
$currentMonth = date('n');
$currentYear = date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$monthlySalesData = array_fill(1, $daysInMonth, 0);

foreach (['sqlsrv', 'pgsql', 'mysql'] as $dbType) {
    if (isset($db_results[$dbType]['data']['sales_days'])) {
        foreach ($db_results[$dbType]['data']['sales_days'] as $sale) {
            if (isset($sale['day']) && is_numeric($sale['day']) && $sale['day'] >= 1 && $sale['day'] <= $daysInMonth) {
                $day = (int)$sale['day'];
                $total = floatval($sale['total']);
                $monthlySalesData[$day] += $total;
            }
        }
    }
}

// Create labels for all days of the month
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

// ===========================
// EXPORT FUNCTIONALITY
// ===========================
if (isset($_POST['export_pdf'])) {
    // Show printable PDF version
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pharmacy Analytics Report - PDF</title>
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 20mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12pt;
                    line-height: 1.5;
                    color: #000;
                }
                .no-print {
                    display: none !important;
                }
                .break-before {
                    page-break-before: always;
                }
                .break-after {
                    page-break-after: avoid;
                }
                .no-break {
                    page-break-inside: avoid;
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.5;
                color: #000;
                margin: 0;
                padding: 20px;
                max-width: 1000px;
                margin: 0 auto;
                background: white;
            }
            
            .print-header {
                text-align: center;
                border-bottom: 3px solid #1c4966;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .company-name {
                font-size: 28pt;
                font-weight: bold;
                color: #1c4966;
                margin-bottom: 10px;
            }
            
            .report-title {
                font-size: 20pt;
                font-weight: bold;
                color: #2a5d7a;
                margin-bottom: 10px;
            }
            
            .report-subtitle {
                font-size: 14pt;
                color: #666;
                margin-bottom: 5px;
            }
            
            .report-info {
                font-size: 11pt;
                color: #888;
                margin-top: 10px;
            }
            
            .section {
                margin: 25px 0;
                page-break-inside: avoid;
            }
            
            .section-title {
                font-size: 16pt;
                font-weight: bold;
                color: #1c4966;
                border-bottom: 2px solid #1c4966;
                padding-bottom: 8px;
                margin-bottom: 15px;
                page-break-after: avoid;
            }
            
            .stat-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin: 20px 0;
            }
            
            .stat-card {
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 5px;
                text-align: center;
                background: #f8fafc;
                page-break-inside: avoid;
            }
            
            .stat-number {
                font-size: 24pt;
                font-weight: bold;
                color: #1c4966;
                margin: 10px 0;
            }
            
            .stat-label {
                font-size: 11pt;
                color: #666;
                font-weight: bold;
            }
            
            .stat-subtext {
                font-size: 10pt;
                color: #888;
                margin-top: 5px;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                page-break-inside: avoid;
            }
            
            .data-table th {
                background-color: #1c4966;
                color: white;
                font-weight: bold;
                padding: 12px;
                text-align: left;
                border: 1px solid #ddd;
            }
            
            .data-table td {
                padding: 10px;
                border: 1px solid #ddd;
            }
            
            .data-table tr:nth-child(even) {
                background-color: #f8fafc;
            }
            
            .data-table .total-row {
                background-color: #e3f2fd;
                font-weight: bold;
            }
            
            .highlight {
                background-color: #fff3cd !important;
            }
            
            .warning {
                background-color: #f8d7da !important;
            }
            
            .db-status {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin: 20px 0;
            }
            
            .db-card {
                border: 2px solid;
                padding: 15px;
                border-radius: 5px;
                text-align: center;
            }
            
            .db-connected {
                border-color: #5cb85c;
                background-color: rgba(92, 184, 92, 0.1);
            }
            
            .db-disconnected {
                border-color: #8a8a8a;
                background-color: rgba(138, 138, 138, 0.1);
                opacity: 0.7;
            }
            
            .db-name {
                font-weight: bold;
                font-size: 14pt;
                margin-bottom: 10px;
            }
            
            .db-text {
                font-size: 11pt;
                color: #666;
            }
            
            .print-actions {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                border: 2px solid #1c4966;
            }
            
            .print-btn {
                background: #1c4966;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                font-size: 14pt;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }
            
            .back-btn {
                background: #8a8a8a;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 12pt;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                font-size: 10pt;
                color: #888;
                text-align: center;
                page-break-before: always;
            }
            
            .page-break {
                page-break-before: always;
                margin-top: 50px;
            }
        </style>
    </head>
    <body>
        <!-- Print Actions -->
        <div class="print-actions no-print">
            <button onclick="window.print()" class="print-btn">
                🖨️ Print / Save as PDF
            </button>
            <button onclick="window.history.back()" class="back-btn">
                ← Back to Reports
            </button>
        </div>
        
        <!-- Report Header -->
        <div class="print-header">
            <div class="company-name">PHARMACY SYSTEM</div>
            <div class="report-title">Analytics Report</div>
            <div class="report-subtitle">Professional Healthcare Management</div>
            <div class="report-info">
                Generated on: <?php echo date('F j, Y, g:i a'); ?><br>
                Report Period: <?php echo $currentMonthName . ' ' . $currentYear; ?><br>
                Generated by: <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($userRole); ?>)
            </div>
        </div>
        
        <!-- Database Status -->
        <div class="section">
            <div class="section-title">Database Connection Status</div>
            <div class="db-status">
                <div class="db-card <?php echo $db_results['sqlsrv']['connected'] ? 'db-connected' : 'db-disconnected'; ?>">
                    <div class="db-name">SQL Server</div>
                    <div class="db-text">
                        <?php echo $db_results['sqlsrv']['connected'] ? 'Connected ✓' : 'Not Connected ✗'; ?>
                        <?php if ($db_results['sqlsrv']['connected'] && isset($db_results['sqlsrv']['data']['users'])): ?>
                        <div style="margin-top: 5px;">
                            Users: <?php echo $db_results['sqlsrv']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="db-card <?php echo $db_results['pgsql']['connected'] ? 'db-connected' : 'db-disconnected'; ?>">
                    <div class="db-name">PostgreSQL</div>
                    <div class="db-text">
                        <?php echo $db_results['pgsql']['connected'] ? 'Connected ✓' : 'Not Connected ✗'; ?>
                        <?php if ($db_results['pgsql']['connected'] && isset($db_results['pgsql']['data']['users'])): ?>
                        <div style="margin-top: 5px;">
                            Users: <?php echo $db_results['pgsql']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="db-card <?php echo $db_results['mysql']['connected'] ? 'db-connected' : 'db-disconnected'; ?>">
                    <div class="db-name">MySQL</div>
                    <div class="db-text">
                        <?php echo $db_results['mysql']['connected'] ? 'Connected ✓' : 'Not Connected ✗'; ?>
                        <?php if ($db_results['mysql']['connected'] && isset($db_results['mysql']['data']['users'])): ?>
                        <div style="margin-top: 5px;">
                            Users: <?php echo $db_results['mysql']['data']['users']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 15px; font-style: italic; color: #666;">
                Connected Databases: <?php echo $connectedDbs; ?>/3
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="section">
            <div class="section-title">Summary Statistics (Combined from all databases)</div>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-subtext">All databases combined</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalPatients; ?></div>
                    <div class="stat-label">Total Patients</div>
                    <div class="stat-subtext">All databases combined</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalPrescriptions; ?></div>
                    <div class="stat-label">Total Prescriptions</div>
                    <div class="stat-subtext">All databases combined</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $lowStock; ?></div>
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-subtext">Quantity < 20</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">RM <?php echo number_format($todaysSales, 2); ?></div>
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-subtext">All databases combined</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">RM <?php echo number_format($monthlySales, 2); ?></div>
                    <div class="stat-label">Monthly Sales</div>
                    <div class="stat-subtext">All databases combined</div>
                </div>
            </div>
        </div>
        
        <!-- Top Medicines -->
        <div class="section">
            <div class="section-title">Top 5 Prescribed Medicines</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Medicine Name</th>
                        <th style="width: 30%;">Times Prescribed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < count($medNames); $i++): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($medNames[$i]); ?></td>
                        <td><?php echo $medCounts[$i]; ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Stock Status -->
        <div class="section">
            <div class="section-title">Lowest Stock Medicines (Need Reorder)</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Medicine Name</th>
                        <th style="width: 30%;">Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < count($stockNames); $i++): ?>
                    <tr class="<?php echo $stockQty[$i] < 20 ? 'warning' : ''; ?>">
                        <td><?php echo htmlspecialchars($stockNames[$i]); ?></td>
                        <td><?php echo $stockQty[$i]; ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Daily Sales Table -->
        <div class="section page-break">
            <div class="section-title">Daily Sales for <?php echo $currentMonthName . ' ' . $currentYear; ?></div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Date</th>
                        <th style="width: 35%;">Day of Week</th>
                        <th style="width: 35%;">Sales (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalMonthly = 0;
                    for ($day = 1; $day <= $daysInMonth; $day++): 
                        $sales = $monthlySalesData[$day];
                        $totalMonthly += $sales;
                        $dateString = $currentMonthName . ' ' . $day . ', ' . $currentYear;
                        $dayOfWeek = date('l', strtotime($currentYear . '-' . $currentMonth . '-' . $day));
                    ?>
                    <tr>
                        <td><?php echo $day . ' ' . $currentMonthName; ?></td>
                        <td><?php echo $dayOfWeek; ?></td>
                        <td><?php echo ($sales > 0 ? 'RM ' . number_format($sales, 2) : '-'); ?></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <tr class="total-row">
                        <td colspan="2"><strong>Total Monthly Sales:</strong></td>
                        <td><strong>RM <?php echo number_format($totalMonthly, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>Report ID: PHARM-<?php echo date('Ymd-His'); ?></div>
            <div>© <?php echo date('Y'); ?> Pharmacy System. All rights reserved.</div>
            <div>This report is generated automatically and should be considered confidential.</div>
        </div>
        
        <script>
            // Auto-print option
            window.onload = function() {
                // Auto-print after 1 second
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
            
            // Listen for print completion
            window.onafterprint = function() {
                // Optionally go back after printing
                // window.history.back();
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_POST['export_excel'])) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Pharmacy_Report_' . date('Y_m_d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Start output
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background-color: #1c4966; color: white; font-weight: bold; padding: 10px; }
            .title { font-size: 18px; font-weight: bold; margin: 10px 0; }
            .subtitle { font-size: 14px; color: #666; margin-bottom: 15px; }
            .section { margin: 20px 0; border: 1px solid #ddd; }
            .section-title { background-color: #f2f2f2; font-weight: bold; padding: 8px; border-bottom: 1px solid #ddd; }
            .data-table { width: 100%; border-collapse: collapse; }
            .data-table th { background-color: #2a5d7a; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
            .data-table td { padding: 8px; border: 1px solid #ddd; }
            .stat-box { background-color: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #1c4966; }
            .highlight { background-color: #fff3cd; }
            .total-row { font-weight: bold; background-color: #f8f9fa; }
        </style>
    </head>
    <body>';
    
    // Report Header
    echo '<div class="title" style="text-align: center;">Pharmacy Analytics Report</div>';
    echo '<div class="subtitle" style="text-align: center;">Generated on: ' . date('F j, Y, g:i a') . '</div>';
    echo '<div class="subtitle" style="text-align: center;">Report Period: ' . $currentMonthName . ' ' . $currentYear . '</div>';
    
    // Database Connection Status
    echo '<div class="section">';
    echo '<div class="section-title">Database Connection Status</div>';
    echo '<div class="stat-box">';
    echo '<strong>Connected Databases:</strong> ' . $connectedDbs . '/3<br>';
    echo '<strong>SQL Server:</strong> ' . ($db_results['sqlsrv']['connected'] ? 'Connected' : 'Disconnected') . '<br>';
    echo '<strong>PostgreSQL:</strong> ' . ($db_results['pgsql']['connected'] ? 'Connected' : 'Disconnected') . '<br>';
    echo '<strong>MySQL:</strong> ' . ($db_results['mysql']['connected'] ? 'Connected' : 'Disconnected');
    echo '</div>';
    echo '</div>';
    
    // Summary Statistics
    echo '<div class="section">';
    echo '<div class="section-title">Summary Statistics (Combined from all databases)</div>';
    echo '<table class="data-table">';
    echo '<tr>
            <th style="width: 60%;">Metric</th>
            <th style="width: 40%;">Value</th>
          </tr>';
    echo '<tr>
            <td>Total Users</td>
            <td>' . $totalUsers . '</td>
          </tr>';
    echo '<tr>
            <td>Total Patients</td>
            <td>' . $totalPatients . '</td>
          </tr>';
    echo '<tr>
            <td>Total Prescriptions</td>
            <td>' . $totalPrescriptions . '</td>
          </tr>';
    echo '<tr>
            <td>Low Stock Items</td>
            <td>' . $lowStock . '</td>
          </tr>';
    echo '<tr>
            <td>Today\'s Sales</td>
            <td>RM ' . number_format($todaysSales, 2) . '</td>
          </tr>';
    echo '<tr class="total-row">
            <td>Monthly Sales</td>
            <td>RM ' . number_format($monthlySales, 2) . '</td>
          </tr>';
    echo '</table>';
    echo '</div>';
    
    // Top Medicines
    echo '<div class="section">';
    echo '<div class="section-title">Top 5 Prescribed Medicines</div>';
    echo '<table class="data-table">';
    echo '<tr>
            <th style="width: 70%;">Medicine Name</th>
            <th style="width: 30%;">Times Prescribed</th>
          </tr>';
    for ($i = 0; $i < count($medNames); $i++) {
        echo '<tr>
                <td>' . htmlspecialchars($medNames[$i]) . '</td>
                <td>' . $medCounts[$i] . '</td>
              </tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // Stock Status
    echo '<div class="section">';
    echo '<div class="section-title">Lowest Stock Medicines (Need Reorder)</div>';
    echo '<table class="data-table">';
    echo '<tr>
            <th style="width: 70%;">Medicine Name</th>
            <th style="width: 30%;">Current Stock</th>
          </tr>';
    for ($i = 0; $i < count($stockNames); $i++) {
        $rowClass = $stockQty[$i] < 20 ? 'class="highlight"' : '';
        echo '<tr ' . $rowClass . '>
                <td>' . htmlspecialchars($stockNames[$i]) . '</td>
                <td>' . $stockQty[$i] . '</td>
              </tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // Daily Sales Table
    echo '<div class="section">';
    echo '<div class="section-title">Daily Sales for ' . $currentMonthName . ' ' . $currentYear . '</div>';
    echo '<table class="data-table">';
    echo '<tr>
            <th style="width: 30%;">Date</th>
            <th style="width: 35%;">Day of Week</th>
            <th style="width: 35%;">Sales (RM)</th>
          </tr>';
    
    $totalMonthly = 0;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $sales = $monthlySalesData[$day];
        $totalMonthly += $sales;
        $dateString = $currentMonthName . ' ' . $day . ', ' . $currentYear;
        $dayOfWeek = date('l', strtotime($currentYear . '-' . $currentMonth . '-' . $day));
        
        echo '<tr>
                <td>' . $day . ' ' . $currentMonthName . '</td>
                <td>' . $dayOfWeek . '</td>
                <td>' . ($sales > 0 ? 'RM ' . number_format($sales, 2) : '-') . '</td>
              </tr>';
    }
    
    echo '<tr class="total-row">
            <td colspan="2"><strong>Total Monthly Sales:</strong></td>
            <td><strong>RM ' . number_format($totalMonthly, 2) . '</strong></td>
          </tr>';
    echo '</table>';
    echo '</div>';
    
    // Footer
    echo '<div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 11px; color: #666;">
            Report generated by: ' . htmlspecialchars($username) . '<br>
            User Role: ' . htmlspecialchars($userRole) . '<br>
            Generated on: ' . date('Y-m-d H:i:s') . '
          </div>';
    
    echo '</body></html>';
    exit;
}
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

        /* Export Buttons Container */
        .export-container {
            display: flex;
            gap: 10px;
        }

        /* Export Button Styles */
        .export-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .export-pdf {
            background: linear-gradient(135deg, #d9534f, #c9302c);
            color: white;
        }

        .export-pdf:hover {
            background: linear-gradient(135deg, #c9302c, #ac2925);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 83, 79, 0.3);
        }

        .export-excel {
            background: linear-gradient(135deg, #5cb85c, #449d44);
            color: white;
        }

        .export-excel:hover {
            background: linear-gradient(135deg, #449d44, #398439);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(92, 184, 92, 0.3);
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

        /* Export Notice */
        .export-notice {
            background: var(--blue-light);
            border-left: 4px solid var(--blue-accent);
            padding: 15px 20px;
            margin-top: 20px;
            border-radius: 8px;
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
                flex-direction: column;
                align-items: flex-start;
            }
            
            .export-container {
                width: 100%;
                justify-content: flex-start;
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
            
            .export-btn {
                width: 100%;
                justify-content: center;
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
                        <li><a href="reports.php" class="active"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
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
                    <h1>Reports</h1>
                    <p>Pharmacy Performance Dashboard - <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <!-- Export Buttons -->
                    <div class="export-container">
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="export_pdf" class="export-btn export-pdf">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </form>
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="export_excel" class="export-btn export-excel">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                        </form>
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

                <!-- Charts Section -->
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

                <!-- Statistics Cards -->
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

                <!-- Export Notice -->
                <div class="export-notice">
                    <i class="fas fa-info-circle"></i>
                    <strong>Professional Reports Available:</strong> Export complete analytics as PDF or Excel format. 
                    PDF reports include detailed tables and formatting. Excel exports include color-coded data for easy analysis.
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
        // Sales Chart with colorful gradient
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(76, 175, 80, 0.8)');
        gradient.addColorStop(0.5, 'rgba(33, 150, 243, 0.6)');
        gradient.addColorStop(1, 'rgba(76, 175, 80, 0.1)');
        
        // Truncate long labels for better display
        const salesLabels = <?php echo json_encode($salesDays); ?>;
        const truncatedLabels = salesLabels.map(label => {
            if (salesLabels.length > 15) {
                const parts = label.split(' ');
                return parts[0];
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
                    borderColor: '#4CAF50',
                    backgroundColor: gradient,
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2196F3',
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
                        '#FF5252',
                        '#FF4081',
                        '#E040FB',
                        '#7C4DFF',
                        '#536DFE',
                        '#448AFF',
                        '#40C4FF',
                        '#18FFFF',
                        '#64FFDA',
                        '#69F0AE'
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
                        '#FF5252',
                        '#FF9800',
                        '#FFEB3B',
                        '#4CAF50',
                        '#2196F3',
                        '#9C27B0',
                        '#E91E63',
                        '#00BCD4',
                        '#8BC34A',
                        '#FF5722'
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