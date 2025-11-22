<?php require_once 'connection.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Alerts</title>
    <style>
        /* Reused design */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Arial; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);min-height:100vh;padding:20px}
        .container{max-width:1100px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)}
        .header{background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%);color:#fff;padding:20px}
        .content{padding:20px}
        .medicines-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
        .medicine-card{background:#fff;border:1px solid #eaeaea;border-radius:10px;padding:14px}
        .medicine-name{font-weight:700}
        .small{font-size:0.9rem;color:#555}
        a.back{display:inline-block;margin-top:12px;color:#0066ff}
        .low{background:#fff3e0;padding:8px;border-radius:6px;color:#e65100;margin-top:8px;font-weight:700}
        .critical{background:#ffcccc;padding:8px;border-radius:6px;color:#c62828;margin-top:8px;font-weight:700}
    </style>
    <script>
        /* Inlined MedManager (from med_manager.js) */
        (function(global){
            const STORAGE_KEY = 'medicines';
            function load(){const raw=localStorage.getItem(STORAGE_KEY); if(!raw) return []; try{return JSON.parse(raw)}catch(e){return[]}}
            function save(list){localStorage.setItem(STORAGE_KEY, JSON.stringify(list))}
            function generateId(){return 'MED'+Date.now().toString().slice(-6)}
            function getById(id){const all=load(); return all.find(m=>m.id===id)||null}
            function add(m){const all=load(); if(!m.id) m.id=generateId(); all.push(m); save(all); return m}
            function update(id, updated){const all=load(); const idx=all.findIndex(x=>x.id===id); if(idx===-1) return false; all[idx]=Object.assign({}, all[idx], updated, {id}); save(all); return true}
            function remove(id){let all=load(); const before=all.length; all=all.filter(x=>x.id!==id); save(all); return all.length<before}
            function filterExpiring(days=30){const all=load(); const now=new Date(); const limit=new Date(now); limit.setDate(limit.getDate()+Number(days)); return all.filter(m=>{ if(!m.expiryDate) return false; const d=new Date(m.expiryDate); return d<=limit && d>=now })}
            function filterExpired(){const all=load(); const now=new Date(); return all.filter(m=>m.expiryDate && new Date(m.expiryDate)<now)}
            function filterLowStock(){const all=load(); return all.filter(m=>Number(m.stock)<=Number(m.minStock))}
            function ensureSampleData(){const all=load(); if(all.length) return; const sample=[{id:'MED001',name:'Aspirin',category:'Painkiller',stock:150,minStock:50,expiryDate:'2025-12-31',price:5.99},{id:'MED002',name:'Amoxicillin',category:'Antibiotic',stock:30,minStock:100,expiryDate:'2025-06-15',price:12.50},{id:'MED003',name:'Ibuprofen',category:'Painkiller',stock:200,minStock:100,expiryDate:'2026-03-20',price:7.99}]; save(sample)}
            global.MedManager={load,save,add,update,remove,getById,filterExpiring,filterExpired,filterLowStock,ensureSampleData}
        })(window);
    </script>
</head>
<body>
    <div class="container">
        <header class="header"><h1>⚠️ Low Stock / Out of Stock</h1></header>
        <div class="content">
            <div id="list" class="medicines-list"></div>
            <a class="back" href="medDirectory.php">← Back to Directory</a>
        </div>
    </div>

    <script>
        function render(list){
            const c = document.getElementById('list');
            if (!list.length) { c.innerHTML = '<p class="small">No low-stock medicines.</p>'; return; }
            c.innerHTML = list.map(m=>{
                const status = (Number(m.stock) <= 0) ? `<div class="critical">Out of stock</div>` : `<div class="low">Low stock: ${m.stock} units</div>`;
                return `<div class="medicine-card"><div class="medicine-name">${m.name}</div><div class="small">ID: ${m.id} • ${m.category||''}</div><div style="margin-top:8px">Min Stock: ${m.minStock}</div>${status}<div style="margin-top:10px"><a href="edit_medicine.php?id=${encodeURIComponent(m.id)}">Edit</a></div></div>`;
            }).join('');
        }

        MedManager.ensureSampleData();
        render(MedManager.filterLowStock());
    </script>
</body>
</html>
