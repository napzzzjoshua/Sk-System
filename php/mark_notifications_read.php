<?php
// mark_notifications_read.php
session_start();
require_once 'db_conn.php';



// Only allow if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Mark all notifications as read for admin (admin sees all notifications, not per user)
$sql = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $conn->error]);
}
$conn->close();
?>