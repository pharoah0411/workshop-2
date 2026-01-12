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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
    body { background-color: #f0f2f5; display: flex; min-height: 100vh; }

    /* --- SIDEBAR STYLE --- */
    .sidebar {
        width: 280px;
        background-color: #007bff;
        color: white;
        padding: 40px 0;
        position: fixed;
        height: 100vh;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        z-index: 1000;
    }

    .sidebar-brand {
        padding: 0 30px;
        margin-bottom: 40px;
        font-size: 1.5em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .nav-menu { list-style: none; }
    
    /* FLOAT EFFECT: Sidebar Links */
    .nav-item a {
        display: block;
        padding: 15px 30px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        font-size: 0.95em;
    }

    .nav-item a:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
        padding-left: 40px; /* Slight slide in effect */
        transform: translateX(5px); /* Subtle horizontal float */
    }

    .nav-item.active a {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        border-left: 5px solid white;
    }

    /* --- MAIN CONTENT STYLE --- */
    .main-content {
        margin-left: 280px;
        flex: 1;
        padding: 50px;
    }

    .page-title {
        font-size: 2.2em;
        color: #1a237e;
        margin-bottom: 40px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* FLOAT EFFECT: Module Cards */
    .module-section {
        background: white;
        border-radius: 16px;
        padding: 35px;
        margin-bottom: 30px;
        max-width: 900px;
        /* Initial state: subtle shadow */
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .module-section:hover {
        /* Hover state: Lifts up and shadow deepens */
        transform: translateY(-10px); 
        box-shadow: 0 20px 30px rgba(0,0,0,0.1);
        border-color: #007bff;
    }

    .module-header {
        font-size: 1.5em;
        font-weight: 700;
        color: #1a237e;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .module-list {
        list-style: none;
        padding-left: 10px;
    }

    .module-list li {
        margin-bottom: 12px;
    }

    /* FLOAT EFFECT: Individual List Buttons */
    .module-list a {
        text-decoration: none;
        color: #444;
        font-weight: 600;
        display: inline-block;
        padding: 8px 16px;
        border-radius: 8px;
        background: #f8f9fa;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .module-list a:hover {
        background: #eef6ff;
        color: #007bff;
        transform: translateY(-3px); /* Button floats up slightly */
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        border-color: rgba(0, 123, 255, 0.2);
    }

    /* Top Utility Bar */
    .top-bar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 30px;
        gap: 25px;
        align-items: center;
    }
    
    .logout-btn {
        background: #ffeded;
        color: #d32f2f;
        padding: 8px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s;
    }

    .logout-btn:hover {
        background: #d32f2f;
        color: white;
        transform: scale(1.05);
    }
</style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">USER & MANAGEMENT</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item active"><a href="user_management.php">User List</a></li>
            <li class="nav-item"><a href="add_user.php">Add User</a></li>
            <li class="nav-item"><a href="patient_list.php">Patient List</a></li>
            <li class="nav-item"><a href="add_patient.php">Add Patient</a></li>
            <li class="nav-item"><a href="permissions.php">Role Permissions</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <span>Welcome, <strong><?= htmlspecialchars($username) ?></strong></span>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <h1 class="page-title"><i class="fas fa-user-circle"></i> User Management Dashboard</h1>

        <div class="module-section animate__animated animate__fadeIn">
            <div class="module-header">
                <span>👨‍💼</span> User Accounts
            </div>
            <ul class="module-list">
                <li><a href="user_list.php">View All Users</a></li>
                <li><a href="add_user.php">Add New User</a></li>
            </ul>
        </div>

        <div class="module-section animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
            <div class="module-header">
                <span>🩺</span> Patient Management
            </div>
            <ul class="module-list">
                <li><a href="patient_list.php">View Patient List</a></li>
                <li><a href="add_patient.php">Add New Patient</a></li>
            </ul>
        </div>

        <div class="module-section animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
            <div class="module-header">
                <span>🔐</span> Role & Permission Control
            </div>
            <ul class="module-list">
                <li><a href="permissions.php">Manage Role Permissions</a></li>
            </ul>
        </div>
    </div>

</body>
</html>