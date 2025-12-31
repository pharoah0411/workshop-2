<?php
include 'connection.php'; 

$message = "";
$status_type = "";

// --- 1. DATA FETCHING FOR DROPDOWNS ---
// We need to fetch patients from ALL databases to populate the dropdown
$all_patients = [];

// Fetch from MySQL
if (isset($conn_mysql)) {
    $res = mysqli_query($conn_mysql, "SELECT patient_id, patient_name FROM patients");
    while($row = mysqli_fetch_assoc($res)) { 
        $all_patients[] = ['id' => $row['patient_id'], 'name' => $row['patient_name'], 'db' => 'MySQL']; 
    }
}

// Fetch from PostgreSQL
if (isset($conn_pg)) {
    $res = pg_query($conn_pg, "SELECT patient_id, patient_name FROM patients");
    while($row = pg_fetch_assoc($res)) { 
        $all_patients[] = ['id' => $row['patient_id'], 'name' => $row['patient_name'], 'db' => 'PostgreSQL']; 
    }
}

// Fetch from SQL Server
if (isset($conn_sql)) {
    $res = sqlsrv_query($conn_sql, "SELECT patient_id, patient_name FROM patients");
    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) { 
        $all_patients[] = ['id' => $row['patient_id'], 'name' => $row['patient_name'], 'db' => 'SQL Server']; 
    }
}

// Fetch Medicines (Assuming they are in your SQL Server inventory)
$medicines = [];
if (isset($conn_sql)) {
    $res = sqlsrv_query($conn_sql, "SELECT medicine_name FROM medicines");
    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) { $medicines[] = $row['medicine_name']; }
}

// --- 2. PROCESSING THE INSERT ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_data = explode('|', $_POST['patient_info']); // Contains ID and Source DB
    $p_id = $patient_data[0];
    $p_source = $patient_data[1];
    
    $medicine = $_POST['medicine'];
    $dosage = $_POST['dosage'];
    $duration = $_POST['duration'];

    $insert_sql = "INSERT INTO prescriptions (patient_id, medicine_name, dosage, duration, date_added, source_origin) 
                   VALUES (?, ?, ?, ?, GETDATE(), ?)";
    
    $params = array($p_id, $medicine, $dosage, $duration, $p_source);
    $stmt = sqlsrv_query($conn_sql, $insert_sql, $params);

    if ($stmt) {
        $message = "✅ Prescription successfully created in SQL Server!";
        $status_type = "success";
    } else {
        $message = "❌ Error saving prescription.";
        $status_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Prescription | Pharmacy System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #e9ecef;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background: #ffffff;
            width: 500px;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            border-top: 6px solid #008080; /* Corporate Teal */
        }

        .header {
            padding: 25px;
            text-align: center;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .header h2 {
            margin: 0;
            color: #008080;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .form-area { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #444;
            font-size: 14px;
        }

        select, input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            background: #fafafa;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #008080;
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            background: #008080;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-submit:hover { background: #006666; }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .footer { text-align: center; padding-bottom: 20px; }
        .footer a { color: #008080; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>Prescription Entry</h2>
    </div>

    <div class="form-area">
        <?php if ($message): ?>
            <div class="alert <?php echo $status_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Patient</label>
                <select name="patient_info" required>
                    <option value="">-- Select Patient (All Databases) --</option>
                    <?php foreach($all_patients as $p): ?>
                        <option value="<?php echo $p['id'].'|'.$p['db']; ?>">
                            <?php echo $p['name']; ?> (ID: <?php echo $p['id']; ?> - <?php echo $p['db']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Medicine</label>
                <select name="medicine" required>
                    <option value="">-- Select Medicine --</option>
                    <?php foreach($medicines as $med): ?>
                        <option value="<?php echo $med; ?>"><?php echo $med; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Dosage Instructions</label>
                <select name="dosage" required>
                    <option value="">-- Select Dosage --</option>
                    <option value="1x Daily (Morning)">1x Daily (Morning)</option>
                    <option value="2x Daily (Morning & Night)">2x Daily (Morning & Night)</option>
                    <option value="3x Daily (After Meals)">3x Daily (After Meals)</option>
                    <option value="Every 4 Hours">Every 4 Hours</option>
                    <option value="As Needed (SOS)">As Needed (SOS)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Duration (Days)</label>
                <input type="number" name="duration" min="1" max="90" value="7" required>
            </div>

            <button type="submit" class="btn-submit">GENERATE PRESCRIPTION</button>
        </form>
    </div>

    <div class="footer">
        <a href="meddirectory.php">Back to Dashboard</a>
    </div>
</div>

</body>
</html>