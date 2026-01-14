<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   LOGIN CHECK
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   ROLE CHECK: SINGLE ROLE
========================= */
function requireRole(string $requiredRole): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        die("<h3 style='color:red;'>❌ Access Denied: Admin only</h3>");
    }
}

/* =========================
   ROLE CHECK: MULTIPLE ROLES
========================= */
function hasAnyRole(array $roles): bool {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    return in_array($_SESSION['role'], $roles, true);
}
