/* med_manager.js */
(function(global){
    // Point to your main folder root
    const API_BASE = '/workshop-2'; 

    async function load(filter = 'all', search = ''){
        try {
            // FIXED: Pointing to api_medicine.php and only calling fetch ONCE
            let url = `${API_BASE}/api_medicine.php`;
            if(search) url += `?search=${encodeURIComponent(search)}`;

            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
            
            const json = await response.json();
            const rawData = json.data || [];

            // FIXED: Mapping SQL Server UPPERCASE fields to the names used in your UI
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