<?php
session_start();
require_once 'db_conn.php';
if (!isset($_SESSION['user_id']) || strpos($_SESSION['role'], 'SK') === false) {
    header("Location: login.php");
    exit;
}

$fullname = $_SESSION['fullname'];

// --- Fetch barangay of the current user ---
$sql  = "SELECT barangay FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($barangay);
$stmt->fetch();
$stmt->close();

// --- Barangay-based SK logo logic ---
$logoPath = "../sk_logo.png"; // default logo
if (strcasecmp($barangay, 'Suba') === 0) {
    $logoPath = "../sk_suba.jpg";
} elseif (strcasecmp($barangay, 'San Isidro') === 0) {
    $logoPath = "../sk_sanisidro.jpg";
}

// --- Fetch data from the 'submissions' table, filtered by user's barangay ---
$submissions_data = [];
// IMPORTANT: Only select submissions where the barangay matches the user's barangay.
$sql_submissions = "SELECT title, status FROM submissions WHERE barangay = ?";

// Use prepared statement for security
$stmt_submissions = $conn->prepare($sql_submissions);
if ($stmt_submissions === false) {
    die("Error preparing submission statement: " . $conn->error);
}
$stmt_submissions->bind_param("s", $barangay);
$stmt_submissions->execute();
$result_submissions = $stmt_submissions->get_result();

if ($result_submissions->num_rows > 0) {
    while($row = $result_submissions->fetch_assoc()) {
        $submissions_data[] = $row;
    }
}
$stmt_submissions->close();

// --- Fetch data from the 'financial_aid_requests' table, filtered by user's barangay ---
$financial_aid_data = [];
// IMPORTANT: Only select financial aid requests where the barangay matches the user's barangay.
$sql_financial_aid = "SELECT student_name, reason, status FROM financial_aid_requests WHERE barangay = ?";

// Use prepared statement for security
$stmt_financial_aid = $conn->prepare($sql_financial_aid);
if ($stmt_financial_aid === false) {
    die("Error preparing financial aid statement: " . $conn->error);
}
$stmt_financial_aid->bind_param("s", $barangay);
$stmt_financial_aid->execute();
$result_financial_aid = $stmt_financial_aid->get_result();

if ($result_financial_aid->num_rows > 0) {
    while($row = $result_financial_aid->fetch_assoc()) {
        $financial_aid_data[] = $row;
    }
}
$stmt_financial_aid->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Submissions</title>
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
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="SK Logo"
             class="h-12 w-12 rounded-full border-2 border-white">
        <h2 class="text-2xl font-semibold text-white">SK System</h2>
        </div>
        
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">SK Official Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="sk_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                <a href="sk_list.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                    <i class="fas fa-users-cog"></i><span>SK List</span>
                </a>
            </li>
            <li>
                <a href="document_submissions.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                    <i class="fas fa-file-invoice"></i><span>Document Submissions</span>
                </a>
            </li>
                <li>
                    <a href="submit_proposal.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-file-upload"></i>
                        <span>Project Proposal</span>
                    </a>
                </li>
                <li>
                    <a href="financial_aid.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Financial Aid</span>
                    </a>
                </li>
                <li>
                    <a href="monitor_submissions.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-search-plus"></i>
                        <span>Monitor Submissions</span>
                    </a>
                </li>
                <li>
                    <a href="scholarship_list.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-user-graduate"></i>
                        <span>Scholarship List</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="mt-auto">
            <a href="login.php" class="flex items-center space-x-3 text-red-400 hover:text-white hover:bg-red-600 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <div class="space-y-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Monitor Submissions</h1>
                    <p class="text-gray-500 mt-1">View the status of all submitted requests for **<?= htmlspecialchars($barangay) ?>**.</p>
                </div>
            </div>

            <!-- START: Combined Container for Submissions and Financial Aid -->
            <div class="bg-white p-8 rounded-2xl shadow-lg">

                <!-- Tab Buttons -->
                <div class="flex space-x-4 mb-6 border-b border-gray-200 pb-4">
                    <button id="tab-proposals" class="px-6 py-2 rounded-xl font-semibold transition duration-200 text-sm focus:outline-none bg-[#F97316] text-white shadow-md">
                        Project Proposals
                    </button>
                    <button id="tab-financial" class="px-6 py-2 rounded-xl font-semibold transition duration-200 text-sm focus:outline-none bg-gray-200 text-gray-700 hover:bg-gray-300">
                        Financial Aid Requests
                    </button>
                </div>

                <!-- Content Area 1: Project Proposals -->
                <div id="submissions-content" class="content-tab">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Project Proposals Status</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Project Title</th>
                                    <th scope="col" class="py-3 px-6">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($submissions_data)): ?>
                                    <tr class="bg-white border-b">
                                        <td colspan="2" class="py-4 px-6 text-center text-gray-500">No project proposals found for <?= htmlspecialchars($barangay) ?>.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($submissions_data as $submission): ?>
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($submission['title']); ?>
                                        </td>
                                        <td class="py-4 px-6 font-semibold">
                                            <?php
                                            $statusClass = '';
                                            switch ($submission['status']) {
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
                                            echo "<span class='$statusClass'>" . htmlspecialchars($submission['status']) . "</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Content Area 2: Financial Aid Requests (Hidden by default) -->
                <div id="financial-aid-content" class="content-tab" style="display: none;">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Financial Aid Requests Status</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Student Name</th>
                                    <th scope="col" class="py-3 px-6">Reason</th>
                                    <th scope="col" class="py-3 px-6">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($financial_aid_data)): ?>
                                    <tr class="bg-white border-b">
                                        <td colspan="3" class="py-4 px-6 text-center text-gray-500">No financial aid requests found for <?= htmlspecialchars($barangay) ?>.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($financial_aid_data as $request): ?>
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($request['student_name']); ?>
                                        </td>
                                        <td class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($request['reason']); ?>
                                        </td>
                                        <td class="py-4 px-6 font-semibold">
                                            <?php
                                            $statusClass = '';
                                            switch ($request['status']) {
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
                                            echo "<span class='$statusClass'>" . htmlspecialchars($request['status']) . "</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- END: Combined Container -->
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const proposalsButton = document.getElementById('tab-proposals');
            const financialButton = document.getElementById('tab-financial');
            const proposalsContent = document.getElementById('submissions-content');
            const financialContent = document.getElementById('financial-aid-content');

            // Tailwind classes for styling (matching existing styles and the active sidebar link color)
            const activeClasses = 'bg-[#F97316] text-white shadow-md';
            const inactiveClasses = 'bg-gray-200 text-gray-700 hover:bg-gray-300';
            const baseClasses = 'px-6 py-2 rounded-xl font-semibold transition duration-200 text-sm focus:outline-none';

            /**
             * Switches the active tab content and updates button styling.
             * @param {string} activeTab - 'proposals' or 'financial'
             */
            function switchTab(activeTab) {
                if (activeTab === 'proposals') {
                    // Show proposals, hide financial
                    proposalsContent.style.display = 'block';
                    financialContent.style.display = 'none';

                    // Update button styles: proposals active, financial inactive
                    proposalsButton.className = baseClasses + ' ' + activeClasses;
                    financialButton.className = baseClasses + ' ' + inactiveClasses;
                } else {
                    // Show financial, hide proposals
                    proposalsContent.style.display = 'none';
                    financialContent.style.display = 'block';

                    // Update button styles: financial active, proposals inactive
                    financialButton.className = baseClasses + ' ' + activeClasses;
                    proposalsButton.className = baseClasses + ' ' + inactiveClasses;
                }
            }

            // Set up event listeners
            proposalsButton.addEventListener('click', () => switchTab('proposals'));
            financialButton.addEventListener('click', () => switchTab('financial'));

            // Set initial state to 'Project Proposals'
            switchTab('proposals');
        });
    </script>
</body>
</html>
