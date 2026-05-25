<?php
session_start();
require_once 'db_conn.php';

// ✅ Check if logged in and role is valid
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], [
    'SK Chairperson', 'SK Members', 'SK Treasurer', 'SK Secretary'
])) {
    header("Location: login.php");
    exit;
}

// ✅ Safely set $fullname from session
$fullname = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Unknown User';

// ✅ Fetch proposals submitted by the logged-in user
$user_id = $_SESSION['user_id'];
$sql = "SELECT title, description, status, submitted_at 
        FROM submissions 
        WHERE submitted_by = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Proposal Status</title>
    <link rel="stylesheet" href="../css/proposal_status.css">
</head>
<body>
<div class="sidebar">
    <h2>SK Dashboard</h2>
    <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="submit_proposal.php">Submit Project Proposal</a></li>
        <li><a href="proposal_status.php" class="active">Proposal Status</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="content">
    <h2>Welcome, <?php echo htmlspecialchars($fullname); ?></h2>
    <table>
        <tr>
            <th>Project Title</th>
            <th>Description</th>
            <th>Status</th>
            <th>Submitted</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><?php echo htmlspecialchars($row['submitted_at']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No proposals found.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
