<?php
// Run this script ONCE to add sample SK officials for San Isidro
require_once 'db_conn.php';

$barangay = 'San Isidro';
$officials = [
    ['Juan Dela Cruz', 'SK Chairperson'],
    ['Maria Santos', 'SK Secretary'],
    ['Pedro Reyes', 'SK Treasurer'],
    ['Ana Lopez', 'SK Members'],
    ['Jose Garcia', 'SK Official']
];

$success = 0;
foreach ($officials as $official) {
    $stmt = $conn->prepare("INSERT INTO sk_list (fullname, position, barangay) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sss', $official[0], $official[1], $barangay);
        if ($stmt->execute()) {
            $success++;
        }
        $stmt->close();
    }
}
$conn->close();
echo "Inserted $success SK officials for $barangay.";
?>