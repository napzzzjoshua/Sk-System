<?php
session_start();

// --- Access Control (Preserved) ---
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['SK Official','SK Chairperson','SK Members','SK Treasurer','SK Secretary'])
) {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
global $conn;

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$message_type = '';
$message_text = '';

// --- Fetch User Data (Preserved) ---
$sql  = "SELECT barangay, position, profile_photo, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($barangay, $position, $profile_photo, $user_email);
$stmt->fetch();
$stmt->close();

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";

// --- Logo Logic (Preserved) ---
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Notification Count (For Dashboard Consistency) ---
$unread_count_sql = "SELECT COUNT(*) AS unread_count FROM sk_notifications WHERE (is_read = 0 AND email = ?) OR (is_read = 0 AND barangay = ? AND position = ?) OR (is_read = 0 AND barangay = ? AND position IS NULL) OR (is_read = 0 AND email IS NULL AND barangay IS NULL AND position = ?)";
$stmt_count = $conn->prepare($unread_count_sql);
$stmt_count->bind_param("sssss", $user_email, $barangay, $position, $barangay, $position);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'] ?? 0;
$stmt_count->close();

// --- Submission Handling Logic (Updated: converts document_content to PDF) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_document'])) {

    $document_type   = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';
    $title           = trim($_POST['title']);
    $document_content = isset($_POST['document_content']) ? $_POST['document_content'] : '';
    $pdf_data        = isset($_POST['pdf_data']) ? $_POST['pdf_data'] : '';

    // Map the exact dropdown option values to their proper category labels saved in DB
    $category_map = [
        'Minutes of Meeting' => 'Minutes of Meeting',
        'SK Resolution'      => 'SK Resolution',
        'Disbursement File'  => 'Disbursement File',
        'Attendance'         => 'Attendance',
        'Report'             => 'Report',
        'Transmittal'        => 'Transmittal',
    ];
    // Use mapped value if valid; otherwise fall back to 'Other'
    $document_type = isset($category_map[$document_type]) ? $category_map[$document_type] : 'Other';

    if (empty($title) || (empty($pdf_data) && empty(trim(strip_tags($document_content))))) {
        $message_type = 'error';
        $message_text = 'Please fill out all fields and complete the document content.';
    } else {
        $upload_dir = 'uploads/document_submissions/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $save_ok = false;
        $destination = '';

        if (!empty($pdf_data) && file_exists($pdf_data) && pathinfo($pdf_data, PATHINFO_EXTENSION) === 'pdf') {
            // PDF was already generated and saved by generate_pdf.php
            $destination = $pdf_data;
            $save_ok = true;
        }

        // Fallback: save as HTML if PDF generation failed or was not sent
        if (!$save_ok) {
            $unique_filename = uniqid('doc_', true) . '.html';
            $destination = $upload_dir . $unique_filename;
            $html_content = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;padding:40px;max-width:850px;margin:auto;font-size:13px;}
table{border-collapse:collapse;width:100%;}
td,th{border:1px solid #333;padding:6px 10px;}
.doc-header{text-align:center;margin-bottom:20px;}
.sig-line{border-bottom:1px solid #000;display:inline-block;min-width:200px;margin-bottom:4px;}
</style>
</head><body>' . $document_content . '</body></html>';
            $save_ok = (file_put_contents($destination, $html_content) !== false);
        }

        if ($save_ok) {
            $sql_insert = "INSERT INTO document_submissions (user_id, barangay, document_category, title, file_path, status, submitted_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("issss", $user_id, $barangay, $document_type, $title, $destination);
                if ($stmt_insert->execute()) {
                    $document_id = $conn->insert_id; 
                    $reviewer_roles = ['SK Official', 'SK Chairperson'];
                    $placeholders = str_repeat('?,', count($reviewer_roles) - 1) . '?'; 
                    $sql_fetch_reviewers = "SELECT email, barangay, position FROM users WHERE role IN ($placeholders) AND barangay = ?";
                    $stmt_reviewers = $conn->prepare($sql_fetch_reviewers);
                    $types = str_repeat('s', count($reviewer_roles)) . 's'; 
                    $bind_params = array_merge($reviewer_roles, [$barangay]);
                    $stmt_reviewers->bind_param($types, ...$bind_params);
                    $stmt_reviewers->execute();
                    $result_reviewers = $stmt_reviewers->get_result();
                    $notification_message = "New document submitted by {$fullname} ({$position}): **{$title}**.";
                    $related_link = "document_submissions.php?id=" . $document_id; 
                    $sql_insert_notification = "INSERT INTO sk_notifications (email, barangay, position, message, related_link, is_read) VALUES (?, ?, ?, ?, ?, 0)";
                    $stmt_notif = $conn->prepare($sql_insert_notification);
                    while ($reviewer = $result_reviewers->fetch_assoc()) {
                        $stmt_notif->bind_param("sssss", $reviewer['email'], $reviewer['barangay'], $reviewer['position'], $notification_message, $related_link);
                        $stmt_notif->execute();
                    }
                    $stmt_reviewers->close();
                    $stmt_notif->close();
                    $message_type = 'success';
                    $message_text = 'Document submitted successfully! Officials have been notified.';
                } else {
                    $message_type = 'error';
                    $message_text = 'Database error: ' . $conn->error;
                    unlink($destination);
                }
                $stmt_insert->close();
            }
        } else {
            $message_type = 'error';
            $message_text = 'Error saving document content.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Documents | SK Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .main-content { flex: 1; padding: 25px 30px; background: #f1f5f9; overflow-y: auto; }
        .content-card { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); }
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        input, select { font-size: 12px !important; border-radius: 12px !important; border: 1px solid #E2E8F0 !important; }
        .btn-primary { background: #ea580c; color: white; transition: all 0.3s; cursor: pointer; }
        .btn-primary:hover { background: #c2410c; transform: translateY(-1px); }
        .step-badge { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; margin-right: 8px; }
        #notificationModal { position: fixed; top: 20px; right: 20px; z-index: 1000; display: none; transform: translateX(120%); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

        /* Document Editor Styles */
        #doc-editor-wrapper { display: none; }
        #doc-editor-wrapper.active { display: block; }
        .editor-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px 12px 0 0; border-bottom: none; align-items: center; }
        .editor-toolbar button { padding: 4px 9px; border-radius: 7px; border: 1px solid #e2e8f0; background: white; font-size: 11px; font-weight: 600; color: #1B1B4B; cursor: pointer; transition: all 0.15s; }
        .editor-toolbar button:hover { background: #FFD700; border-color: #FFD700; color: #1B1B4B; }
        .editor-toolbar select { padding: 4px 8px; border-radius: 7px; font-size: 11px; font-weight: 600; color: #1B1B4B; border: 1px solid #e2e8f0 !important; background: white; cursor: pointer; }
        .toolbar-spacer { margin-left: auto; }
        #doc-editor {
            min-height: 420px;
            max-height: 520px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 12px 12px;
            padding: 24px 32px;
            background: white;
            font-family: 'Times New Roman', serif;
            font-size: 13px;
            line-height: 1.7;
            outline: none;
            color: #1a1a1a;
            transition: all 0.3s ease;
        }
        #doc-editor:focus { border-color: #FFD700; box-shadow: 0 0 0 2px rgba(255,215,0,0.15); }
        #doc-editor table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        #doc-editor td, #doc-editor th { border: 1px solid #555; padding: 5px 8px; min-width: 40px; }
        #doc-editor .doc-center { text-align: center; }
        #doc-editor .doc-bold { font-weight: bold; }
        #doc-editor .sig-block { margin-top: 8px; }
        #doc-editor .sig-line { display: inline-block; border-bottom: 1px solid #000; min-width: 220px; }
        #doc-editor [contenteditable] { outline: none; }
        .editor-placeholder { color: #94a3b8; font-style: italic; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; }

        /* Fullscreen overlay */
        #editor-fullscreen-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 15, 40, 0.55);
            backdrop-filter: blur(4px);
            animation: fsIn 0.2s ease;
        }
        #editor-fullscreen-overlay.active { display: flex; flex-direction: column; }
        @keyframes fsIn { from { opacity: 0; } to { opacity: 1; } }

        #fs-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 10px 16px;
            background: #1B1B4B;
            align-items: center;
            border-bottom: 2px solid #FFD700;
            flex-shrink: 0;
        }
        #fs-toolbar button {
            padding: 5px 11px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all 0.15s;
        }
        #fs-toolbar button:hover { background: #FFD700; border-color: #FFD700; color: #1B1B4B; }
        #fs-toolbar select {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #1B1B4B;
            border: 1px solid rgba(255,255,255,0.2) !important;
            background: rgba(255,255,255,0.9);
            cursor: pointer;
        }
        #fs-toolbar .fs-title {
            font-size: 12px;
            font-weight: 800;
            color: #FFD700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-right: 8px;
        }
        #fs-toolbar .fs-spacer { margin-left: auto; }

        #fs-editor-body {
            flex: 1;
            overflow-y: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 32px 20px;
            background: #e8ecf0;
        }
        #fs-doc-page {
            background: white;
            width: 850px;
            max-width: 100%;
            min-height: 1000px;
            padding: 60px 72px;
            border-radius: 4px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            font-family: 'Times New Roman', serif;
            font-size: 14px;
            line-height: 1.8;
            color: #1a1a1a;
            outline: none;
        }
        #fs-doc-page:focus { outline: none; }
        #fs-doc-page table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        #fs-doc-page td, #fs-doc-page th { border: 1px solid #555; padding: 5px 8px; min-width: 40px; }
        #fs-footer {
            padding: 10px 20px;
            background: #1B1B4B;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="flex items-center gap-2 mb-8 px-2">
            <img src="<?= htmlspecialchars($logoPath) ?>" class="w-8 h-8 rounded-lg object-cover">
            <div>
                <h2 class="font-extrabold text-white text-sm leading-tight">SK Panel</h2>
                <span class="text-[9px] text-[#FFD700] font-bold uppercase tracking-wider"><?= htmlspecialchars($barangay) ?></span>
            </div>
        </div>

        <nav class="flex-grow">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3 px-3">Main Navigation</p>
            <a href="sk_dashboard.php" class="nav-link">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="sk_list.php" class="nav-link"><i class="fas fa-users-viewfinder"></i> SK Members</a>
            <a href="document_submissions.php" class="nav-link active"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="submit_proposal.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
            <a href="financial_aid.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
            <a href="scholarship_list.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
        </nav>

        <div class="user-profile-box flex-col items-start gap-1">
            <div class="flex items-center gap-2 w-full">
                <img src="<?= htmlspecialchars($profilePath) ?>" class="w-9 h-9 rounded-lg object-cover border border-white/20">
                <div class="overflow-hidden">
                    <p class="text-[11px] font-bold text-white truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[9px] text-slate-400 truncate"><?= htmlspecialchars($position) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2 w-full pt-2 border-t border-white/10">
                <a href="settings.php" class="text-[10px] text-[#FFD700] font-bold uppercase hover:opacity-80 transition flex items-center gap-1">
                    <i class="fas fa-cog text-[9px]"></i> Settings
                </a>
                <span class="text-white/20">|</span>
                <a href="login.php" class="text-[10px] text-[#ea580c] font-bold uppercase hover:opacity-80 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-xl">Document Submission</h1>
                <p class="text-xs text-slate-400 font-medium">Streamlined archival and review system</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200 text-[11px] font-bold text-[#1B1B4B]">
                    <i class="fas fa-user-check mr-2 text-[#ea580c]"></i> Verified Account
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="content-card">
                    <form action="document_submissions.php" method="POST" id="mainSubmitForm" class="space-y-6">
                        <!-- Hidden field to carry document content -->
                        <input type="hidden" name="document_content" id="document_content_field">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <label class="text-[11px] font-extrabold text-[#1B1B4B] uppercase tracking-wider flex items-center">
                                    <span class="step-badge bg-blue-100 text-blue-600">1</span> Category
                                </label>
                                <select id="document_type" name="document_type" required onchange="loadTemplate()"
                                        class="w-full p-3 bg-slate-50 outline-none focus:ring-2 focus:ring-[#FFD700] transition">
                                    <option value="" disabled selected>Select Document Type</option>
                                    <option value="Minutes of Meeting">Minutes of Meeting</option>
                                    <option value="SK Resolution">SK Resolution</option>
                                    <option value="Disbursement File">Disbursement Voucher</option>
                                    <option value="Attendance">Attendance (All)</option>
                                    <option value="Report">Report</option>
                                    <option value="Transmittal">Transmittal</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[11px] font-extrabold text-[#1B1B4B] uppercase tracking-wider flex items-center">
                                    <span class="step-badge bg-orange-100 text-orange-600">2</span> Title
                                </label>
                                <input type="text" name="title" required placeholder="Enter descriptive name"
                                       class="w-full p-3 bg-slate-50 outline-none focus:ring-2 focus:ring-[#FFD700] transition">
                            </div>
                        </div>

                        <!-- DOCUMENT EDITOR (replaces the file upload attachment) -->
                        <div id="doc-editor-wrapper" class="space-y-1">
                            <label class="text-[11px] font-extrabold text-[#1B1B4B] uppercase tracking-wider flex items-center">
                                <span class="step-badge bg-purple-100 text-purple-600">3</span> Document Content
                                <span class="ml-2 text-[10px] font-normal text-slate-400 normal-case tracking-normal">(Edit directly — click any field to modify)</span>
                            </label>

                            <!-- Formatting Toolbar -->
                            <div class="editor-toolbar">
                                <button type="button" onclick="fmt('bold')" title="Bold"><b>B</b></button>
                                <button type="button" onclick="fmt('italic')" title="Italic"><i>I</i></button>
                                <button type="button" onclick="fmt('underline')" title="Underline"><u>U</u></button>
                                <div style="width:1px;background:#e2e8f0;margin:0 4px;"></div>
                                <select onchange="fmt('fontSize', this.value); this.value='';" title="Font Size">
                                    <option value="">Size</option>
                                    <option value="1">8pt</option>
                                    <option value="2">10pt</option>
                                    <option value="3">12pt</option>
                                    <option value="4">14pt</option>
                                    <option value="5">18pt</option>
                                    <option value="6">24pt</option>
                                </select>
                                <div style="width:1px;background:#e2e8f0;margin:0 4px;"></div>
                                <button type="button" onclick="fmt('justifyLeft')" title="Align Left"><i class="fas fa-align-left"></i></button>
                                <button type="button" onclick="fmt('justifyCenter')" title="Center"><i class="fas fa-align-center"></i></button>
                                <button type="button" onclick="fmt('justifyRight')" title="Align Right"><i class="fas fa-align-right"></i></button>
                                <div style="width:1px;background:#e2e8f0;margin:0 4px;"></div>
                                <button type="button" onclick="fmt('insertUnorderedList')" title="Bullet List"><i class="fas fa-list-ul"></i></button>
                                <button type="button" onclick="fmt('insertOrderedList')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
                                <div style="width:1px;background:#e2e8f0;margin:0 4px;"></div>
                                <button type="button" onclick="resetTemplate()" title="Reset to Original Template" style="color:#ea580c;border-color:#fdba74;">
                                    <i class="fas fa-rotate-left"></i> Reset
                                </button>
                                <div class="toolbar-spacer"></div>
                                <button type="button" onclick="openFullscreen()" title="Fullscreen Editor" style="color:#1B1B4B;border-color:#1B1B4B;background:#f0f4ff;font-weight:700;">
                                    <i class="fas fa-expand"></i> Fullscreen
                                </button>
                            </div>

                            <!-- Editor Area -->
                            <div id="doc-editor" contenteditable="true" spellcheck="true">
                                <p class="editor-placeholder">← Select a category above to load the official template here. You can then edit it before submitting.</p>
                            </div>
                        </div>

                        <input type="hidden" name="pdf_data" id="pdf_data_field">
                        <button type="button" id="submitBtn" onclick="handleSubmit()" class="btn-primary w-full py-4 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-orange-100 flex items-center justify-center gap-2">
                            <i class="fas fa-check-double"></i> Submit to Secretariat
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-[#1B1B4B] rounded-3xl p-6 text-white overflow-hidden relative">
                    <div class="relative z-10">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-[#FFD700] mb-4">Submission Guide</h4>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <i class="fas fa-info-circle text-xs mt-1 opacity-60"></i>
                                <p class="text-[11px] leading-relaxed text-slate-300">All documents are timestamped and logged under your official ID for transparency.</p>
                            </div>
                            <div class="flex gap-3">
                                <i class="fas fa-shield-alt text-xs mt-1 opacity-60"></i>
                                <p class="text-[11px] leading-relaxed text-slate-300">Once submitted, the file is automatically queued for Reviewer approval.</p>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-folder-open absolute -bottom-4 -right-4 text-7xl text-white opacity-5"></i>
                </div>

                <!-- Modern Details/Info Container -->
                <div class="bg-gradient-to-r from-[#FFD700]/90 to-[#ea580c]/90 rounded-3xl p-6 shadow-lg flex flex-col gap-3 text-[#1B1B4B] border border-yellow-200/60">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-white/80 flex items-center justify-center shadow text-[#FFD700] text-xl">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold uppercase tracking-widest text-[#ea580c] mb-1">Did you know?</h4>
                            <p class="text-[11px] font-semibold text-[#1B1B4B]">You can track the status of your submissions in real-time and receive instant notifications for every update.</p>
                        </div>
                    </div>
                    <ul class="text-[10.5px] text-[#1B1B4B] pl-3 list-disc space-y-1">
                        <li>Select a category to load the official template automatically.</li>
                        <li>Edit directly in the document editor before submitting.</li>
                        <li>Need help? Visit the <span class="font-bold text-[#ea580c]">Help Center</span> or contact your SK Secretariat.</li>
                    </ul>
                </div>
            </div>

            <!-- Move Document Monitoring container below the form fields -->
            <div class="lg:col-span-2 mt-8">
                <div class="bg-white border border-slate-100 rounded-3xl p-6">
                    <h4 class="text-[11px] font-extrabold text-[#1B1B4B] uppercase mb-4 tracking-wider">Document Monitoring</h4>
                    <div class="overflow-x-auto">
                        <div class="max-h-72 overflow-y-auto custom-scrollbar">
                            <table class="min-w-full text-[11px]">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Category</th>
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Title</th>
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Barangay</th>
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Date</th>
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Time</th>
                                    <th class="px-3 py-2 text-left font-bold text-[#1B1B4B]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $monitor_sql = "SELECT document_category, title, barangay, updated_at, status, submitted_at FROM document_submissions WHERE barangay = ? ORDER BY updated_at DESC";
                                $stmt_monitor = $conn->prepare($monitor_sql);
                                $stmt_monitor->bind_param("s", $barangay);
                                $stmt_monitor->execute();
                                $result_monitor = $stmt_monitor->get_result();
                                if ($result_monitor->num_rows > 0) {
                                    while ($row = $result_monitor->fetch_assoc()) {
                                        echo '<tr class="border-b">';
                                        echo '<td class="px-3 py-2">' . htmlspecialchars($row['document_category']) . '</td>';
                                        echo '<td class="px-3 py-2">' . htmlspecialchars($row['title']) . '</td>';
                                        echo '<td class="px-3 py-2">' . htmlspecialchars($row['barangay']) . '</td>';
                                        $date = $row['updated_at'] ? date('M d,  Y', strtotime($row['updated_at'])) : '';
                                        echo '<td class="px-3 py-2">' . htmlspecialchars($date) . '</td>';
                                        // Time column: get time from submitted_at and format to 12-hour with am/pm
                                        $time = $row['submitted_at'] ? date('g:i a', strtotime($row['submitted_at'])) : '';
                                        echo '<td class="px-3 py-2">' . htmlspecialchars($time) . '</td>';
                                        $status = $row['status'];
                                        $statusColor = $status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : ($status === 'Viewed' ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700');
                                        echo '<td class="px-3 py-2"><span class="rounded-xl px-2 py-1 font-bold ' . $statusColor . '">' . htmlspecialchars($status) . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="px-3 py-2 text-slate-400 text-center">No submissions found.</td></tr>';
                                }
                                $stmt_monitor->close();
                                ?>
                            </tbody>
                        </table>
                            </div>
                    </div>
            <style>
                /* Custom scrollbar for vertical sliding */
                .custom-scrollbar::-webkit-scrollbar {
                    width: 7px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #CBD5E1;
                    border-radius: 10px;
                }
                .custom-scrollbar {
                    scrollbar-width: thin;
                    scrollbar-color: #CBD5E1 #f1f5f9;
                }
            </style>
                </div>
            </div>
            </div>
        </div>
    </main>
</div>

<!-- ====== FULLSCREEN EDITOR OVERLAY ====== -->
<div id="editor-fullscreen-overlay">
    <!-- Fullscreen Toolbar -->
    <div id="fs-toolbar">
        <span class="fs-title"><i class="fas fa-file-alt mr-2"></i>Document Editor</span>
        <div style="width:1px;background:rgba(255,255,255,0.2);margin:0 6px;height:20px;"></div>
        <button type="button" onclick="fsFmt('bold')" title="Bold"><b>B</b></button>
        <button type="button" onclick="fsFmt('italic')" title="Italic"><i>I</i></button>
        <button type="button" onclick="fsFmt('underline')" title="Underline"><u>U</u></button>
        <div style="width:1px;background:rgba(255,255,255,0.2);margin:0 6px;height:20px;"></div>
        <select onchange="fsFmt('fontSize', this.value); this.value='';" title="Font Size">
            <option value="">Size</option>
            <option value="1">8pt</option>
            <option value="2">10pt</option>
            <option value="3">12pt</option>
            <option value="4">14pt</option>
            <option value="5">18pt</option>
            <option value="6">24pt</option>
        </select>
        <div style="width:1px;background:rgba(255,255,255,0.2);margin:0 6px;height:20px;"></div>
        <button type="button" onclick="fsFmt('justifyLeft')" title="Align Left"><i class="fas fa-align-left"></i></button>
        <button type="button" onclick="fsFmt('justifyCenter')" title="Center"><i class="fas fa-align-center"></i></button>
        <button type="button" onclick="fsFmt('justifyRight')" title="Align Right"><i class="fas fa-align-right"></i></button>
        <div style="width:1px;background:rgba(255,255,255,0.2);margin:0 6px;height:20px;"></div>
        <button type="button" onclick="fsFmt('insertUnorderedList')" title="Bullet List"><i class="fas fa-list-ul"></i></button>
        <button type="button" onclick="fsFmt('insertOrderedList')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
        <div style="width:1px;background:rgba(255,255,255,0.2);margin:0 6px;height:20px;"></div>
        <button type="button" onclick="fsResetTemplate()" title="Reset Template" style="color:#ffb347;border-color:rgba(255,179,71,0.4);">
            <i class="fas fa-rotate-left"></i> Reset
        </button>
        <div class="fs-spacer"></div>
        <button type="button" onclick="closeFullscreen()" title="Exit Fullscreen" style="background:#ea580c;border-color:#ea580c;color:white;">
            <i class="fas fa-compress"></i> Exit Fullscreen
        </button>
    </div>

    <!-- Paper-style editing area -->
    <div id="fs-editor-body">
        <div id="fs-doc-page" contenteditable="true" spellcheck="true"></div>
    </div>

    <!-- Footer bar -->
    <div id="fs-footer">
        <span style="color:rgba(255,255,255,0.45);font-size:10px;font-family:'Plus Jakarta Sans',sans-serif;margin-right:auto;">
            <i class="fas fa-info-circle mr-1"></i> Changes are saved automatically when you exit fullscreen.
        </span>
        <button type="button" onclick="closeFullscreen()"
            style="padding:8px 22px;border-radius:10px;background:#ea580c;border:none;color:white;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:0.05em;">
            <i class="fas fa-compress mr-1"></i> Done & Exit
        </button>
    </div>
</div>

<?php if ($message_text): ?>
<div id="notificationModal" class="flex items-center gap-4 bg-white p-4 rounded-2xl shadow-2xl border-l-4 <?= ($message_type === 'success') ? 'border-emerald-500' : 'border-red-500' ?> w-80">
    <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center <?= ($message_type === 'success') ? 'bg-emerald-50 text-emerald-500' : 'bg-red-50 text-red-500' ?>">
        <i class="fas <?= ($message_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-lg"></i>
    </div>
    <div class="flex-grow">
        <h4 class="text-[11px] font-black text-[#1B1B4B] uppercase"><?= ($message_type === 'success') ? 'Success' : 'Attention' ?></h4>
        <p class="text-[10px] text-slate-500 font-medium leading-tight"><?= htmlspecialchars($message_text) ?></p>
    </div>
    <button onclick="hideNotif()" class="text-slate-300 hover:text-slate-500"><i class="fas fa-times text-xs"></i></button>
</div>
<?php endif; ?>

<script>
    // =============================================
    // FULLSCREEN EDITOR LOGIC
    // =============================================
    const fsOverlay  = document.getElementById('editor-fullscreen-overlay');
    const fsDocPage  = document.getElementById('fs-doc-page');
    const miniEditor = document.getElementById('doc-editor');

    function openFullscreen() {
        if (!currentCategory) {
            alert('Please select a document category first.');
            return;
        }
        // Sync mini → fullscreen
        fsDocPage.innerHTML = miniEditor.innerHTML;
        fsOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => fsDocPage.focus(), 100);
    }

    function closeFullscreen() {
        // Sync fullscreen → mini
        miniEditor.innerHTML = fsDocPage.innerHTML;
        fsOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // ESC key exits fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && fsOverlay.classList.contains('active')) {
            closeFullscreen();
        }
    });

    function fsFmt(cmd, val) {
        fsDocPage.focus();
        document.execCommand(cmd, false, val || null);
    }

    // =============================================
    // DOCUMENT TEMPLATES (HTML format, editable)
    // =============================================
    const TEMPLATES = {
        'Minutes of Meeting': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <p style="font-size:11px;">Republika ng Pilipinas &nbsp;&nbsp; Lalawigan ng Laguna &nbsp;&nbsp; Bayan ng Majayjay</p>
  <p style="font-weight:bold;font-size:15px;margin-top:6px;">TANGGAPAN NG SANGGUNIANG KABATAAN</p>
  <p style="font-weight:bold;font-size:12px;margin-top:4px;">SINIPIMULA SA KATITIKAN NG BUWANANG PANGKARANIWANG PULONG NG<br>
  SANGGUNIANG KABATAAN NG BARANGAY SAN ISIDRO, MAJAYJAY, LAGUNA<br>
  NA GINANAP NOONG <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> NG <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>, SA GANAP NA IKA-<span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> NG GABI SA BULWAGANG PULUNGAN.</p>
</div>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">MGA DUMALO:</p>
<table style="width:75%;border:none;margin:0 0 0 20px;font-family:'Times New Roman',serif;">
  <tr><td style="border:none;padding:2px 0;">KGG. MELANIE G. MERAÑA</td><td style="border:none;padding:2px 0;">SK CHAIRPERSON</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. HEIDI B. BOMUEL</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. KARYLE A. BOMUEL</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. JOSHUA M. NAPOLA</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. AUDREY JEARLD T. BOMUEL</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. JEAN CLAUDINE J. PATRON</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. ALESSANDRA MOIRA C. ROXAS</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. MICHAELLA HEARTH GERALDO</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">IBA PANG DUMALO:</p>
<table style="width:75%;border:none;margin:0 0 0 20px;font-family:'Times New Roman',serif;">
  <tr><td style="border:none;padding:2px 0;">MICHAELA DIANNE R. YBUAN</td><td style="border:none;padding:2px 0;">SK INGAT-YAMAN</td></tr>
  <tr><td style="border:none;padding:2px 0;">ERICKA SHYNE L. BOMUEL</td><td style="border:none;padding:2px 0;">SK KALIHIM</td></tr>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">HINDI DUMALO:</p>
<p style="font-family:'Times New Roman',serif;">WALA</p>
<br>
<p style="font-weight:bold;text-align:center;font-family:'Times New Roman',serif;">KATITIKAN NG PULONG</p>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">PAGTAWAG SA KAAYUSAN</p>
<p style="font-family:'Times New Roman',serif;">&nbsp;</p>
<p style="font-family:'Times New Roman',serif;">&nbsp;</p>
<p style="font-family:'Times New Roman',serif;">&nbsp;</p>
<br>
<p style="font-family:'Times New Roman',serif;">Inihanda ni:</p>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">PINATOTOHANAN NI:</p>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK CHAIRPERSON</p>
    </td>
    <td style="border:none;width:50%;"></td>
  </tr>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">PINAGTIBAY:</p>
<table style="width:100%;border:none;margin-top:10px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>Kalihim</p>
    </td>
    <td style="border:none;width:50%;"></td>
  </tr>
</table>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;width:50%;"></td>
  </tr>
</table>`,

        'SK Resolution': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <p style="font-size:11px;">Republika ng Pilipinas &nbsp;&nbsp; Lalawigan ng Laguna &nbsp;&nbsp; Bayan ng Majayjay</p>
  <p style="font-weight:bold;font-size:12px;margin-top:8px;">SINIPIMULA SA KATITIKAN NG BUWANANG PANGKARANIWANG PULONG NG<br>
  SANGGUNIANG KABATAAN NG BARANGAY SAN ISIDRO, MAJAYJAY, LAGUNA<br>
  NA GINANAP NOONG <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> NG <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>, SA GANAP NA <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> NG GABI SA BULWAGANG PULUNGAN.</p>
</div>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">MGA DUMALO:</p>
<table style="width:75%;border:none;margin:0 0 0 20px;font-family:'Times New Roman',serif;">
  <tr><td style="border:none;padding:2px 0;">KGG. HEIDI B. BOMUEL</td><td style="border:none;padding:2px 0;">SK CHAIRPERSON</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. KARYLE A. BOMUEL</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. JOSHUA M. NAPOLA</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. AUDREY JEARLD T. BOMUEL</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. JEAN CLAUDINE J. PATRON</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. ALESSANDRA MOIRA C. ROXAS</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
  <tr><td style="border:none;padding:2px 0;">KGG. MICHAELLA HEARTH GERALDO</td><td style="border:none;padding:2px 0;">SK KAGAWAD</td></tr>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">IBA PANG DUMALO:</p>
<table style="width:75%;border:none;margin:0 0 0 20px;font-family:'Times New Roman',serif;">
  <tr><td style="border:none;padding:2px 0;">MICHAELA DIANNE R. YBUAN</td><td style="border:none;padding:2px 0;">SK INGAT-YAMAN</td></tr>
  <tr><td style="border:none;padding:2px 0;">ERICKA SHYNE L. BOMUEL</td><td style="border:none;padding:2px 0;">SK KALIHIM</td></tr>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">RESOLUSYON BLG. <span style="border-bottom:1px solid #000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></p>
<br>
<p style="text-align:justify;font-family:'Times New Roman',serif;font-weight:bold;">RESOLUSYON PINAGTITIBAY NG SANGGUNIANG KABATAAN NG SAN ISIDRO, MAJAYJAY, LAGUNA NA ITAAS SA POSISYON SI KGG. HEIDI B. BOMUEL. MULA SA PAGIGING PRIMERA KAGAWAD HANGGANG SA ISANG GANAP NA SK CHAIRPERSON NG NASABING BARANGAY.</p>
<br>
<p style="font-weight:bold;text-align:center;font-family:'Times New Roman',serif;">PINAGTIBAY</p>
<br>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <p>Pinatotohanan:</p>
      <div style="border-bottom:1px solid #000;width:80%;margin:24px auto 4px;">&nbsp;</div>
      <p>Tagapangulo ng SK</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <p>Binigyang Pansin:</p>
      <div style="border-bottom:1px solid #000;width:80%;margin:24px auto 4px;">&nbsp;</div>
      <p>Kalihim</p>
    </td>
  </tr>
</table>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px auto 4px;">&nbsp;</div>
      <p>SK KAGAWAD</p>
    </td>
  </tr>
</table>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <p>Pinagtibay:</p>
      <div style="border-bottom:1px solid #000;width:80%;margin:24px auto 4px;">&nbsp;</div>
      <p>Tagapangulo ng SK</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:48px auto 4px;">&nbsp;</div>
      <p>Punong Barangay</p>
    </td>
  </tr>
</table>`,

        'Disbursement File': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <table style="width:100%;border-collapse:collapse;">
    <tr>
      <td style="border:1px solid #333;padding:6px;font-weight:bold;font-size:14px;text-align:center;">DISBURSEMENT VOUCHER</td>
      <td style="border:1px solid #333;padding:6px;font-weight:bold;text-align:center;">DV No. <span style="border-bottom:1px solid #000;display:inline-block;min-width:160px;">&nbsp;(YEAR-MONTH-TRANSACTION NUMBER)</span></td>
    </tr>
  </table>
</div>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;margin-top:0;">
  <tr>
    <td style="border:1px solid #333;padding:6px;vertical-align:top;">
      <strong>SK of Barangay:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:100px;">&nbsp;</span><br>
      <strong>City/Municipality:</strong> Majayjay &nbsp; <strong>Province:</strong> Laguna<br>
      <strong>Payee:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:140px;">&nbsp;</span><br>
      <strong>Address:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:140px;">&nbsp;</span> &nbsp;
      <strong>TIN:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:100px;">&nbsp;</span>
    </td>
    <td style="border:1px solid #333;padding:6px;vertical-align:top;">
      <strong>Date:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:120px;">&nbsp;</span><br>
      <br>
      <strong>Source of fund:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:140px;">&nbsp;</span>
    </td>
  </tr>
</table>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <thead>
    <tr style="background:#f5f5f5;">
      <th style="border:1px solid #333;padding:6px;text-align:left;">Particulars</th>
      <th style="border:1px solid #333;padding:6px;text-align:right;width:130px;">Amount</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="border:1px solid #333;padding:6px;">TO: <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;">&nbsp;</span></td>
      <td style="border:1px solid #333;padding:6px;text-align:right;">P 00,000</td>
    </tr>
    <tr><td style="border:1px solid #333;padding:6px;">&nbsp;</td><td style="border:1px solid #333;padding:6px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:6px;">&nbsp;</td><td style="border:1px solid #333;padding:6px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:6px;">&nbsp;</td><td style="border:1px solid #333;padding:6px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:6px;">&nbsp;</td><td style="border:1px solid #333;padding:6px;">&nbsp;</td></tr>
    <tr>
      <td style="border:1px solid #333;padding:6px;font-weight:bold;">Amount Due &#9658;</td>
      <td style="border:1px solid #333;padding:6px;font-weight:bold;text-align:right;">Php P 00,000</td>
    </tr>
  </tbody>
</table>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:1px solid #333;padding:8px;vertical-align:top;width:33%;">
      <p><strong>A. Certified:</strong><br>As to existence of appropriation for obligations.</p>
      <div style="border-bottom:1px solid #000;width:90%;margin:20px 0 4px;">&nbsp;</div>
      <p>Signature over printed name<br><strong>Budget Monitoring Officer</strong><br>Date: month-day-year</p>
    </td>
    <td style="border:1px solid #333;padding:8px;vertical-align:top;width:33%;">
      <p><strong>B. Certified:</strong><br>As to availability of funds. For the purpose, and completeness and propriety of supporting documents.</p>
      <div style="border-bottom:1px solid #000;width:90%;margin:20px 0 4px;">&nbsp;</div>
      <p>Signature over printed name<br><strong>SK Treasurer</strong><br>Date: month-day-year</p>
    </td>
    <td style="border:1px solid #333;padding:8px;vertical-align:top;width:33%;">
      <p><strong>C. Certified:</strong><br>As to validity, propriety, and legality of claim and approved for payment:</p>
      <div style="border-bottom:1px solid #000;width:90%;margin:20px 0 4px;">&nbsp;</div>
      <p>Signature over printed name<br><strong>SK Chairman</strong><br>Date: month-day-year</p>
    </td>
  </tr>
  <tr>
    <td colspan="3" style="border:1px solid #333;padding:8px;">
      <strong>Check No.:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:80px;">&nbsp;</span> &nbsp;
      <strong>Date:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:80px;">&nbsp;</span> &nbsp;
      <strong>Bank Name:</strong> Land Bank of the Philippines &nbsp;
      <strong>Bank Branch:</strong> Sta. Cruz, Laguna &nbsp;
      <strong>OR No.:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:80px;">&nbsp;</span> &nbsp;
      <strong>Date:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:80px;">&nbsp;</span>
      <p style="margin-top:8px;"><strong>D. Received Payment:</strong></p>
      <table style="width:100%;border:none;margin-top:8px;">
        <tr>
          <td style="border:none;text-align:center;width:50%;">
            <div style="border-bottom:1px solid #000;width:80%;margin:20px auto 4px;">&nbsp;</div>
            <p>Signature Over Printed Name of Payee/Authorized representative<br>month-day-year &nbsp; Date</p>
          </td>
          <td style="border:none;text-align:center;width:50%;">
            <div style="border-bottom:1px solid #000;width:80%;margin:20px auto 4px;">&nbsp;</div>
            <p>Signature Over Printed Name of Payee/Authorized representative<br>month-day-year &nbsp; Date</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>`,

        'Attendance': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <p style="font-size:11px;">Republic of the Philippines &nbsp; Province of Laguna &nbsp; Municipality of Majayjay</p>
  <p style="font-weight:bold;font-size:15px;margin-top:6px;">LNK 2025</p>
  <p style="font-size:13px;margin-top:2px;">ATTENDANCE SHEET</p>
</div>
<br>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <thead>
    <tr style="background:#f5f5f5;">
      <th style="border:1px solid #333;padding:8px;text-align:center;width:50px;">#</th>
      <th style="border:1px solid #333;padding:8px;text-align:left;">NAME</th>
      <th style="border:1px solid #333;padding:8px;text-align:center;">SIGNATURE</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">1</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">2</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">3</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">4</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">5</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">6</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">7</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">8</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">9</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">10</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">11</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">12</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">13</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">14</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">15</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">16</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">17</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">18</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">19</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">20</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">21</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">22</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">23</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">24</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;text-align:center;">25</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
  </tbody>
</table>
<br>
<p style="font-family:'Times New Roman',serif;"><strong>PREPARED BY:</strong></p>
<table style="width:100%;border:none;margin-top:20px;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <div style="border-bottom:1px solid #000;width:60%;margin:20px auto 4px;">&nbsp;</div>
      <p>HON. JOHN MATTHEW ARQUIZA<br>SK SECRETARY</p>
    </td>
    <td style="border:none;width:50%;"></td>
  </tr>
</table>`,

        'Report': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <p style="font-size:11px;">REPUBLIC OF THE PHILIPPINES &nbsp; PROVINCE OF LAGUNA &nbsp; MUNICIPALITY OF MAJAYJAY &nbsp; BARANGAY ___________</p>
  <p style="font-weight:bold;font-size:13px;margin-top:4px;">OFFICE OF THE SANGGUNIANG KABATAAN</p>
  <p style="font-weight:bold;font-size:15px;margin-top:6px;">MONTHLY REPORT / ACCOMPLISHMENT REPORT</p>
</div>
<br>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <thead>
    <tr style="background:#f5f5f5;">
      <th style="border:1px solid #333;padding:6px;text-align:center;width:80px;">DATE</th>
      <th style="border:1px solid #333;padding:6px;text-align:left;">ACTIVITY</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;width:80px;">HON.</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;">COMMITTEE ON / SK CHAIRPERSON</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
  </tbody>
</table>
<br>
<p style="font-weight:bold;font-family:'Times New Roman',serif;">ATTENDANCE (DATE &amp; TIME)</p>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <thead>
    <tr style="background:#f5f5f5;">
      <th style="border:1px solid #333;padding:6px;text-align:center;">NAME</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;">POSITION</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;">SIGNATURE</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;width:80px;">TIME</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
  </tbody>
</table>
<br>
<table style="width:100%;border:none;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:center;width:50%;">
      <p><strong>PREPARED BY:</strong></p>
      <div style="border-bottom:1px solid #000;width:80%;margin:20px auto 4px;">&nbsp;</div>
      <p>SK SECRETARY</p>
    </td>
    <td style="border:none;text-align:center;width:50%;">
      <p><strong>ATTESTED BY:</strong></p>
      <div style="border-bottom:1px solid #000;width:80%;margin:20px auto 4px;">&nbsp;</div>
      <p>SK CHAIRPERSON</p>
    </td>
  </tr>
</table>`,

        'Transmittal': `
<div style="text-align:center;font-family:'Times New Roman',serif;">
  <p style="font-weight:bold;">Barangay ___________</p>
  <p>Municipality of MAJAYJAY &nbsp;&nbsp;&nbsp; Province of LAGUNA</p>
  <br>
  <p style="font-weight:bold;font-size:15px;">TRANSMITTAL LETTER</p>
</div>
<br>
<table style="width:100%;border:none;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:right;width:160px;">&nbsp;</td>
    <td style="border:none;">August &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;, 2022</td>
  </tr>
</table>
<br>
<table style="width:100%;border:none;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;vertical-align:top;width:60px;"><strong>TO:</strong></td>
    <td style="border:none;">Jeremy P. Liboon<br>Municipal Accountant<br>Municipality of MAJAYJAY</td>
  </tr>
</table>
<br>
<p style="font-family:'Times New Roman',serif;">Sir / Madam</p>
<br>
<p style="font-family:'Times New Roman',serif;text-align:justify;">We submit herewith the following documents a) certified copy of the cashbook, b) copy of PBCs issued and c) original of the Disbursement Voucher/Payroll issued for the month of <strong>July 2022</strong> duly acknowledged by the payees;</p>
<br>
<table style="width:100%;border-collapse:collapse;font-family:'Times New Roman',serif;">
  <thead>
    <tr style="background:#f5f5f5;">
      <th colspan="2" style="border:1px solid #333;padding:6px;text-align:center;">A. DV/Payroll</th>
      <th colspan="2" style="border:1px solid #333;padding:6px;text-align:center;">Check</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;">Payee</th>
      <th style="border:1px solid #333;padding:6px;text-align:center;">Amount</th>
      <th colspan="2" style="border:1px solid #333;padding:6px;text-align:center;">PB Certification</th>
    </tr>
    <tr>
      <th style="border:1px solid #333;padding:4px;text-align:center;">Date</th>
      <th style="border:1px solid #333;padding:4px;text-align:center;">No</th>
      <th style="border:1px solid #333;padding:4px;text-align:center;">Date</th>
      <th style="border:1px solid #333;padding:4px;text-align:center;">No</th>
      <th style="border:1px solid #333;padding:4px;"></th>
      <th style="border:1px solid #333;padding:4px;"></th>
      <th style="border:1px solid #333;padding:4px;text-align:center;">Date</th>
      <th style="border:1px solid #333;padding:4px;text-align:center;">No</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td><td style="border:1px solid #333;padding:8px;">&nbsp;</td></tr>
    <tr>
      <td colspan="4" style="border:1px solid #333;padding:8px;"></td>
      <td style="border:1px solid #333;padding:8px;font-weight:bold;">TOTAL</td>
      <td style="border:1px solid #333;padding:8px;"></td>
      <td colspan="2" style="border:1px solid #333;padding:8px;"></td>
    </tr>
  </tbody>
</table>
<br>
<p style="font-family:'Times New Roman',serif;"><strong>B. Other Reports</strong></p>
<p style="font-family:'Times New Roman',serif;">1. Report of Accountability for Accountable Forms ( RAAF ) July 2022</p>
<p style="font-family:'Times New Roman',serif;">2. Cashbook ( July 2022)</p>
<p style="font-family:'Times New Roman',serif;">3. RAO (July 2022)</p>
<br>
<p style="font-family:'Times New Roman',serif;">Please acknowledge receipt hereof</p>
<br>
<p style="font-family:'Times New Roman',serif;">Very truly your's</p>
<br>
<table style="width:100%;border:none;font-family:'Times New Roman',serif;">
  <tr>
    <td style="border:none;text-align:left;width:50%;">
      <div style="border-bottom:1px solid #000;width:80%;margin:30px 0 4px;">&nbsp;</div>
      <p><strong>JHERLYN G. ESTRADA</strong><br>SK Treasurer</p>
    </td>
    <td style="border:none;width:50%;"></td>
  </tr>
</table>`
    };

    let currentCategory = '';

    function loadTemplate() {
        const select = document.getElementById('document_type');
        const wrapper = document.getElementById('doc-editor-wrapper');
        const editor  = document.getElementById('doc-editor');
        const category = select.value;

        if (TEMPLATES[category]) {
            currentCategory = category;
            editor.innerHTML = TEMPLATES[category];
            wrapper.classList.add('active');
            // Scroll editor into view smoothly
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            wrapper.classList.remove('active');
            editor.innerHTML = '';
            currentCategory = '';
        }
    }

    function resetTemplate() {
        if (currentCategory && TEMPLATES[currentCategory]) {
            if (confirm('Reset document to the original template? Your edits will be lost.')) {
                document.getElementById('doc-editor').innerHTML = TEMPLATES[currentCategory];
            }
        }
    }

    function fmt(cmd, val) {
        document.getElementById('doc-editor').focus();
        document.execCommand(cmd, false, val || null);
    }

    async function handleSubmit() {
        const editor = document.getElementById('doc-editor');
        const rawText = editor.innerText.trim();
        const titleInput = document.querySelector('[name="title"]');
        const titleVal = titleInput ? titleInput.value.trim() : '';
        const docTypeVal = document.getElementById('document_type') ? document.getElementById('document_type').value : '';

        if (!currentCategory) { alert('Please select a document category first.'); return; }
        if (!rawText || rawText.length < 5) { alert('Document content appears to be empty. Please fill in the template.'); return; }
        if (!titleVal) { alert('Please enter a document title.'); return; }

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating PDF...';

        const editorContent = editor.innerHTML;

        // Show overlay
        const overlay = document.createElement('div');
        overlay.id = 'pdf-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(27,27,75,0.75);z-index:99998;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px);';
        overlay.innerHTML = '<div style="background:white;border-radius:16px;padding:32px 40px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
            + '<i class="fas fa-file-pdf" style="font-size:36px;color:#ea580c;display:block;margin-bottom:12px;"></i>'
            + '<div style="font-family:sans-serif;font-size:14px;font-weight:800;color:#1B1B4B;margin-bottom:6px;">Converting to PDF</div>'
            + '<div style="font-family:sans-serif;font-size:11px;color:#94a3b8;">Please wait while your document is being prepared...</div>'
            + '</div>';
        document.body.appendChild(overlay);

        try {
            // Send HTML content to server-side PDF generator (generate_pdf.php)
            const formData = new FormData();
            formData.append('html_content', editorContent);
            formData.append('title', titleVal);
            formData.append('document_type', currentCategory);

            const response = await fetch('generate_pdf.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'PDF generation failed on server.');
            }

            // Server saved the PDF — put its path into hidden field and submit
            document.getElementById('pdf_data_field').value = result.pdf_path;
            document.getElementById('document_content_field').value = editorContent;

            overlay.remove();

            const form = btn.closest('form');
            const si = document.createElement('input');
            si.type = 'hidden'; si.name = 'submit_document'; si.value = '1';
            form.appendChild(si);
            form.submit();

        } catch (err) {
            const ov = document.getElementById('pdf-overlay');
            if (ov) ov.remove();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-double"></i> Submit to Secretariat';
            alert('Failed to generate PDF.\n' + err.message);
        }
    }

    function captureContent() {
        const editor = document.getElementById('doc-editor');
        const content = editor.innerHTML.trim();
        const rawText = editor.innerText.trim();

        if (!currentCategory) {
            alert('Please select a document category first.');
            return false;
        }
        if (!rawText || rawText.length < 5) {
            alert('Document content appears to be empty. Please fill in the template.');
            return false;
        }

        document.getElementById('document_content_field').value = content;
        return true;
    }

    // Toast Animation Logic
    const modal = document.getElementById('notificationModal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => { modal.style.transform = 'translateX(0)'; }, 100);
        setTimeout(hideNotif, 6000);
    }

    function hideNotif() {
        if (modal) {
            modal.style.transform = 'translateX(120%)';
            setTimeout(() => { modal.style.display = 'none'; }, 500);
        }
    }
</script>
</body>
</html>