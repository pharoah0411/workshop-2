<?php
require_once 'connection.php';
require_once 'audit.php'; // Included for audit trail
session_start();

// 1. Determine if we are ADDING (id=0) or EDITING (id > 0)
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$source = $_REQUEST['source'] ?? ''; 
$med = null;
$isEdit = ($id > 0);

// Only redirect if it's an EDIT attempt but source is missing
if ($isEdit && empty($source)) {
    header('Location: medDirectory.php');
    exit;
}

// 2. Handle Form Submission (INSERT or UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetSource = $_POST['source']; // From the form
    $name = $_POST['name'];
    $category = $_POST['category'];
    $stock = intval($_POST['stock']);
    $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $price = floatval($_POST['price']);
    $supplier = $_POST['supplier'];
    $success = false;

    try {
        if ($isEdit) {
            // --- UPDATE LOGIC ---
            if ($targetSource === 'MySQL' && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("UPDATE MEDICINE SET NAME=?, CATEGORY_TYPE=?, QUANTITY_IN_STOCK=?, EXPIRY_DATE=?, UNIT_PRICE=?, SUPPLIER_NAME=? WHERE MEDICINE_ID=?");
                $stmt->bind_param("ssisdsi", $name, $category, $stock, $expiry, $price, $supplier, $id);
                $success = $stmt->execute();
            } elseif ($targetSource === 'Postgres' && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
            } elseif ($targetSource === 'SQLServer' && isset($pdo_sqlsrv)) { // FIXED: Use $pdo_sqlsrv
                $stmt = $pdo_sqlsrv->prepare("UPDATE MEDICINE SET NAME=:n, CATEGORY_TYPE=:c, QUANTITY_IN_STOCK=:s, EXPIRY_DATE=:e, UNIT_PRICE=:p, SUPPLIER_NAME=:sup WHERE MEDICINE_ID=:id");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier, ':id'=>$id]);
            }
            $action = "UPDATED";
        } else {
            // --- INSERT LOGIC ---
            if ($targetSource === 'MySQL' && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisds", $name, $category, $stock, $expiry, $price, $supplier);
                $success = $stmt->execute();
            } elseif ($targetSource === 'Postgres' && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:n, :c, :s, :e, :p, :sup)");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier]);
            } elseif ($targetSource === 'SQLServer' && isset($pdo_sqlsrv)) { // FIXED: Use $pdo_sqlsrv
                $stmt = $pdo_sqlsrv->prepare("INSERT INTO MEDICINE (NAME, CATEGORY_TYPE, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE, SUPPLIER_NAME) VALUES (:n, :c, :s, :e, :p, :sup)");
                $success = $stmt->execute([':n'=>$name, ':c'=>$category, ':s'=>$stock, ':e'=>$expiry, ':p'=>$price, ':sup'=>$supplier]);
            }
            $action = "ADDED";
        }

        if ($success) {
            // Audit Log using Postgres connection
            if (isset($pg_conn)) {
                logAudit($pg_conn, $action, 'Medicine', "$action medicine '$name' in $targetSource database");
            }
            header('Location: medDirectory.php');
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 3. Fetch Data for Editing
if ($isEdit) {
    if ($source === 'MySQL' && isset($mysql_conn2)) {
        $stmt = $mysql_conn2->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $med = array_change_key_case($row, CASE_UPPER);
    } elseif ($source === 'Postgres' && isset($pg_conn)) {
        $stmt = $pg_conn->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    } elseif ($source === 'SQLServer' && isset($pdo_sqlsrv)) { // FIXED: Use $pdo_sqlsrv
        $stmt = $pdo_sqlsrv->prepare("SELECT * FROM MEDICINE WHERE MEDICINE_ID = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $med = array_change_key_case($row, CASE_UPPER);
    }
}

$expiryDate = ($med && !empty($med['EXPIRY_DATE'])) ? date('Y-m-d', strtotime(is_object($med['EXPIRY_DATE']) ? $med['EXPIRY_DATE']->format('Y-m-d') : $med['EXPIRY_DATE'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM - <?php echo $isEdit ? 'Edit' : 'Add'; ?> Medicine</title>
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
            max-width: 600px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
        }

        .form-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 25px 30px;
            text-align: center;
        }

        .form-header h1 {
            font-size: 1.4em;
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

        .btn-submit {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            width: 100%;
            padding: 15px;
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
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(28, 73, 102, 0.2);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9em;
            margin-top: 20px;
            transition: color 0.3s ease;
        }

        .btn-back:hover {
            color: var(--blue-accent);
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

        .source-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
        }

        .badge-mysql {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .badge-postgres {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .badge-sqlserver {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
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
            <h1>
                <?php echo $isEdit ? '<i class="fas fa-edit"></i> Edit Medicine' : '<i class="fas fa-plus-circle"></i> Add New Medicine'; ?>
                <?php if($isEdit): ?>
                    <span class="source-badge badge-<?php echo strtolower($source); ?>"><?php echo $source; ?></span>
                <?php endif; ?>
            </h1>
            <p><?php echo $isEdit ? 'Update medicine details in your inventory' : 'Add a new medicine to your inventory'; ?></p>
        </header>

        <div class="form-content">
            <?php if (isset($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Target Database</label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-control" value="<?php echo $source; ?>" readonly>
                        <input type="hidden" name="source" value="<?php echo $source; ?>">
                    <?php else: ?>
                        <select name="source" class="form-control" required>
                            <option value="">-- Select Database --</option>
                            <option value="MySQL">MySQL</option>
                            <option value="Postgres">PostgreSQL</option>
                            <option value="SQLServer">SQL Server</option>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Medicine Name</label>
                    <input name="name" class="form-control" value="<?php echo htmlspecialchars($med['NAME']??''); ?>" required placeholder="Enter medicine name">
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <?php $c = $med['CATEGORY_TYPE']??''; ?>
                        <option value="Tablet" <?php if($c=='Tablet') echo 'selected';?>>Tablet</option>
                        <option value="Capsule" <?php if($c=='Capsule') echo 'selected';?>>Capsule</option>
                        <option value="Syrup" <?php if($c=='Syrup') echo 'selected';?>>Syrup</option>
                        <option value="Injection" <?php if($c=='Injection') echo 'selected';?>>Injection</option>
                        <option value="Ointment" <?php if($c=='Ointment') echo 'selected';?>>Ointment</option>
                        <option value="Other" <?php if($c=='Other') echo 'selected';?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantity in Stock</label>
                        <input name="stock" type="number" class="form-control" value="<?php echo $med['QUANTITY_IN_STOCK']??0; ?>" required min="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input name="expiryDate" type="date" class="form-control" value="<?php echo $expiryDate; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unit Price</label>
                        <div class="price-wrapper">
                            <input name="price" type="number" step="0.01" class="form-control" value="<?php echo $med['UNIT_PRICE']??0; ?>" required min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Supplier Name</label>
                        <input name="supplier" class="form-control" value="<?php echo htmlspecialchars($med['SUPPLIER_NAME']??''); ?>" placeholder="Enter supplier name">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <?php if($isEdit): ?>
                        <i class="fas fa-save"></i> Update Medicine
                    <?php else: ?>
                        <i class="fas fa-plus"></i> Add to Inventory
                    <?php endif; ?>
                </button>
            </form>
            
            <div style="text-align: center;">
                <a href="medDirectory.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Medicine Directory
                </a>
            </div>
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
        });
    </script>
</body>
</html>