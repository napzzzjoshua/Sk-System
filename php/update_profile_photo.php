<?php
session_start();
require_once 'db_conn.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

if (!empty($_FILES['profile_image']['name'])) {
    $targetDir  = "uploads/profiles/";
    $fileName   = time() . "_" . basename($_FILES['profile_image']['name']);
    $targetFile = $targetDir . $fileName;
    $ext        = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg','jpeg','png','gif'])
        && move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {

        $sql = "UPDATE users SET profile_photo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fileName, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: sk_dashboard.php");
exit;
