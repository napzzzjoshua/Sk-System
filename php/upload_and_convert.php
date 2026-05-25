<?php
// upload_and_convert.php
// Receives a .docx file, converts to image (first page), returns image path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['wordFile'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
    exit;
}

$uploadDir = '../uploads/converted/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$docx = $_FILES['wordFile'];
$ext = strtolower(pathinfo($docx['name'], PATHINFO_EXTENSION));
if ($ext !== 'docx') {
    echo json_encode(['success' => false, 'error' => 'Only .docx files allowed.']);
    exit;
}

$filename = uniqid('doc_', true) . '.docx';
$filepath = $uploadDir . $filename;
if (!move_uploaded_file($docx['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
    exit;
}

// Convert DOCX to PDF using LibreOffice
$pdfPath = $uploadDir . uniqid('doc_', true) . '.pdf';
$cmd = 'soffice --headless --convert-to pdf --outdir ' . escapeshellarg($uploadDir) . ' ' . escapeshellarg($filepath);
exec($cmd, $output, $ret);

$pdfFile = preg_replace('/\.docx$/', '.pdf', $filepath);
if (!file_exists($pdfFile)) {
    echo json_encode(['success' => false, 'error' => 'Conversion to PDF failed.']);
    exit;
}

// Convert first page of PDF to image using Imagick
$imagePath = $uploadDir . uniqid('img_', true) . '.jpg';
try {
    $im = new Imagick();
    $im->setResolution(200, 200);
    $im->readImage($pdfFile.'[0]'); // first page only
    $im->setImageFormat('jpg');
    $im->writeImage($imagePath);
    $im->clear();
    $im->destroy();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Image conversion failed: ' . $e->getMessage()]);
    exit;
}

// Optionally, clean up docx/pdf
// unlink($filepath);
// unlink($pdfFile);

// Return relative path for frontend
$imageUrl = str_replace('..', '', $imagePath);
echo json_encode(['success' => true, 'image' => $imageUrl]);
