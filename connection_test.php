<?php
// connection_test.php
$local_host = "localhost";
$local_db   = "pharmacy_db"; 
$local_user = "root";
$local_pass = "";

try {
    $pdo = new PDO("mysql:host=$local_host;dbname=$local_db", $local_user, $local_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // This variable name must match what login.php looks for
    $conn = $pdo; 
} catch (Exception $e) {
    die("Local Test Connection Failed: " . $e->getMessage());
}
?>