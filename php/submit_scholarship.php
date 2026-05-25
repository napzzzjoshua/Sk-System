<?php
// Include your database connection file
require_once 'db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // File upload directory
    $uploadDir = "uploads/";

    // Create the upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Function to handle file uploads
    function uploadFile($fileInputName, $uploadDir) {
        $filePath = null;
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
            $fileName = basename($_FILES[$fileInputName]['name']);
            $targetFilePath = $uploadDir . uniqid() . '_' . $fileName;

            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFilePath)) {
                $filePath = $targetFilePath;
            } else {
                echo "Error uploading file: " . $_FILES[$fileInputName]['error'];
            }
        }
        return $filePath;
    }

    // Upload files and get their paths
    $studentIdPath = uploadFile('student_id', $uploadDir);
    $corPath = uploadFile('cor', $uploadDir);
    $gradesPath = uploadFile('grades', $uploadDir);
    $votersIdPath = uploadFile('voters_id', $uploadDir);
    $psaPath = uploadFile('psa', $uploadDir);

    // Get personal information from POST data
    $surname = $_POST['surname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $barangay = $_POST['barangay'];
    $educational_level = $_POST['educational_level'];

    // Prepare and bind the SQL statement
    $stmt = $conn->prepare("INSERT INTO scholarship_applications (surname, firstname, middlename, barangay, educational_level, student_id, cor, grades, voters_id, psa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $surname, $firstname, $middlename, $barangay, $educational_level, $studentIdPath, $corPath, $gradesPath, $votersIdPath, $psaPath);

    // Execute the statement
    if ($stmt->execute()) {
        header("Location: scholarship_list.php?success=1");
    } else {
        header("Location: scholarship_list.php?error=1");
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();

} else {
    // If not a POST request, redirect back
    header("Location: scholarship_list.php");
}
?>
