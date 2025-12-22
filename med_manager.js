/* med_manager.js */
(function(global){
    // FIXED: Pointing to your main folder root (no /api subfolder)
    const API_BASE = '/workshop-2'; 

    async function load(){
        try {
            // FIXED: Pointing to api_medicine.php and only calling fetch ONCE
            const res = await fetch(`${API_BASE}/api_medicine.php`);
            
            if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
            
            const json = await res.json();
            // Your API returns data inside a 'data' property
            const rawData = json.data || [];

            // FIXED: Mapping SQL Server UPPERCASE fields to JavaScript names
            return rawData.map(m => ({
                id: m.MEDICINE_ID,
                name: m.NAME,
                stock: Number(m.QUANTITY_IN_STOCK || 0),
                minStock: 50, 
                expiryDate: m.EXPIRY_DATE || 'No Expiry',
                price: Number(m.UNIT_PRICE || 0)
            }));
        } catch (err) {
            console.error('MedManager API Error:', err);
            return [];
        }
    }

    global.MedManager = { load };
})(window);