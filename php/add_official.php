<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SK Official','SK Chairperson','SK Members','SK Treasurer','SK Secretary'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

$target_dir = "uploads/profiles/";
$default_photo = "default-avatar.png";

$first_name  = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name   = trim($_POST['last_name'] ?? '');
$position    = trim($_POST['position'] ?? '');
$barangay    = trim($_POST['barangay'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone_number= trim($_POST['phone_number'] ?? '');

if (empty($first_name) || empty($last_name) || empty($position) || empty($barangay)) {
    echo json_encode(['success' => false, 'error' => 'Required fields are missing.']);
    exit();
}

$profile_photo_db_path = $default_photo;
if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $imageFileType = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
    $unique_name = $last_name . '_' . uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $unique_name;
    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if($check === false) {
        echo json_encode(['success' => false, 'error' => 'File is not a valid image.']);
        exit();
    }
    if ($_FILES["profile_photo"]["size"] > 5000000) {
        echo json_encode(['success' => false, 'error' => 'File too large (max 5MB).']);
        exit();
    }
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        echo json_encode(['success' => false, 'error' => 'Only JPG, JPEG, PNG & GIF allowed.']);
        exit();
    }
    if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
        $profile_photo_db_path = $unique_name;
    }
}

$sql = "INSERT INTO sk_list (first_name, middle_name, last_name, position, barangay, email, phone_number, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ssssssss", $first_name, $middle_name, $last_name, $position, $barangay, $email, $phone_number, $profile_photo_db_path);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'SQL preparation failed: ' . $conn->error]);
}
$conn->close();