<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$user_id = $_SESSION['user_id'];
// Updated the query to select all data from the aid_requests table
$result = $conn->query("SELECT * FROM aid_requests ORDER BY created_at DESC");
$fullname = $_SESSION['fullname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application Status</title>
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
            background-color: #1F2937;
            color: #F9FAFB;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }
        .main-content {
            padding: 2rem;
            background-color: #F3F4F6;
            min-height: 100vh;
            width: 100%;
        }
        @media (min-width: 768px) {
            .sidebar {
                width: 280px;
                position: fixed;
                height: 100%;
            }
            .main-content {
                margin-left: 280px;
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
<body class="flex flex-col md:flex-row">

    <aside class="sidebar shadow-xl">
        <div class="flex items-center space-x-4 mb-6">
            <img src="../sk_logo.png" alt="SK Logo" class="h-12 w-12 rounded-full border-2 border-white">
            <h2 class="text-xl font-semibold text-white">Beneficiary System</h2>
        </div>
        
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">User Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="beneficiary_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
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
                    <a href="track_status.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
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
                    <h1 class="text-3xl font-bold text-gray-800">Track Application Status</h1>
                    <p class="text-gray-500 mt-1">Review the status of your submitted aid requests.</p>
                </div>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Your Aid Requests</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3 px-6">Reason</th>
                                <th scope="col" class="py-3 px-6">Amount</th>
                                <th scope="col" class="py-3 px-6">Aid Type</th>
                                <th scope="col" class="py-3 px-6">Submitted By</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Date Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="py-4 px-6 font-medium text-gray-900 break-words max-w-xs">
                                        <?= htmlspecialchars($row['reason']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        ₱<?= number_format($row['amount'], 2) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?= htmlspecialchars($row['aid_type']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?= htmlspecialchars($row['submitted_by']) ?>
                                    </td>
                                    <td class="py-4 px-6 font-semibold">
                                        <?php
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
                                        echo "<span class='$statusClass'>" . htmlspecialchars($row['status']) . "</span>";
                                        ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?= htmlspecialchars($row['created_at']) ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="py-4 px-6 text-center" colspan="6">No aid requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
