<?php
session_start();
header('Content-Type: application/json');

// --- 1. Basic Auth Check ---
// The sender's unique ID comes from the session, so we must check if the user is logged in.
if (!isset($_SESSION['user_id']) || empty($_POST['message_content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request, session missing, or message content is empty.']);
    exit;
}

// --- Connection and Setup Check ---
if (!@include_once 'db_conn.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection file (db_conn.php) is missing or failed to load.']);
    exit;
}

if (isset($conn) && $conn->connect_error) {
    http_response_code(500);
    error_log("Connection failed: " . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed. Please check your credentials in db_conn.php.']);
    exit;
}

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection object ($conn) was not created by db_conn.php.']);
    exit;
}

// --- AUTOMATIC SENDER ID ASSIGNMENT ---
// This is the core logic: The unique ID from the authenticated user's session is automatically assigned as the sender_id.
$sender_id = $_SESSION['user_id']; 

// ASSUMPTION: The Central Admin/SK President (the receiver) is ID 1.
$receiver_id = 1; 
$message_content = trim($_POST['message_content']);

try {
    // --- 2. Fetch detailed user info for insertion (Name and Barangay) ---
    // We only need surname, firstname, middlename, and barangay.
    $sql_user = "SELECT surname, firstname, middlename, barangay FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    
    if ($stmt_user === false) {
        error_log("User details query prepare failed: " . $conn->error);
        throw new Exception("SQL Error: Failed to prepare user details query. MySQL Error: " . $conn->error);
    }

    $stmt_user->bind_param("i", $sender_id);
    $stmt_user->execute();
    $stmt_user->bind_result($surname, $firstname, $middlename, $barangay);
    $stmt_user->fetch();
    $stmt_user->close();

    // Construct the full name
    $sender_fullname = trim($surname . ' ' . $firstname . ' ' . $middlename);

    if (empty($sender_fullname) || empty($barangay)) {
        throw new Exception("Could not retrieve necessary user details.");
    }

    // --- 3. Insert the message into chat_messages ---
    // The INSERT query now includes sender_id, receiver_id, and uses the default value for admin_name.
    $sql_insert = "INSERT INTO chat_messages (sender_id, receiver_id, sender_fullname, barangay, messages_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    
    if ($stmt_insert === false) {
        $mysql_error = $conn->error;
        error_log("Message insertion query prepare failed: " . $mysql_error);
        throw new Exception("SQL Error: Failed to prepare message insertion query. Check table columns. MySQL Error: " . $mysql_error);
    }

    // Bind parameters: integer (sender_id), integer (receiver_id), string (fullname), string (barangay), string (content) ('iissi')
    // We are binding 5 variables: sender_id (int), receiver_id (int), sender_fullname (string), barangay (string), message_content (string) -> 'iisss'
    $stmt_insert->bind_param("iisss", $sender_id, $receiver_id, $sender_fullname, $barangay, $message_content);

    if ($stmt_insert->execute()) {
        echo json_encode([
            'success' => true,
            'message_id' => $conn->insert_id,
            'sent_at' => date('h:i A'), 
            'is_user' => true,
            'content' => htmlspecialchars($message_content) 
        ]);
    } else {
        error_log("Message execution failed: " . $stmt_insert->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to insert message into the database.']);
    }
    $stmt_insert->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error during message processing: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
