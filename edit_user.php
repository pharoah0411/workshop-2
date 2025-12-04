<?php
include "connectionSyameel.php";
include "header.php";

$message = "";

// 🟦 1. Get user ID
if (!isset($_GET['id'])) {
    die("<p style='color:red;'>No user selected.</p>");
}

$user_id = $_GET['id'];

// 🟦 2. Fetch the user data
$sql = 'SELECT * FROM "user" WHERE user_id = :id';
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("<p style='color:red;'>User not found.</p>");
}

// 🟦 3. Handle updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $phone    = trim($_POST["phone"]);
    $role     = trim($_POST["role"]);
    $password = trim($_POST["password"]);

    try {
        if ($password !== "") {
            // Update with password
            $sqlUpdate = '
                UPDATE "user"
                SET username = :u, name = :n, email = :e, phone = :ph, role = :r, password = :p
                WHERE user_id = :id
            ';

            $stmtUp = $conn->prepare($sqlUpdate);
            $stmtUp->execute([
                ':u'  => $username,
                ':n'  => $name,
                ':e'  => $email,
                ':ph' => $phone,
                ':r'  => $role,
                ':p'  => $password,
                ':id' => $user_id
            ]);
        } else {
            // Update WITHOUT password
            $sqlUpdate = '
                UPDATE "user"
                SET username = :u, name = :n, email = :e, phone = :ph, role = :r
                WHERE user_id = :id
            ';

            $stmtUp = $conn->prepare($sqlUpdate);
            $stmtUp->execute([
                ':u'  => $username,
                ':n'  => $name,
                ':e'  => $email,
                ':ph' => $phone,
                ':r'  => $role,
                ':id' => $user_id
            ]);
        }

        $message = "<p style='color:green;'>User updated successfully!</p>";

        // Reload updated data
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $message = "<p style='color:red;'>ERROR: " . $e->getMessage() . "</p>";
    }
}
?>

<h1>Edit User</h1>
<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:600px;">

    <label><strong>Username:</strong></label><br>
    <input type="text" name="username" value="<?= $user['username'] ?>" required class="input-box"><br><br>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" value="<?= $user['name'] ?>" required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email" value="<?= $user['email'] ?>" class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone" value="<?= $user['phone'] ?>" class="input-box"><br><br>

    <label><strong>Role:</strong></label><br>
    <select name="role" class="input-box" required>
        <option value="admin"      <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="pharmacist" <?= $user['role'] == 'pharmacist' ? 'selected' : '' ?>>Pharmacist</option>
    </select>
    <br><br>

    <label><strong>Change Password (optional):</strong></label><br>
    <input type="password" name="password" placeholder="Leave blank to keep old password" class="input-box"><br><br>

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Save Changes
    </button>

    <a href="user_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:5px;
       margin-left:10px; text-decoration:none;">
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
