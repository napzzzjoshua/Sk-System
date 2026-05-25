<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Include the database connection file
require_once 'db_conn.php';


// Check if a user ID is provided in the request
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'User ID not provided.']);
    exit;
}

$userId = $_GET['id'];
$source = isset($_GET['source']) ? $_GET['source'] : 'users';

if ($source === 'sec_users') {
    // For sec_users table (no email, mobile, profile_photo, id_document, gender, dob, civil_status, role)
    $stmt = $conn->prepare("SELECT firstname, middlename, surname, position, barangay, status FROM sec_users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user['fullname'] = $user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['surname'];
        // Fill missing fields with 'N/A' or empty string for modal compatibility
        $user['email'] = 'N/A';
        $user['mobile'] = 'N/A';
        $user['profile_photo'] = '';
        $user['id_document'] = '';
        $user['gender'] = 'N/A';
        $user['dob'] = 'N/A';
        $user['civil_status'] = 'N/A';
        $user['role'] = 'N/A';
        // position is present in sec_users
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found.']);
    }
    $stmt->close();
} else {
    // Default: users table
    $stmt = $conn->prepare("SELECT firstname, middlename, surname, email, mobile, profile_photo, id_document, gender, dob, civil_status, role, position, barangay, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user['fullname'] = $user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['surname'];
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found.']);
    }
    $stmt->close();
}

$conn->close();

?>