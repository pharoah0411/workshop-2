<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Admin';

// --- 1. INITIALIZE ALL AGGREGATED METRICS (DO NOT DELETE) ---
$totalUsers = 0;
$totalPatients = 0;
$totalPrescriptions = 0;
$lowStockCount = 0;
$monthlySales = 0.00;
$yearlySales = 0.00;

$dailySalesData = []; 
$medicineUsage = []; 
$patientEngagement = []; 

// Initialize current month's days for chart labels
$daysInMonth = date('t');
for($i = 1; $i <= $daysInMonth; $i++) {
    $dateLabel = date('d M', strtotime(date('Y-m-') . $i));
    $dailySalesData[$dateLabel] = 0;
}

// Connection Health Check
$db_status = [
    'MySQL' => (isset($mysql_conn2) && !($mysql_conn2->connect_error ?? false)),
    'Postgres' => isset($pg_conn),
    'SQLServer' => isset($pdo)
];

// --- 2. GLOBAL AGGREGATION LOGIC (FETCH FROM ALL DBs) ---
foreach ($db_status as $db_type => $is_online) {
    if (!$is_online) continue;
    try {
        if ($db_type === 'MySQL') {
            // Totals
            $totalUsers += $mysql_conn2->query("SELECT COUNT(*) FROM `USER`")->fetch_row()[0];
            $totalPatients += $mysql_conn2->query("SELECT COUNT(*) FROM PATIENT")->fetch_row()[0];
            $totalPrescriptions += $mysql_conn2->query("SELECT COUNT(*) FROM PRESCRIPTION")->fetch_row()[0];
            $lowStockCount += $mysql_conn2->query("SELECT COUNT(*) FROM MEDICINE WHERE QUANTITY_IN_STOCK < 50")->fetch_row()[0];

            // Sales (Monthly/Yearly)
            $res = $mysql_conn2->query("SELECT DAY(PAYMENT_DATE) as d, SUM(TOTAL_AMOUNT) as total FROM PAYMENT WHERE MONTH(PAYMENT_DATE) = MONTH(CURDATE()) AND YEAR(PAYMENT_DATE) = YEAR(CURDATE()) GROUP BY d");
            while($r = $res->fetch_assoc()) {
                $lbl = date('d M', strtotime(date('Y-m-').$r['d']));
                $dailySalesData[$lbl] += $r['total']; $monthlySales += $r['total'];
            }
            $yearlySales += $mysql_conn2->query("SELECT SUM(TOTAL_AMOUNT) FROM PAYMENT WHERE YEAR(PAYMENT_DATE) = YEAR(CURDATE())")->fetch_row()[0] ?? 0;

            // Rankings
            $m_res = $mysql_conn2->query("SELECT m.NAME, COUNT(pd.MEDICINE_ID) as count FROM PRESCRIPTION_DETAIL pd JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID GROUP BY m.NAME");
            while($r = $m_res->fetch_assoc()) { $medicineUsage[$r['NAME']] = ($medicineUsage[$r['NAME']] ?? 0) + $r['count']; }
            $p_res = $mysql_conn2->query("SELECT p.NAME, COUNT(pr.PRESCRIPTION_ID) as count FROM PRESCRIPTION pr JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID GROUP BY p.NAME");
            while($r = $p_res->fetch_assoc()) { $patientEngagement[$r['NAME']] = ($patientEngagement[$r['NAME']] ?? 0) + $r['count']; }

        } elseif ($db_type === 'Postgres') {
            $totalUsers += $pg_conn->query('SELECT COUNT(*) FROM "user"')->fetchColumn();
            $totalPatients += $pg_conn->query('SELECT COUNT(*) FROM PATIENT')->fetchColumn();
            $totalPrescriptions += $pg_conn->query('SELECT COUNT(*) FROM PRESCRIPTION')->fetchColumn();
            $lowStockCount += $pg_conn->query('SELECT COUNT(*) FROM MEDICINE WHERE QUANTITY_IN_STOCK < 50')->fetchColumn();

            $res = $pg_conn->query("SELECT EXTRACT(DAY FROM PAYMENT_DATE) as d, SUM(TOTAL_AMOUNT) as total FROM PAYMENT WHERE TO_CHAR(PAYMENT_DATE, 'MM') = TO_CHAR(CURRENT_DATE, 'MM') GROUP BY d");
            while($r = $res->fetch(PDO::FETCH_ASSOC)) {
                $lbl = date('d M', strtotime(date('Y-m-').(int)$r['d']));
                $dailySalesData[$lbl] += $r['total']; $monthlySales += $r['total'];
            }
            $yearlySales += $pg_conn->query("SELECT SUM(TOTAL_AMOUNT) FROM PAYMENT WHERE EXTRACT(YEAR FROM PAYMENT_DATE) = EXTRACT(YEAR FROM CURRENT_DATE)")->fetchColumn() ?? 0;

            $m_res = $pg_conn->query("SELECT m.NAME, COUNT(pd.MEDICINE_ID) as count FROM PRESCRIPTION_DETAIL pd JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID GROUP BY m.NAME");
            while($r = $m_res->fetch(PDO::FETCH_ASSOC)) { $medicineUsage[$r['name']] = ($medicineUsage[$r['name']] ?? 0) + $r['count']; }
            $p_res = $pg_conn->query("SELECT p.NAME, COUNT(pr.PRESCRIPTION_ID) as count FROM PRESCRIPTION pr JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID GROUP BY p.NAME");
            while($r = $p_res->fetch(PDO::FETCH_ASSOC)) { $patientEngagement[$r['name']] = ($patientEngagement[$r['name']] ?? 0) + $r['count']; }

        } elseif ($db_type === 'SQLServer') {
            $totalUsers += $pdo->query("SELECT COUNT(*) FROM [USER]")->fetchColumn();
            $totalPatients += $pdo->query("SELECT COUNT(*) FROM PATIENT")->fetchColumn();
            $totalPrescriptions += $pdo->query("SELECT COUNT(*) FROM PRESCRIPTION")->fetchColumn();
            $lowStockCount += $pdo->query("SELECT COUNT(*) FROM MEDICINE WHERE QUANTITY_IN_STOCK < 50")->fetchColumn();

            $res = $pdo->query("SELECT DAY(PAYMENT_DATE) as d, SUM(TOTAL_AMOUNT) as total FROM PAYMENT WHERE MONTH(PAYMENT_DATE) = MONTH(GETDATE()) GROUP BY DAY(PAYMENT_DATE)");
            while($r = $res->fetch(PDO::FETCH_ASSOC)) {
                $lbl = date('d M', strtotime(date('Y-m-').$r['d']));
                $dailySalesData[$lbl] += $r['total']; $monthlySales += $r['total'];
            }
            $yearlySales += $pdo->query("SELECT SUM(TOTAL_AMOUNT) FROM PAYMENT WHERE YEAR(PAYMENT_DATE) = YEAR(GETDATE())")->fetchColumn() ?? 0;

            $m_res = $pdo->query("SELECT m.NAME, COUNT(pd.MEDICINE_ID) as count FROM PRESCRIPTION_DETAIL pd JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID GROUP BY m.NAME");
            while($r = $m_res->fetch(PDO::FETCH_ASSOC)) { $medicineUsage[$r['NAME']] = ($medicineUsage[$r['NAME']] ?? 0) + $r['count']; }
            $p_res = $pdo->query("SELECT p.NAME, COUNT(pr.PRESCRIPTION_ID) as count FROM PRESCRIPTION pr JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID GROUP BY p.NAME");
            while($r = $p_res->fetch(PDO::FETCH_ASSOC)) { $patientEngagement[$r['NAME']] = ($patientEngagement[$r['NAME']] ?? 0) + $r['count']; }
        }
    } catch (Exception $e) {}
}

arsort($medicineUsage); $top5Meds = array_slice($medicineUsage, 0, 5);
arsort($patientEngagement); $top5Patients = array_slice($patientEngagement, 0, 5);

// --- 3. PROFESSIONAL PDF REPORT ---
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    header('Content-Type: text/html');
    ?>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; }
        /* Clean styles for the top navigation */
        .nav-links { 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }

        .nav-btn { 
            color: white; 
            text-decoration: none; 
            padding: 8px 12px; 
            border-radius: 6px; 
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: 0.3s;
        }

        .nav-btn.active { 
            background: white; 
            color: #1565c0; 
            font-weight: bold; 
        }

        .logout-link {
            background: #c62828; /* Professional red background */
            color: white;
            font-weight: bold;
            margin-left: 10px;
        }

        .logout-link:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }

        /* This makes the 'Reports' button white as per your image */
        .nav-btn.active { 
            background: white !important; 
            color: #1565c0 !important; 
            font-weight: bold; 
        }


        .header { border-bottom: 5px solid #1565c0; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
        .p-card { background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #1565c0; color: white; padding: 12px; text-align: left; }
        td { border-bottom: 1px solid #eee; padding: 10px; }
    </style>
    <body onload="window.print()">
        <div class="header">
            <h1>Global Performance Report</h1>
            <div style="text-align:right;">ID: <?= strtoupper(uniqid()) ?><br><?= date('d M Y') ?></div>
        </div>
        <div class="summary-grid">
            <div class="p-card"><span>Total Prescriptions</span><br><strong><?= $totalPrescriptions ?></strong></div>
            <div class="p-card"><span>Total Patients</span><br><strong><?= $totalPatients ?></strong></div>
            <div class="p-card"><span>Yearly Revenue</span><br><strong>RM <?= number_format($yearlySales, 2) ?></strong></div>
        </div>
        <h2>Top Medicines Used</h2>
        <table><thead><tr><th>Medicine Name</th><th>Prescription Count</th></tr></thead><tbody>
            <?php foreach($top5Meds as $m => $c) echo "<tr><td>$m</td><td>$c</td></tr>"; ?>
        </tbody></table>
    </body>
    <?php exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics | Pharmacy</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Shared Responsive UI CSS */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1300px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; padding-bottom: 40px; }
        
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background: #1565c0; color: white; }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; transition: 0.3s; padding: 8px 12px; border-radius: 6px; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }

        .export-section { padding: 20px 30px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .btn-export { padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; transition: 0.4s; color: white; border: none; font-size: 14px; position: relative; overflow: hidden; }
        .btn-export::after { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; border-radius: 50%; background: rgba(255,255,255,0.3); transform: translate(-50%, -50%); transition: 0.6s; }
        .btn-export:hover::after { width: 300px; height: 300px; }
        .pdf { background: linear-gradient(135deg, #dc3545, #c82333); }
        .print { background: linear-gradient(135deg, #28a745, #218838); }
        .excel { background: linear-gradient(135deg, #17a2b8, #138496); }
        .btn-export:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; padding: 30px; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); text-align: center; border-bottom: 5px solid #1565c0; transition: 0.4s; }
        .card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); }
        .card h2 { font-size: 2.2em; color: #333; margin: 10px 0; }
        .card p { color: #666; font-size: 0.8em; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; }

        .charts-section { padding: 0 30px; display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px; }
        .chart-container { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); border: 1px solid #eee; }
        .chart-container h3 { color: #333; margin-bottom: 25px; border-left: 5px solid #0066ff; padding-left: 15px; }

        @media print { .no-print { display: none !important; } .container { box-shadow: none; } body { background: white; } }
    </style>
</head>
<body>

<div class="container">
    <header class="top-nav no-print">
        <div>Welcome, <strong><?= htmlspecialchars($username) ?></strong> <small>(<?= $userRole ?>)</small></div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="user_management.php" class="nav-btn">👤 User Management</a>
            <a href="medDirectory.php" class="nav-btn">💊 Med Inventory</a>
            <a href="Sales_Billing.php"  class="nav-btn">💰 Sales & Billing</a>
            <a href="reportDashboard.php" class="nav-btn active">📊 Reports</a>
            <a href="viewPrescription.php" class="nav-btn">📈 Prescription</a>
            <a href="logout.php" style="border:1px solid white; padding:5px 10px; border-radius:5px;">Log Out</a>
        </div>
    </header>

    <div class="export-section no-print">
        <div><h2 style="margin:0; color:#333;">Reports and Analytics</h2><p style="color:#666; font-size:0.9em;">Consolidated Pharmacy Data</p></div>
        <div class="export-buttons">
            <a href="?export=pdf" class="btn-export pdf animate__animated animate__bounceIn" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <button onclick="window.print()" class="btn-export print animate__animated animate__bounceIn" style="animation-delay: 0.1s"><i class="fas fa-print"></i> Print Report</button>
            <button onclick="exportToExcel()" class="btn-export excel animate__animated animate__bounceIn" style="animation-delay: 0.2s"><i class="fas fa-file-excel"></i> Excel Export</button>
        </div>
    </div>

    <div class="summary-grid">
        <div class="card animate__animated animate__fadeInUp">
            <h2><?= number_format($totalPatients) ?></h2><p>Total Patients</p>
        </div>
        <div class="card animate__animated animate__fadeInUp" style="border-color:#ffc107; animation-delay:0.1s;">
            <h2><?= number_format($totalUsers) ?></h2><p>System Users</p>
        </div>
        <div class="card animate__animated animate__fadeInUp" style="border-color:#0066ff; animation-delay:0.2s;">
            <h2><?= number_format($totalPrescriptions) ?></h2><p>Total Prescriptions</p>
        </div>
        <div class="card animate__animated animate__fadeInUp" style="border-color:#28a745; animation-delay:0.3s;">
            <h2 style="font-size:1.8em;">RM <?= number_format($monthlySales, 2) ?></h2><p>Monthly Sales</p>
        </div>
        <div class="card animate__animated animate__fadeInUp" style="border-color:#fd7e14; animation-delay:0.4s;">
            <h2 style="font-size:1.8em;">RM <?= number_format($yearlySales, 2) ?></h2><p>Yearly Sales</p>
        </div>
        <div class="card animate__animated animate__fadeInUp" style="border-color:#dc3545; animation-delay:0.5s;">
            <h2><?= number_format($lowStockCount) ?></h2><p>Low Stock Items</p>
        </div>
    </div>

    <div class="charts-section">
        <div class="chart-container animate__animated animate__fadeInLeft">
            <h3>💰 Global Revenue Streams (Month)</h3>
            <canvas id="salesChart" height="110"></canvas>
        </div>
        <div class="chart-container animate__animated animate__fadeInRight">
            <h3>💊 Top 5 Medicines</h3>
            <canvas id="medChart"></canvas>
        </div>
    </div>

    <div style="padding: 0 30px 30px;">
        <div class="chart-container">
            <h3>⭐ Top Patients (Prescription Volume)</h3>
            <div style="display:flex; justify-content:space-around; padding:10px 0;">
                <?php foreach($top5Patients as $name => $count): ?>
                <div style="text-align:center;">
                    <div style="font-size:1.5em; font-weight:bold; color:#1565c0;"><?= $count ?></div>
                    <div style="font-size:0.9em; color:#666;"><?= htmlspecialchars($name) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Sales Trend Chart
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($dailySalesData)) ?>,
        datasets: [{
            label: 'Combined Revenue (RM)',
            data: <?= json_encode(array_values($dailySalesData)) ?>,
            borderColor: '#0066ff',
            backgroundColor: 'rgba(0, 102, 255, 0.1)',
            borderWidth: 4,
            fill: true,
            tension: 0.4
        }]
    },
    options: { scales: { y: { beginAtZero: true, ticks: { callback: v => 'RM ' + v.toLocaleString() } } } }
});

// 2. Medicine Usage Bar
new Chart(document.getElementById('medChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($top5Meds)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($top5Meds)) ?>,
            backgroundColor: ['#0066ff', '#28a745', '#fd7e14', '#17a2b8', '#6c757d'],
            borderRadius: 6
        }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});

// 3. EXCEL EXPORT (INTERESTING STYLE)
function exportToExcel() {
    let html = '<table border="1"><tr style="background:#1565c0; color:white;"><th colspan="2">PHARMACY GLOBAL REPORT</th></tr>';
    html += '<tr><td><b>Total Patients</b></td><td><?= $totalPatients ?></td></tr>';
    html += '<tr><td><b>Yearly Revenue</b></td><td>RM <?= number_format($yearlySales, 2) ?></td></tr>';
    html += '<tr><td colspan="2"><b>Top Medicines Usage:</b></td></tr>';
    <?php foreach($top5Meds as $m => $c) echo "html += '<tr><td>$m</td><td>$c</td></tr>';"; ?>
    html += '</table>';
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'Pharmacy_Consolidated_Report.xls';
    a.click();
}
</script>
</body>
</html>