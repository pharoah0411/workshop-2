<?php
require_once 'connection.php';

// Fetch all medicines for export from ALL databases
$all_meds = [];
$sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";

try {
    // 1. Fetch from MySQL
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $result = $mysql_conn2->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = 'MYSQL'; 
                $all_meds[] = $r;
            }
        }
    }

    // 2. Fetch from PostgreSQL
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->query($sql);
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['DB_SOURCE'] = 'POSTGRES';
                $all_meds[] = $r;
            }
        }
    }

    // 3. Fetch from SQL Server
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $r = array_change_key_case($r, CASE_UPPER);
                $r['DB_SOURCE'] = 'SQLSRV';
                $all_meds[] = $r;
            }
        }
    }
} catch (Exception $e) { 
    die("Export failed: " . $e->getMessage()); 
}

$type = $_GET['type'] ?? 'excel';

if ($type === 'excel') {
    // CSV Export (Excel compatible)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=MultiDB_Inventory_Report_' . date('Ymd') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Updated Headers to include Source
    fputcsv($output, ['Source DB', 'Medicine ID', 'Medicine Name', 'Category', 'Stock Level', 'Expiry Date', 'Unit Price ($)']);
    
    foreach ($all_meds as $m) {
        $formattedPrice = '$' . number_format((float)$m['UNIT_PRICE'], 2);
        $formattedDate = !empty($m['EXPIRY_DATE']) 
            ? date('d-M-Y', strtotime($m['EXPIRY_DATE'])) 
            : 'N/A';

        // Write row to CSV with Source DB tag
        fputcsv($output, [
            $m['DB_SOURCE'],
            $m['MEDICINE_ID'],
            $m['NAME'],
            $m['CATEGORY_TYPE'],
            $m['QUANTITY_IN_STOCK'],
            $formattedDate,
            $formattedPrice
        ]);
    }
    
    fclose($output);
    exit;
} elseif ($type === 'print' || $type === 'pdf') {
    // Print/PDF View
    ?>
    <html>
    <head>
        <title>Full Inventory Report (All Databases)</title>
        <style>
            body { font-family: sans-serif; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            .source-tag { font-size: 0.8em; font-weight: bold; color: #666; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print">
            <button onclick="window.print()">Print / Save PDF</button> 
            <a href="medDirectory.php">Back to Directory</a>
        </div>
        <h1>Combined Medicine Inventory Report</h1>
        <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_meds as $m): ?>
                <tr>
                    <td><span class="source-tag"><?= $m['DB_SOURCE'] ?></span></td>
                    <td><?= htmlspecialchars($m['MEDICINE_ID']) ?></td>
                    <td><?= htmlspecialchars($m['NAME']) ?></td>
                    <td><?= htmlspecialchars($m['CATEGORY_TYPE'] ?? 'N/A') ?></td>
                    <td><?= $m['QUANTITY_IN_STOCK'] ?></td>
                    <td>$<?= number_format((float)$m['UNIT_PRICE'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}