<?php
// PostgreSQL Connection (Using your existing file name)

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
?>
