<?php
session_start();

// 1. Authorization Check
// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

// 2. Database Connection and Input Validation
// Make sure 'db_conn.php' securely establishes and provides a MySQLi connection in $conn
require_once 'db_conn.php'; 

// Check for required POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['document_id']) || empty($_POST['document_id'])) {
    // Redirect if not a POST request or missing ID
    header("Location: documents.php?status=error&message=InvalidRequest");
    exit;
}

// Sanitize and validate the document ID
$document_id = filter_var($_POST['document_id'], FILTER_VALIDATE_INT);

if ($document_id === false) {
    // Redirect if ID is not a valid integer
    header("Location: documents.php?status=error&message=InvalidDocumentID");
    exit;
}

// Ensure the database connection is available
if (!isset($conn)) {
    error_log("Database connection not established in archive_document.php");
    header("Location: documents.php?status=error&message=DatabaseError");
    exit;
}

// 3. Start Database Transaction
// This ensures that either both INSERT (archive) and DELETE (submissions) succeed, or neither happens.
$conn->begin_transaction();

try {
    // A. SELECT the document data from document_submissions
    // Select the necessary fields
    $select_query = "SELECT title, document_category, barangay, submitted_at, file_path FROM document_submissions WHERE id = ?";
    $select_stmt = $conn->prepare($select_query);
    
    if (!$select_stmt) {
        throw new Exception("Prepare select failed: " . $conn->error);
    }
    
    $select_stmt->bind_param("i", $document_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Document not found in submissions. ID: " . $document_id);
    }

    $doc = $result->fetch_assoc();
    $select_stmt->close();

    // *** DATA PREPARATION AND TRUNCATION (Crucial for preventing data length errors) ***
    // Schema limits provided: document_id (INT), title (255), document_category (100), 
    //                         barangay (100), submitted_at (DATETIME), file_path (500), is_archived (TINYINT)
    
    $doc_title = substr($doc['title'] ?? '[No Title Provided]', 0, 255); 
    $doc_category = substr($doc['document_category'] ?? '[Unknown Category]', 0, 100);
    $doc_barangay = substr($doc['barangay'] ?? '[Unknown Barangay]', 0, 100);
    $doc_file_path = substr($doc['file_path'] ?? '[No File Path]', 0, 500); 

    // Ensure submitted_at is formatted for the DATETIME column
    $formatted_submitted_at = date('Y-m-d H:i:s', strtotime($doc['submitted_at'] ?? 'now'));
    $is_archived = 1; // TINYINT expected


    // B. INSERT the data into document_archive
    $insert_query = "
        INSERT INTO document_archive 
        (document_id, title, document_category, barangay, submitted_at, file_path, is_archived) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    "; // *** 7 PLACEHOLDERS (?), one for each column ***
    $insert_stmt = $conn->prepare($insert_query);
    
    if (!$insert_stmt) {
        throw new Exception("Prepare insert failed: " . $conn->error);
    }

    // *** CORRECTED BINDING: 7 BINDINGS (isssssi) for the 7 placeholders ***
    if (!$insert_stmt->bind_param(
        "isssssi", // i (document_id), s (title), s (category), s (barangay), s (submitted_at), s (file_path), i (is_archived)
        $document_id,
        $doc_title,
        $doc_category,
        $doc_barangay,
        $formatted_submitted_at, 
        $doc_file_path,
        $is_archived
    )) {
        throw new Exception("Insert bind_param failed: " . $insert_stmt->error);
    }

    if (!$insert_stmt->execute()) {
        // If the execution fails, log the specific error
        throw new Exception("Insert into archive failed: " . $insert_stmt->error);
    }
    $insert_stmt->close();

    // C. DELETE the record from document_submissions
    $delete_stmt = $conn->prepare("DELETE FROM document_submissions WHERE id = ?");
    if (!$delete_stmt) {
        throw new Exception("Prepare delete failed: " . $conn->error);
    }
    $delete_stmt->bind_param("i", $document_id);

    if (!$delete_stmt->execute()) {
        throw new Exception("Delete from submissions failed: " . $delete_stmt->error);
    }
    $delete_stmt->close();

    // 4. Commit Transaction
    $conn->commit();

} catch (Exception $e) {
    // 5. Rollback on Error
    $conn->rollback();
    error_log("Document archiving failed: " . $e->getMessage());
    // Redirect with the actual database error message for debugging purposes
    $message = urlencode("Failed to archive document. Error: " . $e->getMessage());
    header("Location: documents.php?status=error&message=" . $message);
    exit;
}

// 6. Redirect on Success
$message = urlencode("Document successfully archived.");
header("Location: documents.php?status=success&message=" . $message);
exit;
?>
