<?php
// Include your database connection file
require_once 'db_conn.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve personal information
    $id = $_POST['id'] ?? null;
    $surname = $_POST['surname'] ?? null;
    $firstname = $_POST['firstname'] ?? null;
    $middlename = $_POST['middlename'] ?? null;
    $barangay = $_POST['barangay'] ?? null;
    $educational_level = $_POST['educational_level'] ?? null;

    if (!$id) {
        // If no ID is provided, redirect with an error
        header("Location: scholarship_list.php?error=1");
        exit;
    }

    // Prepare the update statement for personal information
    $update_sql = "UPDATE scholarship_applications SET surname = ?, firstname = ?, middlename = ?, barangay = ?, educational_level = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssi", $surname, $firstname, $middlename, $barangay, $educational_level, $id);
    $stmt->execute();
    $stmt->close();

    // Now, handle the file uploads
    $upload_dir = 'uploads/';
    $file_fields = ['student_id', 'cor', 'grades', 'voters_id', 'psa'];

    // Get the current file paths from the database to handle replacements
    $select_sql = "SELECT student_id, cor, grades, voters_id, psa FROM scholarship_applications WHERE id = ?";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("i", $id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    $current_files = $result->fetch_assoc();
    $select_stmt->close();

    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
            $file_tmp_path = $_FILES[$field]['tmp_name'];
            $file_name = $_FILES[$field]['name'];
            $file_size = $_FILES[$field]['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Generate a unique filename to avoid conflicts
            $new_file_name = $field . '_' . time() . '_' . uniqid() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;

            // Check if the old file exists and delete it
            if (!empty($current_files[$field])) {
                $old_file_path = $current_files[$field];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }

            // Move the new file to the destination folder
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                // Update the database with the new file path
                $file_update_sql = "UPDATE scholarship_applications SET {$field} = ? WHERE id = ?";
                $file_update_stmt = $conn->prepare($file_update_sql);
                $file_update_stmt->bind_param("si", $dest_path, $id);
                $file_update_stmt->execute();
                $file_update_stmt->close();
            }
        }
    }

    // Redirect back to the list page with a success message
    header("Location: scholarship_list.php?update=1");
    exit;
} else {
    // If not a POST request, redirect back
    header("Location: scholarship_list.php");
    exit;
}

$conn->close();
?>
