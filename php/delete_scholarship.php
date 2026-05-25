<?php
// Include your database connection file
require_once 'db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if ($id > 0) {
        // Prepare a delete statement to prevent SQL injection
        $sql = "DELETE FROM scholarship_applications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method or missing ID.']);
}

$conn->close();
?>
