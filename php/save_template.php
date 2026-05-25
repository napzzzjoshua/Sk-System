<?php
// save_template.php
// Receives HTML from edit_template.php, converts to DOCX, and offers download

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['html_content'])) {
    $html = $_POST['html_content'];
    $template_name = $_POST['template'] ?? 'Edited_Template';

    require_once __DIR__ . '/../vendor/autoload.php'; // PHPWord
    use PhpOffice\PhpWord\PhpWord;
    use PhpOffice\PhpWord\IOFactory;
    use PhpOffice\PhpWord\Shared\Html as PhpWordHtml;

    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    PhpWordHtml::addHtml($section, $html, false, false);

    $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($template_name, PATHINFO_FILENAME)) . '_edited.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
} else {
    echo '<p style="color:red">No content to save.</p>';
}
?>