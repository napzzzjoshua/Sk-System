<?php
/**
 * get_template.php
 * AJAX endpoint — reads the chosen .docx template and returns its text paragraphs
 * as a JSON array so the front-end can render an inline editor.
 * Also handles saving an edited docx back to disk.
 *
 * GET  ?category=Minutes+of+Meeting  → returns {paragraphs:[...], tables:[...]}
 * POST (JSON body with paragraphs/tables + category)  → saves edited .docx, returns {file: 'path/to/saved.docx'}
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// Category → template filename map  (same filenames as the format folder)
// ---------------------------------------------------------------------------
$templateMap = [
    'Minutes of Meeting' => 'MINUTES_FORMAT.docx',
    'SK Resolution'      => 'RESOLUTION_FORMAT.docx',
    'Disbursement File'  => 'DV_FORMAT.docx',
    'Attendance'         => 'ATTENDANCE-FOR-ALL.docx',
    'Report'             => 'REPORT.docx',
    'Transmittal'        => 'TRANSMITTAL-JULY2022.docx',
];

$formatDir = __DIR__ . '/uploads/format/';
$editedDir = __DIR__ . '/uploads/edited_templates/';
if (!is_dir($editedDir)) { mkdir($editedDir, 0777, true); }

// ===========================================================================
// GET  — parse docx and return editable structure
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    if (!array_key_exists($category, $templateMap)) {
        echo json_encode(['error' => 'Unknown category']);
        exit;
    }

    $docxPath = $formatDir . $templateMap[$category];
    if (!file_exists($docxPath)) {
        echo json_encode(['error' => 'Template file not found: ' . $templateMap[$category]]);
        exit;
    }

    $result = parseDocx($docxPath);
    echo json_encode($result);
    exit;
}

// ===========================================================================
// POST — rebuild docx with user edits and save a temp file
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $category   = isset($body['category'])   ? trim($body['category'])   : '';
    $paragraphs = isset($body['paragraphs']) ? $body['paragraphs']       : [];
    $tables     = isset($body['tables'])     ? $body['tables']           : [];

    if (!array_key_exists($category, $templateMap)) {
        echo json_encode(['error' => 'Unknown category']);
        exit;
    }

    $docxPath = $formatDir . $templateMap[$category];
    if (!file_exists($docxPath)) {
        echo json_encode(['error' => 'Template file not found']);
        exit;
    }

    $savedPath = rebuildDocx($docxPath, $paragraphs, $tables, $editedDir);
    if ($savedPath === false) {
        echo json_encode(['error' => 'Failed to rebuild docx']);
        exit;
    }

    // Return a web-accessible path relative to the php root
    $webPath = str_replace(__DIR__ . '/', '', $savedPath);
    echo json_encode(['file' => $webPath, 'filename' => basename($savedPath)]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;

// ===========================================================================
// HELPERS
// ===========================================================================

/**
 * Parse a .docx file and return its body paragraphs + table cells as arrays.
 * Each paragraph: { id, text, bold, italic, underline, align, isHeader, isBlank }
 * Each table:     { id, rows: [ [{ text, bold, colspan },...] ] }
 */
function parseDocx(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['error' => 'Cannot open docx'];
    }

    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xmlContent === false) {
        return ['error' => 'Cannot read document.xml'];
    }

    // Suppress namespace warnings
    $xml = simplexml_load_string(
        str_replace(
            ['xmlns:w=', 'w:'],
            ['xmlns:w_ns=', 'w_'],
            $xmlContent
        )
    );

    // Parse via DOM instead — more reliable
    $dom = new DOMDocument();
    $dom->loadXML($xmlContent);

    $W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $body = $dom->getElementsByTagNameNS($W, 'body')->item(0);

    if (!$body) {
        return ['paragraphs' => [], 'tables' => []];
    }

    $paragraphs = [];
    $tables     = [];
    $pIdx = 0;
    $tIdx = 0;

    foreach ($body->childNodes as $child) {
        $localName = $child->localName;

        if ($localName === 'p') {
            $info = extractParagraphInfo($child, $W);
            $info['id'] = 'p' . $pIdx++;
            $paragraphs[] = $info;

        } elseif ($localName === 'tbl') {
            $tableData = ['id' => 't' . $tIdx++, 'rows' => []];
            $rows = $child->getElementsByTagNameNS($W, 'tr');
            foreach ($rows as $row) {
                $rowData = [];
                $cells = $row->getElementsByTagNameNS($W, 'tc');
                foreach ($cells as $cell) {
                    $cellText = '';
                    foreach ($cell->getElementsByTagNameNS($W, 'p') as $cp) {
                        $line = extractParagraphInfo($cp, $W);
                        if ($cellText !== '' && $line['text'] !== '') {
                            $cellText .= "\n";
                        }
                        $cellText .= $line['text'];
                    }
                    $rowData[] = ['text' => $cellText];
                }
                $tableData['rows'][] = $rowData;
            }
            $tables[] = $tableData;
        }
    }

    return ['paragraphs' => $paragraphs, 'tables' => $tables];
}

function extractParagraphInfo(DOMNode $p, string $W): array {
    $text   = '';
    $bold   = false;
    $italic = false;
    $under  = false;
    $align  = 'left';
    $style  = '';
    $isBlank = true;

    // Style / heading detection
    foreach ($p->childNodes as $child) {
        if ($child->localName === 'pPr') {
            foreach ($child->childNodes as $pPrChild) {
                if ($pPrChild->localName === 'pStyle') {
                    $style = $pPrChild->getAttributeNS($W, 'val');
                }
                if ($pPrChild->localName === 'jc') {
                    $align = $pPrChild->getAttributeNS($W, 'val') ?: 'left';
                }
            }
        }
    }

    // Text runs
    foreach ($p->getElementsByTagNameNS($W, 'r') as $run) {
        $rPr = null;
        foreach ($run->childNodes as $rc) {
            if ($rc->localName === 'rPr') { $rPr = $rc; break; }
        }
        if ($rPr) {
            foreach ($rPr->childNodes as $rPrChild) {
                if ($rPrChild->localName === 'b')  { $bold   = true; }
                if ($rPrChild->localName === 'i')  { $italic = true; }
                if ($rPrChild->localName === 'u')  { $under  = true; }
            }
        }
        foreach ($run->getElementsByTagNameNS($W, 't') as $t) {
            $text .= $t->nodeValue;
        }
    }

    if (trim($text) !== '') { $isBlank = false; }

    $isHeader = in_array(strtolower($style), ['heading1', 'heading 1', 'heading2', 'heading 2', 'title']);

    return [
        'text'     => $text,
        'bold'     => $bold,
        'italic'   => $italic,
        'underline'=> $under,
        'align'    => $align,
        'style'    => $style,
        'isHeader' => $isHeader,
        'isBlank'  => $isBlank,
    ];
}

/**
 * Rebuild the docx by replacing paragraph text nodes in document.xml
 * with the user-edited versions, then save as a new file.
 */
function rebuildDocx(string $sourcePath, array $paragraphs, array $tables, string $destDir): string|false {
    // Copy the original docx to a temp location
    $tempFile = $destDir . 'edited_' . uniqid('', true) . '.docx';
    if (!copy($sourcePath, $tempFile)) { return false; }

    $zip = new ZipArchive();
    if ($zip->open($tempFile) !== true) { return false; }

    $xmlContent = $zip->getFromName('word/document.xml');
    if ($xmlContent === false) { $zip->close(); return false; }

    $dom = new DOMDocument();
    $dom->loadXML($xmlContent);

    $W    = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $body = $dom->getElementsByTagNameNS($W, 'body')->item(0);

    if (!$body) { $zip->close(); return false; }

    // Index incoming edits by id
    $pEdits = [];
    foreach ($paragraphs as $pe) {
        if (isset($pe['id'])) { $pEdits[$pe['id']] = $pe['text']; }
    }

    $tEdits = [];
    foreach ($tables as $te) {
        if (isset($te['id'])) { $tEdits[$te['id']] = $te['rows']; }
    }

    $pIdx = 0;
    $tIdx = 0;

    foreach ($body->childNodes as $child) {
        $localName = $child->localName;

        if ($localName === 'p') {
            $id = 'p' . $pIdx++;
            if (array_key_exists($id, $pEdits)) {
                replaceParaText($child, $W, $dom, $pEdits[$id]);
            }

        } elseif ($localName === 'tbl') {
            $id = 't' . $tIdx++;
            if (array_key_exists($id, $tEdits)) {
                $rowEdits = $tEdits[$id];
                $rows = $child->getElementsByTagNameNS($W, 'tr');
                $rIdx = 0;
                foreach ($rows as $row) {
                    if (!isset($rowEdits[$rIdx])) { $rIdx++; continue; }
                    $cells = $row->getElementsByTagNameNS($W, 'tc');
                    $cIdx  = 0;
                    foreach ($cells as $cell) {
                        if (!isset($rowEdits[$rIdx][$cIdx])) { $cIdx++; continue; }
                        $newText = $rowEdits[$rIdx][$cIdx]['text'];
                        // Replace all paragraphs in cell with one paragraph having new text
                        $cellParas = $cell->getElementsByTagNameNS($W, 'p');
                        $first = true;
                        foreach ($cellParas as $cp) {
                            if ($first) {
                                replaceParaText($cp, $W, $dom, $newText);
                                $first = false;
                            }
                            // extra paragraphs in same cell — leave them (avoid modifying NodeList while iterating)
                        }
                        $cIdx++;
                    }
                    $rIdx++;
                }
            }
        }
    }

    $newXml = $dom->saveXML();
    $zip->addFromString('word/document.xml', $newXml);
    $zip->close();

    return $tempFile;
}

/**
 * Replace the text content of a paragraph's runs with a single run containing $newText.
 * Preserves the first run's rPr (formatting).
 */
function replaceParaText(DOMNode $para, string $W, DOMDocument $dom, string $newText): void {
    // Grab first run's rPr if it exists
    $firstRPr = null;
    foreach ($para->childNodes as $child) {
        if ($child->localName === 'r') {
            foreach ($child->childNodes as $rc) {
                if ($rc->localName === 'rPr') {
                    $firstRPr = $rc->cloneNode(true);
                    break;
                }
            }
            break;
        }
    }

    // Remove all existing runs
    $runs = $para->getElementsByTagNameNS($W, 'r');
    $toRemove = [];
    foreach ($runs as $r) { $toRemove[] = $r; }
    foreach ($toRemove as $r) { if ($r->parentNode) { $r->parentNode->removeChild($r); } }

    // Create new run
    $newRun = $dom->createElementNS($W, 'w:r');
    if ($firstRPr) { $newRun->appendChild($firstRPr); }

    $newT = $dom->createElementNS($W, 'w:t');
    $newT->nodeValue = $newText;
    if (strlen($newText) > 0 && ($newText[0] === ' ' || $newText[strlen($newText)-1] === ' ')) {
        $newT->setAttribute('xml:space', 'preserve');
    }
    $newRun->appendChild($newT);

    $para->appendChild($newRun);
}