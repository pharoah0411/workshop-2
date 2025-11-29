<?php
echo "<h2>PHP & PostgreSQL Test Page</h2>";

// 1. Test PHP
echo "<p><strong>PHP is working!</strong></p>";

// 2. Test loaded extensions
echo "<h3>Loaded Extensions:</h3>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

// 3. Test PostgreSQL Connection
echo "<h3>PostgreSQL Connection Test:</h3>";

$host = "localhost";
$port = "5432";
$dbname = "Workshop";
$user = "postgres";
$password = "admin";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p style='color: green;'><strong>Connected to PostgreSQL successfully!</strong></p>";

    // Test sample query
    $stmt = $conn->query("SELECT NOW() AS server_time");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>PostgreSQL Server Time: " . $row['server_time'] . "</p>";
}
catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Connection failed:</strong> " . $e->getMessage() . "</p>";
}
?>
