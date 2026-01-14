<?php 
require_once 'session_check.php';

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Management | Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    
    <style>
        /* Medical Professional Color Scheme with Dark Blue - EXACT SAME AS DASHBOARD */
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

        /* DASHBOARD CONTAINER - EXACT SAME SIZE AND STYLE */
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

        /* SIDEBAR - EXACT SAME AS DASHBOARD */
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

        /* Navigation Menu - SAME STYLE */
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
            cursor: button;
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

        /* MAIN CONTENT AREA - EXACT SAME AS DASHBOARD */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header - EXACT SAME */
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

        /* Content Area - EXACT SAME */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Welcome Section - EXACT SAME */
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

        /* System Navigation - UPDATED TO MATCH DASHBOARD STYLE */
        .system-nav {
            display: flex;
            gap: 12px;
            padding: 18px;
            background: white;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            flex-wrap: wrap;
        }

        .system-nav a {
            background: var(--blue-light);
            color: var(--dark-blue);
            font-weight: 500;
            border-radius: 8px;
            padding: 10px 18px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            font-size: 0.9em;
        }

        .system-nav a:hover {
            background: var(--blue-accent);
            color: white;
            transform: translateY(-2px);
        }

        .system-nav a.active {
            background: var(--dark-blue);
            color: white;
        }

        /* Module Sections - UPDATED TO MATCH DASHBOARD CARDS */
        .module-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
        }

        .module-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .module-header {
            font-size: 1.2em;
            color: var(--dark-blue);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .module-list {
            list-style: none;
            padding-left: 10px;
        }

        .module-list li {
            margin-bottom: 15px;
        }

        .module-list a {
            text-decoration: none;
            color: var(--dark-grey);
            font-weight: 500;
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            background: var(--blue-light);
            transition: all 0.3s ease;
            border: 1px solid transparent;
            width: 250px;
            max-width: 100%;
            font-size: 0.95em;
        }

        .module-list a:hover {
            background: var(--blue-accent);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.25);
            border-color: var(--blue-accent);
        }

        /* Responsive Design - EXACT SAME AS DASHBOARD */
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
            
            .system-nav {
                overflow-x: auto;
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
            
            .module-section {
                padding: 20px;
            }
            
            .module-list a {
                width: 100%;
            }
            
            .system-nav a {
                padding: 8px 12px;
                font-size: 0.85em;
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
            
            .module-header {
                font-size: 1.1em;
            }
            
            .system-nav {
                padding: 12px;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- DASHBOARD CONTAINER - EXACT SAME STRUCTURE -->
    <div class="dashboard-container">
        <!-- SIDEBAR - EXACT SAME AS DASHBOARD -->
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
                        <li><a href="user_management.php" class="active"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                    </ul>
                </div>
            </nav>

            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>User Management</h1>
                    <p>Manage users, patients, and permissions - <?php echo date('l, F j, Y'); ?></p>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Welcome Section -->
                <section class="welcome-section">
                    <div class="welcome-text">
                        <h2>User Management Dashboard</h2>
                        <p>Manage all user accounts, patients, and role permissions in one place</p>
                    </div>
                </section>

                <!-- Module Sections -->
                <div class="module-section">
                    <div class="module-header">
                        <i class="fas fa-user-circle"></i> User Accounts
                    </div>
                    <ul class="module-list">
                        <li><a href="user_list.php"><i class="fas fa-list"></i> View All Users</a></li>
                        <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                    </ul>
                </div>

                <div class="module-section">
                    <div class="module-header">
                        <i class="fas fa-user-injured"></i> Patient Management
                    </div>
                    <ul class="module-list">
                        <li><a href="patient_list.php"><i class="fas fa-list"></i> View Patient List</a></li>
                        <li><a href="add_patient.php"><i class="fas fa-user-plus"></i> Add New Patient</a></li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search functionality - SAME AS DASHBOARD
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    alert(`Searching for: "${searchTerm}"`);
                    // Implement actual search functionality here
                }
            }
        });

        // Sidebar navigation active state - SAME AS DASHBOARD
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                sessionStorage.setItem('activePage', this.getAttribute('href'));
            });
        });

        // Restore active page on page load - SAME AS DASHBOARD
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const activePage = sessionStorage.getItem('activePage') || 'user_management.php';
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                const linkPage = link.getAttribute('href');
                link.classList.remove('active');
                
                if (linkPage === currentPage || linkPage === activePage) {
                    link.classList.add('active');
                }
            });
        });

        // Clear search on escape - SAME AS DASHBOARD
        document.querySelector('.search-box input').addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
            }
        });

        // Update time display - SAME AS DASHBOARD
        function updateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.header-title p');
            if (timeElement) {
                timeElement.textContent = `User Management - ${now.toLocaleDateString('en-US', { 
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