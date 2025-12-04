<?php
include "connectionSyameel.php";
include "header.php";

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Insert into user table
    try {
        $sql = "INSERT INTO \"user\" (username, password, role, name, email, phone)
                VALUES (:u, :p, :r, :n, :e, :ph)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':u' => $username,
            ':p' => $password,  // plain text for now (can be updated)
            ':r' => $role,
            ':n' => $name,
            ':e' => $email,
            ':ph' => $phone
        ]);

        $message = "<p style='color:green;'>User has been added successfully!</p>";

    } catch (PDOException $e) {
        $message = "<p style='color:red;'>ERROR: " . $e->getMessage() . "</p>";
    }
}
?>

<h1>Add New User</h1>

<?= $message ?>

<form method="POST" 
      style="background:white; padding:20px; border-radius:10px; width:600px;">

    <label><strong>Username:</strong></label><br>
    <input type="text" name="username" required class="input-box"><br><br>

    <label><strong>Password:</strong></label><br>
    <input type="password" name="password" required class="input-box"><br><br>

    <label><strong>Role:</strong></label><br>
    <select name="role" class="input-box" required>
        <option value="">Select role...</option>
        <option value="admin">Admin</option>
        <option value="pharmacist">Pharmacist</option>
    </select>
    <br><br>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email" required class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone" required class="input-box"><br><br>

    <button type="submit" 
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Add User
    </button>

    <a href="user_list.php" 
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
