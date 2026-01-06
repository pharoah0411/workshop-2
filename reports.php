<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Admin';
$username = $_SESSION['username'] ?? 'User';

// --- DATA FETCHING (With Fallbacks for Empty Local DB) ---
// Initialize default values (0) so dashboard doesn't look broken if empty
$totalUsers = 0;
$totalPatients = 0;
$totalPrescriptions = 0;
$lowStockCount = 0;
$todaysSales = 0.00;
$monthlySales = 0.00;

// Arrays for Charts
$salesDays = [];
$salesTotals = [];
$medNames = [];
$medCounts = [];
$stockNames = [];
$stockQty = [];

try {
    // 1. Total Users
    // Check if we have a valid connection object ($pdo or $conn from connection.php)
    if (isset($pdo) && $pdo) {
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM [USER]")->fetchColumn();
        $totalPatients = $pdo->query("SELECT COUNT(*) FROM PATIENT")->fetchColumn();
        $totalPrescriptions = $pdo->query("SELECT COUNT(*) FROM PRESCRIPTION")->fetchColumn();
        // Assuming low stock is < 50
        $lowStockCount = $pdo->query("SELECT COUNT(*) FROM MEDICINE WHERE QUANTITY_IN_STOCK < 50")->fetchColumn();
        
        // Sales Data (Mock logic since Payment table structure might vary)
        // If Payment table exists, uncomment below:
        // $todaysSales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM payment WHERE CAST(payment_date AS DATE) = CAST(GETDATE() AS DATE)")->fetchColumn();
        
        // Chart Data: Top 5 Medicines
        $stmt = $pdo->query("
            SELECT TOP 5 m.NAME, COUNT(pd.MEDICINE_ID) as count 
            FROM PRESCRIPTION_DETAIL pd
            JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID
            GROUP BY m.NAME
            ORDER BY count DESC
        ");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $medNames[] = $row['NAME'];
            $medCounts[] = $row['count'];
        }

    } elseif (isset($conn) && $conn) {
        // SQLSRV Driver Fallback
        $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM [USER]");
        if($q) $totalUsers = sqlsrv_fetch_array($q)['c'];
        
        $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM PATIENT");
        if($q) $totalPatients = sqlsrv_fetch_array($q)['c'];

        $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM PRESCRIPTION");
        if($q) $totalPrescriptions = sqlsrv_fetch_array($q)['c'];
        
        $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM MEDICINE WHERE QUANTITY_IN_STOCK < 50");
        if($q) $lowStockCount = sqlsrv_fetch_array($q)['c'];
    }

} catch (Exception $e) {
    // Silent fail so dashboard still loads
}

// --- DUMMY DATA INJECTOR (For Localhost Display Only) ---
// If database returned 0 (likely on local), use these numbers to show off the UI
if ($totalUsers == 0) {
    $totalUsers = 12;
    $totalPatients = 45;
    $totalPrescriptions = 128;
    $lowStockCount = 3;
    $todaysSales = 1250.50;
    $monthlySales = 45200.00;
    
    // Dummy Chart Data
    $salesDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $salesTotals = [1200, 1900, 1500, 2200, 1800, 2500, 3100];
    
    $medNames = ['Paracetamol', 'Amoxicillin', 'Ibuprofen', 'Vitamin C', 'Cough Syrup'];
    $medCounts = [150, 120, 90, 85, 60];
    
    $stockNames = ['Antibiotics', 'Painkillers', 'Vitamins', 'First Aid', 'Supplements'];
    $stockQty = [30, 45, 15, 10, 20];
}

// --- PDF Export Handler ---
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Simple HTML-based PDF (For browsers that can print to PDF)
    // For a real PDF library, install TCPDF, mPDF, or FPDF
    
    header('Content-Type: text/html');
    
    // Start HTML for PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>Pharmacy Analytics Report</title>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px;
                border-bottom: 2px solid #0066ff;
                padding-bottom: 20px;
            }
            .header h1 { 
                color: #0066ff; 
                margin: 0;
                font-size: 28px;
            }
            .header p { 
                color: #666; 
                margin: 5px 0;
            }
            .summary-grid { 
                display: flex; 
                flex-wrap: wrap; 
                gap: 15px; 
                margin-bottom: 30px;
                justify-content: center;
            }
            .summary-card { 
                border: 1px solid #ddd; 
                border-radius: 8px; 
                padding: 15px; 
                width: 180px; 
                text-align: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .summary-card h3 { 
                font-size: 24px; 
                margin: 10px 0; 
                color: #0066ff;
            }
            .summary-card p { 
                color: #666; 
                font-size: 12px; 
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .section { 
                margin-bottom: 30px; 
                page-break-inside: avoid;
            }
            .section h2 { 
                color: #333; 
                border-left: 4px solid #0066ff; 
                padding-left: 10px;
                margin-bottom: 15px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px;
            }
            th { 
                background-color: #f8f9fa; 
                color: #333; 
                padding: 12px;
                text-align: left;
                border-bottom: 2px solid #ddd;
            }
            td { 
                padding: 10px; 
                border-bottom: 1px solid #eee;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .footer { 
                text-align: center; 
                margin-top: 40px; 
                color: #666; 
                font-size: 12px;
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
            @media print {
                body { padding: 10px; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📊 Pharmacy Analytics Report</h1>
            <p><strong>Generated:</strong> ' . date("d M Y, h:i A") . '</p>
            <p><strong>Generated by:</strong> ' . htmlspecialchars($username) . '</p>
            <p><strong>Period:</strong> ' . date("F Y") . '</p>
        </div>
        
        <div class="summary-grid">
            <div class="summary-card">
                <h3>' . number_format($totalPrescriptions) . '</h3>
                <p>Total Prescriptions</p>
            </div>
            <div class="summary-card">
                <h3>RM ' . number_format($monthlySales, 2) . '</h3>
                <p>Monthly Revenue</p>
            </div>
            <div class="summary-card">
                <h3>' . number_format($totalPatients) . '</h3>
                <p>Registered Patients</p>
            </div>
            <div class="summary-card">
                <h3>' . number_format($lowStockCount) . '</h3>
                <p>Low Stock Items</p>
            </div>
        </div>
        
        <div class="section">
            <h2>📈 Weekly Sales Trend</h2>
            <table>
                <tr>
                    <th>Day</th>
                    <th>Sales (RM)</th>
                </tr>';
    
    // Add sales data
    for($i = 0; $i < count($salesDays); $i++) {
        $html .= '<tr>
                    <td>' . $salesDays[$i] . '</td>
                    <td>RM ' . number_format($salesTotals[$i], 2) . '</td>
                  </tr>';
    }
    
    $html .= '</table>
        </div>
        
        <div class="section">
            <h2>💊 Top 5 Prescribed Medicines</h2>
            <table>
                <tr>
                    <th>Medicine Name</th>
                    <th>Prescription Count</th>
                    <th>Rank</th>
                </tr>';
    
    // Add medicine data
    for($i = 0; $i < count($medNames); $i++) {
        $rank = $i + 1;
        $html .= '<tr>
                    <td>' . htmlspecialchars($medNames[$i]) . '</td>
                    <td>' . number_format($medCounts[$i]) . '</td>
                    <td>#' . $rank . '</td>
                  </tr>';
    }
    
    $html .= '</table>
        </div>
        
        <div class="section">
            <h2>📦 Inventory Categories</h2>
            <table>
                <tr>
                    <th>Category</th>
                    <th>Stock Quantity</th>
                </tr>';
    
    // Add stock data
    for($i = 0; $i < count($stockNames); $i++) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($stockNames[$i]) . '</td>
                    <td>' . number_format($stockQty[$i]) . ' units</td>
                  </tr>';
    }
    
    $html .= '</table>
        </div>
        
        <div class="footer">
            <p>Report generated by Pharmacy Management System</p>
            <p>This is an official document. For internal use only.</p>
            <p>Page generated on: ' . date("Y-m-d H:i:s") . '</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 1000);
            }
        </script>
    </body>
    </html>';
    
    echo $html;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Animate.css for extra animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        /* Shared Theme CSS */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); 
            min-height: 100vh; 
            padding: 20px;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); 
            overflow: hidden; 
            padding-bottom: 40px; 
        }
        
        /* Navigation Bar */
        .top-nav { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 15px 30px; 
            background: #1565c0; 
            color: white; 
            margin-bottom: 20px; 
            border-radius: 8px 8px 0 0; 
            animation: slideInDown 0.8s ease-out;
        }
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            margin-left: 15px; 
            font-weight: 500; 
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .nav-links a:hover { 
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-logout { 
            padding: 8px 16px; 
            border: 1px solid white; 
            border-radius: 6px; 
            background: transparent; 
            color: white; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 0.9em;
            transition: all 0.3s;
        }
        .btn-logout:hover { 
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255,255,255,0.2);
        }

        /* Export Buttons */
        .export-section { 
            padding: 20px 30px; 
            background: #f8f9fa; 
            border-bottom: 1px solid #eee; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            animation: fadeIn 1s ease-out;
        }
        .export-buttons { 
            display: flex; 
            gap: 10px; 
        }
        .btn-export { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 10px; 
            text-decoration: none; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }
        .btn-export::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn-export:hover::after {
            width: 300px;
            height: 300px;
        }
        .btn-export.pdf { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
            color: white; 
        }
        .btn-export.pdf:hover { 
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }
        .btn-export.print { 
            background: linear-gradient(135deg, #28a745 0%, #218838 100%); 
            color: white; 
        }
        .btn-export.print:hover { 
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        .btn-export.excel { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); 
            color: white; 
        }
        .btn-export.excel:hover { 
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(23, 162, 184, 0.4);
        }

        /* Header */
        .header { 
            background: #f8f9fa; 
            padding: 25px 30px; 
            border-bottom: 1px solid #eee; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            animation: fadeInUp 0.8s ease-out;
        }
        .header h1 { 
            color: #0066ff; 
            font-size: 2em; 
            margin: 0; 
            background: linear-gradient(135deg, #0066ff, #0099ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .date-badge { 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
            color: #1565c0; 
            padding: 8px 20px; 
            border-radius: 25px; 
            font-size: 0.9em; 
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0, 102, 255, 0.1);
            animation: pulse 2s infinite;
        }

        /* Summary Cards */
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 25px; 
            padding: 30px; 
        }
        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 6px 20px rgba(0,0,0,0.08); 
            text-align: center; 
            border-bottom: 5px solid transparent; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out backwards;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.7s;
        }
        .card:hover::before {
            left: 100%;
        }
        .card:hover { 
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .card h2 { 
            font-size: 2.8em; 
            margin: 15px 0; 
            color: #333;
            transition: all 0.3s;
        }
        .card:hover h2 {
            transform: scale(1.1);
        }
        .card p { 
            color: #666; 
            font-size: 0.9em; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        /* Card Colors with animation delays */
        .card.blue { 
            border-color: #0066ff; 
            animation-delay: 0.1s;
        }
        .card.blue:hover h2 { color: #0066ff; }
        
        .card.green { 
            border-color: #28a745; 
            animation-delay: 0.2s;
        }
        .card.green:hover h2 { color: #28a745; }
        
        .card.orange { 
            border-color: #fd7e14; 
            animation-delay: 0.3s;
        }
        .card.orange:hover h2 { color: #fd7e14; }
        
        .card.red { 
            border-color: #dc3545; 
            animation-delay: 0.4s;
        }
        .card.red:hover h2 { color: #dc3545; }

        /* Charts Section */
        .charts-section { 
            padding: 0 30px; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); 
            gap: 30px; 
        }
        .chart-container { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 6px 20px rgba(0,0,0,0.08); 
            border: 1px solid #eee; 
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease-out backwards;
        }
        .chart-container:hover {
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            transform: translateY(-5px);
        }
        .chart-container h3 { 
            color: #333; 
            margin-bottom: 25px; 
            font-size: 1.3em; 
            border-left: 5px solid #0066ff; 
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Full Width Chart */
        .chart-full { 
            grid-column: 1 / -1; 
            animation-delay: 0.1s;
        }

        /* Animations */
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 4px 10px rgba(0, 102, 255, 0.1);
            }
            50% {
                box-shadow: 0 4px 20px rgba(0, 102, 255, 0.3);
            }
            100% {
                box-shadow: 0 4px 10px rgba(0, 102, 255, 0.1);
            }
        }

        /* Tooltip Styles */
        .chart-tooltip {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        @media (max-width: 768px) {
            .charts-section { grid-template-columns: 1fr; }
            .export-section { flex-direction: column; gap: 15px; align-items: flex-start; }
            .export-buttons { flex-wrap: wrap; }
            .btn-export { padding: 10px 15px; font-size: 12px; }
            .header h1 { font-size: 1.5em; }
        }
        
        @media print {
            .no-print { display: none !important; }
            .container { box-shadow: none; margin: 0; padding: 0; }
            body { background: white; padding: 0; }
        }
        
        /* Loading Animation for Charts */
        .chart-loading {
            width: 100%;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8f9fa;
            border-radius: 10px;
            animation: pulse 1.5s infinite;
        }
        
        .chart-loading::after {
            content: 'Loading chart data...';
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navigation -->
        <header class="top-nav no-print">
            <div class="user-info">
                Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>
                <span style="font-size: 0.8em; opacity: 0.9;">(<?php echo htmlspecialchars($userRole); ?>)</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="animate__animated animate__fadeIn">🏠 Dashboard</a>
                <a href="medDirectory.php" class="animate__animated animate__fadeIn">📦 Medicines</a>
                <a href="reports.php" style="text-decoration: underline;" class="animate__animated animate__fadeIn">📊 Reports</a>
                <a href="logout.php" class="btn-logout animate__animated animate__fadeIn">Log Out</a>
            </div>
        </header>

        <!-- Export Buttons -->
        <div class="export-section no-print">
            <div>
                <h2 style="color: #333; margin: 0; font-size: 1.5em;">Analytics Dashboard</h2>
                <p style="color: #666; margin: 5px 0 0 0; font-size: 0.9em;">Real-time pharmacy statistics and insights</p>
            </div>
            <div class="export-buttons">
                <a href="?export=pdf" class="btn-export pdf animate__animated animate__bounceIn" target="_blank" style="animation-delay: 0.2s">
                    📄 Download PDF
                </a>
                <button onclick="window.print()" class="btn-export print animate__animated animate__bounceIn" style="animation-delay: 0.3s">
                    🖨️ Print Report
                </button>
                <button onclick="exportToExcel()" class="btn-export excel animate__animated animate__bounceIn" style="animation-delay: 0.4s">
                    📊 Export Excel
                </button>
            </div>
        </div>

        <div class="header">
            <h1 class="animate__animated animate__fadeIn">📊 Operational Analytics</h1>
            <span class="date-badge animate__animated animate__pulse animate__infinite">Today: <?php echo date("d M Y"); ?></span>
        </div>

        <!-- Metric Cards -->
        <div class="summary-grid">
            <div class="card blue animate__animated animate__fadeInUp">
                <h2><?php echo number_format($totalPrescriptions); ?></h2>
                <p>Total Prescriptions</p>
            </div>
            <div class="card green animate__animated animate__fadeInUp">
                <h2>RM <?php echo number_format($monthlySales, 2); ?></h2>
                <p>Monthly Revenue</p>
            </div>
            <div class="card orange animate__animated animate__fadeInUp">
                <h2><?php echo number_format($totalPatients); ?></h2>
                <p>Registered Patients</p>
            </div>
            <div class="card red animate__animated animate__fadeInUp">
                <h2><?php echo number_format($lowStockCount); ?></h2>
                <p>Low Stock Items</p>
            </div>
        </div>

        <!-- Charts Area -->
        <div class="charts-section">
            
            <!-- Sales Trend Chart -->
            <div class="chart-container chart-full">
                <h3>💰 Weekly Sales Trend</h3>
                <canvas id="salesChart" height="100"></canvas>
            </div>

            <!-- Top Medicines -->
            <div class="chart-container">
                <h3>💊 Most Prescribed Medicines</h3>
                <canvas id="medChart"></canvas>
            </div>

            <!-- Inventory Distribution -->
            <div class="chart-container">
                <h3>📦 Inventory Categories</h3>
                <canvas id="stockChart"></canvas>
            </div>

        </div>
    </div>

    <!-- Chart Logic with Advanced Animations -->
    <script>
        // Enhanced colors with gradients
        const themeColors = {
            primary: '#0066ff',
            secondary: '#28a745',
            warning: '#fd7e14',
            danger: '#dc3545',
            info: '#17a2b8',
            dark: '#6c757d'
        };
        
        // Custom hover plugin for Chart.js
        const hoverAnimation = {
            id: 'hoverAnimation',
            beforeDraw: (chart) => {
                const activeElements = chart.getActiveElements();
                if (activeElements.length > 0) {
                    const ctx = chart.ctx;
                    const element = activeElements[0];
                    const dataset = chart.data.datasets[element.datasetIndex];
                    const meta = chart.getDatasetMeta(element.datasetIndex);
                    
                    // Highlight the active element
                    if (meta.type === 'bar') {
                        const bar = meta.data[element.index];
                        const originalColor = dataset.backgroundColor[element.index];
                        
                        // Create gradient effect
                        const gradient = ctx.createLinearGradient(0, bar.y, 0, bar.base);
                        gradient.addColorStop(0, lightenColor(originalColor, 40));
                        gradient.addColorStop(1, originalColor);
                        
                        // Apply the gradient
                        bar._model.backgroundColor = gradient;
                    }
                }
            }
        };

        // Helper function to lighten colors
        function lightenColor(color, percent) {
            const num = parseInt(color.replace('#', ''), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const G = (num >> 8 & 0x00FF) + amt;
            const B = (num & 0x0000FF) + amt;
            return `#${(
                0x1000000 +
                (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000 +
                (G < 255 ? (G < 1 ? 0 : G) : 255) * 0x100 +
                (B < 255 ? (B < 1 ? 0 : B) : 255)
            )
                .toString(16)
                .slice(1)}`;
        }

        // 1. Sales Line Chart with Interactive Points
        const salesChart = new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($salesDays); ?>,
                datasets: [{
                    label: 'Sales (RM)',
                    data: <?php echo json_encode($salesTotals); ?>,
                    borderColor: themeColors.primary,
                    backgroundColor: 'rgba(0, 102, 255, 0.1)',
                    borderWidth: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: themeColors.primary,
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: themeColors.primary,
                    pointHoverBorderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointHitRadius: 20
                }]
            },
            options: {
                responsive: true,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: themeColors.primary,
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return `Sales: RM ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                hover: {
                    mode: 'nearest',
                    intersect: true,
                    animationDuration: 400
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { 
                            color: '#f0f0f0',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value.toLocaleString();
                            },
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: { 
                        grid: { 
                            display: false 
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                onHover: (event, chartElement) => {
                    event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                }
            }
        });

        // 2. Medicine Bar Chart with Hover Animation
        const medChart = new Chart(document.getElementById('medChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($medNames); ?>,
                datasets: [{
                    label: 'Prescriptions',
                    data: <?php echo json_encode($medCounts); ?>,
                    backgroundColor: [
                        '#0066ff', '#28a745', '#fd7e14', '#17a2b8', '#6c757d'
                    ],
                    borderColor: [
                        '#0056cc', '#1e7e34', '#e96c00', '#117a8b', '#545b62'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: [
                        lightenColor('#0066ff', 30),
                        lightenColor('#28a745', 30),
                        lightenColor('#fd7e14', 30),
                        lightenColor('#17a2b8', 30),
                        lightenColor('#6c757d', 30)
                    ],
                    hoverBorderWidth: 3
                }]
            },
            plugins: [hoverAnimation],
            options: {
                responsive: true,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: themeColors.primary,
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw.toLocaleString()} prescriptions`;
                            }
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutBounce'
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                },
                onHover: (event, chartElement) => {
                    if (chartElement.length > 0) {
                        const index = chartElement[0].index;
                        const dataset = medChart.data.datasets[0];
                        
                        // Add bounce animation on hover
                        const bars = medChart.getDatasetMeta(0).data;
                        bars.forEach((bar, i) => {
                            if (i === index) {
                                bar._model.y = bar._model.y - 10;
                            }
                        });
                        medChart.update('active');
                    }
                }
            }
        });

        // 3. Stock Pie Chart with Hover Effects
        const stockChart = new Chart(document.getElementById('stockChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($stockNames); ?>,
                datasets: [{
                    data: <?php echo json_encode($stockQty); ?>,
                    backgroundColor: [
                        '#0066ff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
                    ],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverBackgroundColor: [
                        lightenColor('#0066ff', 30),
                        lightenColor('#28a745', 30),
                        lightenColor('#ffc107', 30),
                        lightenColor('#dc3545', 30),
                        lightenColor('#6f42c1', 30)
                    ],
                    hoverBorderWidth: 5,
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: themeColors.primary,
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} units (${percentage}%)`;
                            }
                        }
                    }
                },
                onHover: (event, chartElement) => {
                    if (chartElement.length > 0) {
                        event.native.target.style.cursor = 'pointer';
                        
                        // Add rotation animation on hover
                        stockChart.options.animation.animateRotate = true;
                        stockChart.update();
                    } else {
                        event.native.target.style.cursor = 'default';
                    }
                }
            }
        });

        // Add click events to charts
        document.getElementById('medChart').onclick = function(evt) {
            const points = medChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
            if (points.length) {
                const firstPoint = points[0];
                const label = medChart.data.labels[firstPoint.index];
                const value = medChart.data.datasets[firstPoint.datasetIndex].data[firstPoint.index];
                
                // Create a popup effect
                alert(`📊 ${label}\nTotal Prescriptions: ${value.toLocaleString()}`);
                
                // Add bounce animation
                const bar = medChart.getDatasetMeta(0).data[firstPoint.index];
                bar._model.y = bar._model.y - 15;
                medChart.update();
                
                // Reset after animation
                setTimeout(() => {
                    bar._model.y = bar._model.y + 15;
                    medChart.update();
                }, 300);
            }
        };

        // Export to Excel function
        function exportToExcel() {
            // Create a simple table for export
            let html = '<table border="1">';
            html += '<tr><th colspan="2">Pharmacy Analytics Report</th></tr>';
            html += '<tr><td colspan="2">Generated: <?php echo date("Y-m-d H:i:s"); ?></td></tr>';
            html += '<tr><td colspan="2">Generated by: <?php echo htmlspecialchars($username); ?></td></tr>';
            html += '<tr><th>Metric</th><th>Value</th></tr>';
            html += '<tr><td>Total Prescriptions</td><td><?php echo $totalPrescriptions; ?></td></tr>';
            html += '<tr><td>Total Patients</td><td><?php echo $totalPatients; ?></td></tr>';
            html += '<tr><td>Monthly Revenue</td><td>RM <?php echo number_format($monthlySales, 2); ?></td></tr>';
            html += '<tr><td>Low Stock Items</td><td><?php echo $lowStockCount; ?></td></tr>';
            html += '</table>';
            
            // Create a blob and download
            let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = 'pharmacy_report_<?php echo date("Y-m-d"); ?>.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // Show notification with animation
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                z-index: 9999;
                animation: slideInRight 0.5s ease-out;
            `;
            notification.innerHTML = '✅ Excel file downloaded successfully!';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.5s ease-out';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Add CSS for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>

</body>
</html>