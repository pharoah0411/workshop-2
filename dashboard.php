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
        /* Reusing core CSS design */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); overflow: hidden; }
        
        /* Header */
        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 30px 20px; text-align: center; }
        .header-content h1 { font-size: 2.2em; margin-bottom: 5px; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2); }
        .subtitle { font-size: 1.0em; opacity: 0.9; }

        /* Dashboard Grid Layout for Modules */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }

        .module-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            border-top: 5px solid #0066ff; /* Primary color indicator */
            display: flex;
            flex-direction: column;
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .module-header {
            padding: 15px 20px;
            background: #e3f2fd;
            border-bottom: 1px solid #d0e0ff;
        }
        .module-header h2 {
            font-size: 1.4em;
            color: #0066ff;
            margin: 0;
        }

        .module-content {
            padding: 20px;
            flex-grow: 1; /* Ensures content fills space for alignment */
        }

        .module-content ul {
            list-style: none;
            padding: 0;
        }
        .module-content li {
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        .module-content li a {
            color: #333;
            text-decoration: none;
            transition: color 0.2s;
            display: block;
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
        }
        .module-content li a:hover {
            color: #0099ff;
            text-decoration: underline;
        }
        
        .controls-top {
            padding: 20px 30px 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding-bottom: 20px;
        }
        .controls-top span {
            font-weight: 600;
            color: #333;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="controls-top">
            <span>Logged in as: **<?php echo htmlspecialchars($username); ?>** (Role: **<?php echo htmlspecialchars($userRole); ?>**)</span>

            <div style="display: flex; gap: 10px;">
                <?php if(strtolower($userRole) === 'admin'): ?>
                    <a href="view_audit.php" class="btn" style="background: #0066ff; color: white;">🛡️ Audit Trail</a>
                    <a href="backup.php" class="btn" style="background: #28a745; color: white;">💾 Backup System</a>
                <?php endif; ?>
         
                <a href="logout.php" class="btn btn-secondary">Log Out</a>
            </div>
        </div>
        
        <header class="header">
            <div class="header-content">
                <h1>PHARMACY MANAGEMENT SYSTEM</h1>
                <p class="subtitle">Central dashboard for all operational modules.</p>
            </div>
        </header>

        <section class="module-grid">
            
            <div class="module-card">
                <div class="module-header"><h2>📦 Inventory Management</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="medDirectory.php">Medicine List / Dashboard</a></li>
                    </ul>
                </div>
            </div>

            <div class="module-card">
                <div class="module-header"><h2>📝 Prescription Management</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="prescriptionDashboard.php">Prescription List / Dashboard (Future Page)</a></li>
                    </ul>
                </div>
            </div>

            <div class="module-card">
                <div class="module-header"><h2>💰 Sales & Billing</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="Sales_Billing.php">Sales Dashboard (Future Page)</a></li>
                    </ul>
                </div>
            </div>

             <div class="module-card">
                <div class="module-header"><h2>👤 User & Management</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="user_management.php">User List / Dashboard (Future Page)</a></li>
                    </ul>
                </div>
            </div>

             <div class="module-card">
                <div class="module-header"><h2>📈 Reports & Analytics</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="reports.php">📊 Report Dashboard</a></li>
                    </ul>
                </div>
            </div>

            <!-- 💾 BACKUP & RESTORE CARD -->
            <div class="module-card">
                <div class="module-header"><h2>💾 Backup & Restore</h2></div>
                <div class="module-content">
                    <ul>
                        <li><a href="backup.php">Database Backup System</a></li>
                        <li style="margin-top: 10px; color: #666; font-size: 0.9em; list-style: none;">
                            ✅ SQL + Excel formats<br>
                            ✅ Multi-database support<br>
                            ✅ Restore functionality
                        </li>
                    </ul>
                </div>
            </div>

        </section>
    </div>
</body>
</html>