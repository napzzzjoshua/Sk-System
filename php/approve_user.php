<?php
session_start();
// This file assumes 'db_conn.php' exists and provides the $conn object for MySQL connection.
require_once 'db_conn.php';

// Check for Admin access and necessary user ID parameter
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin' || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$user_id_to_act = intval($_GET['id']);
$admin_fullname = $_SESSION['fullname'];

// =======================================================
// MODIFICATION: Get the source table and validate it
// =======================================================
$source_table = 'users'; // Default to 'users' for safety
if (isset($_GET['source']) && in_array($_GET['source'], ['users', 'sec_users'])) {
    $source_table = $_GET['source'];
}
// =======================================================

// 1. Fetch user details before updating (ensuring they are still pending)
// Dynamically select the table based on the 'source' parameter
$select_sql = "SELECT id, firstname, middlename, surname FROM {$source_table} WHERE id = ? AND status = 'Pending'";
$stmt = $conn->prepare($select_sql);
$stmt->bind_param("i", $user_id_to_act);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if ($user_data) {
    // The recipient_id for the notification is the ID of the user being approved
    $recipient_id = $user_data['id'];
    $user_fullname = trim($user_data['firstname'] . ' ' . $user_data['surname']); // Full name for admin message

    // Start transaction for atomic operations
    $conn->begin_transaction();

    try {
        // 2. Update user status to 'Approve' in the correct source table
        $update_sql = "UPDATE {$source_table} SET status = 'Approve' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id_to_act);
        $update_stmt->execute();
        $update_stmt->close();

        // 3. Insert notification into the 'notifications' table for the approved user
        $message = "Your account has been **approved** by Admin {$admin_fullname}. Welcome to the system!";
        $link = "manage_users.php"; // Link to the user management page after approval
        $icon = "fas fa-user-check";
        $color = "green"; // Custom color for success

        // NOTE: Notifications are typically stored for the main user base (assumed 'users' table). 
        // This logic reuses the ID for notification regardless of the source table, assuming IDs are not conflicting or the admin will handle follow-up.
        $notification_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, message, link, icon, color, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        $notification_stmt->bind_param("issss", $recipient_id, $message, $link, $icon, $color);
        $notification_stmt->execute();
        $notification_stmt->close();

        // Commit transaction if all database operations succeeded
        $conn->commit();

        $_SESSION['notification_message'] = "User **{$user_fullname}** (Source: **{$source_table}**) has been successfully approved and notified.";
        $_SESSION['notification_type'] = "success";

    } catch (Exception $e) {
        // Rollback transaction on failure
        $conn->rollback();
        error_log("Approval Error: " . $e->getMessage());
        $_SESSION['notification_message'] = "Error approving user in table **{$source_table}**. Please try again.";
        $_SESSION['notification_type'] = "error";
    }

} else {
    $_SESSION['notification_message'] = "User not found in **{$source_table}** or already processed.";
    $_SESSION['notification_type'] = "error";
}

// Redirect back to the user management page
header("Location: manage_users.php");
exit;
?>