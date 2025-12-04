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
$mysql2_servername = "10.245.156.39"; 
$mysql2_username = "farah";
$mysql2_password = "Syimazmi201.";
$mysql2_dbname = "medicine prescription";
$mysql2_port = 3306; 

// Suppress error with @, check manually below
$temp_mysql2 = @new mysqli($mysql2_servername, $mysql2_username, $mysql2_password, $mysql2_dbname, $mysql2_port);

if ($temp_mysql2->connect_error) {
    // You can log this error or display it if needed
    $mysql2_error = "MySQL #2 Failed: " . $temp_mysql2->connect_error;
} else {
    $mysql_conn2 = $temp_mysql2;
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
} catch (PDOException $e) {
    $pg_error = "PostgreSQL Failed: " . $e->getMessage();
}

// ==================================================================================
// 3. SQL Server Connection (IP Address)
// ==================================================================================
$serverIp = "10.245.156.110"; 
$serverPort = "1433";        
$serverName = "$serverIp, $serverPort"; // Note: SQL Server uses a comma for ports

$database = "Workshop";
$uid = "myuser";          // UPDATE THIS if 'myuser' is not correct
$pass = "StrongPass123!"; // UPDATE THIS if 'StrongPass123!' is not correct

try {
    // Try PDO first (Recommended)
    $dsn = "sqlsrv:Server={$serverName};Database={$database}";
    $pdo = new PDO($dsn, $uid, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
} catch (PDOException $e) {
    // If PDO fails, try legacy sqlsrv driver
    if (function_exists('sqlsrv_connect')) {
        $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
        $conn = @sqlsrv_connect($serverName, $connectionInfo);
        
        if ($conn === false) {
             // Optional error handling
             // echo "SQL Server Legacy Connection Failed.<br>";
        }
    }
}
?>