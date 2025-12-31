<?php
require_once "session_check.php";   // ✅ auto logout + login check
require_once 'connection.php';

// Check if user is NOT logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM DASHBOARD</title>
    <style>
        /* Core Reset & Typography */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
            background: #f0f2f5; 
            min-height: 100vh; 
        }

        /* Top navigation bar */
        .top-nav {
            background: white;
            padding: 12px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .user-info { font-size: 0.95em; color: #444; }
        .user-info b { color: #0066ff; }

        /* Main Hero Header */
        .header { 
            background: linear-gradient(135deg, #0052cc 0%, #007bff 100%); 
            color: white; 
            padding: 50px 20px; 
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 { 
            font-size: 2.5em; 
            margin-bottom: 10px; 
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header p { opacity: 0.9; font-size: 1.1em; }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 50px 20px; }

        /* Dashboard Grid */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        /* Unified Module Card Style */
        .module-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid #e1e4e8;
            height: 100%; /* Ensures all boxes in a row match height */
        }
        
        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0,0,0,0.1);
            border-color: #0066ff;
        }

        .module-header {
            padding: 20px;
            background: #f8fbff;
            border-bottom: 1px solid #f0f2f5;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .module-header h2 {
            font-size: 1.2em;
            color: #0b2f6d;
            margin: 0;
        }

        .module-icon {
            font-size: 1.5em;
        }

        .module-content {
            padding: 20px;
            flex-grow: 1;
        }

        .module-content ul {
            list-style: none;
            padding: 0;
        }

        /* Styled Links */
        .module-content li a {
            color: #495057;
            text-decoration: none;
            display: block;
            padding: 10px 12px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #f8f9fa;
            transition: all 0.2s;
            font-weight: 500;
            border-left: 3px solid transparent;
        }

        .module-content li a:hover {
            background: #e7f1ff;
            color: #0066ff;
            border-left: 3px solid #0066ff;
            padding-left: 18px;
        }

        /* Buttons */
        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            transition: 0.2s;
            display: inline-block;
        }
        .btn-audit { background: #0066ff; color: white; margin-right: 10px; }
        .btn-audit:hover { background: #0052cc; }
        .btn-logout { background: #f1f3f5; color: #495057; }
        .btn-logout:hover { background: #e9ecef; }

    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="user-info">
            Welcome back, <b><?php echo htmlspecialchars($username); ?></b> 
            <span style="color: #6c757d; margin-left: 10px;">(Role: <?php echo htmlspecialchars($userRole); ?>)</span>
        </div>
        <div class="actions">
            <?php if(strtolower($userRole) === 'admin'): ?>
                <a href="view_audit.php" class="btn btn-audit">🛡️ Audit Trail</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-logout">Log Out</a>
        </div>
    </nav>

    <header class="header">
        <div class="container">
            <h1>Pharmacy Management</h1>
            <p>Select a module below to manage your operations.</p>
        </div>
    </header>

    <main class="container">
        <section class="module-grid">
            
            <div class="module-card">
                <div class="module-header">
                    <span class="module-icon">📦</span>
                    <h2>Inventory</h2>
                </div>
                <div class="module-content">
                    <ul>
                        <li><a href="medDirectory.php">Medicine Directory</a></li>
                        <li><a href="stock.php">Stock Levels</a></li>
                    </ul>
                </div>
            </div>

            <div class="module-card">
                <div class="module-header">
                    <span class="module-icon">📝</span>
                    <h2>Prescriptions</h2>
                </div>
                <div class="module-content">
                    <ul>
                        <li><a href="prescriptionDashboard.php">Prescription Dashboard</a></li>
                        <li><a href="createPrescription.php">New Prescription</a></li>
                    </ul>
                </div>
            </div>

            <div class="module-card">
                <div class="module-header">
                    <span class="module-icon">💰</span>
                    <h2>Sales & Billing</h2>
                </div>
                <div class="module-content">
                    <ul>
                        <li><a href="Sales_Billing.php">Sales Dashboard</a></li>
                        <li><a href="new_sale.php">Process New Sale</a></li>
                    </ul>
                </div>
            </div>

             <div class="module-card">
                <div class="module-header">
                    <span class="module-icon">👤</span>
                    <h2>Management</h2>
                </div>
                <div class="module-content">
                    <ul>
                        <li><a href="user_management.php">User Management</a></li>
                        <li><a href="patient_list.php">Patient Records</a></li>
                    </ul>
                </div>
            </div>

             <div class="module-card">
                <div class="module-header">
                    <span class="module-icon">📈</span>
                    <h2>Analytics</h2>
                </div>
                <div class="module-content">
                    <ul>
                        <li><a href="reports.php">Performance Reports</a></li>
                    </ul>
                </div>
            </div>

        </section>
    </main>

</body>
</html>