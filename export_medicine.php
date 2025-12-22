<?php
require_once 'connection.php';

// Fetch all medicines for export
$all_meds = [];
$sql = "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $all_meds[] = array_change_key_case($row, CASE_UPPER);
        }
    }
} catch (Exception $e) { die("Export failed: " . $e->getMessage()); }

$type = $_GET['type'] ?? 'excel';

if ($type === 'excel') {
    // CSV Export (Excel compatible)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Inventory_Report_' . date('Ymd') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // User-friendly Headers
    fputcsv($output, ['Medicine ID', 'Medicine Name', 'Category', 'Stock Level', 'Expiry Date', 'Unit Price ($)']);
    
    foreach ($all_meds as $m) {
        // 1. Format Price: Convert 0.2 to $0.20
        $formattedPrice = '$' . number_format((float)$m['UNIT_PRICE'], 2);
        
        // 2. Format Date: Convert 2027-12-31 to 31-Dec-2027 (Better for non-IT users)
        $formattedDate = !empty($m['EXPIRY_DATE']) 
            ? date('d-M-Y', strtotime($m['EXPIRY_DATE'])) 
            : 'N/A';

        // Write row to CSV
        fputcsv($output, [
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
        <title>Inventory Report</title>
        <style>
            body { font-family: sans-serif; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print"><button onclick="window.print()">Print / Save PDF</button> <a href="medDirectory.php">Back</a></div>
        <h1>Medicine Inventory Report - <?= date('Y-m-d') ?></h1>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Stock</th><th>Price</th></tr></thead>
            <tbody>
                <?php foreach ($all_meds as $m): ?>
                <tr><td><?= $m['MEDICINE_ID'] ?></td><td><?= $m['NAME'] ?></td><td><?= $m['QUANTITY_IN_STOCK'] ?></td><td>$<?= $m['UNIT_PRICE'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}