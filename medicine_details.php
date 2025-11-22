<?php require_once 'connection.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medicine Details</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Arial;background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);min-height:100vh;padding:20px}
        .container{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)}
        .header{background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);color:#fff;padding:20px}
        .content{padding:20px}
        dl{max-width:600px}
        dt{font-weight:700;margin-top:8px}
        dd{margin-left:0;margin-bottom:8px}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
    </style>
    <script src="med_manager.js"></script>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🔎 Medicine Details</h1></header>
        <div class="content">
            <div id="details"></div>
            <p><a id="editLink" href="#">Edit</a> | <a class="back" href="medDirectory.php">Back to Directory</a></p>
        </div>
    </div>

    <script>
        (async function(){
            function qs(name){return new URLSearchParams(location.search).get(name);} 
            const id = qs('id');
            if (!id) { alert('No id'); location.href='medDirectory.php'; return; }
            const m = await MedManager.getById(id);
            if (!m) { alert('Not found'); location.href='medDirectory.php'; return; }
            document.getElementById('details').innerHTML = `<dl><dt>ID</dt><dd>${m.id}</dd><dt>Name</dt><dd>${m.name}</dd><dt>Category</dt><dd>${m.category||''}</dd><dt>Stock</dt><dd>${m.stock}</dd><dt>Min Stock</dt><dd>${m.minStock||0}</dd><dt>Expiry</dt><dd>${m.expiryDate||''}</dd><dt>Price</dt><dd>$${Number(m.price).toFixed(2)}</dd></dl>`;
            document.getElementById('editLink').href = 'edit_medicine.php?id='+encodeURIComponent(m.id);
        })();
    </script>
</body>
</html>
