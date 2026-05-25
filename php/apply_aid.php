<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

$fullname = $_SESSION['fullname'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $reason = $_POST['reason'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $aid_type = $_POST['aid_type'] ?? '';

    if (!empty($reason) && !empty($amount) && !empty($aid_type)) {
        // First, get the user's details from the users table
        $stmt_user = $conn->prepare("SELECT surname, barangay FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result = $stmt_user->get_result();
        $user_details = $result->fetch_assoc();
        $stmt_user->close();

        if ($user_details) {
            $submitted_by = "{$user_details['surname']} from {$user_details['barangay']}";

            // Prepare the SQL statement to insert the new data, including the formatted string
            $stmt = $conn->prepare("INSERT INTO aid_requests (reason, amount, aid_type, submitted_by, status) VALUES ( ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("sdss", $reason, $amount, $aid_type, $submitted_by);
            
            if ($stmt->execute()) {
                $success_message = "Your application has been submitted successfully.";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "User details could not be found.";
        }
    } else {
        $error_message = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Aid</title>
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
        /* New styles for the toast notification */
        #toast-notification {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateX(100%);
            transition: transform 0.5s ease-out, opacity 0.5s ease-out, visibility 0.5s ease-out;
        }
        #toast-notification.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
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
                    <a href="beneficiary_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="apply_aid.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
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
                    <h1 class="text-3xl font-bold text-gray-800">Apply for Financial Aid</h1>
                    <p class="text-gray-500 mt-1">Fill out the form below to submit a new request.</p>
                </div>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg max-w-2xl mx-auto">
                <form method="post" action="apply_aid.php" class="space-y-6">
                    <div>
                        <label for="aid_type" class="block text-sm font-medium text-gray-700 mb-2">Type of Financial Aid</label>
                        <input type="text" name="aid_type" id="aid_type" placeholder="e.g., Educational, Medical" required class="mt-1 block w-full px-4 py-3 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200">
                    </div>
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Aid Request</label>
                        <textarea name="reason" id="reason" placeholder="Reason for aid request" rows="5" required class="mt-1 block w-full px-4 py-3 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200"></textarea>
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Requested Amount (PHP)</label>
                        <input type="number" name="amount" id="amount" placeholder="Requested Amount" required class="mt-1 block w-full px-4 py-3 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-[#F97316] text-white py-3 px-6 rounded-xl font-semibold hover:bg-orange-600 transition duration-200 shadow-md">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Toast Notification Container -->
    <div id="toast-notification" class="w-full max-w-xs bg-white rounded-lg shadow-lg p-4 pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500 h-6 w-6"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="text-sm font-medium text-gray-900">Success!</p>
                <p class="mt-1 text-sm text-gray-500" id="toast-message">Your application has been submitted successfully.</p>
            </div>
            <div class="ml-4 flex-shrink-0 flex">
                <button type="button" class="inline-flex text-gray-400 hover:text-gray-500 focus:outline-none" onclick="hideToast()">
                    <span class="sr-only">Close</span>
                    <i class="fas fa-times h-5 w-5"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Check if there is a success message from the PHP backend
        const successMessage = "<?= htmlspecialchars($success_message) ?>";
        if (successMessage) {
            document.getElementById('toast-message').innerText = successMessage;
            showToast();
        }

        // Check if there is an error message from the PHP backend
        const errorMessage = "<?= htmlspecialchars($error_message) ?>";
        if (errorMessage) {
            document.getElementById('toast-message').innerText = errorMessage;
            document.getElementById('toast-notification').classList.remove('bg-white', 'ring-black');
            document.getElementById('toast-notification').classList.add('bg-red-100', 'ring-red-400');
            document.querySelector('#toast-notification .fa-check-circle').classList.remove('text-green-500');
            document.querySelector('#toast-notification .fa-check-circle').classList.add('text-red-700');
            document.querySelector('#toast-notification .fa-check-circle').classList.remove('fa-check-circle');
            document.querySelector('#toast-notification .fa-check-circle').classList.add('fa-exclamation-circle');

            showToast();
        }

        function showToast() {
            const toast = document.getElementById('toast-notification');
            toast.classList.add('show');
            setTimeout(() => {
                hideToast();
            }, 5000); // Hide after 5 seconds
        }

        function hideToast() {
            const toast = document.getElementById('toast-notification');
            toast.classList.remove('show');
        }
    </script>

</body>
</html>
