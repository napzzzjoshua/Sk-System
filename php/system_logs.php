<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$fullname = $_SESSION['fullname'];

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Simulated logs and analytics data
$total_logs = 1250;
$unique_users = 85;
$logs_last_24h = 15;

$logs = [
  ["User login", "Jasper Ricamora", "2025-07-14 08:25"],
  ["User registered", "New Beneficiary", "2025-07-13 15:47"],
  ["Admin updated content", "Admin", "2025-07-12 09:10"],
  ["SK Official reviewed application", "SK Official", "2025-07-12 09:05"],
  ["User login", "Jane Doe", "2025-07-11 14:00"],
  ["Form submission", "John Smith", "2025-07-11 11:30"],
  ["User logout", "Jane Doe", "2025-07-11 10:00"],
  ["Admin login", "Admin", "2025-07-11 09:00"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs</title>
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
    </style>
</head>
<body class="flex md:flex-row flex-col">

    <aside class="sidebar shadow-xl">
        <div class="flex items-center space-x-4 mb-6">
            <img src="../majayjay_logo.jpg" alt="SK Logo" class="h-12 w-12 rounded-full border-2 border-white">
            <h2 class="text-xl font-semibold text-white">Admin System</h2>
        </div>
        
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">Admin Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="admin_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                 <a href="geo_mapping.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-map-location-dot"></i>
                        <span>Geo Mapping</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-users-cog"></i>
                        <span>Manage Users & Permissions</span>
                    </a>
                </li>
                <li>
                    <a href="requests.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-folder-open"></i>
                        <span>Requests</span>
                    </a>
                </li>
                <li>
                    <a href="system_logs.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-chart-line"></i>
                        <span>System Logs & Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="form_management.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-edit"></i>
                        <span>Content & Form Management</span>
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">System Logs & Analytics</h1>
                    <p class="text-gray-500 mt-1">Monitor all system activities and user behavior.</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 sm:mt-0">
                    <span id="current-time" class="text-gray-600 text-sm font-medium"></span>
                    <div class="relative">
                        <button class="bg-white p-3 rounded-full shadow-md text-gray-500 hover:text-gray-700 transition duration-200 focus:outline-none">
                            <i class="fas fa-bell text-lg"></i>
                        </button>
                        <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full border-2 border-white"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Logs</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $total_logs ?></p>
                    </div>
                    <div class="p-3 bg-blue-100 text-blue-600 rounded-full">
                        <i class="fas fa-file-alt text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Users</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $unique_users ?></p>
                    </div>
                    <div class="p-3 bg-green-100 text-green-600 rounded-full">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Logs Last 24h</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $logs_last_24h ?></p>
                    </div>
                    <div class="p-3 bg-orange-100 text-orange-600 rounded-full">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent System Activities</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($log[0]) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($log[1]) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($log[2]) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Real-time clock script
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>