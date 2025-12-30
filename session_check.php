<?php
session_start();

// ⏱️ Session timeout (10 minutes)
$timeout_duration = 600;


// Check last activity time
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > $timeout_duration) {
        // Session expired → logout
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();
