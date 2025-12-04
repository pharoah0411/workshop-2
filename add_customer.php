<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// DATABASE CONNECTION
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

$success = "";
$error = "";

// HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $ic = $_POST['ic'];
    $dob = $_POST['dob'];
    $address = $_POST['address'];
    $username = $_POST['username'];
    $password_form = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        // INSERT INTO USER TABLE
        $sql_user = $conn->prepare("
            INSERT INTO \"user\" (username, password, role, name, email, phone)
            VALUES (:username, :password, 'customer', :fullname, :email, :phone)
            RETURNING user_id;
        ");

        $sql_user->execute([
            ':username' => $username,
            ':password' => $password_form,
            ':fullname' => $fullname,
            ':email' => $email,
            ':phone' => $phone
        ]);

        $new_user_id = $sql_user->fetchColumn();

        // INSERT INTO PATIENT TABLE
        $sql_patient = $conn->prepare("
            INSERT INTO patient (user_id, gender, dob, address, ic_no)
            VALUES (:uid, :gender, :dob, :address, :ic);
        ");

        $sql_patient->execute([
            ':uid' => $new_user_id,
            ':gender' => $gender,
            ':dob' => $dob,
            ':address' => $address,
            ':ic' => $ic
        ]);

        $success = "Customer successfully added!";
    } 
    catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Customer - Pharmacy Management System</title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: #f0f4ff;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;            /* FIXED WIDTH */
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

        .sidebar a:hover {
            background:#11408a;
        }

        /* CONTENT AREA */
        .content {
            margin-left: 360px;      /* MATCH SIDEBAR WIDTH */
            padding: 20px;      /* MOVE TITLE DOWN */
        }

        h1 {
            color: #0b2f6d;
            margin-top: 0;
        }

        .form-box {
            width: 650px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #0b2f6d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background: #11408a;
        }

        .success {
            color: green;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .back-btn {
            background: #6c757d;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Pharmacy Management System</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="customer.php" style="background:#11408a;">Customers</a>
    <a href="staff.php">Staff</a>
    <a href="products.php">Products</a>
    <a href="sales.php">Sales</a>
    <a href="reports.php">Reports</a>

    <a style="background:#c62828;" href="logout.php">Logout</a>
</div>

<!-- CONTENT -->
<div class="content">

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Add New Customer</h1>
        <a href="customer.php" class="back-btn">← Back</a>
    </div>

    <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST">

            <h3>Login Details</h3>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <h3>Personal Information</h3>
            <input type="text" name="fullname" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="phone" placeholder="Phone Number">

            <select name="gender" required>
                <option value="">Select Gender...</option>
                <option>Male</option>
                <option>Female</option>
            </select>

            <label>Date of Birth:</label>
            <input type="date" name="dob" required>

            <input type="text" name="ic" placeholder="IC Number" required>

            <textarea name="address" placeholder="Address" rows="3"></textarea>

            <button type="submit">Add Customer</button>
        </form>
    </div>

</div>

</body>
</html>
