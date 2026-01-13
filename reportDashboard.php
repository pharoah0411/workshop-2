<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'connection.php';

// Initialize variables
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

// Database connection priority: SQL Server > PostgreSQL > MySQL
$db = null;
$db_type = '';
$error = '';

// Determine which database connection to use
if ($pdo_sqlsrv) {
    $db = $pdo_sqlsrv;
    $db_type = 'sqlsrv';
    $error .= "<!-- Using SQL Server -->\n";
} elseif ($pg_conn) {
    $db = $pg_conn;
    $db_type = 'pgsql';
    $error .= "<!-- Using PostgreSQL -->\n";
} elseif ($mysql_conn2) {
    $db = $mysql_conn2;
    $db_type = 'mysql';
    $error .= "<!-- Using MySQL -->\n";
}

if ($db) {
    try {
        // Function to execute query based on database type
        function executeQuery($db, $db_type, $sql) {
            if ($db_type === 'mysql') {
                $result = $db->query($sql);
                return $result ? $result->fetch_assoc() : ['total' => 0];
            } else {
                $stmt = $db->query($sql);
                return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : ['total' => 0];
            }
        }
        
        // Function to fetch all rows
        function fetchAllRows($db, $db_type, $sql) {
            $data = [];
            if ($db_type === 'mysql') {
                $result = $db->query($sql);
                if ($result) {
                    while($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
            } else {
                $stmt = $db->query($sql);
                if ($stmt) {
                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $data[] = $row;
                    }
                }
            }
            return $data;
        }

        // 1. TOTAL USERS (Different table names per database)
        $user_table = '';
        if ($db_type === 'sqlsrv') {
            $user_table = '[USER]'; // SQL Server uses brackets
        } elseif ($db_type === 'pgsql') {
            $user_table = '"user"'; // PostgreSQL uses quotes
        } else {
            $user_table = '`user`'; // MySQL uses backticks
        }
        
        $result = executeQuery($db, $db_type, "SELECT COUNT(*) AS total FROM $user_table");
        $totalUsers = $result['total'];
        
        // 2. TOTAL PATIENTS
        $result = executeQuery($db, $db_type, "SELECT COUNT(*) AS total FROM patient");
        $totalPatients = $result['total'];
        
        // 3. TOTAL PRESCRIPTIONS
        $result = executeQuery($db, $db_type, "SELECT COUNT(*) AS total FROM prescription");
        $totalPrescriptions = $result['total'];
        
        // 4. LOW STOCK MEDICINES
        $result = executeQuery($db, $db_type, "SELECT COUNT(*) AS total FROM medicine WHERE quantity_in_stock < 20");
        $lowStock = $result['total'];
        
        // 5. TODAY'S SALES
        $todayCondition = '';
        if ($db_type === 'sqlsrv') {
            $todayCondition = "CAST(payment_date AS DATE) = CAST(GETDATE() AS DATE)";
        } elseif ($db_type === 'pgsql') {
            $todayCondition = "DATE(payment_date) = CURRENT_DATE";
        } else {
            $todayCondition = "DATE(payment_date) = CURDATE()";
        }
        
        $result = executeQuery($db, $db_type, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE $todayCondition");
        $todaysSales = $result['total'];
        
        // 6. MONTHLY SALES
        $monthCondition = '';
        if ($db_type === 'sqlsrv') {
            $monthCondition = "MONTH(payment_date) = MONTH(GETDATE()) AND YEAR(payment_date) = YEAR(GETDATE())";
        } elseif ($db_type === 'pgsql') {
            $monthCondition = "EXTRACT(MONTH FROM payment_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM payment_date) = EXTRACT(YEAR FROM CURRENT_DATE)";
        } else {
            $monthCondition = "MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
        }
        
        $result = executeQuery($db, $db_type, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM payment WHERE $monthCondition");
        $monthlySales = $result['total'];
        
        // 7. DAILY SALES CHART DATA (Last 7 days)
        $salesSql = '';
        if ($db_type === 'sqlsrv') {
            $salesSql = "SELECT DAY(payment_date) as day, SUM(total_amount) as total 
                        FROM payment 
                        WHERE payment_date >= DATEADD(day, -7, GETDATE())
                        GROUP BY DAY(payment_date), payment_date
                        ORDER BY payment_date";
        } elseif ($db_type === 'pgsql') {
            $salesSql = "SELECT EXTRACT(DAY FROM payment_date) as day, SUM(total_amount) as total 
                        FROM payment 
                        WHERE payment_date >= CURRENT_DATE - INTERVAL '7 days'
                        GROUP BY EXTRACT(DAY FROM payment_date), payment_date
                        ORDER BY payment_date";
        } else {
            $salesSql = "SELECT DAY(payment_date) as day, SUM(total_amount) as total 
                        FROM payment 
                        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY DAY(payment_date), payment_date
                        ORDER BY payment_date";
        }
        
        $salesData = fetchAllRows($db, $db_type, $salesSql);
        
        if (!empty($salesData)) {
            foreach ($salesData as $row) {
                $salesDays[] = $row['day'];
                $salesTotals[] = floatval($row['total']);
            }
        }
        
        // If no recent data, show last 7 days with zero values
        if (empty($salesDays)) {
            for ($i = 6; $i >= 0; $i--) {
                $date = date('d', strtotime("-$i days"));
                $salesDays[] = $date;
                $salesTotals[] = 0;
            }
        }
        
        // 8. TOP 5 MEDICINES
        $topMedSql = '';
        if ($db_type === 'sqlsrv') {
            $topMedSql = "SELECT TOP 5 m.name, COUNT(pd.medicine_id) AS times_prescribed
                         FROM prescription_detail pd
                         JOIN medicine m ON pd.medicine_id = m.medicine_id
                         GROUP BY m.name, m.medicine_id
                         ORDER BY times_prescribed DESC";
        } else {
            $topMedSql = "SELECT m.name, COUNT(pd.medicine_id) AS times_prescribed
                         FROM prescription_detail pd
                         JOIN medicine m ON pd.medicine_id = m.medicine_id
                         GROUP BY m.name, m.medicine_id
                         ORDER BY times_prescribed DESC
                         LIMIT 5";
        }
        
        $medData = fetchAllRows($db, $db_type, $topMedSql);
        foreach ($medData as $row) {
            $medNames[] = $row['name'];
            $medCounts[] = intval($row['times_prescribed']);
        }
        
        // 9. STOCK PIE CHART (Lowest 5 medicines)
        $stockSql = '';
        if ($db_type === 'sqlsrv') {
            $stockSql = "SELECT TOP 5 name, quantity_in_stock 
                        FROM medicine 
                        WHERE quantity_in_stock > 0
                        ORDER BY quantity_in_stock ASC";
        } else {
            $stockSql = "SELECT name, quantity_in_stock 
                        FROM medicine 
                        WHERE quantity_in_stock > 0
                        ORDER BY quantity_in_stock ASC
                        LIMIT 5";
        }
        
        $stockData = fetchAllRows($db, $db_type, $stockSql);
        foreach ($stockData as $row) {
            $stockNames[] = $row['name'];
            $stockQty[] = intval($row['quantity_in_stock']);
        }
        
        $error .= "<!-- Connected to: $db_type -->\n";
        
    } catch (Exception $e) {
        $error .= "Database Error (" . $db_type . "): " . htmlspecialchars($e->getMessage());
    }
} else {
    $error = "No database connection available. Please check your connection settings.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Reports & Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #0b2f6d 0%, #1a4a8f 100%);
            color: white;
            padding: 25px 20px;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo h2 {
            font-size: 22px;
            font-weight: 600;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            color: #d1d9f0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .logout-btn {
            margin-top: 40px;
            padding: 14px;
            background-color: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .logout-btn:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e6ef;
        }

        .header h1 {
            color: #0b2f6d;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .db-indicator {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background-color: #0b2f6d;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid #0b2f6d;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #0b2f6d;
            margin-bottom: 10px;
        }

        .stat-card.sales .value {
            color: #28a745;
        }

        .stat-card.patients .value {
            color: #17a2b8;
        }

        .stat-card.stock .value {
            color: #ffc107;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .chart-container h2 {
            color: #0b2f6d;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #c33;
        }

        .db-info {
            background-color: #e8f4fd;
            color: #0b2f6d;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #0b2f6d;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 10px;
            }
            
            .logo h2, .nav-links a span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <h2>🏥 PharmaSys</h2>
            </div>
            
            <ul class="nav-links">
                <li><a href="dashboard.php"><i>📊</i> <span>Dashboard</span></a></li>
                <li><a href="medDirectory.php"><i>💊</i> <span>Medicines</span></a></li>
                <li><a href="prescriptionDashboard.php"><i>📋</i> <span>Prescriptions</span></a></li>
                <li><a href="sales_billing.php"><i>💰</i> <span>Sales & Billing</span></a></li>
                <li><a href="user_management.php"><i>👥</i> <span>User Management</span></a></li>
                <li><a href="reports.php" class="active"><i>📈</i> <span>Reports & Analytics</span></a></li>
            </ul>
            
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i>🚪</i> <span>Logout</span>
            </button>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <h1><i>📈</i> Reports & Analytics</h1>
                    <div class="db-info">
                        Connected to: <strong><?php echo strtoupper($db_type); ?> Database</strong>
                    </div>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php 
                        $initial = isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U';
                        echo $initial;
                        ?>
                    </div>
                    <div>
                        <h3><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></h3>
                        <p style="color: #666; font-size: 14px;">Admin</p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error) && strpos($error, 'Error:') !== false): ?>
                <div class="error-message">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="value"><?php echo $totalUsers; ?></div>
                </div>
                
                <div class="stat-card patients">
                    <h3>Total Patients</h3>
                    <div class="value"><?php echo $totalPatients; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Prescriptions</h3>
                    <div class="value"><?php echo $totalPrescriptions; ?></div>
                </div>
                
                <div class="stat-card stock">
                    <h3>Low Stock Medicines</h3>
                    <div class="value"><?php echo $lowStock; ?></div>
                </div>
                
                <div class="stat-card sales">
                    <h3>Today's Sales</h3>
                    <div class="value">RM <?php echo number_format($todaysSales, 2); ?></div>
                </div>
                
                <div class="stat-card sales">
                    <h3>Monthly Sales</h3>
                    <div class="value">RM <?php echo number_format($monthlySales, 2); ?></div>
                </div>
            </div>
            
            <div class="charts-grid">
                <div class="chart-container">
                    <h2><i>📊</i> Daily Sales (Last 7 Days)</h2>
                    <div class="chart-wrapper">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h2><i>💊</i> Top 5 Prescribed Medicines</h2>
                    <div class="chart-wrapper">
                        <canvas id="medChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container" style="grid-column: span 2;">
                    <h2><i>📦</i> Lowest Stock Medicines</h2>
                    <div class="chart-wrapper">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($salesDays); ?>,
                datasets: [{
                    label: 'Sales (RM)',
                    data: <?php echo json_encode($salesTotals); ?>,
                    borderColor: '#0b2f6d',
                    backgroundColor: 'rgba(11, 47, 109, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#0b2f6d',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
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
                        }
                    }
                }
            }
        });
        
        // Top Medicines Chart
        const medCtx = document.getElementById('medChart').getContext('2d');
        new Chart(medCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($medNames); ?>,
                datasets: [{
                    label: 'Times Prescribed',
                    data: <?php echo json_encode($medCounts); ?>,
                    backgroundColor: 'rgba(11, 47, 109, 0.8)',
                    borderColor: '#0b2f6d',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Stock Chart
        const stockCtx = document.getElementById('stockChart').getContext('2d');
        new Chart(stockCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($stockNames); ?>,
                datasets: [{
                    data: <?php echo json_encode($stockQty); ?>,
                    backgroundColor: [
                        '#ff7675', '#74b9ff', '#55efc4', '#a29bfe', '#ffeaa7'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%'
            }
        });
    </script>
</body>
</html>