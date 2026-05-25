<?php
session_start();
header('Content-Type: application/json');

// 1. Security Check: Ensure Admin is logged in and the request method is POST
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit;
}

// Extract Admin's ID from the session for sender_id
// This is crucial: the Admin is the one sending the message, so their ID is the SENDER_ID
$sender_id = $_SESSION['user_id']; 

// Ensure database connection file is available
if (!file_exists('db_conn.php')) {
    echo json_encode(['success' => false, 'message' => 'Database configuration file missing.']);
    exit;
}
require_once 'db_conn.php';

// Check if $conn is available (assuming db_conn.php establishes it)
if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// 2. Input Validation and Receiver ID Handling
$message_content = isset($_POST['message_content']) ? trim($_POST['message_content']) : '';
$sender_fullname = isset($_POST['sender_fullname']) ? trim($_POST['sender_fullname']) : '';
// Get the ID of the user the Admin is replying to. This will be the RECEIVER_ID.
$receiver_id_post = isset($_POST['receiver_id']) ? $_POST['receiver_id'] : null;

// The barangay column is nullable, so we will set a default of NULL
$barangay = null; 
// Admin's messages typically don't need a barangay associated in this context, but we keep it NULL to match the schema

if (empty($message_content) || empty($sender_fullname)) {
    echo json_encode(['success' => false, 'message' => 'Message content or sender details are missing.']);
    exit;
}

// Validate and set receiver_id. It must be a valid integer if provided.
// Since Admin replies must go to a specific user, receiver_id must be set.
if ($receiver_id_post !== null && is_numeric($receiver_id_post)) {
    $receiver_id = (int)$receiver_id_post;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient selected.']);
    exit;
}

// 3. Define Fixed Value
$admin_name = 'Sk President'; // Fixed value for the admin's role/name

// 4. Prepare and Execute INSERT Query with sender_id and receiver_id
$sql = "INSERT INTO chat_messages (sender_id, receiver_id, admin_name, sender_fullname, barangay, messages_content) 
        VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    // Bind parameters: isssss 
    // i: sender_id (Admin's ID)
    // s: receiver_id (Recipient's ID, bound as string to handle potential mysqli type issues, though it should be integer)
    // s: admin_name
    // s: sender_fullname (Admin User's Fullname)
    // s: barangay (NULL)
    // s: messages_content
    
    // NOTE: If your mysqli version requires explicit 'i' for integers, you might need to cast $receiver_id to string 
    // or adjust the binding types. We will use 'isssss' as a robust approach, treating IDs as strings 
    // for binding consistency if they are nullable in the schema, but since they are being passed, we can use 'i' for both IDs.
    
    // Correct binding: 'ii' for IDs, 'ssss' for strings
    $stmt->bind_param("iissis", $sender_id, $receiver_id, $admin_name, $sender_fullname, $barangay, $message_content);


    if ($stmt->execute()) {
        // Success: Send a JSON response
        echo json_encode(['success' => true, 'message' => 'Message inserted.']);
    } else {
        // Failure during execution
        error_log("SQL Error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error during insertion.']);
    }

    $stmt->close();
} else {
    // Failure during statement preparation
    error_log("SQL Prepare Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Could not prepare the database statement.']);
}

?>
