<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$fullname = $_SESSION['fullname'];

// Query to get the count of pending applications
$pending_query = "SELECT COUNT(*) AS pending_count FROM aid_requests WHERE status = 'Pending'";
$pending_result = $conn->query($pending_query);
$pending_count = $pending_result->fetch_assoc()['pending_count'];

// Query to get the count of approved applications
$approved_query = "SELECT COUNT(*) AS approved_count FROM aid_requests WHERE status = 'Approved'";
$approved_result = $conn->query($approved_query);
$approved_count = $approved_result->fetch_assoc()['approved_count'];

// Query to get the total amount of approved aid
$aid_query = "SELECT SUM(amount) AS total_aid FROM aid_requests WHERE status = 'Approved'";
$aid_result = $conn->query($aid_query);
$total_aid_received = $aid_result->fetch_assoc()['total_aid'];

// Handle case where no aid has been received
if ($total_aid_received === null) {
    $total_aid_received = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beneficiary Dashboard</title>
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
        .status-pending {
            color: #F59E0B;
        }
        .status-approved {
            color: #10B981;
        }
        .status-rejected {
            color: #EF4444;
        }
    </style>
</head>
<body class="flex md:flex-row flex-col">

    <aside class="sidebar shadow-xl">
        <div class="flex items-center space-x-4 mb-6">
            <img src="../sk_logo.png" alt="SK Logo" class="h-12 w-12 rounded-full border-2 border-white">
            <h2 class="text-xl font-semibold text-white">Beneficiary System</h2>
        </div>
        
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">User Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="beneficiary_dashboard.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="apply_aid.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Apply for Aid</span>
                    </a>
                </li>
                <li>
                    <a href="track_status.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-search"></i>
                        <span>Track Application Status</span>
                    </a>
                </li>
                <li>
                    <a href="submit_feedback.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-comment"></i>
                        <span>Submit Feedback</span>
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
                    <h1 class="text-3xl font-bold text-gray-800">Hello, <?= htmlspecialchars($fullname) ?>!</h1>
                    <p class="text-gray-500 mt-1">Welcome to your dashboard. Here's a quick overview of your aid requests.</p>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Pending Applications -->
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Pending Applications</p>
                        <h3 class="mt-1 text-3xl font-bold text-gray-900"><?= htmlspecialchars($pending_count) ?></h3>
                    </div>
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                        <i class="fas fa-hourglass-half fa-2x"></i>
                    </div>
                </div>

                <!-- Approved Applications -->
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Approved Applications</p>
                        <h3 class="mt-1 text-3xl font-bold text-gray-900"><?= htmlspecialchars($approved_count) ?></h3>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
                
                <!-- Total Aid Received -->
                <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Aid Received</p>
                        <h3 class="mt-1 text-3xl font-bold text-gray-900">₱<?= number_format($total_aid_received, 2) ?></h3>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Applications Table -->
            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Recent Applications</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3 px-6">Aid Type</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query for recent applications
                            $recent_query = "SELECT aid_type, status, created_at FROM aid_requests WHERE submitted_by = '" . $_SESSION['fullname'] . "' ORDER BY created_at DESC LIMIT 5";
                            $recent_result = $conn->query($recent_query);

                            if ($recent_result->num_rows > 0) {
                                while ($row = $recent_result->fetch_assoc()) {
                                    $statusClass = '';
                                    switch ($row['status']) {
                                        case 'Pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        case 'Approved':
                                            $statusClass = 'status-approved';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'status-rejected';
                                            break;
                                    }
                                    echo '<tr class="bg-white border-b hover:bg-gray-50">';
                                    echo '<td class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">' . htmlspecialchars($row['aid_type']) . '</td>';
                                    echo '<td class="py-4 px-6 font-semibold"><span class="' . $statusClass . '">' . htmlspecialchars($row['status']) . '</span></td>';
                                    echo '<td class="py-4 px-6">' . htmlspecialchars($row['created_at']) . '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr class="bg-white border-b hover:bg-gray-50"><td class="py-4 px-6 text-center" colspan="3">No recent applications found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
