<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Management System</title>

    <style>
        *{ box-sizing:border-box; }

        body{
            margin:0;
            font-family: Arial, sans-serif;
            background:#f0f4ff;
        }

        /* Sidebar */
        .sidebar{
            width:320px;
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            height:100vh;
            position:fixed;
            top:0; left:0;
            padding:20px;
            color:#fff;
            overflow-y:auto;
        }

        .sidebar a{
            color:white;
            text-decoration:none;
            display:block;
            padding:12px 14px;
            border-radius:8px;
            margin-bottom:6px;
            font-weight:600;
            transition: background 0.3s;
        }

        .sidebar a:hover{
            background: rgba(255,255,255,0.20);
        }

        /* Main content */
        .content{
            margin-left:320px;
            padding:30px 30px;
            min-height:100vh;
        }

        /* Optional: make tables/cards not squeeze */
        .content .page-wrap{
            max-width:1200px;
        }
    </style>
</head>

<body>

<?php include "sidebar.php"; ?>

<div class="content">
<div class="page-wrap">
