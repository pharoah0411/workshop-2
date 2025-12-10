<?php
include "connectionSyameel.php";
include "header.php";

$message = "";

// 🟦 1. Get patient ID
if (!isset($_GET['id'])) {
    die("<p style='color:red;'>No patient selected.</p>");
}

$patient_id = $_GET['id'];

// 🟦 2. Fetch the patient + user info
$sql = "
SELECT 
    p.patient_id, p.gender, p.dob, p.ic_no, p.address,
    u.user_id, u.name, u.email, u.phone
FROM patient p
JOIN \"user\" u ON p.user_id = u.user_id
WHERE p.patient_id = :pid
";

$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("<p style='color:red;'>Patient not found.</p>");
}

$user_id = $patient['user_id'];

// 🟦 3. Handle updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // User table data
    $name  = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);

    // Patient table data
    $gender  = trim($_POST["gender"]);
    $dob     = trim($_POST["dob"]);
    $ic_no   = trim($_POST["ic_no"]);
    $address = trim($_POST["address"]);

    try {
        $conn->beginTransaction();

        // Update user table
        $sqlUser = "
            UPDATE \"user\"
            SET name = :n, email = :e, phone = :ph
            WHERE user_id = :uid
        ";

        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([
            ':n'   => $name,
            ':e'   => $email,
            ':ph'  => $phone,
            ':uid' => $user_id
        ]);

        // Update patient table
        $sqlPatient = "
            UPDATE patient
            SET gender = :g, dob = :dob, ic_no = :ic, address = :addr
            WHERE patient_id = :pid
        ";

        $stmtPatient = $conn->prepare($sqlPatient);
        $stmtPatient->execute([
            ':g'    => $gender,
            ':dob'  => $dob,
            ':ic'   => $ic_no,
            ':addr' => $address,
            ':pid'  => $patient_id
        ]);

        $conn->commit();

        $message = "<p style='color:green;'>Patient updated successfully!</p>";

        // Reload updated data
        $stmt->execute([':pid' => $patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "<p style='color:red;'>ERROR: " . $e->getMessage() . "</p>";
    }
}
?>

<h1>Edit Patient</h1>
<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:650px;">

    <h3>Patient Information</h3>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" value="<?= $patient['name'] ?>" required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email" value="<?= $patient['email'] ?>" class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone" value="<?= $patient['phone'] ?>" class="input-box"><br><br>

    <label><strong>Gender:</strong></label><br>
    <select name="gender" class="input-box" required>
        <option value="Male"   <?= $patient['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= $patient['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
    </select>
    <br><br>

    <label><strong>Date of Birth:</strong></label><br>
    <input type="date" name="dob" value="<?= $patient['dob'] ?>" required class="input-box"><br><br>

    <label><strong>IC Number:</strong></label><br>
    <input type="text" name="ic_no" value="<?= $patient['ic_no'] ?>" required class="input-box"><br><br>

    <label><strong>Address:</strong></label><br>
    <textarea name="address" rows="3" class="input-box"><?= $patient['address'] ?></textarea><br><br>

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Save Changes
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
