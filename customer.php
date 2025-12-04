<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// DB connection
$host = "localhost";
$port = "5432";
$dbname = "Workshop";
$user = "postgres";
$password = "admin";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch customers (JOIN USER + PATIENT)
$query = $conn->prepare("
    SELECT 
        p.patient_id,
        u.name AS fullname,
        u.email,
        u.phone,
        p.gender,
        p.ic_no,
        p.address,
        p.dob
    FROM patient p
    LEFT JOIN \"user\" u ON p.user_id = u.user_id
    ORDER BY p.patient_id ASC;
");

$query->execute();
$customers = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customers - Pharmacy Management System</title>
    <style>
        body { margin: 0; font-family: Arial; background: #f0f4ff; }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #0b2f6d;
            height: 100vh;
            position: fixed;
            padding: 20px;
            color: white;
        }
        .sidebar a {
            display: block;
            padding: 12px;
            color: white;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 5px;
        }
        .sidebar a:hover { background:#11408a; }

        /* Content */
        .content {
            margin-left: 360px;
            padding: 20px;
        }

        h1 { color: #0b2f6d; }

        .btn-add {
            background: #28a745;
            padding: 10px 15px;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 15px;
            display: inline-block;
        }

        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }

        th {
            background: #0b2f6d;
            color: white;
            padding: 12px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover { background: #eef5ff; }

        .btn-edit {
            background: #1e90ff;
            padding: 6px 10px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn-delete {
            background: #d9534f;
            padding: 6px 10px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Pharmacy Management System</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="customers.php" style="background:#11408a;">Customers</a>
    <a href="staff.php">Staff</a>
    <a href="products.php">Products</a>
    <a href="sales.php">Sales</a>
    <a href="reports.php">Reports</a>
    <a style="background:#c62828;" href="logout.php">Logout</a>
</div>

<!-- Content -->
<div class="content">
    <h1>Customer List</h1>

    <a class="btn-add" href="add_customer.php">+ Add Customer</a>

    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>IC No</th>
            <th>Gender</th>
            <th>DOB</th>
            <th>Address</th>
            <th>Action</th>
        </tr>

        <?php foreach ($customers as $c): ?>
        <tr>
            <td><?= $c['patient_id'] ?></td>
            <td><?= htmlspecialchars($c['fullname']) ?></td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td><?= htmlspecialchars($c['phone']) ?></td>
            <td><?= htmlspecialchars($c['ic_no']) ?></td>
            <td><?= htmlspecialchars($c['gender']) ?></td>
            <td><?= htmlspecialchars($c['dob']) ?></td>
            <td><?= htmlspecialchars($c['address']) ?></td>

            <td>
                <a class="btn-edit" href="edit_customer.php?id=<?= $c['patient_id'] ?>">Edit</a>
                <a class="btn-delete" 
                   href="delete_customer.php?id=<?= $c['patient_id'] ?>"
                   onclick="return confirm('Delete this customer?')">
                   Delete
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

</div>

</body>
</html>
