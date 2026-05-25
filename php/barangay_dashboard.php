<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Barangay Official') {
    header("Location: login.php");
    exit;
}

$fullname = $_SESSION['fullname'];
require_once 'db_conn.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F3F4F6;
        }
        .sidebar {
            width: 280px;
            background-color: #1F2937;
            color: #F9FAFB;
            position: fixed;
            height: 100%;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            background-color: #F3F4F6;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        th {
            background-color: #F3F4F6;
            font-weight: 600;
            color: #4B5563;
        }
        tr:hover {
            background-color: #F9FAFB;
        }
    </style>
</head>
<body class="flex md:flex-row flex-col">

    <aside class="sidebar shadow-xl">
        <div class="flex items-center space-x-4 mb-6">
            <img src="../sk_logo.png" alt="SK Logo" class="h-12 w-12 rounded-full border-2 border-white">
            <h2 class="text-2xl font-semibold text-white">Barangay System</h2>
        </div>
        
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">Barangay Official Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="barangay_dashboard.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="view_reports.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-chart-line"></i>
                        <span>View Reports</span>
                    </a>
                </li>
                <li>
                    <a href="generate_reports.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-file-download"></i>
                        <span>Generate Reports</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="mt-auto">
            <a href="logout.php" class="flex items-center space-x-3 text-red-400 hover:text-white hover:bg-red-600 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <div class="space-y-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Barangay Dashboard</h1>
                    <p class="text-gray-500 mt-1">Review and approve SK submissions.</p>
                </div>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <table>
                    <thead>
                        <tr>
                            <th>Project Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </main>
</body>
</html>