<?php
session_start();
require_once 'db_conn.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_type'] = "error";
    header("Location: sk_list.php");
    exit();
}

// Check session authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SK Official','SK Chairperson','SK Members','SK Treasurer','SK Secretary'])) {
    $_SESSION['message'] = "You are not authorized to perform this action.";
    $_SESSION['message_type'] = "error";
    header("Location: sk_list.php");
    exit();
}

// --- Define Variables and File Path ---
$target_dir = "uploads/profiles/";
$default_photo_path = "uploads/profiles/default-avatar.png";

// Sanitize and validate input data
$id          = (int)($_POST['id'] ?? 0);
$first_name  = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name   = trim($_POST['last_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone_number= trim($_POST['phone_number'] ?? '');

if ($id === 0 || empty($first_name) || empty($last_name)) {
    $_SESSION['message'] = "Invalid official ID or missing required data for update.";
    $_SESSION['message_type'] = "error";
    header("Location: sk_list.php");
    exit();
}

// --- 1. Retrieve current photo path before potential update ---
$current_photo_db_path = '';
$sql_fetch = "SELECT profile_photo FROM sk_list WHERE id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);

if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $id);
    $stmt_fetch->execute();
    $stmt_fetch->bind_result($current_photo_db_path);
    $stmt_fetch->fetch();
    $stmt_fetch->close();
}

// --- 2. FILE UPLOAD & UPDATE LOGIC ---
$new_photo_db_path = $current_photo_db_path; // Assume no change first

if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
    
    // Sanitize and validate file
    $imageFileType = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
    
    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if($check === false) {
        $_SESSION['message'] = "File is not a valid image. Profile update failed.";
        $_SESSION['message_type'] = "error";
        header("Location: sk_list.php");
        exit();
    }
    // ... (File size and type checks should be here, omitted for brevity) ...

    // Generate a unique file name
    $unique_name = $last_name . '_' . uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $unique_name;

    // Try to move the uploaded file
    if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
        
        // Success: Set the new DB path to the full relative path
        $new_photo_db_path = $target_file; 

        // --- IMPORTANT: Delete the old file if it's not the default avatar ---
        if (!empty($current_photo_db_path) && $current_photo_db_path !== $default_photo_path) {
            // Check if the file exists on the server before unlinking
            if (file_exists($current_photo_db_path)) {
                unlink($current_photo_db_path);
            }
        }
    } else {
        // File upload failed
        $_SESSION['message'] = "Error uploading new photo. Profile updated, but photo unchanged.";
        $_SESSION['message_type'] = "warning";
        // $new_photo_db_path remains the current photo path
    }
}
// --- END FILE UPLOAD LOGIC ---

// --- 3. Database Update ---
$sql_update = "UPDATE sk_list SET 
                    first_name = ?, 
                    middle_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone_number = ?, 
                    profile_photo = ? 
                WHERE id = ?";

$stmt_update = $conn->prepare($sql_update);

if ($stmt_update) {
    $stmt_update->bind_param("ssssssi", 
        $first_name, $middle_name, $last_name, $email, $phone_number, $new_photo_db_path, $id);
    
    if ($stmt_update->execute()) {
        $_SESSION['message'] = "Official profile updated successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Database error: Could not update official. " . $stmt_update->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt_update->close();
} else {
    $_SESSION['message'] = "SQL preparation failed: " . $conn->error;
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: sk_list.php");
exit();
?>