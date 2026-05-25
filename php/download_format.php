<?php
// download_format.php
$filepath = '../uploads/format/APPROVAL OF TRANSACTION.docx';
if (file_exists($filepath)) {
    // Clean output buffer to prevent whitespace before headers
    if (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="APPROVAL OF TRANSACTION.docx"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    flush();
    readfile($filepath);
    exit;
} else {
    header('HTTP/1.1 404 Not Found');
    echo "File not found.";
}
