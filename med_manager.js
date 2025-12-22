/* med_manager.js */
(function(global){
    // Point directly to your workshop-2 folder root
    const API_BASE = '/workshop-2'; 

    async function load(){
        try {
            // Added a timestamp (?t=...) to prevent the browser from using a cached result
            const res = await fetch(`${API_BASE}/api_medicine.php?t=${Date.now()}`);
            
            if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
            
            const json = await res.json();
            const rawData = json.data || [];

            // Mapping SQL Server UPPERCASE fields to JavaScript names
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