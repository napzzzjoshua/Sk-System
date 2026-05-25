<?php
/**
 * generate_pdf.php
 * Server-side HTML → PDF converter using Dompdf.
 * Updated: Prepends official SK letterhead header per document category.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Try multiple possible paths for vendor/autoload.php
$possible_paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$dompdf_autoload = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $dompdf_autoload = $path;
        break;
    }
}

if (!$dompdf_autoload) {
    echo json_encode([
        'success' => false,
        'error'   => 'PDF library not installed. Please run: composer require dompdf/dompdf'
    ]);
    exit;
}

require_once $dompdf_autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$html_content = isset($_POST['html_content']) ? $_POST['html_content'] : '';
$title        = isset($_POST['title'])        ? trim($_POST['title'])   : 'document';
$document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';

if (empty(trim(strip_tags($html_content)))) {
    echo json_encode(['success' => false, 'error' => 'Empty document content.']);
    exit;
}

// =====================================================================
// HEADER IMAGE LOGIC
// Build base64-encoded image strings from document_content_img folder.
// Paths are relative to this file's directory (php/).
// =====================================================================
$img_dir = __DIR__ . '/../document_content_img/';

function imageToBase64($path) {
    if (!file_exists($path)) return null;
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

$majayjay_logo_b64  = imageToBase64($img_dir . 'majayjay_logo.jpg');
$sk_sanisidro_b64   = imageToBase64($img_dir . 'sk_sanisidro.jpg');

// Fallback: empty image src if file not found
$left_logo  = $majayjay_logo_b64  ?: '';
$right_logo = $sk_sanisidro_b64   ?: '';

// =====================================================================
// LETTERHEAD BUILDER — generates the header HTML per document category
// All 6 document types match the exact formats in document_submissions_sec.php
// =====================================================================
function buildLetterhead($document_type, $left_logo, $right_logo) {

    // Shared logo row (used by most categories)
    $logo_row = '
    <table style="width:100%;border:none;border-collapse:collapse;margin-bottom:0;">
      <tr>
        <td style="border:none;text-align:left;width:80px;vertical-align:middle;">
          ' . ($left_logo  ? '<img src="' . $left_logo  . '" style="height:70px;width:auto;">' : '') . '
        </td>
        <td style="border:none;text-align:center;vertical-align:middle;">
          __CENTER_TEXT__
        </td>
        <td style="border:none;text-align:right;width:80px;vertical-align:middle;">
          ' . ($right_logo ? '<img src="' . $right_logo . '" style="height:70px;width:auto;">' : '') . '
        </td>
      </tr>
    </table>
    <hr style="border:none;border-top:2px solid #000;margin:4px 0 8px 0;">
    ';

    switch ($document_type) {

        // ----------------------------------------------------------
        // MINUTES OF MEETING
        // ----------------------------------------------------------
        case 'Minutes of Meeting':
            $center = '
              <p style="margin:2px 0 0 0;font-weight:bold;font-size:12pt;">TANGGAPAN NG SANGGUNIANG KABATAAN</p>
              <p style="margin:1px 0 0 0;font-size:9pt;">Municipality of Majayjay, Laguna</p>
            ';
            return str_replace('__CENTER_TEXT__', $center, $logo_row);

        // ----------------------------------------------------------
        // SK RESOLUTION
        // ----------------------------------------------------------
        case 'SK Resolution':
            $center = '
              <p style="margin:2px 0 0 0;font-weight:bold;font-size:12pt;">SANGGUNIANG KABATAAN</p>
              <p style="margin:1px 0 0 0;font-size:9pt;">Municipality of Majayjay, Laguna</p>
            ';
            return str_replace('__CENTER_TEXT__', $center, $logo_row);

        // ----------------------------------------------------------
        // DISBURSEMENT FILE
        // ----------------------------------------------------------
        case 'Disbursement File':
            return ''; // use original document header

        // ----------------------------------------------------------
        // ATTENDANCE — use original document header (no logos)
        // ----------------------------------------------------------
        case 'Attendance':
            return ''; // use original document header

        // ----------------------------------------------------------
        // REPORT
        // ----------------------------------------------------------
        case 'Report':
            $center = '
              <p style="margin:2px 0 0 0;font-weight:bold;font-size:11pt;">OFFICE OF THE SANGGUNIANG KABATAAN</p>
              <p style="margin:1px 0 0 0;font-size:9pt;">Municipality of Majayjay, Laguna</p>
            ';
            return str_replace('__CENTER_TEXT__', $center, $logo_row);

        // ----------------------------------------------------------
        // TRANSMITTAL — use original document header (no logos)
        // ----------------------------------------------------------
        case 'Transmittal':
            return ''; // use original document header

        // ----------------------------------------------------------
        // DEFAULT / OTHER — use original document header (no logos)
        // ----------------------------------------------------------
        default:
            return ''; // use original document header
    }
}

// Build the letterhead for this submission's category
$letterhead_html = buildLetterhead($document_type, $left_logo, $right_logo);

// =====================================================================
// STRIP the old plain-text header lines from document_content
// (the templates in document_submissions_sec.php include a text-only
//  header div at the top of each template — we remove it so the image
//  letterhead replaces it cleanly in the PDF)
// =====================================================================

// Only strip the original text header when the image letterhead is being used.
// For categories that return '' from buildLetterhead(), keep the document's
// own header intact so it renders as-is in the PDF.
if (!empty($letterhead_html)) {
    // Remove the leading <div style="text-align:center ..."> header block
    // that each template starts with (replaced by the image letterhead).
    $clean_content = preg_replace(
        '/^\s*<div[^>]*text-align\s*:\s*center[^>]*>.*?<\/div>\s*/is',
        '',
        $html_content,
        1   // limit: only first match
    );
} else {
    // No logo letterhead — preserve the document's original header as-is.
    $clean_content = $html_content;
}

// =====================================================================
// ASSEMBLE FULL HTML FOR DOMPDF
// =====================================================================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);   // base64 images need no remote
$options->set('defaultFont', 'serif');

$dompdf = new Dompdf($options);

$full_html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 14mm 22mm 18mm 22mm; }

  * {
    box-sizing: border-box;
    font-family: "Times New Roman", Times, serif !important;
  }

  body {
    font-family: "Times New Roman", Times, serif !important;
    font-size: 12pt;
    line-height: 1.75;
    color: #000;
    margin: 0;
    padding: 0;
  }

  p, div, span, td, th, li, h1, h2, h3, h4, h5, h6 {
    font-family: "Times New Roman", Times, serif !important;
  }

  /* Default: no borders (layout/alignment tables use border:none) */
  table {
    border-collapse: collapse;
    width: 100%;
    margin: 4px 0;
  }

  td, th {
    padding: 3px 6px;
    vertical-align: top;
    border: none;
  }

  /* Only show borders when inline style explicitly says so */
  td[style*="border:1px solid"],
  th[style*="border:1px solid"],
  td[style*="border: 1px solid"],
  th[style*="border: 1px solid"] {
    border: 1px solid #333 !important;
  }

  p { margin: 0 0 4px 0; }

  strong, b { font-weight: bold; }
  em, i     { font-style: italic; }
  u         { text-decoration: underline; }

  /* Fix name-position tables: use 70% width centered so name & position are close */
  table[style*="width:100%;border:none"] {
    width: 70% !important;
    margin-left: auto !important;
    margin-right: auto !important;
  }

  /* Preserve all inline alignment/weight styles */
  [style*="text-align:center"],
  [style*="text-align: center"] { text-align: center !important; }
  [style*="text-align:right"],
  [style*="text-align: right"]  { text-align: right !important; }
  [style*="text-align:justify"],
  [style*="text-align: justify"]{ text-align: justify !important; }
  [style*="font-weight:bold"],
  [style*="font-weight: bold"]  { font-weight: bold !important; }

  /* Signature underline divs */
  div[style*="border-bottom"] {
    display: block;
    border-bottom: 1px solid #000 !important;
  }
  span[style*="border-bottom"] {
    display: inline-block;
    border-bottom: 1px solid #000 !important;
  }

  /* Letterhead container */
  .sk-letterhead {
    width: 100%;
    margin-bottom: 10px;
  }
  .sk-letterhead img {
    display: block;
  }
</style>
</head>
<body>

<!-- ===== OFFICIAL SK LETTERHEAD HEADER (logo categories only) ===== -->
' . (!empty($letterhead_html) ? '<div class="sk-letterhead">' . $letterhead_html . '</div>' : '') . '
<!-- ===== DOCUMENT CONTENT ===== -->
' . $clean_content . '

</body>
</html>';

$dompdf->loadHtml($full_html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdf_output = $dompdf->output();

// Try both possible upload directory locations
$possible_upload_dirs = [
    __DIR__ . '/uploads/document_submissions/',
    __DIR__ . '/../uploads/document_submissions/',
];

$upload_dir = null;
foreach ($possible_upload_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (is_dir($dir)) {
        $upload_dir = $dir;
        break;
    }
}

if (!$upload_dir) {
    echo json_encode(['success' => false, 'error' => 'Could not create uploads directory.']);
    exit;
}

$filename = uniqid('doc_', true) . '.pdf';
$filepath = $upload_dir . $filename;
$relative = 'uploads/document_submissions/' . $filename;

if (file_put_contents($filepath, $pdf_output) !== false) {
    echo json_encode(['success' => true, 'pdf_path' => $relative]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not write PDF file to disk.']);
}