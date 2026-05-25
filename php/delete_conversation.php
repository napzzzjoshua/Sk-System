<?php
session_start();
require_once 'db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;

if ($recipient_id > 0) {
    $sql = "DELETE FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $admin_id, $recipient_id, $recipient_id, $admin_id);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $success]);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid recipient']);
