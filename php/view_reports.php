<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Barangay Officials') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

$query = "SELECT * FROM submissions WHERE 1";
$params = [];
$types = '';

if ($status_filter) {
    $query .= " AND status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

if ($date_filter) {
    $query .= " AND DATE(created_at) = ?";
    $types .= 's';
    $params[] = $date_filter;
}

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Reports</title>
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
            <h2 class="text-2xl font-semibold text-white">Barangay System</h2>
        </div>
        <nav class="flex-grow">
            <p class="text-gray-400 text-xs font-semibold uppercase mb-2 ml-3">Barangay Official Tools</p>
            <ul class="space-y-2">
                <li>
                    <a href="barangay_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="view_reports.php" class="flex items-center space-x-3 bg-[#F97316] text-white p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-chart-line"></i>
                        <span>View Reports</span>
                    </a>
                </li>
                <li>
                    <a href="generate_reports.php" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-700 p-3 rounded-xl transition duration-200 w-full text-sm font-medium">
                        <i class="fas fa-download"></i>
                        <span>Download Report</span>
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
                    <h1 class="text-3xl font-bold text-gray-800">Project Reports</h1>
                    <p class="text-gray-500 mt-1">Filter and view the status of all project submissions.</p>
                </div>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">View Reports</h2>
                <form method="get" class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mb-6">
                    <div class="flex-1">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
                        <select name="status" id="status" class="w-full px-4 py-2 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200">
                            <option value="">All</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="flex-1">
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date:</label>
                        <input type="date" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>" class="w-full px-4 py-2 rounded-lg border-gray-300 border focus:border-[#F97316] focus:ring focus:ring-[#F97316] focus:ring-opacity-50 transition duration-200">
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="w-full md:w-auto px-6 py-2 bg-[#F97316] text-white rounded-lg font-semibold hover:bg-orange-600 transition duration-200">
                            Filter
                        </button>
                        <a href="generate_reports.php" class="w-full md:w-auto px-6 py-2 bg-gray-500 text-white rounded-lg font-semibold hover:bg-gray-600 transition duration-200 text-center">
                            Download CSV
                        </a>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs"><?= htmlspecialchars($row['description']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    if ($row['status'] == 'Pending') echo 'bg-yellow-100 text-yellow-800';
                                    if ($row['status'] == 'Approved') echo 'bg-green-100 text-green-800';
                                    if ($row['status'] == 'Rejected') echo 'bg-red-100 text-red-800';
                                    ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['created_at'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <a href="barangay_dashboard.php" class="text-sm text-gray-600 hover:underline">Back to Dashboard</a>
        </div>
    </main>
</body>
</html>