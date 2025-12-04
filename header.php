<!DOCTYPE html>
<html>
<head>
    <title>Pharmacy Management System</title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: #f0f4ff;
        }

        .sidebar {
            width: 320px;
            background: #0b2f6d;
            height: 100vh;
            position: fixed;
            padding: 20px;
            color: white;
        }

        .sidebar a {
            display: block;
            padding: 12px;
            color: white;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 5px;
        }

        .sidebar a:hover {
            background:#11408a;
        }

        .content {
            margin-left: 340px;
            padding: 40px 20px;
        }

        .card-container {
            display: flex;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 230px;
            margin-right: 20px;
            box-shadow: 0 0 6px rgba(0,0,0,0.1);
        }

        .card h3 {
            margin: 0;
            color: #0b2f6d;
        }

        .card p {
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<?php include "sidebar.php"; ?>

<div class="content">
