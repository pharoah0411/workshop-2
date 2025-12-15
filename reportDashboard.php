<?php
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

// Helper to determine active connection and syntax
$db = null;
$type = '';

if (isset($pg_conn) && $pg_conn instanceof PDO) {
    $db = $pg_conn;
    $type = 'pgsql';
} elseif (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    $db = $mysql_conn2;
    $type = 'mysql';
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $type = 'sqlsrv';
}

if ($db) {
    try {
        // 1. TOTAL USERS (Table: "user" in Postgres, `user` in MySQL)
        $user_table = ($type === 'pgsql') ? '"user"' : (($type === 'mysql') ? '`user`' : '[USER]');
        
        if ($type === 'mysql') {
            $res = $db->query("SELECT COUNT(*) AS total FROM $user_table");
            $totalUsers = $res->fetch_assoc()['total'];
        } else {
            $stmt = $db->query("SELECT COUNT(*) AS total FROM $user_table");
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        // 2. TOTAL PATIENTS
        if ($type === 'mysql') {
            $res = $db->query("SELECT COUNT(*) AS total FROM patient");
            $totalPatients = $res->fetch_assoc()['total'];
        } else {
            $stmt = $db->query("SELECT COUNT(*) AS total FROM patient");
            $totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        // 3. TOTAL PRESCRIPTIONS
        if ($type === 'mysql') {
            $res = $db->query("SELECT COUNT(*) AS total FROM prescription");
            $totalPrescriptions = $res->fetch_assoc()['total'];
        } else {
            $stmt = $db->query("SELECT COUNT(*) AS total FROM prescription");
            $totalPrescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        // 4. LOW STOCK MEDICINES
        // Note: Using table name MEDICINE (uppercase/consistent with medDirectory) or medicine (lowercase)
        // Trying lowercase first as per reports file, but fallback logic might be needed.
        // Assuming 'medicine' based on provided 'reports' file content.
        $med_table = 'medicine'; 
        
        if ($type === 'mysql') {
            $res = $db->query("SELECT COUNT(*) AS total FROM $med_table WHERE quantity_in_stock < 20");
            $lowStock = $res->fetch_assoc()['total'];
        } else {
            $stmt = $db->query("SELECT COUNT(*) AS total FROM $med_table WHERE quantity_in_stock < 20");
            $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        // 5. SALES (TODAY & MONTHLY) - Using 'payment' table
        $date_cond_today = ($type === 'pgsql') ? "DATE(payment_date) = CURRENT_DATE" : "DATE(payment_date) = CURDATE()";
        if ($type === 'sqlsrv') $date_cond_today = "CAST(payment_date AS DATE) = CAST(GETDATE() AS DATE)";

        $date_cond_month = ($type === 'pgsql') ? "TO_CHAR(payment_date, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')" : "MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
        if ($type === 'sqlsrv') $date_cond_month = "MONTH(payment_date) = MONTH(GETDATE()) AND YEAR(payment_date) = YEAR(GETDATE())";

        // Query Execution Helper
        function getSum($db, $type, $table, $col, $cond) {
            $sql = "SELECT COALESCE(SUM($col), 0) AS total FROM $table WHERE $cond";
            if ($type === 'mysql') {
                $res = $db->query($sql);
                return $res ? $res->fetch_assoc()['total'] : 0;
            } else {
                $stmt = $db->query($sql);
                return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC)['total'] : 0;
            }
        }

        $todaysSales = getSum($db, $type, 'payment', 'total_amount', $date_cond_today);
        $monthlySales = getSum($db, $type, 'payment', 'total_amount', $date_cond_month);

        // 6. SALES CHART (Daily Sales)
        $sql_chart = "";
        if ($type === 'pgsql') {
            $sql_chart = "SELECT EXTRACT(DAY FROM payment_date) as day, SUM(total_amount) as total FROM payment WHERE TO_CHAR(payment_date, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM') GROUP BY day ORDER BY day";
        } elseif ($type === 'mysql') {
            $sql_chart = "SELECT DAY(payment_date) AS day, SUM(total_amount) AS total FROM payment WHERE MONTH(payment_date) = MONTH(CURDATE()) GROUP BY day ORDER BY day";
        } elseif ($type === 'sqlsrv') {
            $sql_chart = "SELECT DAY(payment_date) AS day, SUM(total_amount) AS total FROM payment WHERE MONTH(payment_date) = MONTH(GETDATE()) GROUP BY DAY(payment_date) ORDER BY day";
        }

        if ($type === 'mysql') {
            $res = $db->query($sql_chart);
            while($row = $res->fetch_assoc()) { $salesDays[] = $row['day']; $salesTotals[] = $row['total']; }
        } else {
            $stmt = $db->query($sql_chart);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $salesDays[] = $row['day']; $salesTotals[] = $row['total']; }
        }

        // 7. TOP 5 MEDICINES
        // Note: Joins prescription_detail and medicine
        $sql_top = "
            SELECT m.name, COUNT(pd.medicine_id) AS times_prescribed
            FROM prescription_detail pd
            JOIN medicine m ON pd.medicine_id = m.medicine_id
            GROUP BY m.name
            ORDER BY times_prescribed DESC
            LIMIT 5
        ";
        // SQL Server uses TOP instead of LIMIT
        if ($type === 'sqlsrv') {
            $sql_top = "
                SELECT TOP 5 m.name, COUNT(pd.medicine_id) AS times_prescribed
                FROM prescription_detail pd
                JOIN medicine m ON pd.medicine_id = m.medicine_id
                GROUP BY m.name
                ORDER BY times_prescribed DESC
            ";
        }

        if ($type === 'mysql') {
            $res = $db->query($sql_top);
            if($res) while($row = $res->fetch_assoc()) { $medNames[] = $row['name']; $medCounts[] = $row['times_prescribed']; }
        } else {
            $stmt = $db->query($sql_top);
            if($stmt) while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $medNames[] = $row['name']; $medCounts[] = $row['times_prescribed']; }
        }

        // 8. STOCK PIE CHART
        $sql_stock = "SELECT name, quantity_in_stock FROM medicine ORDER BY quantity_in_stock ASC LIMIT 5";
        if ($type === 'sqlsrv') $sql_stock = "SELECT TOP 5 name, quantity_in_stock FROM medicine ORDER BY quantity_in_stock ASC";

        if ($type === 'mysql') {
            $res = $db->query($sql_stock);
            if($res) while($row = $res->fetch_assoc()) { $stockNames[] = $row['name']; $stockQty[] = $row['quantity_in_stock']; }
        } else {
            $stmt = $db->query($sql_stock);
            if($stmt) while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $stockNames[] = $row['name']; $stockQty[] = $row['quantity_in_stock']; }
        }

    } catch (Exception $e) {
        $error = "Data Fetch Error: " . $e->getMessage();
    }
} else {
    $error = "No database connection available.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports & Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #f0f4ff; }
        .sidebar { width: 250px; background: #0b2f6d; height: 100vh; position: fixed; padding: 20px; color: white; }
        .sidebar a { display: block; padding: 12px; color: white; text-decoration: none; margin: 5px 0; border-radius: 5px; }
        .sidebar a:hover { background: #11408a; }
        .content { margin-left: 290px; padding: 20px; }
        
        .card-container { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .card { background: white; flex: 1; min-width: 200px; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        .card h2 { margin: 10px 0; color: #0b2f6d; font-size: 2.5em; }
        .card p { color: #666; font-weight: bold; }
        
        .charts-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .chart-box { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #0b2f6d; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Pharmacy System</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="medDirectory.php">Medicines</a>
    <a href="prescriptionDashboard.php">Prescriptions</a>
    <a href="sales_billing.php">Sales & Billing</a>
    <a href="reports.php" style="background:#11408a;">Reports</a>
    <a style="background:#c62828; margin-top: 50px;" href="logout.php">Logout</a>
</div>

<div class="content">

    <h1>📊 Reports & Analytics Dashboard</h1>
    
    <?php if(isset($error)) echo "<p style='color:red; background:white; padding:10px; border-radius:5px;'>$error</p>"; ?>

    <div class="card-container">
        <div class="card"><h2><?= $totalUsers ?></h2><p>Total Users</p></div>
        <div class="card"><h2><?= $totalPatients ?></h2><p>Total Patients</p></div>
        <div class="card"><h2><?= $totalPrescriptions ?></h2><p>Total Prescriptions</p></div>
        <div class="card"><h2><?= $lowStock ?></h2><p>Low Stock Medicines</p></div>
    </div>

    <div class="card-container">
        <div class="card"><h2>RM <?= number_format((float)$todaysSales, 2) ?></h2><p>Today's Sales</p></div>
        <div class="card"><h2>RM <?= number_format((float)$monthlySales, 2) ?></h2><p>Monthly Sales</p></div>
    </div>

    <h2>📈 Charts</h2>

    <div class="charts-container">
        <div class="chart-box">
            <canvas id="salesChart"></canvas>
        </div>

        <div class="chart-box">
            <canvas id="medChart"></canvas>
        </div>

        <div class="chart-box" style="grid-column: span 2;">
            <canvas id="stockChart" style="max-height: 400px;"></canvas>
        </div>
    </div>

</div>

<script>
    // Sales Chart
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($salesDays) ?>,
            datasets: [{
                label: 'Daily Sales (RM)',
                data: <?= json_encode($salesTotals) ?>,
                borderColor: '#0b2f6d',
                backgroundColor: 'rgba(11, 47, 109, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: { plugins: { title: { display: true, text: 'Daily Sales Performance' } } }
    });

    // Top Medicines Chart
    new Chart(document.getElementById('medChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($medNames) ?>,
            datasets: [{
                label: 'Times Prescribed',
                data: <?= json_encode($medCounts) ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
            }]
        },
        options: { 
            plugins: { title: { display: true, text: 'Top 5 Most Prescribed Medicines' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Stock Pie Chart
    new Chart(document.getElementById('stockChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($stockNames) ?>,
            datasets: [{
                data: <?= json_encode($stockQty) ?>,
                backgroundColor: ['#ff7675', '#74b9ff', '#55efc4', '#a29bfe', '#ffeaa7']
            }]
        },
        options: { 
            plugins: { title: { display: true, text: 'Lowest Stock Medicines' } },
            maintainAspectRatio: false
        }
    });
</script>

</body>
</html>