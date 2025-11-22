<?php require_once 'connection.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Medicine</title>
  <style>
    /* Reused design from medDirectory */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 24px 20px; text-align: center; }
    .header-content h1 { font-size: 1.6em; }
    .content { padding: 24px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display:block; color:#333; font-weight:600; margin-bottom:6px; }
    .form-group input, .form-group select { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
    .form-actions { display:flex; gap:10px; margin-top:16px; }
    .btn { padding:10px 16px; border-radius:8px; border:none; cursor:pointer; }
    .btn-primary { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:#fff; }
    .btn-secondary { background:#e0e0e0; }
    a.back { display:inline-block; margin-top:12px; color:#0066ff; }
  </style>
  <script src="med_manager.js"></script>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="header-content">
        <h1>🏥 Add Medicine</h1>
      </div>
    </header>

    <div class="content">
      <form id="form">
        <div class="form-group">
          <label for="name">Medicine Name</label>
          <input id="name" required>
        </div>
        <div class="form-group">
          <label for="category">Category</label>
          <input id="category">
        </div>
        <div class="form-group">
          <label for="stock">Stock Quantity</label>
          <input id="stock" type="number" min="0" value="0">
        </div>
        <div class="form-group">
          <label for="minStock">Minimum Stock Level</label>
          <input id="minStock" type="number" min="0" value="0">
        </div>
        <div class="form-group">
          <label for="expiryDate">Expiry Date</label>
          <input id="expiryDate" type="date">
        </div>
        <div class="form-group">
          <label for="price">Price per Unit</label>
          <input id="price" type="number" step="0.01" value="0.00">
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Add Medicine</button>
          <button class="btn btn-secondary" type="button" id="cancel">Cancel</button>
        </div>
      </form>

      <a class="back" href="medDirectory.php">← Back to Directory</a>
    </div>
  </div>

  <script>
    document.getElementById('form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const m = {
        name: document.getElementById('name').value.trim(),
        category: document.getElementById('category').value.trim(),
        stock: Number(document.getElementById('stock').value),
        minStock: Number(document.getElementById('minStock').value),
        expiryDate: document.getElementById('expiryDate').value || null,
        price: Number(document.getElementById('price').value) || 0
      };
      await MedManager.add(m);
      location.href = 'medDirectory.php';
    });
    document.getElementById('cancel').addEventListener('click', ()=>location.href='medDirectory.php');
  </script>
</body>
</html>
