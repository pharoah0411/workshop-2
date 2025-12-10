<?php
include "header.php";

// Permission settings (editable from UI)
$permissions = [
    "admin" => [
        "dashboard" => true,
        "user_list" => true,
        "add_user" => true,
        "patient_list" => true,
        "add_patient" => true,
        "edit_patient" => true,
        "history" => true
    ],

    "pharmacist" => [
        "dashboard" => true,
        "user_list" => false,
        "add_user" => false,
        "patient_list" => true,
        "add_patient" => true,
        "edit_patient" => true,
        "history" => true
    ]
];

// Handle update (mock, no DB)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($permissions as $role => &$perms) {
        foreach ($perms as $key => &$value) {
            $value = isset($_POST[$role . "_" . $key]);
        }
    }
    $message = "<p style='color:green;'>Permissions updated successfully! (simulated)</p>";
}
?>

<h1>Role & Permission Management</h1>

<?= $message ?? "" ?>

<form method="POST" 
      style="background:white; padding:20px; border-radius:10px; width:900px;">

    <table border="0" cellpadding="10" cellspacing="0" 
           style="width:100%; background:white; border-radius:10px;">

        <tr style="background:#0b2f6d; color:white;">
            <th>Permission</th>
            <th>Admin</th>
            <th>Pharmacist</th>
        </tr>

        <?php
        $permNames = [
            "dashboard" => "View Dashboard",
            "user_list" => "View User List",
            "add_user" => "Add User",
            "patient_list" => "View Patient List",
            "add_patient" => "Add Patient",
            "edit_patient" => "Edit Patient",
            "history" => "View Patient History"
        ];

        foreach ($permNames as $key => $label): ?>
            <tr style="border-bottom:1px solid #ccc;">
                <td><strong><?= $label ?></strong></td>

                <td style="text-align:center;">
                    <input type="checkbox"
                           name="admin_<?= $key ?>"
                           <?= $permissions["admin"][$key] ? "checked" : "" ?>>
                </td>

                <td style="text-align:center;">
                    <input type="checkbox"
                           name="pharmacist_<?= $key ?>"
                           <?= $permissions["pharmacist"][$key] ? "checked" : "" ?>>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button type="submit"
        style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Save Permissions
    </button>
</form>

</div>
</body>
</html>
