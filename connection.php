<?php
// Central DB connection file for the workshop app.
// This file attempts to create a connection to Microsoft SQL Server
// and exposes either `$conn` (sqlsrv) or `$pdo` (PDO) depending on
// which driver is available. It is intentionally silent (no echo)
// so it is safe to include from pages and API endpoints.

$serverName = "PHAROAHS";
$database = "Workshop";
$uid = ""; // set DB username if using SQL authentication
$pass = ""; // set DB password if using SQL authentication

// If this file is accessed directly (not included), perform a quick
// diagnostic and print environment + driver test results so you can
// verify configuration from a browser. When included by other scripts
// it remains silent as before.
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    // Collect environment info and per-driver test results to help debugging
    $info = [];
    $info['php_version'] = phpversion();
    $info['sapi'] = php_sapi_name();
    $info['server'] = $serverName;
    $info['database'] = $database;
    $info['loaded_extensions'] = get_loaded_extensions();

    $results = [];

    // Test sqlsrv extension if present
    if (function_exists('sqlsrv_connect')) {
        $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
        $tmp = @sqlsrv_connect($serverName, $connectionInfo);
        if ($tmp !== false) {
            echo "connected (sqlsrv)";
            exit;
        }
        $results['sqlsrv'] = 'available but connection failed';
    } else {
        $results['sqlsrv'] = 'extension not installed';
    }

    // Test PDO + sqlsrv driver
    if (class_exists('PDO')) {
        try {
            $pdoDrivers = PDO::getAvailableDrivers();
            $results['pdo_drivers'] = $pdoDrivers;
            if (in_array('sqlsrv', $pdoDrivers, true)) {
                try {
                    $dsn = "sqlsrv:Server={$serverName};Database={$database}";
                    $tmp = new PDO($dsn, $uid, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    echo "connected (pdo_sqlsrv)";
                    exit;
                } catch (PDOException $e) {
                    $results['pdo_sqlsrv_error'] = $e->getMessage();
                }
            } else {
                $results['pdo_sqlsrv'] = 'pdo sqlsrv driver not available';
            }
        } catch (Exception $e) {
            $results['pdo'] = 'PDO present but getAvailableDrivers failed: ' . $e->getMessage();
        }
    } else {
        $results['pdo'] = 'PDO not available';
    }

    // Try PDO ODBC fallback if configured
    try {
        $odbcDriver = '{ODBC Driver 18 for SQL Server}';
        $odbcDsn = "odbc:Driver={$odbcDriver};Server={$serverName};Database={$database};";
        if ($uid !== '') {
            $odbcDsn .= "Uid={$uid};Pwd={$pass};";
        } else {
            $odbcDsn .= "Trusted_Connection=Yes;";
        }
        $tmp = new PDO($odbcDsn);
        echo "connected (pdo_odbc)";
        exit;
    } catch (Exception $e) {
        $results['pdo_odbc'] = $e->getMessage();
    }

    // If we reach here, no driver connected. Show useful diagnostics.
    header('Content-Type: text/plain; charset=utf-8');
    echo "not connected\n\n";
    echo "PHP version: " . $info['php_version'] . "\n";
    echo "SAPI: " . $info['sapi'] . "\n";
    echo "Server: " . $info['server'] . "\n";
    echo "Database: " . $info['database'] . "\n\n";

    echo "Loaded extensions: \n";
    sort($info['loaded_extensions']);
    foreach ($info['loaded_extensions'] as $ext) {
        echo " - " . $ext . "\n";
    }

    echo "\nDriver test results: \n";
    foreach ($results as $k => $v) {
        if (is_array($v)) {
            echo " - $k: " . implode(',', $v) . "\n";
        } else {
            echo " - $k: $v\n";
        }
    }

    echo "\nCommon fixes:\n";
    echo " - Install Microsoft Drivers for PHP for SQL Server that match your PHP version (TS/NTS, architecture).\n";
    echo " - Install ODBC Driver for SQL Server (17 or 18) for PDO ODBC fallback.\n";
    echo " - Enable extensions by copying the driver DLLs into PHP's ext/ and adding extension= lines to php.ini, then restart Apache.\n";
    exit;
}

$dbType = null; // 'sqlsrv' | 'pdo_sqlsrv' | 'pdo_odbc'
$conn = null; // for sqlsrv
$pdo = null;  // for PDO

// Try sqlsrv extension first
if (function_exists('sqlsrv_connect')) {
    $connectionInfo = [
        "Database" => $database,
        "UID" => $uid,
        "PWD" => $pass,
    ];
    $tmp = @sqlsrv_connect($serverName, $connectionInfo);
    if ($tmp !== false) {
        $conn = $tmp;
        $dbType = 'sqlsrv';
        return;
    }
}

// Try PDO with sqlsrv driver
if (class_exists('PDO')) {
    try {
        $dsn = "sqlsrv:Server={$serverName};Database={$database}";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $tmp = new PDO($dsn, $uid, $pass, $options);
        $pdo = $tmp;
        $dbType = 'pdo_sqlsrv';
        return;
    } catch (PDOException $e) {
        // continue to ODBC fallback quietly
    }
}

// Fallback: PDO ODBC (requires ODBC driver)
try {
    $odbcDriver = '{ODBC Driver 18 for SQL Server}';
    $odbcDsn = "odbc:Driver={$odbcDriver};Server={$serverName};Database={$database};";
    if ($uid !== '') {
        $odbcDsn .= "Uid={$uid};Pwd={$pass};";
    } else {
        $odbcDsn .= "Trusted_Connection=Yes;";
    }
    $tmp = new PDO($odbcDsn);
    $pdo = $tmp;
    $dbType = 'pdo_odbc';
    return;
} catch (Exception $e) {
    // All connection attempts failed. We keep the file silent.
    // API endpoints that require DB can check for $conn/$pdo and
    // produce helpful errors to the client.
    return;
}

?>