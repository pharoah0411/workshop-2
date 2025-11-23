/* med_manager.js
   Server-backed helper that calls PHP API endpoints under /api/
   Provides Promise-based functions: load(), getById(id), add(obj), update(id,obj), remove(id)
   The returned medicine objects have the shape used by the UI: { id, name, category, stock, minStock, expiryDate, price }
*/
(function(global){
    const API_BASE = '/workshop-2/api';

    async function load(){
        const res = await fetch(`${API_BASE}/list_medicines.php`);
        try {
            const res = await fetch(`${API_BASE}/list_medicines.php`);
            if (!res.ok) throw new Error('Failed to load medicines');
            const data = await res.json();
            // Map DB fields to UI fields
            return data.map(m => ({
                id: m.Id || m.id,
                name: m.Name || m.name || m.name,
                category: m.category || m.Category_Type || '',
                stock: Number(m.quantity ?? m.Quantity_In_Stock ?? 0),
                minStock: Number(m.minStock ?? 0), // DB has no minStock column by default
                expiryDate: m.expiryDate || m.Expiry_Date || null,
                price: Number(m.unitPrice ?? m.Unit_Price ?? m.price ?? 0),
                supplier: m.supplier || m.Supplier_Name || null,
                stockPrice: m.stockPrice ?? m.Stock_Price ?? null
            }));
        } catch (err) {
            // Fetch failed — fall back to server-rendered data if present
            if (typeof window !== 'undefined' && Array.isArray(window.__SERVER_MEDICINES) && window.__SERVER_MEDICINES.length) {
                return window.__SERVER_MEDICINES.map(m => ({
                    id: (m.Id ?? m.id) + '',
                    name: m.Name || m.name || '',
                    category: m.Category_Type || m.category || '',
                    stock: Number(m.Quantity_In_Stock ?? m.quantity ?? 0),
                    minStock: Number(m.minStock ?? 0),
                    expiryDate: m.Expiry_Date || m.expiryDate || null,
                    price: Number(m.Unit_Price ?? m.unitPrice ?? m.price ?? 0),
                    supplier: m.Supplier_Name || m.supplier || null,
                    stockPrice: m.Stock_Price ?? m.stockPrice ?? null
                }));
            }
            throw err;
        }
        // Map DB fields to UI fields
        return data.map(m => ({
            id: m.Id || m.id,
            name: m.Name || m.name || m.name,
            category: m.category || m.Category_Type || '',
            stock: Number(m.quantity ?? m.Quantity_In_Stock ?? 0),
            minStock: Number(m.minStock ?? 0), // DB has no minStock column by default
            expiryDate: m.expiryDate || m.Expiry_Date || null,
            price: Number(m.unitPrice ?? m.Unit_Price ?? m.price ?? 0),
            supplier: m.supplier || m.Supplier_Name || null,
            stockPrice: m.stockPrice ?? m.Stock_Price ?? null
        }));
    }

    async function getById(id){
        const res = await fetch(`${API_BASE}/get_medicine.php?id=${encodeURIComponent(id)}`);
        try {
            const res = await fetch(`${API_BASE}/get_medicine.php?id=${encodeURIComponent(id)}`);
            if (!res.ok) throw new Error('Failed to fetch medicine');
            const m = await res.json();
            if (!m) return null;
            return {
                id: m.Id || m.id,
                name: m.Name || m.name,
                category: m.category || m.Category_Type || '',
                stock: Number(m.quantity ?? m.Quantity_In_Stock ?? 0),
                minStock: Number(m.minStock ?? 0),
                expiryDate: m.expiryDate || m.Expiry_Date || null,
                price: Number(m.unitPrice ?? m.Unit_Price ?? m.price ?? 0),
                supplier: m.supplier || m.Supplier_Name || null,
                stockPrice: m.stockPrice ?? m.Stock_Price ?? null
            };
        } catch (err) {
            if (typeof window !== 'undefined' && Array.isArray(window.__SERVER_MEDICINES)) {
                const found = window.__SERVER_MEDICINES.find(m => String(m.Id ?? m.id) === String(id));
                if (!found) return null;
                const m = found;
                return {
                    id: m.Id || m.id,
                    name: m.Name || m.name,
                    category: m.Category_Type || m.category || '',
                    stock: Number(m.Quantity_In_Stock ?? m.quantity ?? 0),
                    minStock: Number(m.minStock ?? 0),
                    expiryDate: m.Expiry_Date || m.expiryDate || null,
                    price: Number(m.Unit_Price ?? m.unitPrice ?? m.price ?? 0),
                    supplier: m.Supplier_Name || m.supplier || null,
                    stockPrice: m.Stock_Price ?? m.stockPrice ?? null
                };
            }
            throw err;
        }
    }

    async function add(obj){
        // obj: { name, category, stock, expiryDate, supplier, price }
        const payload = {
            name: obj.name,
            category: obj.category,
            quantity: Number(obj.stock || 0),
            expiryDate: obj.expiryDate || null,
            supplier: obj.supplier || null,
            unitPrice: Number(obj.price || 0),
            stockPrice: obj.stockPrice ?? null
        };
        const res = await fetch(`${API_BASE}/add_medicine.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error('Failed to add medicine');
        return await res.json();
    }

    async function update(id, obj){
        const payload = Object.assign({ id }, {});
        if (obj.name !== undefined) payload.name = obj.name;
        if (obj.category !== undefined) payload.category = obj.category;
        if (obj.stock !== undefined) payload.quantity = Number(obj.stock);
        if (obj.expiryDate !== undefined) payload.expiryDate = obj.expiryDate || null;
        if (obj.supplier !== undefined) payload.supplier = obj.supplier;
        if (obj.price !== undefined) payload.unitPrice = Number(obj.price);
        if (obj.stockPrice !== undefined) payload.stockPrice = obj.stockPrice;

        const res = await fetch(`${API_BASE}/update_medicine.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error('Failed to update medicine');
        return await res.json();
    }

    async function remove(id){
        const res = await fetch(`${API_BASE}/delete_medicine.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(id) })
        });
        if (!res.ok) throw new Error('Failed to delete medicine');
        return await res.json();
    }

    // Helpers based on server data
    async function filterExpiring(days = 30){
        const all = await load();
        const now = new Date();
        const limit = new Date(now);
        limit.setDate(limit.getDate() + Number(days));
        return all.filter(m => { if (!m.expiryDate) return false; const d = new Date(m.expiryDate); return d <= limit && d >= now; });
    }

    async function filterExpired(){
        const all = await load();
        const now = new Date();
        return all.filter(m => m.expiryDate && new Date(m.expiryDate) < now);
    }

    async function filterLowStock(){
        const all = await load();
        return all.filter(m => Number(m.stock) <= Number(m.minStock || 0));
    }

    // noop for DB mode
    function ensureSampleData(){ return; }

    global.MedManager = {
        load, getById, add, update, remove,
        filterExpiring, filterExpired, filterLowStock, ensureSampleData
    };
})(window);
