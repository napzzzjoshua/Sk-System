<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Barangay Officials') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=sk_submissions_report.csv");

$output = fopen("php://output", "w");
fputcsv($output, ['ID', 'Title', 'Description', 'Status', 'Created At']);

$result = $conn->query("SELECT * FROM submissions ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [$row['id'], $row['title'], $row['description'], $row['status'], $row['created_at']]);
}

fclose($output);
exit;
?>
