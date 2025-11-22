<?php require_once 'connection.php';
// Attempt to load medicines server-side so the page can render even
// if API endpoints are unavailable. This uses $pdo (PDO) or $conn
// (sqlsrv) exposed by connection.php.
$server_meds = [];
try {
    if (isset($pdo) && $pdo !== null) {
        $sql = "SELECT Id, Name, Category_Type, Quantity_In_Stock, Expiry_Date, Supplier_Name, Unit_Price, Stock_Price FROM Medicine";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            // Normalize expiry date to ISO string if present
            if (!empty($r['Expiry_Date'])) {
                $d = $r['Expiry_Date'];
                if ($d instanceof DateTime) $r['Expiry_Date'] = $d->format('Y-m-d');
                else $r['Expiry_Date'] = date('Y-m-d', strtotime($r['Expiry_Date']));
            }
            $server_meds[] = $r;
        }
    } elseif (isset($conn) && $conn !== null) {
        $sql = "SELECT Id, Name, Category_Type, Quantity_In_Stock, Expiry_Date, Supplier_Name, Unit_Price, Stock_Price FROM Medicine";
        $stmt = @sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($r['Expiry_Date']) && $r['Expiry_Date'] instanceof DateTime) {
                    $r['Expiry_Date'] = $r['Expiry_Date']->format('Y-m-d');
                }
                $server_meds[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Keep $server_meds empty on error; client will fall back to API or show empty state.
    $server_meds = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .header-content h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
        }

        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 3em;
            min-width: 60px;
            text-align: center;
        }

        .stat-icon.total {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 50%;
        }

        .stat-icon.low-stock {
            background: #fff3e0;
            padding: 10px;
            border-radius: 50%;
        }

        .stat-icon.expiring {
            background: #ffebee;
            padding: 10px;
            border-radius: 50%;
        }

        .stat-icon.expired {
            background: #fce4ec;
            padding: 10px;
            border-radius: 50%;
        }

        .stat-details h3 {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0066ff;
        }

        /* Controls */
        .controls {
            display: flex;
            gap: 15px;
            padding: 20px 30px;
            flex-wrap: wrap;
            align-items: center;
            background: white;
            border-bottom: 1px solid #e0e0e0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 255, 0.4);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .search-bar {
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #0066ff;
            box-shadow: 0 0 5px rgba(0, 102, 255, 0.3);
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #0066ff;
        }

        /* Medicines Section */
        .medicines-section {
            padding: 30px;
        }

        .medicines-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .medicines-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .medicine-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .medicine-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
            border-color: #0066ff;
        }

        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .medicine-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }

        .medicine-id {
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }

        .medicine-category {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-top: 10px;
        }

        .medicine-details {
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 0.95em;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
        }

        .stock-indicator {
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 10px 0;
        }

        .stock-good {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .stock-low {
            background: #ffe0b2;
            color: #e65100;
        }

        .stock-critical {
            background: #ffcccc;
            color: #c62828;
        }

        .expiry-status {
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 10px 0;
        }

        .expiry-good {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .expiry-warning {
            background: #ffe0b2;
            color: #e65100;
        }

        .expiry-expired {
            background: #ffcccc;
            color: #c62828;
        }

        .medicine-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: #667eea;
            color: white;
        }

        .edit-btn:hover {
            background: #5568d3;
        }

        .delete-btn {
            background: #ff6b6b;
            color: white;
        }

        .delete-btn:hover {
            background: #ee5a52;
        }

        .alert-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }

        .alert-low-stock {
            background: #fff3e0;
            color: #e65100;
        }

        .alert-expiring {
            background: #ffebee;
            color: #c62828;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close {
            color: #999;
            float: right;
            font-size: 2em;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #333;
        }

        .modal-content h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0066ff;
            box-shadow: 0 0 5px rgba(0, 102, 255, 0.3);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .form-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .form-actions .btn-primary {
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            color: white;
        }

        .form-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 255, 0.4);
        }

        .form-actions .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .form-actions .btn-secondary:hover {
            background: #d0d0d0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #666;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content h1 {
                font-size: 1.8em;
            }

            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 20px;
            }

            .medicines-list {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-bar {
                min-width: auto;
            }

            .modal-content {
                width: 95%;
                margin: 20% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1>🏥 Medicine Inventory Management</h1>
                <p class="subtitle">Monitor stock levels and expiry dates</p>
            </div>
        </header>

        <!-- Dashboard Stats -->
        <section class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon total">📦</div>
                <div class="stat-details">
                    <h3>Total Medicines</h3>
                    <p class="stat-number" id="totalMedicines">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon low-stock">⚠️</div>
                <div class="stat-details">
                    <h3>Low Stock</h3>
                    <p class="stat-number" id="lowStockCount">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon expiring">🔴</div>
                <div class="stat-details">
                    <h3>Expiring Soon</h3>
                    <p class="stat-number" id="expiringCount">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon expired">❌</div>
                <div class="stat-details">
                    <h3>Expired</h3>
                    <p class="stat-number" id="expiredCount">0</p>
                </div>
            </div>
        </section>

        <!-- Controls -->
        <section class="controls">
            <button class="btn btn-primary" id="addMedicineBtn">+ Add Medicine</button>
            <div class="search-bar">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search medicines by name or ID..."
                    class="search-input"
                >
            </div>
            <select id="filterSelect" class="filter-select">
                <option value="all">All Medicines</option>
                <option value="low-stock">Low Stock</option>
                <option value="expiring">Expiring Soon</option>
                <option value="expired">Expired</option>
            </select>
        </section>

        <!-- Medicine List -->
        <section class="medicines-section">
            <h2>Medicine List</h2>
            <div class="medicines-list" id="medicinesList">
                <?php if (!empty($server_meds)): ?>
                    <?php foreach ($server_meds as $m):
                        $id = htmlspecialchars($m['Id'] ?? $m['id']);
                        $name = htmlspecialchars($m['Name'] ?? $m['name'] ?? '');
                        $category = htmlspecialchars($m['Category_Type'] ?? $m['category'] ?? '');
                        $stock = (int)($m['Quantity_In_Stock'] ?? $m['quantity'] ?? 0);
                        $minStock = (int)($m['minStock'] ?? 0);
                        $price = number_format((float)($m['Unit_Price'] ?? $m['unitPrice'] ?? $m['price'] ?? 0), 2);
                        $expiry = !empty($m['Expiry_Date']) ? $m['Expiry_Date'] : null;
                        // stock status
                        if ($stock === 0) { $stockClass='stock-critical'; $stockText='❌ Out of Stock'; }
                        elseif ($stock < $minStock) { $stockClass='stock-low'; $stockText = "⚠️ Low Stock ({$stock} units)"; }
                        else { $stockClass='stock-good'; $stockText = "✅ In Stock ({$stock} units)"; }
                        // expiry status
                        $expiryClass = 'expiry-good'; $expiryText = '—';
                        if ($expiry) {
                            $ed = strtotime($expiry);
                            $now = time();
                            if ($ed < $now) { $expiryClass='expiry-expired'; $expiryText='❌ EXPIRED'; }
                            else { $days = ceil(($ed - $now) / (60*60*24)); if ($days <= 30) { $expiryClass='expiry-warning'; $expiryText = "⚠️ Expiring in {$days} days"; } else { $expiryClass='expiry-good'; $expiryText = date('M j, Y', $ed); } }
                        }
                    ?>
                        <div class="medicine-card">
                            <div class="medicine-header">
                                <div>
                                    <div class="medicine-name"><?php echo $name; ?></div>
                                    <div class="medicine-id">ID: <?php echo $id; ?></div>
                                </div>
                            </div>
                            <span class="medicine-category"><?php echo $category; ?></span>
                            <div class="medicine-details">
                                <div class="detail-row"><span class="detail-label">Stock:</span><span class="detail-value"><?php echo $stock; ?> units</span></div>
                                <div class="detail-row"><span class="detail-label">Min Stock:</span><span class="detail-value"><?php echo $minStock; ?> units</span></div>
                                <div class="detail-row"><span class="detail-label">Price:</span><span class="detail-value">$<?php echo $price; ?></span></div>
                                <div class="detail-row"><span class="detail-label">Inventory Value:</span><span class="detail-value">$<?php echo number_format($stock * (float)($m['Unit_Price'] ?? $m['unitPrice'] ?? $m['price'] ?? 0), 2); ?></span></div>
                            </div>
                            <div class="stock-indicator <?php echo $stockClass; ?>"><?php echo $stockText; ?></div>
                            <div class="expiry-status <?php echo $expiryClass; ?>">Expires: <?php echo $expiryText; ?></div>
                            <div class="medicine-actions">
                                <a class="action-btn edit-btn" href="edit_medicine.php?id=<?php echo urlencode($id); ?>">✏️ Edit</a>
                                <a class="action-btn" style="background:#4caf50;color:#fff;border-radius:6px;padding:8px 12px;text-decoration:none" href="medicine_details.php?id=<?php echo urlencode($id); ?>">ℹ️ Details</a>
                                <button class="action-btn delete-btn" data-id="<?php echo htmlspecialchars($id); ?>">🗑️ Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-state-icon">📦</div><h3>No medicines found</h3><p>Try adjusting your search or filter criteria</p></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Modal for Adding/Editing Medicine -->
        <div id="medicineModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="modalTitle">Add New Medicine</h2>
                <form id="medicineForm">
                    <div class="form-group">
                        <label for="medicineName">Medicine Name:</label>
                        <input type="text" id="medicineName" required>
                    </div>
                    <div class="form-group">
                        <label for="medicineId">Medicine ID:</label>
                        <input type="text" id="medicineId" required>
                    </div>
                    <div class="form-group">
                        <label for="medicineCategory">Category:</label>
                        <input type="text" id="medicineCategory" placeholder="e.g., Antibiotic, Painkiller">
                    </div>
                    <div class="form-group">
                        <label for="medicineStock">Stock Quantity:</label>
                        <input type="number" id="medicineStock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="medicineMinStock">Minimum Stock Level:</label>
                        <input type="number" id="medicineMinStock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="medicineExpiryDate">Expiry Date:</label>
                        <input type="date" id="medicineExpiryDate" required>
                    </div>
                    <div class="form-group">
                        <label for="medicinePrice">Price per Unit:</label>
                        <input type="number" id="medicinePrice" min="0" step="0.01">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Medicine</button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Server-provided medicine data (if available)
        window.__SERVER_MEDICINES = <?php echo json_encode($server_meds, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || [];
    </script>
    <script src="med_manager.js"></script>
    <script>
    (async function(){
        function formatDate(dateStr){ if(!dateStr) return ''; const d=new Date(dateStr); return d.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}) }
        function formatDateForInput(dateStr){ if(!dateStr) return ''; const d=new Date(dateStr); const year=d.getFullYear(); const month=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return `${year}-${month}-${day}` }

        async function updateStats(){
            const all = await MedManager.load();
            const total = all.length;
            const low = all.filter(m=>Number(m.stock) <= Number(m.minStock || 0)).length;
            const expSoon = all.filter(m=>{ if(!m.expiryDate) return false; const d=new Date(m.expiryDate); const now=new Date(); const diffDays = Math.ceil((d-now)/(1000*60*60*24)); return diffDays>0 && diffDays<=30 }).length;
            const expired = all.filter(m=> m.expiryDate && new Date(m.expiryDate) < new Date()).length;
            document.getElementById('totalMedicines').textContent = total;
            document.getElementById('lowStockCount').textContent = low;
            document.getElementById('expiringCount').textContent = expSoon;
            document.getElementById('expiredCount').textContent = expired;
        }

        function getStockStatus(m){ if(Number(m.stock)===0) return {class:'stock-critical', text:'❌ Out of Stock'}; if(Number(m.stock) < Number(m.minStock || 0)) return {class:'stock-low', text:`⚠️ Low Stock (${m.stock} units)`}; return {class:'stock-good', text:`✅ In Stock (${m.stock} units)`} }
        function getExpiryStatus(m){ if(!m.expiryDate) return {class:'expiry-good', text:'—'}; const d=new Date(m.expiryDate); const now=new Date(); if(d < now) return {class:'expiry-expired', text:'❌ EXPIRED'}; const diffMs = d - now; const days = Math.ceil(diffMs/(1000*60*60*24)); if(days<=30) return {class:'expiry-warning', text:`⚠️ Expiring in ${days} days`}; return {class:'expiry-good', text:`✅ ${formatDate(m.expiryDate)}`} }

        async function renderMedicines(filter, search){
            const container = document.getElementById('medicinesList');
            let meds = await MedManager.load();
            if (filter && filter !== 'all'){
                if (filter === 'low-stock') meds = meds.filter(m => Number(m.stock) <= Number(m.minStock || 0));
                else if (filter === 'expiring') meds = meds.filter(m => { if(!m.expiryDate) return false; const d=new Date(m.expiryDate); const now=new Date(); const diffDays = Math.ceil((d-now)/(1000*60*60*24)); return diffDays>0 && diffDays<=30 });
                else if (filter === 'expired') meds = meds.filter(m => m.expiryDate && new Date(m.expiryDate) < new Date());
            }
            if (search){ const q = search.toLowerCase(); meds = meds.filter(m => (m.name||'').toLowerCase().includes(q) || (String(m.id)||'').toLowerCase().includes(q) || (m.category||'').toLowerCase().includes(q)); }

            if (!meds.length){ container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📦</div><h3>No medicines found</h3><p>Try adjusting your search or filter criteria</p></div>`; return }

            container.innerHTML = meds.map(m=>{
                const stockStatus = getStockStatus(m);
                const expiryStatus = getExpiryStatus(m);
                return `
                <div class="medicine-card">
                    <div class="medicine-header">
                        <div>
                            <div class="medicine-name">${m.name}</div>
                            <div class="medicine-id">ID: ${m.id}</div>
                        </div>
                    </div>
                    <span class="medicine-category">${m.category||''}</span>
                    <div class="medicine-details">
                        <div class="detail-row"><span class="detail-label">Stock:</span><span class="detail-value">${m.stock} units</span></div>
                        <div class="detail-row"><span class="detail-label">Min Stock:</span><span class="detail-value">${m.minStock || 0} units</span></div>
                        <div class="detail-row"><span class="detail-label">Price:</span><span class="detail-value">$${Number(m.price||0).toFixed(2)}</span></div>
                        <div class="detail-row"><span class="detail-label">Inventory Value:</span><span class="detail-value">$${(Number(m.stock||0)*Number(m.price||0)).toFixed(2)}</span></div>
                    </div>
                    <div class="stock-indicator ${stockStatus.class}">${stockStatus.text}</div>
                    <div class="expiry-status ${expiryStatus.class}">Expires: ${expiryStatus.text}</div>
                    <div class="medicine-actions">
                        <a class="action-btn edit-btn" href="edit_medicine.php?id=${encodeURIComponent(m.id)}">✏️ Edit</a>
                        <a class="action-btn" style="background:#4caf50;color:#fff;border-radius:6px;padding:8px 12px;text-decoration:none" href="medicine_details.php?id=${encodeURIComponent(m.id)}">ℹ️ Details</a>
                        <button class="action-btn delete-btn" data-id="${m.id}">🗑️ Delete</button>
                    </div>
                </div>`
            }).join('');

            Array.from(document.querySelectorAll('.delete-btn')).forEach(btn=> btn.addEventListener('click', async ()=>{ if(confirm('Delete this medicine?')){ await MedManager.remove(btn.dataset.id); await refresh(); }}));
        }

        async function refresh(){ await updateStats(); const filter = document.getElementById('filterSelect').value; const search=document.getElementById('searchInput').value.trim(); await renderMedicines(filter, search); }

        document.addEventListener('DOMContentLoaded', ()=>{
            document.getElementById('addMedicineBtn').addEventListener('click', ()=> location.href='add_medicine.php');
            document.getElementById('searchInput').addEventListener('input', ()=> refresh());
            document.getElementById('filterSelect').addEventListener('change', ()=> refresh());
            document.querySelector('.close').addEventListener('click', ()=> document.getElementById('medicineModal').style.display='none');
            document.getElementById('cancelBtn').addEventListener('click', ()=> document.getElementById('medicineModal').style.display='none');
            const modal = document.getElementById('medicineModal'); const form=document.getElementById('medicineForm'); const modalTitle=document.getElementById('modalTitle'); let currentEditId=null;

            window.openModal = async function(id=null){ form.reset(); currentEditId=id; if(id){ const m = await MedManager.getById(id); modalTitle.textContent='Edit Medicine'; document.getElementById('medicineName').value=m.name; document.getElementById('medicineId').value=m.id; document.getElementById('medicineCategory').value=m.category; document.getElementById('medicineStock').value=m.stock; document.getElementById('medicineMinStock').value=m.minStock || 0; document.getElementById('medicineExpiryDate').value=formatDateForInput(m.expiryDate); document.getElementById('medicinePrice').value=m.price; document.getElementById('medicineId').disabled=true; } else { modalTitle.textContent='Add New Medicine'; document.getElementById('medicineId').disabled=false; } modal.style.display='block'; }

            form.addEventListener('submit', async (e)=>{ 
                e.preventDefault();
                const data={ name:document.getElementById('medicineName').value, category:document.getElementById('medicineCategory').value, stock:Number(document.getElementById('medicineStock').value), minStock:Number(document.getElementById('medicineMinStock').value), expiryDate:document.getElementById('medicineExpiryDate').value, price:parseFloat(document.getElementById('medicinePrice').value)||0 };
                if(currentEditId){ await MedManager.update(currentEditId, data); } else { /* user-provided ID is ignored by DB insert; kept for UI only */ await MedManager.add(data); }
                modal.style.display='none';
                await refresh();
            });

            window.addEventListener('click',(e)=>{ if(e.target===modal) modal.style.display='none'; });
            refresh();
        });
    })();
    </script>
</body>
</html>
