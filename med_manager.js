/* med_manager.js
   Shared helper for medicine pages. Uses localStorage key 'medicines'.
*/
(function(global){
    const STORAGE_KEY = 'medicines';

    function load() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        try { return JSON.parse(raw); } catch(e) { return []; }
    }

    function save(list) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    }

    function generateId() {
        return 'MED' + Date.now().toString().slice(-6);
    }

    function getById(id){
        const all = load();
        return all.find(m => m.id === id) || null;
    }

    function add(m){
        const all = load();
        if (!m.id) m.id = generateId();
        all.push(m);
        save(all);
        return m;
    }

    function update(id, updated){
        const all = load();
        const idx = all.findIndex(x => x.id === id);
        if (idx === -1) return false;
        all[idx] = Object.assign({}, all[idx], updated, { id });
        save(all);
        return true;
    }

    function remove(id){
        let all = load();
        const before = all.length;
        all = all.filter(x => x.id !== id);
        save(all);
        return all.length < before;
    }

    function filterExpiring(days=30){
        const all = load();
        const now = new Date();
        const limit = new Date(now);
        limit.setDate(limit.getDate() + Number(days));
        return all.filter(m => {
            if (!m.expiryDate) return false;
            const d = new Date(m.expiryDate);
            return d <= limit && d >= now;
        });
    }

    function filterExpired(){
        const all = load();
        const now = new Date();
        return all.filter(m => m.expiryDate && new Date(m.expiryDate) < now);
    }

    function filterLowStock(){
        const all = load();
        return all.filter(m => Number(m.stock) <= Number(m.minStock));
    }

    function ensureSampleData(){
        const all = load();
        if (all.length) return;
        const sample = [
            { id:'MED001', name:'Aspirin', category:'Painkiller', stock:150, minStock:50, expiryDate:'2025-12-31', price:5.99 },
            { id:'MED002', name:'Amoxicillin', category:'Antibiotic', stock:30, minStock:100, expiryDate:'2025-06-15', price:12.50 },
            { id:'MED003', name:'Ibuprofen', category:'Painkiller', stock:200, minStock:100, expiryDate:'2026-03-20', price:7.99 },
        ];
        save(sample);
    }

    global.MedManager = {
        load, save, add, update, remove, getById,
        filterExpiring, filterExpired, filterLowStock, ensureSampleData
    };
})(window);
