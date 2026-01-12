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
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            height: 100vh;
            position: fixed;
            padding: 20px;
            color: white;
        }

       .sidebar a {
    color: white;
    text-decoration: none;
    display: block;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 6px;
    font-weight: 500;
    transition: background 0.3s;
}

       .sidebar a:hover {
    background: rgba(255, 255, 255, 0.2);
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
