<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $type = $_POST['type']; // 'Financial Aid' or 'Project Proposal'
    $status = $_POST['status']; // 'Approved' or 'Rejected'

    // Determine target table
    $table = ($type === 'Financial Aid') ? 'financial_aid_requests' : 'submissions';

    // ── HANDLE SK FORMAT APPROVAL (HTML → saved as .pdf file, stored in DB) ──
    if ($status === 'Approved' && isset($_POST['use_sk_format']) && $_POST['use_sk_format'] === '1') {

        $pdfHtml = isset($_POST['pdf_html']) ? $_POST['pdf_html'] : '';

        // __DIR__ = absolute path to public_html where this PHP file lives
        if ($type === 'Financial Aid') {
            $relDir = 'uploads/aid_docs/aid_' . $id . '/';
        } else {
            $relDir = 'uploads/admin_docs/';
        }
        $uploadDir = __DIR__ . '/' . $relDir; // absolute physical path

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                echo json_encode(['success' => false, 'message' => 'Could not create directory: ' . $uploadDir]);
                exit;
            }
        }

        // Save as PDF file using mPDF
        $fileName     = 'admin_doc_' . $id . '_' . time() . '.pdf';
        $fullPath     = $uploadDir . $fileName;  // absolute path to write
        $relativePath = $relDir . $fileName;      // relative path stored in DB

        // Load mPDF via Composer autoload
        // Make sure mPDF is installed: composer require mpdf/mpdf
        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            echo json_encode(['success' => false, 'message' => 'mPDF not found. Run: composer require mpdf/mpdf']);
            exit;
        }
        require_once $autoloadPath;

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_top'    => 20,
                'margin_bottom' => 20,
                'margin_left'   => 25,
                'margin_right'  => 25,
            ]);

            $htmlContent = '
<style>
  body { font-family: "Times New Roman", serif; font-size: 13px; line-height: 1.8; color: #000; }
  img  { max-width: 100%; height: auto; }
</style>
' . $pdfHtml;

            $mpdf->WriteHTML($htmlContent);
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()]);
            exit;
        }

        // Verify the PDF was actually created
        if (!file_exists($fullPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save approval document as PDF.']);
            exit;
        }

        // Update status AND admin_doc path in the correct table
        if ($type === 'Financial Aid') {
            $stmt = $conn->prepare("UPDATE financial_aid_requests SET status = ?, admin_doc = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE submissions SET status = ?, admin_doc = ? WHERE id = ?");
        }

        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("ssi", $status, $relativePath, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'doc' => $relativePath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB update failed: ' . $stmt->error]);
        }

        $stmt->close();
        $conn->close();
        exit;
    }
    // ── END SK FORMAT APPROVAL BLOCK ─────────────────────────────────────────

    // Handle file upload for Project Proposal or Financial Aid approval
    if ((($type === 'Project Proposal' || $type === 'Financial Aid') && $status === 'Approved') && isset($_FILES['admin_doc']) && $_FILES['admin_doc']['error'] === UPLOAD_ERR_OK) {
        // For Financial Aid, create a folder per request in aid_docs
        $uploadDir = '../uploads/admin_docs/';
        if ($type === 'Financial Aid') {
            $uploadDir = '../uploads/aid_docs/aid_' . $id . '/';
        }
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileTmp = $_FILES['admin_doc']['tmp_name'];
        $fileName = basename($_FILES['admin_doc']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['doc', 'docx'];
        if (!in_array($fileExt, $allowedExt)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
            exit;
        }
        // Unique file name
        $newFileName = 'admin_doc_' . $id . '_' . time() . '.' . $fileExt;
        $targetPath = $uploadDir . $newFileName;
        if (move_uploaded_file($fileTmp, $targetPath)) {
            // Save relative path in DB
            $relativePath = ($type === 'Financial Aid')
                ? 'uploads/aid_docs/aid_' . $id . '/' . $newFileName
                : 'uploads/admin_docs/' . $newFileName;
            if ($type === 'Project Proposal') {
                $stmt = $conn->prepare("UPDATE submissions SET status = ?, admin_doc = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $relativePath, $id);
            } else if ($type === 'Financial Aid') {
                $stmt = $conn->prepare("UPDATE financial_aid_requests SET status = ?, admin_doc = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $relativePath, $id);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
            exit;
        }
    }
    // If Project Proposal and rejected with reason, update rejection_reason too
    else if ($type === 'Project Proposal' && $status === 'Rejected' && isset($_POST['rejection_reason'])) {
        $reason = $_POST['rejection_reason'];
        $stmt = $conn->prepare("UPDATE submissions SET status = ?, rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $reason, $id);
    } else {
        $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

    $stmt->close();
    $conn->close();
}
?>