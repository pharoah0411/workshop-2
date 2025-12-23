/* presc_manager.js */
(function(global){
    const API_BASE = '/workshop-2';

    async function loadAll(){
        try {
            const res = await fetch(`${API_BASE}/api_prescription.php?t=${Date.now()}`);
            if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
            
            const json = await res.json();
            const rawData = json.data || [];

            // Map SQL Server results to names used in the UI
            return rawData.map(p => ({
                id: p.PRESCRIPTION_ID,
                patientName: p.PATIENT_NAME,
                pharmacistName: p.PHARMACIST_NAME || 'Not Assigned',
                date: p.DATE_ISSUED || 'N/A',
                status: p.STATUS || 'Pending'
            }));
        } catch (err) {
            console.error('Prescription Manager Error:', err);
            return [];
        }
    }

    global.PrescManager = { loadAll };
})(window);