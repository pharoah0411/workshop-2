<?php
require_once 'connection.php';

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$med = null;
$error = '';

if ($id <= 0) {
    header('Location: medDirectory.php');
    exit;
}

// --- Handle Update (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? null;
    $category = $_POST['category'] ?? null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : null;
    $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
    $supplier = $_POST['supplier'] ?? null;

    // 1. MySQL #2 Update
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        try {
            $stmt = $mysql_conn2->prepare("UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?");
            $stmt->bind_param("ssisdsi", $name, $category, $stock, $expiry, $price, $supplier, $id);
            $stmt->execute();
        } catch(Exception $e){}
    }
    
    // 2. PostgreSQL Update
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        try {
            $stmt = $pg_conn->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
            $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
        } catch(Exception $e){}
    }

    // 3. SQL Server Update - FIXED: Use $pdo_sqlsrv
    try {
        if (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) { // FIXED HERE
            $stmt = $pdo_sqlsrv->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
            $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
        } elseif (isset($conn) && $conn !== false) {
            $sql = "UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?";
            sqlsrv_query($conn, $sql, [$name, $category, $stock, $expiry, $price, $supplier, $id]);
        }
    } catch(Exception $e){}

    header('Location: medDirectory.php');
    exit;
}

// --- Fetch Current Details (GET) ---
try {
    if (!$med && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        $stmt = $mysql_conn2->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    }
    if (!$med && isset($pg_conn) && $pg_conn instanceof PDO) {
        $stmt = $pg_conn->prepare("SELECT MEDICINE_ID, NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
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
} catch (Exception $e) { $error = $e->getMessage(); }

// Format Date for input[type="date"]
$expiryDate = '';
if ($med && !empty($med['EXPIRY_DATE'])) {
    if ($med['EXPIRY_DATE'] instanceof DateTime) $expiryDate = $med['EXPIRY_DATE']->format('Y-m-d');
    else $expiryDate = date('Y-m-d', strtotime($med['EXPIRY_DATE']));
}

// Function to handle dropdown selection
function isSelected($current, $target) {
    return (trim($current) === $target) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM - Edit Medicine Details</title>
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

        .form-container {
            width: 100%;
            max-width: 700px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
        }

        .form-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%231c4966' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }

        .form-header-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .form-header h1 {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-header p {
            font-size: 0.9em;
            opacity: 0.9;
            font-weight: 300;
        }

        .medicine-id-display {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .form-content {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: var(--dark-grey);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%231c4966' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            padding-right: 40px;
        }

        .price-wrapper {
            position: relative;
        }

        .price-wrapper::before {
            content: "RM";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: var(--dark-blue);
            z-index: 1;
        }

        .price-wrapper input {
            padding-left: 50px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 20px;
            border: none;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
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
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--dark-blue);
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: var(--dark-grey);
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .form-content {
                padding: 20px;
            }
            
            .form-header {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .form-header h1 {
                font-size: 1.2em;
            }
            
            .form-header p {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <header class="form-header">
            <div class="form-header-content">
                <h1><i class="fas fa-pills"></i> Edit Medicine Details</h1>
                <p>Update medicine information across all databases</p>
                <div class="medicine-id-display">
                    <i class="fas fa-hashtag"></i> ID: <?php echo $id; ?>
                </div>
            </div>
        </header>

        <div class="form-content">
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($med): ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Medicine ID</label>
                        <input class="form-control" value="#<?php echo $id; ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Medicine Name</label>
                        <input name="name" class="form-control" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required placeholder="Enter medicine name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <?php $currCat = $med['CATEGORY_TYPE'] ?? ''; ?>
                        <select name="category" class="form-control">
                            <option value="">-- Select Category --</option>
                            <option value="Tablet" <?php echo isSelected($currCat, 'Tablet'); ?>>Tablet</option>
                            <option value="Capsule" <?php echo isSelected($currCat, 'Capsule'); ?>>Capsule</option>
                            <option value="Syrup" <?php echo isSelected($currCat, 'Syrup'); ?>>Syrup</option>
                            <option value="Ointment" <?php echo isSelected($currCat, 'Ointment'); ?>>Ointment / Cream</option>
                            <option value="Injection" <?php echo isSelected($currCat, 'Injection'); ?>>Injection</option>
                            <option value="Drops" <?php echo isSelected($currCat, 'Drops'); ?>>Drops (Eye/Ear)</option>
                            <option value="Inhaler" <?php echo isSelected($currCat, 'Inhaler'); ?>>Inhaler</option>
                            <option value="Other" <?php echo isSelected($currCat, 'Other'); ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input name="stock" type="number" class="form-control" min="0" value="<?php echo htmlspecialchars($med['QUANTITY_IN_STOCK']??0); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input name="expiryDate" type="date" class="form-control" value="<?php echo $expiryDate; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price per Unit</label>
                        <div class="price-wrapper">
                            <input name="price" type="number" step="0.01" class="form-control" value="<?php echo htmlspecialchars($med['UNIT_PRICE']??0); ?>" required min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Supplier Name</label>
                        <input name="supplier" class="form-control" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>" placeholder="Enter supplier name">
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Update Medicine
                        </button>
                        <a class="btn btn-secondary" href="medDirectory.php">
                            <i class="fas fa-arrow-left"></i> Back to Directory
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Medicine Not Found</h3>
                    <p>The medicine record you're looking for doesn't exist or has been removed.</p>
                    <a class="btn btn-primary" href="medDirectory.php" style="margin-top: 20px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Return to Medicine Directory
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-focus on first input field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[name="name"]');
            if (firstInput) firstInput.focus();
            
            // Format date inputs for better UX
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (!input.value) {
                    // Set min date to today for expiry date
                    const today = new Date().toISOString().split('T')[0];
                    input.min = today;
                }
            });
            
            // Add validation for stock and price
            const stockInput = document.querySelector('input[name="stock"]');
            const priceInput = document.querySelector('input[name="price"]');
            
            if (stockInput) {
                stockInput.addEventListener('change', function() {
                    if (this.value < 0) this.value = 0;
                });
            }
            
            if (priceInput) {
                priceInput.addEventListener('change', function() {
                    if (this.value < 0) this.value = 0;
                    // Format to 2 decimal places
                    this.value = parseFloat(this.value).toFixed(2);
                });
            }
            
            // Add real-time stock warning
            if (stockInput) {
                stockInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const warning = document.createElement('div');
                    warning.className = 'stock-warning';
                    warning.style.cssText = 'font-size: 0.8em; margin-top: 5px; padding: 5px; border-radius: 4px;';
                    
                    // Remove existing warning
                    const existingWarning = this.parentNode.querySelector('.stock-warning');
                    if (existingWarning) {
                        existingWarning.remove();
                    }
                    
                    if (value <= 10) {
                        warning.style.background = '#fff3cd';
                        warning.style.color = '#856404';
                        warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Low stock warning';
                        this.parentNode.appendChild(warning);
                    } else if (value <= 50) {
                        warning.style.background = '#ffeaa7';
                        warning.style.color = '#856404';
                        warning.innerHTML = '<i class="fas fa-info-circle"></i> Moderate stock';
                        this.parentNode.appendChild(warning);
                    }
                });
                
                // Trigger on page load
                if (stockInput.value) {
                    stockInput.dispatchEvent(new Event('input'));
                }
            }
        });
    </script>
</body>
</html>