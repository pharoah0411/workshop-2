<?php
// connection.php

// --- ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize connection variables
$mysql_conn = null;  // Kept null (Connection #1 removed)
$mysql_conn2 = null; // MySQL #2
$pg_conn = null;     // PostgreSQL
$pdo = null;         // SQL Server (PDO)
$conn = null;        // SQL Server (Legacy)

// ==================================================================================
// 1. MySQL Connection #2 (Secondary)
// ==================================================================================
$mysql2_servername = "127.0.0.1";  // or "localhost"
$mysql2_username = "farah";
$mysql2_password = "Syimazmi201.";
$mysql2_dbname = "medicine prescription";
$mysql2_port = 3306; 

try {
    // Attempt to connect. If it fails (timeout/offline), it jumps to 'catch'
    $mysql_conn2 = new mysqli($mysql2_servername, $mysql2_username, $mysql2_password, $mysql2_dbname, $mysql2_port);

    // Check for logical connection errors even if no exception was thrown
    if ($mysql_conn2->connect_error) {
        throw new Exception($mysql_conn2->connect_error);
    }

} catch (Exception $e) {
    // Connection failed: Set variable to null so the app knows it's offline
    $mysql_conn2 = null;
    $mysql2_error = "MySQL Failed: " . $e->getMessage();
}

// ==================================================================================
// 2. PostgreSQL Connection
// ==================================================================================
$pg_host = "10.245.156.44";
$pg_port = "5432"; 
$pg_dbname = "Workshop"; 
$pg_user = "farah"; 
$pg_password = "12345"; 

try {
    $dsn = "pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname";
    $pg_conn = new PDO($dsn, $pg_user, $pg_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $conn = $pg_conn;


} catch (Exception $e) {
    // Connection failed: Set variable to null
    $pg_conn = null;
    $pg_error = "PostgreSQL Failed: " . $e->getMessage();
}

// ==================================================================================
// 3. SQL Server Connection (IP Address)
// ==================================================================================
$serverIp = "10.245.156.110"; 
$serverPort = "1433";        
$serverName = "$serverIp, $serverPort"; // Note: SQL Server uses a comma for ports

$database = "Workshop";
$uid = "myuser";         
$pass = "StrongPass123!"; 

try {
    // Try PDO first (Recommended)
    $dsn = "sqlsrv:Server={$serverName};Database={$database};TrustServerCertificate=true;Encrypt=false";
    $pdo = new PDO($dsn, $uid, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
} catch (Exception $e) {
    // If PDO fails, try legacy sqlsrv driver inside this catch block
    $pdo = null; // Ensure PDO is null
    
    if (function_exists('sqlsrv_connect')) {
        // Use @ to suppress warnings for the legacy driver, as it doesn't always throw Exceptions
        $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
        $conn = @sqlsrv_connect($serverName, $connectionInfo);
        
        if ($conn === false) {
             $conn = null; // Ensure legacy conn is null if failed
             // Optional: log error using sqlsrv_errors()
        }
    }
}
