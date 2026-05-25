<?php
// update_announcement.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

require_once 'db_conn.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || $title === '' || $content === '' || !in_array($status, ['Published', 'Draft'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, status=? WHERE announcement_id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}
$stmt->bind_param('sssi', $title, $content, $status, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update announcement.']);
}
$stmt->close();
$conn->close();
