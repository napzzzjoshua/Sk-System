<?php
// upload_format.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$targetDir = '../uploads/format/';
$targetFile = $targetDir . 'APPROVAL OF TRANSACTION.docx';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['approval_doc'])) {
    if ($_FILES['approval_doc']['type'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        if (move_uploaded_file($_FILES['approval_doc']['tmp_name'], $targetFile)) {
            echo 'success';
        } else {
            http_response_code(500);
            echo 'Failed to upload file.';
        }
    } else {
        http_response_code(400);
        echo 'Invalid file type.';
    }
} else {
    http_response_code(400);
    echo 'No file uploaded.';
}
