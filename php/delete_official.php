<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

require_once 'db_conn.php';

$id = intval($_POST['id']);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
    exit;
}

$sql = "DELETE FROM sk_list WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No row deleted.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Query failed.']);
}
$conn->close();
