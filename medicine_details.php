<?php 
require_once 'connection.php'; 

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$med = null;
$error = '';

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

try {
    // 1. Try MySQL 2
    if (!$med && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    }
    // 2. Try Postgres
    if (!$med && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
    // 3. Try SQL Server - FIXED: Use $pdo_sqlsrv
    if (!$med) {
        if (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) { // FIXED HERE
            $stmt = $pdo_sqlsrv->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $med = array_change_key_case($row, CASE_UPPER);
        } elseif (isset($conn) && $conn !== false) {
            $res = sqlsrv_query($conn, "SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?", [$id]);
            if ($res && $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) $med = array_change_key_case($row, CASE_UPPER);
        }
    }
} catch (Exception $e) {
    $error = 'Failed to load details: ' . htmlspecialchars($e->getMessage());
}

// Date Formatting
$expiryDate = 'N/A';
if ($med && !empty($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) $expiryDate = $med['EXPIRY_DATE']->format('M j, Y');
    else $expiryDate = date('M j, Y', strtotime($med['EXPIRY_DATE']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM - Medicine Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme */
        :root {
            --dark-blue: #1c4966;
            --blue-medium: #2a5d7a;
            --blue-light: #e3f2fd;
            --blue-accent: #4a90e2;
            --cream-white: #f8fafc;
            --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50;
            --alert-red: #d9534f;
            --warning-orange: #f0ad4e;
            --success-green: #5cb85c;
            
            --main-bg: #f5f7fa;
            --card-bg: white;
            --border-color: #e1e8ed;
            --text-primary: var(--dark-grey);
            --text-secondary: var(--soft-grey);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Be Vietnam Pro", sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-weight: 400;
            line-height: 1.5;
        }

        .details-container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
        }

        .details-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .details-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%231c4966' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .header-content h1 {
            font-size: 1.6em;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .header-content p {
            font-size: 1em;
            opacity: 0.9;
            font-weight: 300;
            margin-bottom: 15px;
        }

        .medicine-id-display {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            font-size: 0.9em;
            letter-spacing: 1px;
        }

        .details-content {
            padding: 40px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .detail-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }

        .detail-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .detail-title {
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--soft-grey);
            margin-bottom: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            font-size: 1.4em;
            font-weight: 600;
            color: var(--dark-grey);
            margin-top: 5px;
        }

        .detail-price {
            color: var(--success-green);
            font-size: 1.6em;
        }

        .stock-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-top: 10px;
        }

        .stock-good {
            background: #d4edda;
            color: #155724;
        }

        .stock-low {
            background: #ffe0b2;
            color: #e65100;
        }

        .stock-warning {
            background: #f8d7da;
            color: #721c24;
        }

        .expiry-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-top: 10px;
        }

        .expiry-valid {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .expiry-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        .expiry-expired {
            background: #ffebee;
            color: #c62828;
        }

        .details-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            text-align: center;
            min-width: 160px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(28, 73, 102, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-blue);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--dark-blue);
            transform: translateY(-3px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--dark-blue);
        }

        .empty-state h3 {
            font-size: 1.8em;
            margin-bottom: 10px;
            color: var(--dark-grey);
        }

        .empty-state p {
            font-size: 1.1em;
            color: var(--soft-grey);
            margin-bottom: 30px;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-size: 1em;
        }

        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .details-content {
                padding: 25px;
            }
            
            .details-header {
                padding: 25px 20px;
            }
            
            .header-content h1 {
                font-size: 1.3em;
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                min-width: 140px;
                padding: 12px 20px;
            }
            
            .details-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .details-header {
                padding: 20px 15px;
            }
            
            .header-content h1 {
                font-size: 1.1em;
            }
            
            .header-content p {
                font-size: 0.9em;
            }
            
            .detail-card {
                padding: 20px;
            }
            
            .detail-value {
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <header class="details-header">
            <div class="header-content">
                <?php if ($med): ?>
                    <h1>
                        <i class="fas fa-pills"></i> <?php echo htmlspecialchars($med['NAME']); ?>
                    </h1>
                    <p>Complete medicine information and inventory details</p>
                    <div class="medicine-id-display">
                        <i class="fas fa-hashtag"></i> ID: <?php echo htmlspecialchars($med['MEDICINE_ID']); ?>
                    </div>
                <?php else: ?>
                    <h1><i class="fas fa-search"></i> Medicine Details</h1>
                    <p>View complete medicine information</p>
                <?php endif; ?>
            </div>
        </header>

        <div class="details-content">
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($med): ?>
                <div class="details-grid">
                    <div class="detail-card">
                        <div class="detail-title">
                            <i class="fas fa-tag"></i> Medicine Information
                        </div>
                        <div class="detail-value"><?php echo htmlspecialchars($med['NAME']); ?></div>
                        <div style="margin-top: 15px;">
                            <div class="detail-title">
                                <i class="fas fa-list-alt"></i> Category
                            </div>
                            <div class="detail-value" style="font-size: 1.1em;"><?php echo htmlspecialchars($med['CATEGORY_TYPE'] ?? 'N/A'); ?></div>
                        </div>
                        <div style="margin-top: 15px;">
                            <div class="detail-title">
                                <i class="fas fa-truck"></i> Supplier
                            </div>
                            <div class="detail-value" style="font-size: 1.1em;"><?php echo htmlspecialchars($med['SUPPLIER_NAME'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-title">
                            <i class="fas fa-boxes"></i> Stock Status
                        </div>
                        <div class="detail-value"><?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']); ?> units</div>
                        
                        <?php 
                        $stock = intval($med['QUANTITY_IN_STOCK']);
                        $stockClass = 'stock-good';
                        $stockText = 'In Stock';
                        
                        if ($stock <= 10) {
                            $stockClass = 'stock-warning';
                            $stockText = 'Very Low';
                        } elseif ($stock <= 50) {
                            $stockClass = 'stock-low';
                            $stockText = 'Low Stock';
                        }
                        ?>
                        <div class="stock-status <?php echo $stockClass; ?>">
                            <?php echo $stockText; ?>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <div class="detail-title">
                                <i class="fas fa-calendar-check"></i> Expiry Date
                            </div>
                            <div class="detail-value" style="font-size: 1.1em;"><?php echo htmlspecialchars($expiryDate); ?></div>
                            
                            <?php 
                            $expiryClass = 'expiry-valid';
                            $expiryText = 'Valid';
                            
                            if ($med['EXPIRY_DATE']) {
                                $expiryTimestamp = strtotime($med['EXPIRY_DATE']);
                                $currentTimestamp = time();
                                $daysDiff = floor(($expiryTimestamp - $currentTimestamp) / (60 * 60 * 24));
                                
                                if ($daysDiff < 0) {
                                    $expiryClass = 'expiry-expired';
                                    $expiryText = 'Expired';
                                } elseif ($daysDiff <= 30) {
                                    $expiryClass = 'expiry-warning';
                                    $expiryText = 'Expiring Soon';
                                }
                            }
                            ?>
                            <div class="expiry-status <?php echo $expiryClass; ?>">
                                <?php echo $expiryText; ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-title">
                            <i class="fas fa-money-bill-wave"></i> Pricing
                        </div>
                        <div class="detail-value detail-price">
                            RM <?php echo number_format((float)($med['UNIT_PRICE'] ?? 0), 2); ?>
                        </div>
                        <div style="margin-top: 15px; font-size: 0.9em; color: var(--soft-grey);">
                            <i class="fas fa-info-circle"></i> Price per unit
                        </div>
                        
                        <div style="margin-top: 25px;">
                            <div class="detail-title">
                                <i class="fas fa-calculator"></i> Total Inventory Value
                            </div>
                            <?php 
                            $totalValue = $stock * floatval($med['UNIT_PRICE'] ?? 0);
                            ?>
                            <div class="detail-value" style="font-size: 1.3em; color: var(--dark-blue);">
                                RM <?php echo number_format($totalValue, 2); ?>
                            </div>
                            <div style="font-size: 0.9em; color: var(--soft-grey); margin-top: 5px;">
                                <?php echo $stock; ?> units × RM <?php echo number_format((float)($med['UNIT_PRICE'] ?? 0), 2); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="details-actions">
                    <a href="edit_medicine.php?id=<?php echo urlencode($med['MEDICINE_ID']); ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Medicine
                    </a>
                    <a href="medDirectory.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Directory
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Medicine Not Found</h3>
                    <p>The medicine record you're looking for doesn't exist or has been removed.</p>
                    <a href="medDirectory.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Return to Medicine Directory
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to detail cards on load
            const detailCards = document.querySelectorAll('.detail-card');
            detailCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Add stock level indicator
            const stockValue = <?php echo $stock ?? 0; ?>;
            if (stockValue > 0) {
                // Calculate stock percentage for visualization
                const maxStock = Math.max(stockValue, 100);
                const stockPercentage = Math.min((stockValue / maxStock) * 100, 100);
                
                // Create visual stock bar for cards
                const stockCard = document.querySelector('.detail-card:nth-child(2)');
                if (stockCard) {
                    const stockBar = document.createElement('div');
                    stockBar.style.cssText = `
                        margin-top: 15px;
                        height: 8px;
                        background: #e9ecef;
                        border-radius: 4px;
                        overflow: hidden;
                        position: relative;
                    `;
                    
                    const stockFill = document.createElement('div');
                    stockFill.style.cssText = `
                        height: 100%;
                        width: ${stockPercentage}%;
                        background: ${stockValue <= 10 ? '#dc3545' : stockValue <= 50 ? '#ffc107' : '#28a745'};
                        border-radius: 4px;
                        transition: width 1s ease;
                    `;
                    
                    stockBar.appendChild(stockFill);
                    stockCard.querySelector('.detail-value').parentNode.appendChild(stockBar);
                    
                    // Add percentage label
                    const percentageLabel = document.createElement('div');
                    percentageLabel.style.cssText = `
                        font-size: 0.8em;
                        color: #6c757d;
                        margin-top: 5px;
                        text-align: right;
                    `;
                    percentageLabel.innerHTML = `${Math.round(stockPercentage)}% of typical stock level`;
                    stockBar.parentNode.appendChild(percentageLabel);
                }
            }
        });
    </script>
</body>
</html>