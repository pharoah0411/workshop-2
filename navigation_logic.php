<?php
// navigation_logic.php
// Get current page filename to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Determine which menu item should be active
$active_menu = 'dashboard'; // Default
switch($current_page) {
    case 'dashboard.php':
        $active_menu = 'dashboard';
        break;
    case 'medDirectory.php':
    case 'inventory.php':
    case 'low_stock.php':
        $active_menu = 'inventory';
        break;
    case 'prescriptionDashboard.php':
    case 'add_prescription.php':
    case 'prescription_history.php':
        $active_menu = 'prescriptions';
        break;
    case 'Sales_Billing.php':
    case 'new_sale.php':
    case 'invoice_history.php':
        $active_menu = 'sales';
        break;
    case 'user_management.php':
    case 'role_management.php':
    case 'user_activity.php':
        $active_menu = 'users';
        break;
    case 'reports.php':
    case 'sales_report.php':
    case 'inventory_report.php':
        $active_menu = 'reports';
        break;
    case 'backup.php':
        $active_menu = 'backup';
        break;
    // Add more pages as needed
    default:
        $active_menu = 'dashboard';
}
?>