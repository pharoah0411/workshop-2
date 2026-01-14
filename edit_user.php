<?php
require_once "session_check.php";   // ✅ auto logout + login check
require_once "auth_check.php";     // role-based access
requireRole('admin');              // admin only

require_once "connection.php";

$message = "";

/* =========================
   1) VALIDATE INPUT
========================= */
if (!isset($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing user ID or source.</p>");
}

$user_id = trim($_GET['id']);
$db      = trim($_GET['db']);

// Normalize db name (accept SQL%20Server, sqlserver, etc.)
$dbKey = strtolower(str_replace(' ', '', $db));
if ($dbKey === 'postgres' || $dbKey === 'postgresql') $dbKey = 'postgres';
elseif ($dbKey === 'mysql') $dbKey = 'mysql';
elseif ($dbKey === 'sqlserver' || $dbKey === 'mssql') $dbKey = 'sqlserver';
else die("<p style='color:red;'>Invalid database source.</p>");

/* =========================
   2) FETCH USER BY DB
========================= */
$user = null;

try {
    if ($dbKey === 'postgres') {

        if (!($pg_conn instanceof PDO)) die("<p style='color:red;'>Postgres connection not available.</p>");

        $stmt = $pg_conn->prepare('SELECT user_id, username, name, email, phone, role FROM "user" WHERE user_id = :id');
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

    } elseif ($dbKey === 'mysql') {

        if (!($mysql_conn2 instanceof mysqli)) die("<p style='color:red;'>MySQL connection not available.</p>");

        $stmt = $mysql_conn2->prepare("SELECT USER_ID, USERNAME, NAME, EMAIL, PHONE, ROLE FROM `USER` WHERE USER_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        // Normalize keys to match Postgres style
        if ($user) {
            $user = [
                'user_id'   => $user['USER_ID'],
                'username'  => $user['USERNAME'],
                'name'      => $user['NAME'],
                'email'     => $user['EMAIL'],
                'phone'     => $user['PHONE'],
                'role'      => $user['ROLE'],
            ];
        }

    } elseif ($dbKey === 'sqlserver') {

        if (!($pdo instanceof PDO)) die("<p style='color:red;'>SQL Server connection not available.</p>");

        $stmt = $pdo->prepare("SELECT USER_ID, username, name, email, phone, role FROM [USER] WHERE USER_ID = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $user = [
                'user_id'   => $row['USER_ID'],
                'username'  => $row['username'],
                'name'      => $row['name'],
                'email'     => $row['email'],
                'phone'     => $row['phone'],
                'role'      => $row['role'],
            ];
        }
    }

} catch (Exception $e) {
    die("<p style='color:red;'>Error fetching user: " . htmlspecialchars($e->getMessage()) . "</p>");
}

if (!$user) {
    die("<p style='color:red;'>User not found in selected database.</p>");
}

/* =========================
   3) HANDLE UPDATE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $phone    = trim($_POST["phone"]);
    $role     = trim($_POST["role"]);

    try {
        if ($dbKey === 'postgres') {

            $stmt = $pg_conn->prepare('
                UPDATE "user"
                SET username = :u, name = :n, email = :e, phone = :ph, role = :r
                WHERE user_id = :id
            ');
            $stmt->execute([
                ':u'  => $username,
                ':n'  => $name,
                ':e'  => $email,
                ':ph' => $phone,
                ':r'  => $role,
                ':id' => $user_id
            ]);

        } elseif ($dbKey === 'mysql') {

            $stmt = $mysql_conn2->prepare("
                UPDATE `USER`
                SET USERNAME = ?, NAME = ?, EMAIL = ?, PHONE = ?, ROLE = ?
                WHERE USER_ID = ?
            ");
            $stmt->bind_param("sssssi", $username, $name, $email, $phone, $role, $user_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($dbKey === 'sqlserver') {

            $stmt = $pdo->prepare("
                UPDATE [USER]
                SET username = ?, name = ?, email = ?, phone = ?, role = ?
                WHERE USER_ID = ?
            ");
            $stmt->execute([$username, $name, $email, $phone, $role, $user_id]);
        }

        $message = "<div class='alert-message success'><i class='fas fa-check-circle'></i> User updated successfully in $db database.</div>";

        // Refresh displayed data
        $user['username'] = $username;
        $user['name']     = $name;
        $user['email']    = $email;
        $user['phone']    = $phone;
        $user['role']     = $role;

    } catch (Exception $e) {
        $message = "<div class='alert-message error'><i class='fas fa-exclamation-circle'></i> ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Check if user is NOT logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 🔐 Logged in but forced to reset password
if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
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
    <title>Edit User - Pharmacy System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
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

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Alert Messages */
        .alert-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95em;
        }

        .alert-message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        .alert-message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
        }

        .alert-message i {
            margin-right: 10px;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid #e1e8ed;
        }

        .form-section {
            padding: 25px 30px;
            border-bottom: 1px solid #e1e8ed;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: var(--dark-blue);
        }

        .form-section-header h3 {
            font-size: 1.1em;
            font-weight: 600;
            margin-left: 10px;
        }

        .form-section-header i {
            color: var(--dark-blue);
            font-size: 1.2em;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-grey);
            font-weight: 500;
            font-size: 0.9em;
        }

        .form-label .required {
            color: var(--alert-red);
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            background: var(--cream-white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.95em;
            background: var(--cream-white);
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
        }

        /* Database Info */
        .database-info {
            background: var(--blue-light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid var(--blue-accent);
        }

        .database-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 10px;
            display: block;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e1e8ed;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(28, 73, 102, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            padding: 12px 30px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--dark-blue);
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
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
            
            .header-actions {
                width: 100%;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
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
            
            .form-section {
                padding: 15px;
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
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ADMINISTRATION</div>
                    <ul class="nav-links">
                        <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="add_user.php"><i class="fas fa-user-plus nav-icon"></i>Add New User</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ACCOUNT</div>
                    <ul class="nav-links">
                        <li><a href="profile.php"><i class="fas fa-user-cog nav-icon"></i>Profile Settings</a></li>
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
                    <h1>Edit User</h1>
                    <p>Update user information in <?php echo htmlspecialchars($db); ?> database</p>
                </div>
                <div class="header-actions">
                    <a href="user_management.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to User Management
                    </a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php echo $message; ?>

                <div class="form-container">
                    <form method="POST">
                        <!-- Database Information -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-database"></i>
                                <h3>Database Information</h3>
                            </div>
                            <div class="database-info">
                                <p><strong>Database:</strong> <?php echo htmlspecialchars($db); ?></p>
                                <p><strong>User ID:</strong> <?php echo htmlspecialchars($user_id); ?></p>
                                <p><i class="fas fa-info-circle"></i> You are editing a user from the <?php echo htmlspecialchars($db); ?> database.</p>
                            </div>
                        </div>

                        <!-- User Information -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-user-edit"></i>
                                <h3>User Information</h3>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Username <span class="required">*</span>
                                    </label>
                                    <input type="text" name="username" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['username']); ?>"
                                           placeholder="Enter username">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user-tag"></i> Role <span class="required">*</span>
                                    </label>
                                    <select name="role" class="form-select" required>
                                        <option value="">Select role</option>
                                        <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="pharmacist" <?php echo ($user['role'] === 'pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label">
                                        <i class="fas fa-id-card"></i> Full Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['name']); ?>"
                                           placeholder="Enter full name">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?php echo htmlspecialchars($user['email']); ?>"
                                           placeholder="user@example.com">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i> Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($user['phone']); ?>"
                                           placeholder="Enter 10-digit phone number" pattern="[0-9]{10}"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-section">
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="user_management.php" class="btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Confirm before submitting
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to update this user in <?php echo htmlspecialchars($db); ?> database?')) {
                e.preventDefault();
            }
        });

        // Set active navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>