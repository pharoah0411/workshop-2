<?php
require_once "connection.php";   // <-- Connect to all databases

// Make sure PostgreSQL connection exists
if (!isset($pg_conn)) {
    die("<h3 style='color:red'>PostgreSQL connection failed. Cannot load dashboard.</h3>");
}

// -------------------------
// 1. TODAY'S SALES
// -------------------------
$query_today = "
    SELECT COALESCE(SUM(amount), 0) AS today_total
    FROM payment
    WHERE DATE(payment_date) = CURRENT_DATE
";

$stmt_today = $pg_conn->query($query_today);
$today_sales = $stmt_today->fetch(PDO::FETCH_ASSOC)['today_total'];

// -------------------------
// 2. MONTHLY SALES
// -------------------------
$query_month = "
    SELECT COALESCE(SUM(amount), 0) AS month_total
    FROM payment
    WHERE DATE_PART('month', payment_date) = DATE_PART('month', CURRENT_DATE)
      AND DATE_PART('year', payment_date) = DATE_PART('year', CURRENT_DATE)
";

$stmt_month = $pg_conn->query($query_month);
$month_sales = $stmt_month->fetch(PDO::FETCH_ASSOC)['month_total'];

// -------------------------
// 3. TOTAL REVENUE
// -------------------------
$query_total = "SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM payment";

$stmt_total = $pg_conn->query($query_total);
$total_revenue = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_revenue'];

// -------------------------
// 4. LAST 5 TRANSACTIONS
// -------------------------
$query_last5 = "
    SELECT payment_id, patient_name, amount, payment_date
    FROM payment
    ORDER BY payment_date DESC
    LIMIT 5
";

$stmt_last5 = $pg_conn->query($query_last5);
$transactions = $stmt_last5->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        h1 { color: #333; }
        .card {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        th, td {
            padding: 10px; border-bottom: 1px solid #ddd;
        }
        th { background: #333; color: white; }
    </style>
</head>
<body>

<h1>💰 Sales Dashboard</h1>

<div class="card">
    <h2>Today's Sales</h2>
    <p><strong>RM <?php echo number_format($today_sales, 2); ?></strong></p>
</div>

<div class="card">
    <h2>This Month's Sales</h2>
    <p><strong>RM <?php echo number_format($month_sales, 2); ?></strong></p>
</div>

<div class="card">
    <h2>Total Revenue</h2>
    <p><strong>RM <?php echo number_format($total_revenue, 2); ?></strong></p>
</div>

<div class="card">
    <h2>Last 5 Transactions</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Amount (RM)</th>
            <th>Date</th>
        </tr>
        <?php foreach ($transactions as $t): ?>
        <tr>
            <td><?= $t['payment_id'] ?></td>
            <td><?= htmlspecialchars($t['patient_name']) ?></td>
            <td><?= number_format($t['amount'], 2) ?></td>
            <td><?= $t['payment_date'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
