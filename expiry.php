<?php require_once 'connection.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expiring Medicines</title>
    <style>
        /* Reused design */
        * { margin:0; padding:0; box-sizing:border-box }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px }
        .container { max-width:1100px; margin:0 auto; background:white; border-radius:12px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12) }
        .header { background: linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; padding:20px }
        .header h1{font-size:1.6rem}
        .content { padding:20px }
        .controls { display:flex; gap:8px; align-items:center; margin-bottom:12px }
        input[type=number]{width:90px;padding:8px;border:1px solid #ddd;border-radius:8px}
        .medicines-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
        .medicine-card{background:#fff;border:1px solid #eaeaea;border-radius:10px;padding:14px}
        .medicine-name{font-weight:700}
        .small{font-size:0.9rem;color:#555}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
    </style>
    <script src="med_manager.js"></script>
</head>
<body>
    <div class="container">
        <header class="header"><h1>🕒 Expiring Soon</h1></header>
        <div class="content">
            <div class="controls">
                <label>Show expiring within <input id="days" type="number" value="30"></label>
                <button id="filter" class="btn btn-primary">Filter</button>
            </div>

            <div id="list" class="medicines-list"></div>

            <a class="back" href="medDirectory.php">← Back to Directory</a>
        </div>
    </div>

    <script>
        async function render(list){
            const container = document.getElementById('list');
            if (!list || !list.length) { container.innerHTML = '<p class="small">No medicines found.</p>'; return; }
            container.innerHTML = list.map(m=>{
                return `<div class="medicine-card"><div class="medicine-name">${m.name}</div><div class="small">ID: ${m.id} • ${m.category||''}</div><div style="margin-top:8px">Stock: ${m.stock}</div><div class="small">Expiry: ${m.expiryDate||''}</div><div style="margin-top:10px"><a href="edit_medicine.php?id=${encodeURIComponent(m.id)}">Edit</a></div></div>`;
            }).join('');
        }

        document.getElementById('filter').addEventListener('click', async ()=>{
            const days = Number(document.getElementById('days').value) || 30;
            const list = await MedManager.filterExpiring(days);
            await render(list);
        });

        (async function(){
            const list = await MedManager.filterExpiring(30);
            await render(list);
        })();
    </script>
</body>
</html>
