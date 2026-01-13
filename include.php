<?php
// header.php
session_start();
require_once 'session_check.php';
require_once 'connection.php';
require_once 'navigation_logic.php'; // Include the logic file

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaCare - <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <style>
        /* Your CSS styles here (or link to external CSS) */
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- WELCOME HEADER -->
        <div class="welcome-header">
            <h1 class="welcome-title"><?php echo $pageTitle ?? 'Welcome to PharmaCare'; ?></h1>
            <p class="welcome-subtitle"><?php echo $pageSubtitle ?? 'Complete Pharmacy Management Dashboard'; ?></p>
        </div>
        
        <?php include 'navigation.php'; ?>