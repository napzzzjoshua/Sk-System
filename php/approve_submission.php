<?php
require_once 'db_conn.php';
require_once 'notify.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submission_id'], $_POST['action'])) {
    $id = $_POST['submission_id'];
    $action = $_POST['action'];

    // Fetch email of submitter (dummy logic here, update with actual)
    $stmt = $conn->prepare("SELECT submitted_by FROM submissions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $email = $row['submitted_by'] ?? '';

    // Determine new status
    if ($action == 'approve') {
        $status = 'Approved';
    } elseif ($action == 'forward') {
        $status = 'Forwarded to Municipal/City Office';
    } elseif ($action == 'reject') {
        $status = 'Rejected';
    } elseif ($action == 'revision') {
        $status = 'Revision Requested';
    } else {
        $status = 'Pending';
    }

    // Update status in database
    $update = $conn->prepare("UPDATE submissions SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $id);
    if ($update->execute()) {
        sendNotification($email, "Your project proposal status was updated to: $status", $status);
    }

    header("Location: review_proposal.php");
    exit;
}
?>
