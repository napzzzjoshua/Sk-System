<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Content and Form Management</title>
  <link rel="stylesheet" href="..\css\content_management.css">
</head>
<body>
  <div class="content-container">
    <h2>Content and Form Management</h2>
    <p>This is a placeholder page where you can manage system forms and informational content.</p>
    <ul>
      <li><a href="#">Edit Terms and Conditions</a></li>
      <li><a href="#">Manage Forms (Aid Requests, Proposals)</a></li>
      <li><a href="#">Update Contact Information</a></li>
    </ul>
    <a href="admin_dashboard.php">Back to Dashboard</a>
  </div>
</body>
</html>
