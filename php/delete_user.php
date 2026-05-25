<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

// Check if user ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $user_id = $_GET['id'];

    // Use a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['notification_message'] = "User deleted successfully.";
        $_SESSION['notification_type'] = "success";
    } else {
        $_SESSION['notification_message'] = "Error deleting user: " . $stmt->error;
        $_SESSION['notification_type'] = "error";
    }

    $stmt->close();
} else {
    $_SESSION['notification_message'] = "Invalid user ID provided.";
    $_SESSION['notification_type'] = "error";
}

$conn->close();

// Redirect back to the manage users page
header("Location: manage_users.php");
exit;
?>