<?php
// This file is assumed to be the target of the AJAX request in requests.php

session_start();
header('Content-Type: application/json');

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Ensure the necessary connection file is included
// NOTE: Assuming db_conn.php is in the same directory or correctly path-adjusted.
require_once 'db_conn.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;
    $viewByAdmin = isset($_POST['view_by_admin']) ? $_POST['view_by_admin'] : false;

    // Handle view by admin status change for submissions and financial_aid_requests
    if ($viewByAdmin && $id > 0) {
        if ($type === 'submissions' || $type === 'financial_aid_requests') {
            $sql = "UPDATE `$type` SET status = 'View by Admin' WHERE id = ? AND status = 'Pending'";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Status changed to View by Admin.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No rows updated. Request ID not found or status not Pending.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            }
            $conn->close();
            exit;
        }
    }

    if ($id <= 0 || !in_array($status, ['Approved', 'Rejected']) || !in_array($type, ['financial_aid_requests', 'submissions'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
        exit;
    }
    $tableName = $type;
    $sql = "UPDATE `$tableName` SET status = ?";
    $params = [$status];
    $types = "s";
    if ($status === 'Rejected') {
        if (empty($rejectionReason)) {
             echo json_encode(['success' => false, 'message' => 'Rejection reason is required for rejection status.']);
             exit;
        }
        $sql .= ", rejection_reason = ?";
        $params[] = $rejectionReason;
        $types .= "s";
    }
    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No rows updated. Request ID not found or status unchanged.']);
            }
        } else {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
    }
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

?>
