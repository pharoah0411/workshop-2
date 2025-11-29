<?php
// connectionIrfah.php

$host = "localhost";
$dbname = "Workshop";
$user = "postgres";
$password = "admin";

try {
    // Create PDO connection
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "<p style='color:green;'>Connection successful!</p>";

    // Test query: get all users
    $stmt = $pdo->query("SELECT * FROM \"USER\" LIMIT 5");
    
    if($stmt->rowCount() > 0){
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Name</th></tr>";
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            echo "<tr>";
            echo "<td>".$row['user_id']."</td>";
            echo "<td>".$row['username']."</td>";
            echo "<td>".$row['role']."</td>";
            echo "<td>".$row['name']."</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found.</p>";
    }

} catch (PDOException $e) {
    die("<p style='color:red;'>Connection failed: " . $e->getMessage() . "</p>");
}
?>
