<?php
include "connectionSyameel.php";
include "header.php";

$message = "";

// Function to auto-generate username
function generateUsername($conn) {
    $count = $conn->query("SELECT COUNT(*) FROM \"user\" WHERE role = 'patient'")->fetchColumn();
    $count++;
    return "patient" . str_pad($count, 3, "0", STR_PAD_LEFT); 
}

// Function to auto-generate password
function generatePassword() {
    return "PT" . rand(10000, 99999);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Auto-generate login details
    $username = generateUsername($conn);
    $password = generatePassword();

    // Patient details
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $gender   = trim($_POST['gender']);
    $dob      = trim($_POST['dob']);
    $ic_no    = trim($_POST['ic_no']);
    $address  = trim($_POST['address']);

    try {
        $conn->beginTransaction();

        // Insert into user table
        $sqlUser = "
            INSERT INTO \"user\" (username, password, role, name, email, phone)
            VALUES (:u, :p, 'patient', :n, :e, :ph)
            RETURNING user_id
        ";

        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([
            ':u'  => $username,
            ':p'  => $password,
            ':n'  => $name,
            ':e'  => $email,
            ':ph' => $phone
        ]);

        $newUserId = $stmtUser->fetchColumn();

        // Insert into patient table
        $sqlPatient = "
            INSERT INTO patient (user_id, gender, dob, address, ic_no)
            VALUES (:uid, :g, :dob, :addr, :ic)
        ";

        $stmtPatient = $conn->prepare($sqlPatient);
        $stmtPatient->execute([
            ':uid'  => $newUserId,
            ':g'    => $gender,
            ':dob'  => $dob,
            ':addr' => $address,
            ':ic'   => $ic_no
        ]);

        $conn->commit();

        $message = "
        <p style='color:green;'>
            Patient added successfully!<br>
            <strong>Auto Login:</strong><br>
            Username: <b>$username</b><br>
            Password: <b>$password</b>
        </p>";
    
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "<p style='color:red;'>ERROR: " . $e->getMessage() . "</p>";
    }
}
?>

<h1>Add New Patient</h1>

<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:650px;">

    <h3>Personal Information</h3>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email" class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone" class="input-box"><br><br>

    <label><strong>Gender:</strong></label><br>
    <select name="gender" class="input-box" required>
        <option value="">Select gender...</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
    </select>
    <br><br>

    <label><strong>Date of Birth:</strong></label><br>
    <input type="date" name="dob" required class="input-box"><br><br>

    <label><strong>IC Number:</strong></label><br>
    <input type="text" name="ic_no" required class="input-box"><br><br>

    <label><strong>Address:</strong></label><br>
    <textarea name="address" rows="3" class="input-box"></textarea><br><br>

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Add Patient
    </button>

    <a href="patient_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:5px; margin-left:10px; text-decoration:none;">
        Cancel
    </a>

</form>

<style>
.input-box {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
</style>

</div> <!-- end content -->
</body>
</html>
