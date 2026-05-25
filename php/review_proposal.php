<?php
session_start();
require_once 'db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Barangay Officials') {
    header("Location: login.php");
    exit;
}

$fullname = $_SESSION['fullname'];
$submissions = $conn->query("SELECT * FROM submissions ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Review Proposals</title>
  <link rel="stylesheet" href="..\css\review_reports_styles.css">
</head>
<body>
<div class="dashboard-container">
  <h2>Review and Approve Proposals</h2>
  <p>Welcome, <?php echo htmlspecialchars($fullname); ?></p>
  <table>
    <tr>
      <th>Title</th>
      <th>Description</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
    <?php while ($row = $submissions->fetch_assoc()): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['title']); ?></td>
      <td><?php echo htmlspecialchars($row['description']); ?></td>
      <td><?php echo htmlspecialchars($row['status']); ?></td>
      <td>
        <?php if ($row['status'] === 'Pending'): ?>
        <form method="post" action="approve_submission.php">
  <input type="hidden" name="submission_id" value="<?php echo $row['id']; ?>">
  <button name="action" value="approve">Approve</button>
  <button name="action" value="forward">Forward to City Office</button>
  <button name="action" value="revision">Request Revisions</button>
  <button name="action" value="reject">Reject</button>
</form>

        <?php else: ?>
          <em><?php echo $row['status']; ?></em>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>