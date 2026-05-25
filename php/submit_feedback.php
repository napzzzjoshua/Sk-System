<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $message = $_POST['message'] ?? '';

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, message, submitted_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $user_id, $message);
        
        if ($stmt->execute()) {
            $success_message = "Thank you for your feedback!";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Feedback cannot be empty.";
    }
}

$fullname = $_SESSION['fullname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback</title>
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
                    <a href="track_status.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-search"></i>
                        <span>Track Application Status</span>
                    </a>
                </li>
                <li>
                    <a href="submit_feedback.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
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
                    <h1 class="text-3xl font-bold text-gray-800">Submit Feedback</h1>
                    <p class="text-gray-500 mt-1">Share your thoughts to help us improve.</p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative transition-opacity duration-500 ease-out" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative transition-opacity duration-500 ease-out" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-lg max-w-2xl mx-auto">
                <form method="post" action="submit_feedback.php" class="space-y-6">
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Your Feedback</label>
                        <textarea name="message" id="message" placeholder="Write your feedback here..." rows="5" required class="mt-1 block w-full px-4 py-3 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-[#F97316] text-white py-3 px-6 rounded-xl font-semibold hover:bg-orange-600 transition duration-200 shadow-md">
                            Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>