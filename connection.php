<?php

$serverName = "PHAROAHS";
$database = "Workshop";
$uid = ""; // set DB username if using SQL authentication
$pass = ""; // set DB password if using SQL authentication

// Preferred: try sqlsrv extension first
if (function_exists('sqlsrv_connect')) {
    $connectionInfo = [
        "Database" => $database,
        "UID" => $uid,
        "PWD" => $pass,
    ];
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn) {
        echo "Connection established using sqlsrv extension.<br />";
        return;
    }
    echo "sqlsrv extension loaded but connection failed:\n";
    die(print_r(sqlsrv_errors(), true));
}

// If sqlsrv is not available, try PDO with sqlsrv driver
if (class_exists('PDO')) {
    try {
        // Try PDO sqlsrv DSN
        $dsn = "sqlsrv:Server={$serverName};Database={$database}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $pdo = new PDO($dsn, $uid, $pass, $options);
        echo "Connection established using PDO sqlsrv driver.<br />";
        return;
    } catch (PDOException $e) {
        // continue to ODBC fallback
        $pdoError = $e->getMessage();
    }
}

// Final fallback: try PDO ODBC (requires Microsoft ODBC Driver installed)
try {
    // DSN-less ODBC connection string — ensure correct ODBC driver name for your system
    $odbcDriver = '{ODBC Driver 18 for SQL Server}';
    $odbcDsn = "odbc:Driver={$odbcDriver};Server={$serverName};Database={$database};";
    if ($uid !== '') {
        $odbcDsn .= "Uid={$uid};Pwd={$pass};";
    } else {
        $odbcDsn .= "Trusted_Connection=Yes;";
    }
    $pdo2 = new PDO($odbcDsn);
    echo "Connection established using PDO ODBC driver.<br />";
    return;
} catch (Exception $e) {
    // All methods failed — show helpful diagnostics
    echo "Unable to connect using sqlsrv, PDO sqlsrv, or PDO ODBC.\n";
    if (isset($pdoError)) echo "PDO sqlsrv error: " . htmlspecialchars($pdoError) . "\n";
    echo "PDO/ODBC error: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "\nPossible causes:\n";
    echo " - The Microsoft PHP Drivers for SQL Server (php_sqlsrv and php_pdo_sqlsrv) are not installed/enabled.\n";
    echo " - The Microsoft ODBC Driver for SQL Server is not installed or architecture (x86/x64) mismatches PHP.\n";
    echo " - Visual C++ Redistributable required by the driver is missing.\n";
    echo "\nRun http://localhost/workshop/test_sqlsrv.php to view PHP configuration details and help determine the correct driver build.\n";
    exit;
}

?>