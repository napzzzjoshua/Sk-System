default_timezone_set('Asia/Manila');
<?php
session_start();
// Prevent any output before JSON
ob_start();
header('Content-Type: application/json');

// Disable error reporting output for AJAX
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Use POST method.']);
    exit;
}

require_once 'db_conn.php';

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$response = isset($_POST['response']) ? trim($_POST['response']) : '';

if ($title === '' || $date === '' || $content === '' || $response === '') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Anonymous';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

date_default_timezone_set('Asia/Manila');
$response_date = date('Y-m-d H:i:s');

$sql = "INSERT INTO announcement_responses (announcement_title, announcement_date, announcement_content, response, responder_name, responder_id, response_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: prepare failed.']);
    exit;
}
$stmt->bind_param('sssssis', $title, $date, $content, $response, $name, $user_id, $response_date);
if ($stmt->execute()) {
    ob_end_clean();
    echo json_encode(['success' => true]);
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to save response.']);
}
$stmt->close();
$conn->close();
exit;
