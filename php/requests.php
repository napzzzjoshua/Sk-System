<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$fullname = $_SESSION['fullname'];
$admin_id = $_SESSION['user_id'];

// --- DATA LOGIC: NOTIFICATIONS & CHAT (Matched to Dashboard) ---
$unread_messages_query = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$unread_messages_query->bind_param("i", $admin_id);
$unread_messages_query->execute();
$unread_messages_count = $unread_messages_query->get_result()->fetch_assoc()['count'] ?? 0;

$notifications = [];
$unread_notifications_count = 0;
$notif_res = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if($notif_res){
    while ($row = $notif_res->fetch_assoc()) {
        $notifications[] = $row;
        if ($row['is_read'] == 0) $unread_notifications_count++;
    }
}

// --- DATA LOGIC FOR SORTING/FILTERING ---
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'All';


// For Project Proposal, treat 'Pending' and 'View by Admin' as pending

if ($status_filter === 'Pending') {
    // Include both 'Pending' and 'View by Admin' for Financial Aid and Project Proposal
    $query_aid  = "SELECT id, submitted_by, 'Financial Aid' as request_type, aid_type as category, total_amount as amount, status, created_at, reason as description, NULL as rejection_reason, NULL as document_path FROM financial_aid_requests WHERE status = 'Pending' OR status = 'View by Admin'";
    $query_prop = "SELECT id, submitted_by, 'Project Proposal' as request_type, title as category, budget as amount, status, created_at, description, rejection_reason, document_path FROM submissions WHERE status = 'Pending' OR status = 'View by Admin'";
} else {
    $query_aid  = "SELECT id, submitted_by, 'Financial Aid' as request_type, aid_type as category, total_amount as amount, status, created_at, reason as description, NULL as rejection_reason, NULL as document_path FROM financial_aid_requests WHERE status = '$status_filter'";
    $query_prop = "SELECT id, submitted_by, 'Project Proposal' as request_type, title as category, budget as amount, status, created_at, description, rejection_reason, document_path FROM submissions WHERE status = '$status_filter'";
}

if ($type_filter === 'Financial Aid') {
    $all_requests_query = "$query_aid ORDER BY created_at DESC";
} elseif ($type_filter === 'Project Proposal') {
    $all_requests_query = "$query_prop ORDER BY created_at DESC";
} else {
    $all_requests_query = "($query_aid) UNION ALL ($query_prop) ORDER BY created_at DESC";
}

$result = $conn->query($all_requests_query);
$all_requests = [];
if($result) {
    while($row = $result->fetch_assoc()) { $all_requests[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Courier+Prime&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --navy-primary: #1B1B4B;
            --gold-accent: #FFD700;
            --bg-light: #f1f5f9;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--navy-primary); overflow-x: hidden; }
        
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E6E8F0; position: fixed; height: 100vh; z-index: 40; display: flex; flex-direction: column; overflow: hidden; }
        
        .nav-item { 
            display: flex; align-items: center; padding: 0.6rem 1.25rem; margin: 0.15rem 0.75rem; 
            border-radius: 10px; font-size: 0.85rem; font-weight: 500; color: #8E92BC; 
            transition: all 0.2s; text-decoration: none; 
        }
        .nav-item i { width: 20px; font-size: 1rem; margin-right: 1rem; display: flex; justify-content: center; }
        
        .tool-label { font-size: 0.65rem; font-weight: 700; color: #ABB1D1; letter-spacing: 0.05em; padding: 0.75rem 1.5rem 0.25rem; text-transform: uppercase; }
        .nav-tool-item { 
            display: flex; align-items: center; padding: 0.5rem 1.25rem; margin: 0.1rem 0.75rem; 
            border-radius: 8px; font-size: 0.8rem; font-weight: 500; color: #8E92BC; 
            transition: all 0.2s; text-decoration: none; position: relative;
        }
        .nav-tool-item i { font-size: 0.85rem; margin-right: 1rem; width: 20px; text-align: center; }
        .nav-tool-item:hover { background-color: #F4F5FF; color: var(--navy-primary); }

        .nav-item:hover:not(.active) { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item.active { 
            background: var(--navy-primary); 
            color: white; 
            box-shadow: 0 4px 10px rgba(27, 27, 75, 0.15); 
            border-right: 3px solid var(--gold-accent);
        }

        .main-content { margin-left: 260px; padding: 1.5rem; min-height: 100vh; }
        .card-white { background: white; border-radius: 20px; border: 1px solid #F0F1F7; padding: 1.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }
        
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }

        .user-menu-item { display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: #4A5568; transition: all 0.2s; cursor: pointer; }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }

        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }

        .search-input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: white; border-radius: 12px; border: 1px solid #E2E8F0; font-size: 0.85rem; outline: none; transition: 0.2s; }
        .search-input:focus { border-color: var(--navy-primary); }

        #detailsModal, #confirmModal, #receiptModal { display: none; position: fixed; inset: 0; background: rgba(27, 27, 75, 0.4); backdrop-filter: blur(4px); z-index: 100; align-items: center; justify-content: center; }
        .modal-card { background: white; width: 90%; max-width: 450px; border-radius: 24px; overflow: hidden; animation: modalPop 0.25s ease-out; }

        /* Details Modal Overrides */
        #detailsModal .modal-card {
            max-width: 600px;
            display: flex;
            flex-direction: column;
            max-height: 88vh;
        }
        #detailsModal .modal-card .modal-body {
            overflow-y: auto;
            flex: 1;
        }
        #detailsModal .modal-card .modal-body::-webkit-scrollbar { width: 4px; }
        #detailsModal .modal-card .modal-body::-webkit-scrollbar-track { background: #f1f5f9; }
        #detailsModal .modal-card .modal-body::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 4px; }

        .detail-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
        .detail-value { font-size: 13px; font-weight: 600; color: #1e293b; }

        .attached-img-container {
            border: 1px solid #e0e7ff;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 10px;
        }
        .attached-img-header {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px;
            background: #eef2ff;
            border-bottom: 1px solid #e0e7ff;
        }
        .attached-img-header span { font-size: 11px; font-weight: 700; color: #4338ca; }
        .attached-img-body { padding: 12px; background: #f8faff; text-align: center; }
        .attached-img-body img { max-width: 100%; max-height: 260px; object-fit: contain; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); cursor: zoom-in; }

        /* Image lightbox */
        #imgLightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out; }
        #imgLightbox img { max-width:90vw; max-height:90vh; border-radius:12px; box-shadow:0 8px 40px rgba(0,0,0,0.4); }
        
        /* Thermal Receipt Style */
        .receipt-paper {
            background: #fff;
            width: 320px;
            padding: 20px;
            font-family: 'Courier Prime', monospace;
            color: #000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            margin: auto;
        }
        .receipt-paper::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 10px;
            background: linear-gradient(-45deg, transparent 5px, #fff 5px), linear-gradient(45deg, transparent 5px, #fff 5px);
            background-size: 10px 10px;
        }

        @keyframes modalPop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        @media print {
            body * { visibility: hidden; }
            #receiptModal, #receiptModal * { visibility: visible; }
            #receiptModal { position: absolute; left: 0; top: 0; background: white; }
            .no-print { display: none; }
        }

        /* ===== SK IMAGE EDITOR ===== */
        #skImgOverlay {
            display: none;
            position: fixed;
            z-index: 9998;
            pointer-events: none;
            box-sizing: border-box;
        }
        #skImgOverlay.active { display: block; }
        .sk-img-border {
            position: absolute;
            inset: -2px;
            border: 1.5px solid #4f46e5;
            border-radius: 2px;
            pointer-events: none;
            box-shadow: 0 0 0 1px rgba(79,70,229,0.15);
        }
        /* Word-style circular resize handles */
        .sk-img-handle {
            position: absolute;
            width: 12px; height: 12px;
            background: white;
            border: 1.5px solid #4f46e5;
            border-radius: 50%;
            pointer-events: all;
            box-sizing: border-box;
            z-index: 9999;
            box-shadow: 0 1px 4px rgba(79,70,229,0.25), 0 0 0 1px rgba(255,255,255,0.8);
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .sk-img-handle:hover {
            transform: scale(1.25);
            box-shadow: 0 2px 8px rgba(79,70,229,0.4), 0 0 0 2px white;
            background: #eef2ff;
        }
        .sk-handle-nw { top:-6px;  left:-6px;  cursor:nw-resize; }
        .sk-handle-n  { top:-6px;  left:calc(50% - 6px); cursor:n-resize; }
        .sk-handle-ne { top:-6px;  right:-6px; cursor:ne-resize; }
        .sk-handle-e  { top:calc(50% - 6px); right:-6px; cursor:e-resize; }
        .sk-handle-se { bottom:-6px; right:-6px; cursor:se-resize; }
        .sk-handle-s  { bottom:-6px; left:calc(50% - 6px); cursor:s-resize; }
        .sk-handle-sw { bottom:-6px; left:-6px; cursor:sw-resize; }
        .sk-handle-w  { top:calc(50% - 6px); left:-6px; cursor:w-resize; }

        #skImgToolbar {
            display: none;
            position: fixed;
            z-index: 10000;
            background: #1B1B4B;
            border-radius: 10px;
            padding: 5px 10px;
            align-items: center;
            gap: 2px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.25);
            white-space: nowrap;
            transform: translateX(-50%);
        }
        #skImgToolbar.active { display: flex; }
        .sk-tb-btn {
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 26px;
            border-radius: 6px;
            border: none; background: transparent;
            color: #c7d2fe;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            position: relative;
        }
        .sk-tb-btn:hover { background: rgba(255,255,255,0.12); color: white; }
        .sk-tb-btn.active { background: #6366f1; color: white; }
        .sk-tb-sep { width: 1px; height: 18px; background: rgba(255,255,255,0.15); margin: 0 3px; }
        .sk-tb-label { font-size: 9px; font-weight: 700; color: #818cf8; text-transform: uppercase; letter-spacing: 0.05em; padding: 0 4px; }
        .sk-tb-btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 5px;
            white-space: nowrap;
            pointer-events: none;
        }
        /* Dim the editor img when not selected */
        #skFormatEditor img { cursor: pointer; transition: outline 0.1s; }
        #skFormatEditor img:hover { outline: 1px dashed #818cf8; outline-offset: 2px; }

        /* ===== WORD-STYLE RIGHT-CLICK CONTEXT MENU ===== */
        #skCtxMenu {
            display: none;
            position: fixed;
            z-index: 99999;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18), 0 1px 4px rgba(0,0,0,0.10);
            min-width: 220px;
            padding: 3px 0;
            font-family: 'Inter', sans-serif;
            user-select: none;
        }
        #skCtxMenu.open { display: block; }

        .wctx-item {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 16px 5px 10px;
            font-size: 12px; font-weight: 400; color: #1f2937;
            cursor: pointer; white-space: nowrap; position: relative;
            min-height: 28px;
        }
        .wctx-item:hover { background: #e8eaf6; color: #1B1B4B; }
        .wctx-item.disabled { color: #9ca3af; cursor: default; }
        .wctx-item.disabled:hover { background: transparent; }
        .wctx-icon { width: 20px; text-align: center; font-size: 12px; color: #4b5563; flex-shrink: 0; }
        .wctx-item:hover .wctx-icon { color: #1B1B4B; }
        .wctx-item.disabled .wctx-icon { color: #d1d5db; }
        .wctx-arrow { margin-left: auto; font-size: 9px; color: #6b7280; padding-left: 12px; }
        .wctx-check { width: 16px; text-align: center; font-size: 10px; color: #1B1B4B; flex-shrink: 0; margin-left: -4px; }
        .wctx-sep { height: 1px; background: #e5e7eb; margin: 3px 0; }
        .wctx-bold { font-weight: 600; }

        /* Paste Options box */
        .wctx-paste-row {
            display: flex; align-items: center; gap: 4px;
            padding: 5px 10px;
        }
        .wctx-paste-btn {
            display: flex; align-items: center; justify-content: center;
            width: 32px; height: 30px;
            border: 1px solid #d1d5db; border-radius: 3px;
            background: white; cursor: pointer; font-size: 13px; color: #374151;
            transition: background 0.12s, border-color 0.12s;
        }
        .wctx-paste-btn:hover { background: #e8eaf6; border-color: #6366f1; color: #1B1B4B; }
        .wctx-paste-label { font-size: 11px; font-weight: 600; color: #374151; margin-left: 4px; }

        /* Sub-menu */
        .wctx-submenu { position: relative; }
        .wctx-sub-panel {
            display: none;
            position: absolute;
            left: 100%; top: -4px;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.16);
            min-width: 200px;
            padding: 3px 0;
            z-index: 100000;
        }
        .wctx-submenu:hover .wctx-sub-panel { display: block; }

        /* Size & Position modal */
        #skSizePosModal {
            display: none; position: fixed; inset: 0;
            background: rgba(27,27,75,0.4); backdrop-filter: blur(3px);
            z-index: 100002; align-items: center; justify-content: center;
        }
        #skSizePosModal.open { display: flex; }
        #skSizePosModal .sp-card {
            background: white; width: 340px; border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2); overflow: hidden;
        }
        #skSizePosModal .sp-head {
            background: #1B1B4B; padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between;
        }
        #skSizePosModal .sp-head h4 { font-size: 13px; font-weight: 700; color: white; margin: 0; }
        #skSizePosModal .sp-body { padding: 18px; }
        #skSizePosModal label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; margin-top: 12px; }
        #skSizePosModal label:first-child { margin-top: 0; }
        #skSizePosModal input[type=number] {
            width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 7px 10px; font-size: 13px; color: #1e293b;
            outline: none; transition: border 0.15s;
        }
        #skSizePosModal input[type=number]:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.12); }
        #skSizePosModal .sp-lock { display: flex; align-items: center; gap: 6px; margin-top: 10px; }
        #skSizePosModal .sp-lock input { width: auto; }
        #skSizePosModal .sp-lock span { font-size: 11px; color: #64748b; }
        #skSizePosModal .sp-footer { display: flex; gap: 8px; margin-top: 16px; }
        #skSizePosModal .sp-footer button { flex: 1; padding: 9px; border-radius: 10px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; }
        #skSizePosModal .sp-footer .sp-cancel { background: #f1f5f9; color: #64748b; }
        #skSizePosModal .sp-footer .sp-apply  { background: #1B1B4B; color: white; }

        /* Alt Text modal */
        #skAltTextModal {
            display: none; position: fixed; inset: 0;
            background: rgba(27,27,75,0.4); backdrop-filter: blur(3px);
            z-index: 100002; align-items: center; justify-content: center;
        }
        #skAltTextModal.open { display: flex; }
    </style>
</head>
<body class="flex">

    <aside class="sidebar">
        <div class="p-6 text-lg font-bold text-slate-800 flex items-center gap-3">
            <div class="w-8 h-8 bg-[#1B1B4B] rounded-lg flex items-center justify-center text-[#FFD700]">
                <i class="fas fa-shield-halved text-sm"></i>
            </div>
            <span class="tracking-tight">MAJAYJAY <span style="color: #FFD700;">SK</span></span>
        </div>
        
        <nav class="flex-grow">
            <a href="admin_dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
            
            <div class="tool-label">Tools</div>
            <a href="admin_chat.php" class="nav-tool-item">
                <i class="fa-solid fa-comment-dots"></i>
                <span>Messages</span>
                <?php if($unread_messages_count > 0): ?>
                    <span class="tool-badge"><?= $unread_messages_count > 99 ? '99+' : $unread_messages_count ?></span>
                <?php endif; ?>
            </a>
            <a href="uploads/charter/citizen_charter.pdf" target="_blank" class="nav-tool-item">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>Citizen Charter</span>
            </a>
            
            <div class="tool-label">Main Menu</div>
            <a href="geo_mapping.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
            <a href="manage_users.php" class="nav-item"><i class="fa-solid fa-users-gear"></i><span>Manage Users</span></a>
            <a href="requests.php" class="nav-item active"><i class="fa-solid fa-clipboard-list"></i><span>Requests</span></a>
            <a href="documents.php" class="nav-item"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="form_management.php" class="nav-item"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-[#1B1B4B]">Request Management</h2>
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <button id="notifButton" class="relative text-gray-400 hover:text-[#1B1B4B] transition outline-none">
                        <i class="far fa-bell text-lg"></i>
                        <?php if($unread_notifications_count > 0): ?>
                            <span id="notifBadge" class="badge-count"><?= $unread_notifications_count ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifDropdown" class="dropdown-menu mt-4">
                        <div class="p-4 border-b border-gray-100"><h4 class="font-bold text-sm">Notifications</h4></div>
                        <div class="max-h-60 overflow-y-auto p-2">
                            <?php if(empty($notifications)): ?>
                                <p class="p-3 text-xs text-gray-400 text-center">No new notifications</p>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="p-3 border-b border-gray-50 text-xs text-gray-600"><?= htmlspecialchars($n['message']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button id="adminProfileBtn" class="flex items-center gap-3 border-l pl-6 border-gray-200 focus:outline-none hover:opacity-80 transition">
                        <div class="text-right">
                            <p class="text-xs font-bold leading-none"><?= htmlspecialchars($fullname) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Administrator <i class="fa-solid fa-chevron-down ml-1"></i></p>
                        </div>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($fullname) ?>&background=1B1B4B&color=FFD700" class="w-8 h-8 rounded-full border border-gray-200">
                    </button>

                    <div id="profileDropdown" class="dropdown-menu mt-4" style="width: 200px;">
                        <div class="p-3 border-b border-gray-50">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Account Settings</h4>
                        </div>
                        <div class="py-1">
                            <a href="admin_profile.php" class="user-menu-item">
                                <i class="fa-solid fa-user-gear"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="admin_preference.php" class="user-menu-item">
                                <i class="fa-solid fa-sliders"></i>
                                <span>Preferences</span>
                            </a>
                            <a href="admin_security_logs.php" class="user-menu-item">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Security Logs</span>
                            </a>
                        </div>
                    </div>
                </div>
                </div>
        </header>

        <div class="flex flex-col md:flex-row gap-4 mb-6 items-center justify-between">
            <div class="relative w-full md:w-96">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="tableSearch" onkeyup="searchTable()" placeholder="Search requests..." class="search-input">
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Edit SK Format Button -->
                <button onclick="openSkFormatEditor()" class="flex items-center gap-2 px-4 py-2 bg-[#1B1B4B] text-white text-[11px] font-bold rounded-xl hover:bg-indigo-900 transition shadow-sm border border-indigo-800">
                    <i class="fa-solid fa-file-pen text-[#FFD700]"></i>
                    Edit SK Format
                </button>

                <div class="flex gap-2 bg-white p-1 rounded-xl border border-gray-100 shadow-sm">
                    <a href="?status=Pending&type=<?= $type_filter ?>" class="px-4 py-1.5 text-[10px] font-bold rounded-lg transition <?= $status_filter === 'Pending' ? 'bg-[#1B1B4B] text-white' : 'text-gray-400 hover:bg-gray-50' ?>">Pending</a>
                    <a href="?status=Approved&type=<?= $type_filter ?>" class="px-4 py-1.5 text-[10px] font-bold rounded-lg transition <?= $status_filter === 'Approved' ? 'bg-[#1B1B4B] text-white' : 'text-gray-400 hover:bg-gray-50' ?>">Approved</a>
                    <a href="?status=Rejected&type=<?= $type_filter ?>" class="px-4 py-1.5 text-[10px] font-bold rounded-lg transition <?= $status_filter === 'Rejected' ? 'bg-[#1B1B4B] text-white' : 'text-gray-400 hover:bg-gray-50' ?>">Rejected</a>
                </div>
            </div>
        </div>

        <div class="mb-4 flex gap-2">
            <a href="?status=<?= $status_filter ?>&type=Financial Aid" class="text-[11px] font-bold px-3 py-1 rounded-full border <?= $type_filter === 'Financial Aid' ? 'bg-amber-100 border-amber-200 text-amber-700' : 'bg-white text-gray-400 border-gray-100' ?>">Financial Aid</a>
            <a href="?status=<?= $status_filter ?>&type=Project Proposal" class="text-[11px] font-bold px-3 py-1 rounded-full border <?= $type_filter === 'Project Proposal' ? 'bg-amber-100 border-amber-200 text-amber-700' : 'bg-white text-gray-400 border-gray-100' ?>">Proposals</a>
        </div>

        <div class="card-white overflow-x-auto">
            <table class="w-full text-left" id="requestTable">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                        <th class="pb-3 pl-2">Submitted By</th>
                        <th class="pb-3">Type</th>
                        <th class="pb-3">Category/Title</th>
                        <th class="pb-3 text-center">Amount</th>
                        <th class="pb-3 text-center">Status</th>
                        <th class="pb-3 text-right pr-2">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if(empty($all_requests)): ?>
                        <tr><td colspan="6" class="py-10 text-center text-xs text-gray-400 font-medium">No matching requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach($all_requests as $req): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-4 pl-2 text-sm font-semibold text-slate-700"><?= htmlspecialchars($req['submitted_by']) ?></td>
                                <td class="py-4"><span class="text-[9px] font-bold px-2 py-0.5 bg-slate-100 rounded text-slate-500 uppercase"><?= $req['request_type'] ?></span></td>
                                <td class="py-4 text-xs text-[#1B1B4B]"><?= htmlspecialchars($req['category']) ?></td>
                                <td class="py-4 text-sm font-bold text-slate-700 text-center">₱<?= number_format($req['amount'], 2) ?></td>
                                <td class="py-4 text-center"><span class="status-pill status-<?= strtolower($req['status']) ?>"><?= $req['status'] ?></span></td>
                                <td class="py-4 text-right pr-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <?php if($req['status'] === 'Pending'): ?>
                                        <?php endif; ?>

                                        <?php if($req['status'] === 'Approved'): ?>
                                            <button onclick='openReceiptModal(<?= json_encode($req) ?>)' 
                                                    title="View Receipt"
                                                    class="w-8 h-8 inline-flex items-center justify-center bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition shadow-sm border border-amber-100">
                                                <i class="fa-solid fa-receipt text-xs"></i>
                                            </button>
                                        <?php endif; ?>

                                        <button onclick='openDetailModal(<?= json_encode($req) ?>)' 
                                                title="View Details"
                                                class="w-8 h-8 inline-flex items-center justify-center bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-lg transition shadow-sm border border-indigo-100">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="receiptModal" onclick="closeReceiptModal()" class="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="receipt-paper" onclick="event.stopPropagation()">
            <div class="text-center mb-4">
                <div class="flex justify-center mb-2">
                    <div class="w-10 h-10 bg-[#1B1B4B] rounded-lg flex items-center justify-center text-[#FFD700]">
                        <i class="fas fa-shield-halved text-xl"></i>
                    </div>
                </div>
                <h2 class="font-bold text-lg leading-tight uppercase">Majayjay SK System</h2>
                <p class="text-[10px]">Official Transaction Receipt</p>
                <p class="text-[10px]">Majayjay, Laguna, Philippines</p>
            </div>
            
            <div class="border-t border-b border-dashed border-gray-400 py-2 my-4 text-[11px]">
                <div class="flex justify-between"><span>DATE:</span><span id="r-date"></span></div>
                <div class="flex justify-between"><span>TRANS ID:</span><span id="r-id"></span></div>
                <div class="flex justify-between"><span>ADMIN:</span><span><?= strtoupper($fullname) ?></span></div>
            </div>

            <div class="space-y-3 mb-6">
                <div>
                    <p class="text-[10px] font-bold text-gray-500 uppercase">Description</p>
                    <p id="r-desc" class="text-xs font-bold"></p>
                </div>
                <div class="flex justify-between items-end border-b border-gray-100 pb-2">
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Submitted By</p>
                        <p id="r-name" class="text-xs"></p>
                    </div>
                    <div class="text-right">
                        <p id="r-type" class="text-[9px] bg-gray-100 px-1 rounded"></p>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <span class="font-bold text-sm">TOTAL AMOUNT:</span>
                <span id="r-amount" class="font-bold text-lg"></span>
            </div>

            <div class="text-center text-[10px] space-y-1 mb-4">
                <p class="font-bold">*** STATUS: APPROVED ***</p>
                <p>Thank you for your service to the youth!</p>
            </div>

            <div class="no-print flex gap-2">
                <button onclick="window.print()" class="flex-1 py-2 bg-slate-800 text-white text-[10px] font-bold rounded-md hover:bg-black transition">PRINT</button>
                <button onclick="closeReceiptModal()" class="flex-1 py-2 bg-gray-100 text-gray-500 text-[10px] font-bold rounded-md hover:bg-gray-200 transition">CLOSE</button>
            </div>
        </div>
    </div>

    <div id="confirmModal" onclick="closeConfirmModal()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="p-8 text-center">
                <div id="confirmIconCircle" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i id="confirmIcon" class="text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-[#1B1B4B] mb-2">Confirmation</h3>
                <p id="confirmMessage" class="text-xs text-gray-500 leading-relaxed px-4"></p>
                
                <div class="flex gap-3 mt-8">
                    <button onclick="closeConfirmModal()" class="flex-1 py-3 bg-gray-100 text-gray-500 text-xs font-bold rounded-xl hover:bg-gray-200 transition">Cancel</button>
                    <button id="confirmActionButton" class="flex-1 py-3 text-white text-xs font-bold rounded-xl shadow-lg transition">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div id="imgLightbox" onclick="document.getElementById('imgLightbox').style.display='none'">
        <img id="imgLightboxSrc" src="" alt="Full View">
    </div>

    <div id="detailsModal" onclick="closeDetailModal()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-[#1B1B4B] px-6 py-5 text-white flex justify-between items-start flex-shrink-0">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-7 h-7 bg-[#FFD700] rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-clipboard-list text-[#1B1B4B] text-xs"></i>
                        </div>
                        <h3 class="font-bold text-sm tracking-tight">Request Details</h3>
                    </div>
                    <div class="flex items-center gap-2 ml-9">
                        <span id="m-type-badge" class="text-[9px] font-bold bg-white/15 text-[#FFD700] px-2 py-0.5 rounded-full uppercase tracking-wide border border-white/10"></span>
                        <span id="m-status-badge" class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide"></span>
                    </div>
                </div>
                <button onclick="closeDetailModal()" class="hover:rotate-90 transition-transform text-white/70 hover:text-white mt-1"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <!-- Modal Body (scrollable) -->
            <div class="modal-body p-5 space-y-4">

                <!-- Subject / Category -->
                <div class="p-4 bg-gradient-to-br from-indigo-50 to-slate-50 rounded-2xl border border-indigo-100">
                    <p class="detail-label"><i class="fa-solid fa-tag mr-1 text-indigo-300"></i>Subject / Category</p>
                    <p id="m-category" class="font-bold text-base text-[#1B1B4B] mt-1 leading-snug"></p>
                </div>

                <!-- Requester + Amount -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="detail-label"><i class="fa-solid fa-user mr-1 text-slate-300"></i>Requester</p>
                        <p id="m-name" class="detail-value mt-1 text-sm leading-snug"></p>
                    </div>
                    <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100">
                        <p class="detail-label"><i class="fa-solid fa-peso-sign mr-1 text-emerald-300"></i>Amount</p>
                        <p id="m-amount" class="font-bold text-lg text-emerald-600 mt-1"></p>
                    </div>
                </div>

                <!-- Date Submitted -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="detail-label"><i class="fa-regular fa-calendar mr-1 text-slate-300"></i>Date Submitted</p>
                    <p id="m-date" class="detail-value mt-1 text-sm"></p>
                </div>

                <!-- Description -->
                <div>
                    <p class="detail-label ml-1 mb-2"><i class="fa-solid fa-align-left mr-1 text-slate-300"></i>Narrative / Description</p>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p id="m-desc-text" class="text-xs text-slate-600 leading-relaxed italic"></p>
                    </div>
                </div>

                <!-- Attached File (injected here if applicable) -->
                <div id="m-attachment-area"></div>

                <!-- Action Buttons area -->
                <div id="m-action-area"></div>

            </div>
        </div>
    </div>

    <!-- ===== SK FORMAT EDITOR MODAL ===== -->
    <div id="skFormatModal" onclick="closeSkFormatEditor()" style="display:none; position:fixed; inset:0; background:rgba(27,27,75,0.5); backdrop-filter:blur(4px); z-index:300; align-items:center; justify-content:center;">
        <div onclick="event.stopPropagation()" style="width:90%; max-width:720px; max-height:92vh; display:flex; flex-direction:column;" class="rounded-2xl shadow-2xl bg-white overflow-hidden animate-[modalPop_0.25s_ease-out]">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 bg-[#1B1B4B] flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[#FFD700] rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-file-word text-[#1B1B4B] text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-white">SK Format Editor</h3>
                        <p class="text-[10px] text-[#FFD700] font-semibold uppercase tracking-widest">uploads/sk_president_format/SK_FORMAT.docx</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleSkFormatFullscreen()" id="skFormatFullscreenBtn" title="Fullscreen" class="text-white hover:text-[#FFD700] transition text-base px-2 py-1 rounded-lg hover:bg-white/10">
                        <i id="skFormatFullscreenIcon" class="fa-solid fa-expand"></i>
                    </button>
                    <button onclick="closeSkFormatEditor()" class="text-white hover:rotate-90 transition-transform text-lg"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <!-- Status bar -->
            <div id="skFormatStatus" class="hidden flex-shrink-0"></div>

            <!-- Loading state -->
            <div id="skFormatLoading" class="flex-1 flex flex-col items-center justify-center py-16 gap-3">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-400"></i>
                <p class="text-xs text-gray-400 font-medium">Loading SK_FORMAT.docx content...</p>
            </div>

            <!-- Editor body (hidden until loaded) -->
            <div id="skFormatBody" class="hidden flex-1 overflow-y-auto px-6 py-5 space-y-4">

                <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                    <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 text-sm flex-shrink-0"></i>
                    <p class="text-[11px] text-amber-700 leading-relaxed">
                        Edit the SK Format document below. Your changes will be saved directly into <strong>SK_FORMAT.docx</strong> on the server and used automatically when approving requests.
                        Formatting (bold, fonts, layout) is preserved — only the text content is editable here.
                    </p>
                </div>

                <!-- Toolbar -->
                <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 flex-wrap">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mr-1">Format:</span>
                    <button type="button" onclick="skFormatExecCmd('bold')" title="Bold" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 font-bold text-sm">B</button>
                    <button type="button" onclick="skFormatExecCmd('italic')" title="Italic" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 italic text-sm">I</button>
                    <button type="button" onclick="skFormatExecCmd('underline')" title="Underline" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 underline text-sm">U</button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" onclick="skFormatExecCmd('justifyLeft')" title="Align Left" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 text-xs"><i class="fa-solid fa-align-left"></i></button>
                    <button type="button" onclick="skFormatExecCmd('justifyCenter')" title="Center" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 text-xs"><i class="fa-solid fa-align-center"></i></button>
                    <button type="button" onclick="skFormatExecCmd('justifyRight')" title="Align Right" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition text-slate-600 text-xs"><i class="fa-solid fa-align-right"></i></button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <select onchange="skFormatExecCmd('fontSize', this.value); this.value=''" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 text-slate-600 bg-white outline-none">
                        <option value="">Size</option>
                        <option value="1">8pt</option>
                        <option value="2">10pt</option>
                        <option value="3">12pt</option>
                        <option value="4">14pt</option>
                        <option value="5">18pt</option>
                        <option value="6">24pt</option>
                        <option value="7">36pt</option>
                    </select>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <!-- Insert Image Button -->
                    <label title="Insert Image" class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition cursor-pointer px-2 py-1 rounded hover:bg-indigo-50">
                        <i class="fa-solid fa-image text-xs"></i>
                        <span>Insert Image</span>
                        <input type="file" id="skInsertImageInput" accept="image/*" class="hidden" onchange="skInsertImage(this)">
                    </label>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <a id="skFormatDownloadBtn" href="uploads/sk_president_format/SK_FORMAT.docx" download class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition ml-auto px-2">
                        <i class="fa-solid fa-download text-xs"></i> Download Current
                    </a>
                </div>

                <!-- Editable document area -->
                <div id="skFormatEditor"
                     contenteditable="true"
                     spellcheck="true"
                     style="min-height:320px; font-family: 'Times New Roman', serif; font-size: 13px; line-height: 1.8; padding: 32px 40px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; box-shadow: inset 0 1px 3px rgba(0,0,0,0.04);"
                     class="focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                <!-- Upload replacement docx -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-2">Or replace with a new .docx file</p>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 px-3 py-2 bg-white border border-slate-300 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-300 transition text-[11px] font-semibold text-slate-600">
                            <i class="fa-solid fa-upload text-indigo-500 text-xs"></i>
                            Choose .docx file
                            <input type="file" id="skFormatFileInput" accept=".docx,.doc" class="hidden" onchange="handleSkFormatFileChange(this)">
                        </label>
                        <span id="skFormatFileName" class="text-[11px] text-gray-400 italic">No file chosen</span>
                        <button type="button" id="skFormatUploadBtn" onclick="uploadSkFormat()" style="display:none;" class="ml-auto px-3 py-2 bg-indigo-600 text-white text-[11px] font-bold rounded-lg hover:bg-indigo-700 transition">
                            <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload & Replace
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div id="skFormatFooter" class="hidden flex-shrink-0 flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 bg-slate-50">
                <p class="text-[10px] text-gray-400"><i class="fa-solid fa-shield-halved mr-1 text-indigo-400"></i>Changes save directly to the server</p>
                <div class="flex gap-2">
                    <button type="button" onclick="closeSkFormatEditor()" class="px-4 py-2 bg-gray-100 text-gray-500 text-xs font-bold rounded-xl hover:bg-gray-200 transition">Cancel</button>
                    <button type="button" onclick="saveSkFormat()" id="skFormatSaveBtn" class="px-5 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-100">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ===== END SK FORMAT EDITOR MODAL ===== -->

    <!-- Rejection Reason Modal -->
    <div id="rejectionReasonModal" onclick="closeRejectionReasonModal()" style="display:none; position:fixed; inset:0; background:rgba(27,27,75,0.4); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center;">
        <div onclick="event.stopPropagation()" class="w-full max-w-md rounded-2xl shadow-2xl bg-white overflow-hidden animate-[modalPop_0.25s_ease-out]">
            <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-rose-500 to-rose-600">
                <div>
                    <h3 class="font-bold text-base text-white tracking-tight">Rejection Reason</h3>
                    <p class="text-xs text-[#FFD700] opacity-90 font-semibold mt-1">Please provide a reason</p>
                </div>
                <button onclick="closeRejectionReasonModal()" class="hover:rotate-90 transition-transform text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-8 py-7">
                <label for="rejectionReasonInput" class="block text-xs font-bold text-gray-500 mb-2">Rejection Reason</label>
                <textarea id="rejectionReasonInput" rows="4" class="w-full p-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 transition mb-4 resize-none" placeholder="Enter rejection reason..."></textarea>
                <div class="flex gap-3 mt-2">
                    <button onclick="closeRejectionReasonModal()" class="flex-1 py-2 bg-gray-100 text-gray-500 text-xs font-bold rounded-xl hover:bg-gray-200 transition shadow">Cancel</button>
                    <button id="confirmRejectionReasonBtn" class="flex-1 py-2 bg-gradient-to-r from-rose-500 to-rose-600 text-white text-xs font-bold rounded-xl hover:from-rose-600 hover:to-rose-700 transition shadow-lg">Confirm</button>
                </div>
            </div>
        </div>
    </div>

        <script>
            // --- Details Modal Action Buttons ---
            function detailsApprove(id, type) {
                closeDetailModal();
                setTimeout(function() {
                    updateRequestStatus(id, type, 'Approved');
                }, 200);
            }
                // Show rejection reason modal instead of direct reject
                let rejectData = {};
                function detailsReject(id, type) {
                    closeDetailModal();
                    rejectData = {id, type};
                    setTimeout(function() {
                        document.getElementById('rejectionReasonInput').value = '';
                        document.getElementById('rejectionReasonModal').style.display = 'flex';
                    }, 200);
                }
                function closeRejectionReasonModal() {
                    document.getElementById('rejectionReasonModal').style.display = 'none';
                }
        </script>

    <script>
        // Dropdown Handlers
        const notifButton = document.getElementById('notifButton');
        const notifBadge = document.getElementById('notifBadge');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            profileDropdown.classList.remove('show');
            if(notifBadge) {
                notifBadge.remove();
                fetch('mark_notifications_read.php'); 
            }
        });

        adminBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            notifDropdown.classList.remove('show');
        });

        window.onclick = () => {
            if(notifDropdown) notifDropdown.classList.remove('show');
            if(profileDropdown) profileDropdown.classList.remove('show');
        }

        function searchTable() {
            const input = document.getElementById("tableSearch");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("requestTable");
            const tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                let rowText = tr[i].textContent || tr[i].innerText;
                tr[i].style.display = (rowText.toUpperCase().indexOf(filter) > -1) ? "" : "none";
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-circle-xmark';
            
            toast.className = `fixed bottom-5 right-5 ${bgColor} text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 z-[200] transform transition-all duration-300 translate-y-20 opacity-0`;
            toast.innerHTML = `
                <i class="fa-solid ${icon} text-lg"></i>
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-80">System Update</span>
                    <span class="text-xs font-semibold">${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('translate-y-20', 'opacity-0');
            }, 100);

            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function updateRequestStatus(id, type, status) {
            const modal = document.getElementById('confirmModal');
            const message = document.getElementById('confirmMessage');
            const icon = document.getElementById('confirmIcon');
            const circle = document.getElementById('confirmIconCircle');
            const actionBtn = document.getElementById('confirmActionButton');

                if(status === 'Approved') {
                    message.innerHTML = `Are you sure you want to change this <span class='font-bold text-slate-700'>${type}</span> status to <span class='text-emerald-600 font-bold'>Approved</span>?`;
                    icon.className = "fa-solid fa-check text-emerald-600";
                    circle.className = "w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-4";
                    actionBtn.className = "flex-1 py-3 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition";
                    actionBtn.onclick = function() {
                        const formData = new FormData();
                        formData.append('id', id);
                        formData.append('type', type);
                        formData.append('status', status);
                        fetch('update_request_status.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(data => {
                            showToast('Status successfully updated to Approved', 'success');
                            closeConfirmModal();
                            setTimeout(() => { location.reload(); }, 1500);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('An error occurred during update', 'error');
                        });
                    };
                    modal.style.display = 'flex';
                }

            modal.style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function openDetailModal(data) {
            // --- Type badge ---
            const typeBadge = document.getElementById('m-type-badge');
            typeBadge.textContent = data.request_type;

            // --- Status badge ---
            const statusBadge = document.getElementById('m-status-badge');
            const statusMap = {
                'Pending':       'bg-amber-400/20 text-amber-200 border border-amber-400/30',
                'View by Admin': 'bg-sky-400/20 text-sky-200 border border-sky-400/30',
                'Approved':      'bg-emerald-400/20 text-emerald-200 border border-emerald-400/30',
                'Rejected':      'bg-rose-400/20 text-rose-200 border border-rose-400/30',
            };
            statusBadge.className = 'text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide ' + (statusMap[data.status] || 'bg-white/10 text-white');
            statusBadge.textContent = data.status;

            // --- Core fields ---
            document.getElementById('m-category').innerText = data.category;
            document.getElementById('m-name').innerText = data.submitted_by;
            document.getElementById('m-amount').innerText = '₱' + parseFloat(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2});

            // --- Date ---
            const dateEl = document.getElementById('m-date');
            if (data.created_at) {
                const d = new Date(data.created_at);
                dateEl.textContent = d.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }) + ' · ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
            } else {
                dateEl.textContent = '—';
            }

            // --- Description text ---
            const descEl = document.getElementById('m-desc-text');
            const attachArea = document.getElementById('m-attachment-area');
            attachArea.innerHTML = '';

            if (data.status === 'Rejected' && data.request_type === 'Project Proposal' && data.rejection_reason) {
                descEl.innerHTML = `<span class='font-bold text-rose-600 not-italic'>Rejection Reason:</span> ${data.rejection_reason}`;
            } else {
                descEl.textContent = data.description || 'No additional details provided for this request.';

                // --- Attached file (Project Proposal) ---
                if (data.request_type === 'Project Proposal' && data.document_path) {
                    const imgExts = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
                    const lowerPath = data.document_path.toLowerCase();
                    const isImage = imgExts.some(ext => lowerPath.endsWith(ext));
                    let fileSrc = data.document_path.startsWith('uploads/') ? data.document_path : 'uploads/' + data.document_path;

                    if (isImage) {
                        attachArea.innerHTML = `
                            <div class="attached-img-container">
                                <div class="attached-img-header">
                                    <i class="fa-solid fa-image text-indigo-500 text-xs"></i>
                                    <span>Attached Image</span>
                                    <a href="${fileSrc}" target="_blank" class="ml-auto text-[10px] text-indigo-400 hover:text-indigo-600 font-semibold transition">Open full ↗</a>
                                </div>
                                <div class="attached-img-body">
                                    <img src="${fileSrc}" alt="Proposal Attachment"
                                        onclick="document.getElementById('imgLightboxSrc').src=this.src; document.getElementById('imgLightbox').style.display='flex';"
                                        onerror="this.closest('.attached-img-container').innerHTML='<p class=\'p-4 text-xs text-rose-500 font-semibold text-center\'>⚠ Image could not be loaded</p>'">
                                </div>
                            </div>`;
                    } else {
                        attachArea.innerHTML = `
                            <a href="${fileSrc}" target="_blank"
                               class="flex items-center gap-3 p-3 bg-indigo-50 border border-indigo-100 rounded-2xl hover:bg-indigo-100 transition group">
                                <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-700 transition">
                                    <i class="fa-solid fa-file-lines text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold text-indigo-700">Attached Document</p>
                                    <p class="text-[10px] text-indigo-400">${fileSrc.split('/').pop()}</p>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square text-indigo-300 ml-auto text-xs"></i>
                            </a>`;
                    }
                }
            }

            // --- If status View by Admin — mark as viewed ---
            if (data.status === 'Pending') {
                let tableType = null;
                if (data.request_type === 'Project Proposal') tableType = 'submissions';
                if (data.request_type === 'Financial Aid') tableType = 'financial_aid_requests';
                if (tableType) {
                    const formData = new FormData();
                    formData.append('id', data.id);
                    formData.append('type', tableType);
                    formData.append('view_by_admin', 'true');
                    fetch('update_status.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(result => {
                            if (result.success) { showToast('Marked as viewed by admin', 'success'); setTimeout(() => location.reload(), 1200); }
                        })
                        .catch(() => showToast('Failed to mark as viewed', 'error'));
                }
            }

            // --- Action buttons ---
            const actionArea = document.getElementById('m-action-area');
            actionArea.innerHTML = '';

            if ((data.status === 'Pending' || data.status === 'View by Admin') &&
                (data.request_type === 'Project Proposal' || data.request_type === 'Financial Aid')) {

                actionArea.innerHTML = `
                    <div class="space-y-3 pt-1">
                        <div class="flex items-center gap-3 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-2xl">
                            <div class="w-8 h-8 bg-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-file-word text-white text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold text-indigo-700 uppercase tracking-wide">Built-in Approval Format</p>
                                <p class="text-[10px] text-indigo-400 truncate">SK_FORMAT.docx → Auto-converted to PDF on Approve</p>
                            </div>
                            <span class="text-[9px] font-bold bg-indigo-600 text-white px-2 py-1 rounded-full flex-shrink-0">READY</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" id="approveBtn"
                                class="py-3 bg-emerald-600 text-white text-xs font-bold rounded-2xl hover:bg-emerald-700 transition shadow-md shadow-emerald-100 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                            <button type="button" onclick="detailsReject('${data.id}', '${data.request_type}')"
                                class="py-3 bg-rose-500 text-white text-xs font-bold rounded-2xl hover:bg-rose-600 transition shadow-md shadow-rose-100 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </div>
                    </div>`;

                const approveBtn = document.getElementById('approveBtn');
                if (approveBtn) {
                    approveBtn.onclick = function() {
                        approveBtn.disabled = true;
                        approveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

                        // Capture the current SK Format Editor HTML content (includes NOTED BY section + any edits)
                        const editorEl = document.getElementById('skFormatEditor');
                        let pdfHtml = '';
                        if (editorEl) {
                            // Clone so we can clean up interactive elements before sending
                            const clone = editorEl.cloneNode(true);
                            // Clean up NOTED BY signature wrap
                            const sigWrap = clone.querySelector('#skNotedByImgWrap');
                            const sigImg  = clone.querySelector('#skNotedByImg');
                            const sigPh   = clone.querySelector('#skNotedByPlaceholder');
                            const sigInput = clone.querySelector('#skNotedBySigInput');
                            if (sigWrap) {
                                sigWrap.style.border  = 'none';
                                sigWrap.style.padding = '0';
                                sigWrap.style.cursor  = 'default';
                                sigWrap.removeAttribute('title');
                            }
                            if (sigPh)    sigPh.remove();
                            if (sigInput) sigInput.remove();
                            if (sigImg && sigImg.src && sigImg.src !== window.location.href) {
                                sigImg.style.display = 'block';
                            }
                            // Clean up SK Chairperson signature wrap
                            const chairWrap  = clone.querySelector('#skChairSigWrap');
                            const chairImg   = clone.querySelector('#skChairSigImg');
                            const chairPh    = clone.querySelector('#skChairSigPlaceholder');
                            const chairInput = clone.querySelector('#skChairSigInput');
                            if (chairWrap) {
                                chairWrap.style.border  = 'none';
                                chairWrap.style.padding = '0';
                                chairWrap.style.cursor  = 'default';
                                chairWrap.removeAttribute('title');
                            }
                            if (chairPh)    chairPh.remove();
                            if (chairInput) chairInput.remove();
                            if (chairImg && chairImg.src && chairImg.src !== window.location.href) {
                                chairImg.style.display = 'block';
                            }
                            pdfHtml = clone.innerHTML;
                        }

                        const formData = new FormData();
                        formData.append('id',            data.id);
                        formData.append('type',          data.request_type === 'Financial Aid' ? 'Financial Aid' : data.request_type);
                        formData.append('status',        'Approved');
                        formData.append('use_sk_format', '1');
                        formData.append('pdf_html',      pdfHtml);   // full editor HTML for PDF conversion
                        // Pass request meta so the server can fill placeholders if needed
                        formData.append('submitted_by',  data.submitted_by  || '');
                        formData.append('category',      data.category      || '');
                        formData.append('amount',        data.amount        || '');
                        formData.append('description',   data.description   || '');

                        fetch('update_request_status.php', { method: 'POST', body: formData })
                            .then(r => r.text())
                            .then(resp => {
                                let result = {};
                                try {
                                    result = JSON.parse(resp);
                                } catch(e) {
                                    // Raw response was not JSON — treat as server error
                                    approveBtn.disabled = false;
                                    approveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Approve';
                                    showToast('Server error: unexpected response. Check PHP logs.', 'error');
                                    console.error('Non-JSON response from server:', resp);
                                    return;
                                }

                                if (result.success === false) {
                                    approveBtn.disabled = false;
                                    approveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Approve';
                                    showToast(result.message || 'Approval failed. Please try again.', 'error');
                                } else {
                                    showToast('Approved! Approval document saved successfully.', 'success');
                                    closeDetailModal();
                                    setTimeout(() => location.reload(), 1500);
                                }
                            })
                            .catch(() => {
                                approveBtn.disabled = false;
                                approveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Approve';
                                showToast('Network error during approval. Please try again.', 'error');
                            });
                    };
                }
            }



            document.getElementById('detailsModal').style.display = 'flex';
        }
        function closeDetailModal() { document.getElementById('detailsModal').style.display = 'none'; }

        // --- RECEIPT FUNCTIONS ---
        function openReceiptModal(data) {
            document.getElementById('r-date').innerText = new Date().toLocaleDateString() + ' ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('r-id').innerText = (data.request_type.charAt(0) + data.id + Math.floor(100 + Math.random() * 900)).toUpperCase();
            document.getElementById('r-desc').innerText = data.category;
            document.getElementById('r-name').innerText = data.submitted_by;
            document.getElementById('r-type').innerText = data.request_type;
            document.getElementById('r-amount').innerText = '₱' + parseFloat(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('receiptModal').style.display = 'flex';
        }
        function closeReceiptModal() { document.getElementById('receiptModal').style.display = 'none'; }

            // --- Rejection Reason Modal Logic ---
                document.getElementById('confirmRejectionReasonBtn').onclick = function() {
                    const reason = document.getElementById('rejectionReasonInput').value.trim();
                    if (!reason) {
                        showToast('Please enter a rejection reason.', 'error');
                        return;
                    }
                    // Directly update status to Rejected with reason
                    const formData = new FormData();
                    formData.append('id', rejectData.id);
                    formData.append('type', rejectData.type);
                    formData.append('status', 'Rejected');
                    formData.append('rejection_reason', reason);
                    closeRejectionReasonModal();
                    fetch('update_request_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        showToast('Status successfully updated to Rejected', 'error');
                        setTimeout(() => { location.reload(); }, 1500);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred during update', 'error');
                    });
                };
    </script>
    <!-- ===== SK FORMAT EDITOR SCRIPT ===== -->
    <script>
        function skInsertImage(input) {
            const file = input.files[0];
            if (!file) return;
            // Reset input so the same file can be re-selected
            input.value = '';

            const reader = new FileReader();
            reader.onload = function(e) {
                const editor = document.getElementById('skFormatEditor');
                editor.focus();

                // Try to insert at current cursor position
                const sel = window.getSelection();
                if (sel && sel.rangeCount) {
                    const range = sel.getRangeAt(0);
                    // Make sure cursor is inside the editor
                    if (editor.contains(range.commonAncestorContainer)) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.cssText = 'max-width:100%; height:auto; border-radius:6px; margin:8px 0; display:block; box-shadow:0 2px 8px rgba(0,0,0,0.10); cursor:pointer;';
                        img.title = 'Click to resize: hold and drag corner';
                        // Make image resizable via contenteditable native handles
                        img.setAttribute('contenteditable', 'false');
                        range.collapse(false);
                        range.insertNode(img);
                        // Move cursor after the image
                        range.setStartAfter(img);
                        range.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(range);
                    } else {
                        // Fallback: append at end
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.cssText = 'max-width:100%; height:auto; border-radius:6px; margin:8px 0; display:block; box-shadow:0 2px 8px rgba(0,0,0,0.10);';
                        editor.appendChild(img);
                    }
                } else {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = 'max-width:100%; height:auto; border-radius:6px; margin:8px 0; display:block; box-shadow:0 2px 8px rgba(0,0,0,0.10);';
                    editor.appendChild(img);
                }
                showToast('Image inserted into document!', 'success');
            };
            reader.readAsDataURL(file);
        }

        function skFormatExecCmd(cmd, val) {
            document.getElementById('skFormatEditor').focus();
            document.execCommand(cmd, false, val || null);
        }

        /* ─── NOTED BY block HTML ─── */
        function _skNotedByBlock() {
            // Returns the NOTED BY section HTML.
            // Signature image uses mix-blend-mode:multiply so a white background becomes
            // transparent-looking on white paper; actual PNG transparency is preserved too.
            return `<div id="skNotedBySection" style="margin-top:48px; font-family:'Times New Roman',serif; font-size:13px;">
                        <p style="margin:0 0 4px 0; font-weight:600;">NOTED BY:</p>
                        <div style="display:inline-block; border:1px dashed #b0b8c8; border-radius:6px; padding:6px 10px; min-width:220px; min-height:80px; position:relative; background:transparent; text-align:left; cursor:pointer;" id="skNotedByImgWrap" title="Click to upload signature image">
                            <img id="skNotedByImg"
                                 src=""
                                 alt="Signature"
                                 style="display:none; max-width:200px; max-height:80px; object-fit:contain; mix-blend-mode:multiply; background:transparent;"
                                 contenteditable="false">
                            <span id="skNotedByPlaceholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8; pointer-events:none;">Click to upload signature</span>
                            <input type="file" id="skNotedBySigInput" accept="image/png,image/gif,image/svg+xml,image/*" class="hidden" style="display:none;">
                        </div>
                        <div style="margin-top:6px;">
                            <div style="width:220px; border-bottom:1.5px solid #000; margin-bottom:3px;"></div>
                            <p style="margin:0; font-weight:700; text-decoration:underline; font-size:13px;">HON. BRIAN E. FRESCO, LPT</p>
                            <p style="margin:0; font-size:12px;">SK PEDERASYON PRESIDENT-MAJAYJAY</p>
                        </div>
                    </div>`;
        }

        /* ─── Full SK FORMAT document template (matches SK_FORMAT.docx) ─── */
        function _skFullDocTemplate() {
            return `<div style="font-family:'Times New Roman',serif; font-size:13px; line-height:1.8; color:#000;">

                <p style="margin:0 0 16px 0;">Date: _________</p>

                <p style="margin:0 0 14px 0; text-align:justify;">
                    Magandang araw po. Ako po ay sumulat upang ipaalam sa inyong tanggapan ang planong paglalabas ng transaksyon ng Sangguniang Kabataan para sa pagpapatupad ng isang proyekto/programa ng aming konseho. Ang nasabing proyekto ay bahagi ng mga nakaplanong gawain ng SK para sa kapakanan at kaunlaran ng kabataan sa ating barangay. Kaugnay nito, narito po ang detalye ng nasabing proyekto:
                </p>

                <p style="margin:0 0 8px 0;">Pangalan ng Proyekto/Programa: ________________________</p>
                <p style="margin:0 0 8px 0;">Halaga (Amount): Php ________________________</p>
                <p style="margin:0 0 8px 0;">Pinanggalingan ng Pondo (Source of Fund): ________________________</p>
                <p style="margin:0 0 8px 0;">Pangalan ng Supplier/Business Name: ________________________</p>
                <p style="margin:0 0 16px 0;">Implementation Date: ____________________________</p>

                <p style="margin:0 0 14px 0; text-align:justify;">
                    Ang transaksyong ito ay isinagawa alinsunod sa mga umiiral na alituntunin at proseso sa paggamit ng pondo ng Sangguniang Kabataan. Umaasa po kami sa inyong patuloy na suporta sa mga programang inilulunsad ng Sangguniang Kabataan para sa ikabubuti ng ating komunidad. Maraming salamat po.
                </p>

                <p style="margin:0 0 4px 0;">Lubos na gumagalang,</p>

                <div style="margin-top:32px; margin-bottom:40px;">
                    <div style="display:inline-block; border:1px dashed #b0b8c8; border-radius:6px; padding:6px 10px; min-width:220px; min-height:80px; position:relative; background:transparent; text-align:left; cursor:pointer;" id="skChairSigWrap" title="Click to upload SK Chairperson signature">
                        <img id="skChairSigImg" src="" alt="SK Chairperson Signature" style="display:none; max-width:200px; max-height:80px; object-fit:contain; mix-blend-mode:multiply; background:transparent;" contenteditable="false">
                        <span id="skChairSigPlaceholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8; pointer-events:none;">Click to upload signature</span>
                        <input type="file" id="skChairSigInput" accept="image/png,image/gif,image/svg+xml,image/*" style="display:none;">
                    </div>
                    <div style="margin-top:6px;">
                        <div style="width:220px; border-bottom:1.5px solid #000; margin-bottom:3px;"></div>
                        <p style="margin:0; font-size:13px;">SK Chairperson - (Name of Brgy.)</p>
                    </div>
                </div>

                <div id="skNotedBySection" style="margin-top:8px;">
                    <p style="margin:0 0 4px 0; font-weight:600;">NOTED BY:</p>
                    <div style="display:inline-block; border:1px dashed #b0b8c8; border-radius:6px; padding:6px 10px; min-width:220px; min-height:80px; position:relative; background:transparent; text-align:left; cursor:pointer;" id="skNotedByImgWrap" title="Click to upload signature image">
                        <img id="skNotedByImg" src="" alt="Signature" style="display:none; max-width:200px; max-height:80px; object-fit:contain; mix-blend-mode:multiply; background:transparent;" contenteditable="false">
                        <span id="skNotedByPlaceholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8; pointer-events:none;">Click to upload signature</span>
                        <input type="file" id="skNotedBySigInput" accept="image/png,image/gif,image/svg+xml,image/*" class="hidden" style="display:none;">
                    </div>
                    <div style="margin-top:6px;">
                        <div style="width:220px; border-bottom:1.5px solid #000; margin-bottom:3px;"></div>
                        <p style="margin:0; font-weight:700; text-decoration:underline; font-size:13px;">HON. BRIAN E. FRESCO, LPT</p>
                        <p style="margin:0; font-size:12px;">SK PEDERASYON PRESIDENT-MAJAYJAY</p>
                    </div>
                </div>

            </div>`;
        }

        /* ─── Wire up SK Chairperson signature upload ─── */
        function _skBindChairSig() {
            const wrap  = document.getElementById('skChairSigWrap');
            const input = document.getElementById('skChairSigInput');
            const img   = document.getElementById('skChairSigImg');
            const ph    = document.getElementById('skChairSigPlaceholder');
            if (!wrap || !input || !img) return;

            wrap.addEventListener('click', function(e) {
                if (e.target === img) return;
                input.click();
            });

            input.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(ev) {
                    img.src = ev.target.result;
                    img.style.display = 'block';
                    if (ph) ph.style.display = 'none';
                    wrap.style.border = 'none';
                    wrap.style.padding = '0';
                    showToast('SK Chairperson signature uploaded!', 'success');
                };
                reader.readAsDataURL(file);
                this.value = '';
            });
        }

        /* ─── Re-wire helpers: used when loading from localStorage cache ─── */
        /* File inputs are stripped before saving; these inject fresh ones back in */
        function _skRewireNotedBy() {
            const wrap = document.getElementById('skNotedByImgWrap');
            const img  = document.getElementById('skNotedByImg');
            const ph   = document.getElementById('skNotedByPlaceholder');
            if (!wrap) return;
            // Remove any leftover input, then add a fresh one
            var old = document.getElementById('skNotedBySigInput');
            if (old) old.remove();
            var input = document.createElement('input');
            input.type = 'file'; input.id = 'skNotedBySigInput';
            input.accept = 'image/png,image/gif,image/svg+xml,image/*';
            input.style.display = 'none';
            wrap.appendChild(input);
            wrap.addEventListener('click', function(e) {
                if (e.target === img) return;
                input.click();
            });
            input.addEventListener('change', function() {
                var file = this.files[0]; if (!file) return;
                var reader = new FileReader();
                reader.onload = function(ev) {
                    img.src = ev.target.result; img.style.display = 'block';
                    if (ph) ph.style.display = 'none';
                    wrap.style.border = 'none'; wrap.style.padding = '0';
                    showToast('Signature image uploaded!', 'success');
                };
                reader.readAsDataURL(file); this.value = '';
            });
        }

        function _skRewireChairSig() {
            const wrap = document.getElementById('skChairSigWrap');
            const img  = document.getElementById('skChairSigImg');
            const ph   = document.getElementById('skChairSigPlaceholder');
            if (!wrap) return;
            var old = document.getElementById('skChairSigInput');
            if (old) old.remove();
            var input = document.createElement('input');
            input.type = 'file'; input.id = 'skChairSigInput';
            input.accept = 'image/png,image/gif,image/svg+xml,image/*';
            input.style.display = 'none';
            wrap.appendChild(input);
            wrap.addEventListener('click', function(e) {
                if (e.target === img) return;
                input.click();
            });
            input.addEventListener('change', function() {
                var file = this.files[0]; if (!file) return;
                var reader = new FileReader();
                reader.onload = function(ev) {
                    img.src = ev.target.result; img.style.display = 'block';
                    if (ph) ph.style.display = 'none';
                    wrap.style.border = 'none'; wrap.style.padding = '0';
                    showToast('SK Chairperson signature uploaded!', 'success');
                };
                reader.readAsDataURL(file); this.value = '';
            });
        }

        /* ─── Wire up NOTED BY image upload ─── */
        function _skBindNotedBy() {
            const wrap  = document.getElementById('skNotedByImgWrap');
            const input = document.getElementById('skNotedBySigInput');
            const img   = document.getElementById('skNotedByImg');
            const ph    = document.getElementById('skNotedByPlaceholder');
            if (!wrap || !input || !img) return;

            wrap.addEventListener('click', function(e) {
                // Don't re-trigger if clicking on the img itself
                if (e.target === img) return;
                input.click();
            });

            input.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(ev) {
                    img.src = ev.target.result;
                    img.style.display = 'block';
                    if (ph) ph.style.display = 'none';
                    wrap.style.border = 'none';
                    wrap.style.padding = '0';
                    showToast('Signature image uploaded!', 'success');
                };
                reader.readAsDataURL(file);
                this.value = '';
            });
        }

        function openSkFormatEditor() {
            document.getElementById('skFormatModal').style.display = 'flex';
            document.getElementById('skFormatLoading').classList.remove('hidden');
            document.getElementById('skFormatBody').classList.add('hidden');
            document.getElementById('skFormatFooter').classList.add('hidden');
            document.getElementById('skFormatStatus').classList.add('hidden');

            const editor = document.getElementById('skFormatEditor');

            // ── Check localStorage cache first (set by saveSkFormat on successful save) ──
            let cachedHtml = null;
            try { cachedHtml = localStorage.getItem('sk_format_html_cache'); } catch(e) {}

            if (cachedHtml && cachedHtml.trim().length > 0) {
                // Instantly load the last-saved version — no server round-trip needed
                document.getElementById('skFormatLoading').classList.add('hidden');
                document.getElementById('skFormatBody').classList.remove('hidden');
                document.getElementById('skFormatFooter').classList.remove('hidden');
                editor.innerHTML = cachedHtml;
                // Re-wire interactive sig uploads (file inputs were stripped on save)
                _skRewireNotedBy();
                _skRewireChairSig();
                return;
            }

            // ── No cache: fetch from server (first-time load or cache was cleared) ──
            fetch('get_sk_format.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('skFormatLoading').classList.add('hidden');
                    document.getElementById('skFormatBody').classList.remove('hidden');
                    document.getElementById('skFormatFooter').classList.remove('hidden');
                    if (data.success && data.html && data.html.trim().length > 0) {
                        editor.innerHTML = data.html;
                        // If the server HTML is missing the interactive NOTED BY block, use full template
                        if (!editor.querySelector('#skNotedBySection')) {
                            editor.innerHTML = _skFullDocTemplate();
                        }
                    } else {
                        editor.innerHTML = _skFullDocTemplate();
                    }
                    _skBindNotedBy();
                    _skBindChairSig();
                })
                .catch(() => {
                    document.getElementById('skFormatLoading').classList.add('hidden');
                    document.getElementById('skFormatBody').classList.remove('hidden');
                    document.getElementById('skFormatFooter').classList.remove('hidden');
                    editor.innerHTML = _skFullDocTemplate();
                    _skBindNotedBy();
                    _skBindChairSig();
                });
        }

        let skFormatIsFullscreen = false;
        function toggleSkFormatFullscreen() {
            const modalInner = document.querySelector('#skFormatModal > div');
            const icon = document.getElementById('skFormatFullscreenIcon');
            skFormatIsFullscreen = !skFormatIsFullscreen;
            if (skFormatIsFullscreen) {
                modalInner.style.width = '100%';
                modalInner.style.maxWidth = '100%';
                modalInner.style.maxHeight = '100vh';
                modalInner.style.height = '100vh';
                modalInner.style.borderRadius = '0';
                icon.className = 'fa-solid fa-compress';
                document.getElementById('skFormatFullscreenBtn').title = 'Exit Fullscreen';
            } else {
                modalInner.style.width = '90%';
                modalInner.style.maxWidth = '720px';
                modalInner.style.maxHeight = '92vh';
                modalInner.style.height = '';
                modalInner.style.borderRadius = '';
                icon.className = 'fa-solid fa-expand';
                document.getElementById('skFormatFullscreenBtn').title = 'Fullscreen';
            }
        }

        function closeSkFormatEditor() {
            document.getElementById('skFormatModal').style.display = 'none';
            // Reset fullscreen if active
            if (skFormatIsFullscreen) {
                const modalInner = document.querySelector('#skFormatModal > div');
                modalInner.style.width = '90%';
                modalInner.style.maxWidth = '720px';
                modalInner.style.maxHeight = '92vh';
                modalInner.style.height = '';
                modalInner.style.borderRadius = '';
                document.getElementById('skFormatFullscreenIcon').className = 'fa-solid fa-expand';
                document.getElementById('skFormatFullscreenBtn').title = 'Fullscreen';
                skFormatIsFullscreen = false;
            }
            // Reset file input
            document.getElementById('skFormatFileInput').value = '';
            document.getElementById('skFormatFileName').textContent = 'No file chosen';
            document.getElementById('skFormatUploadBtn').style.display = 'none';
        }

        function saveSkFormat() {
            const btn = document.getElementById('skFormatSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving...';

            // Clean up file inputs from clone so they don't bloat the saved HTML
            const editor = document.getElementById('skFormatEditor');
            const cleanClone = editor.cloneNode(true);
            cleanClone.querySelectorAll('input[type="file"]').forEach(function(el){ el.remove(); });
            const htmlToSave = cleanClone.innerHTML;

            // ── Persist to localStorage so reopening always shows latest saved version ──
            try {
                localStorage.setItem('sk_format_html_cache', htmlToSave);
                localStorage.setItem('sk_format_html_cache_ts', Date.now().toString());
            } catch(e) { /* storage quota – ignore */ }

            const formData = new FormData();
            formData.append('html_content', htmlToSave);

            fetch('save_sk_format.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes';
                    showSkFormatStatus(data.success, data.message || (data.success ? 'SK_FORMAT.docx updated successfully!' : 'Save failed.'));
                    if (data.success) {
                        showToast('SK Format saved successfully!', 'success');
                    } else {
                        // Server save failed – clear cache to avoid showing stale HTML next time
                        try { localStorage.removeItem('sk_format_html_cache'); } catch(e) {}
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes';
                    showSkFormatStatus(false, 'Network error. Could not save.');
                    try { localStorage.removeItem('sk_format_html_cache'); } catch(e) {}
                });
        }

        function handleSkFormatFileChange(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('skFormatFileName').textContent = file.name;
                document.getElementById('skFormatUploadBtn').style.display = 'inline-flex';
            } else {
                document.getElementById('skFormatFileName').textContent = 'No file chosen';
                document.getElementById('skFormatUploadBtn').style.display = 'none';
            }
        }

        function uploadSkFormat() {
            const fileInput = document.getElementById('skFormatFileInput');
            const file = fileInput.files[0];
            if (!file) return;

            const btn = document.getElementById('skFormatUploadBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Uploading...';

            const formData = new FormData();
            formData.append('sk_format_file', file);

            fetch('save_sk_format.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload & Replace';
                    showSkFormatStatus(data.success, data.message || (data.success ? 'SK_FORMAT.docx replaced successfully!' : 'Upload failed.'));
                    if (data.success) {
                        showToast('SK Format replaced successfully!', 'success');
                        // Clear cache so the editor loads the newly uploaded file content
                        try { localStorage.removeItem('sk_format_html_cache'); } catch(e) {}
                        // Reload the editor content from new file
                        setTimeout(() => openSkFormatEditor(), 800);
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload & Replace';
                    showSkFormatStatus(false, 'Network error. Could not upload.');
                });
        }

        function showSkFormatStatus(success, msg) {
            const el = document.getElementById('skFormatStatus');
            el.className = success
                ? 'flex items-center gap-2 px-5 py-3 text-xs font-semibold text-emerald-700 bg-emerald-50 border-b border-emerald-100'
                : 'flex items-center gap-2 px-5 py-3 text-xs font-semibold text-rose-600 bg-rose-50 border-b border-rose-100';
            el.innerHTML = `<i class="fa-solid ${success ? 'fa-check-circle text-emerald-500' : 'fa-circle-xmark text-rose-500'}"></i> ${msg}`;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }
    </script>
    <!-- ===== END SK FORMAT EDITOR SCRIPT ===== -->

    <!-- ===== SK IMAGE EDITOR OVERLAY ===== -->
    <!-- Resize handles overlay (fixed position, tracks selected image) -->
    <div id="skImgOverlay">
        <div class="sk-img-border"></div>
        <div class="sk-img-handle sk-handle-nw" data-handle="nw"></div>
        <div class="sk-img-handle sk-handle-n"  data-handle="n"></div>
        <div class="sk-img-handle sk-handle-ne" data-handle="ne"></div>
        <div class="sk-img-handle sk-handle-e"  data-handle="e"></div>
        <div class="sk-img-handle sk-handle-se" data-handle="se"></div>
        <div class="sk-img-handle sk-handle-s"  data-handle="s"></div>
        <div class="sk-img-handle sk-handle-sw" data-handle="sw"></div>
        <div class="sk-img-handle sk-handle-w"  data-handle="w"></div>
    </div>

    <!-- Floating toolbar (appears above selected image) -->
    <div id="skImgToolbar">
        <span class="sk-tb-label">Wrap</span>
        <button class="sk-tb-btn" id="skWrapNone"   title="No Wrap (Block)"   onclick="skImgSetWrap('none')"><i class="fa-solid fa-square"></i></button>
        <button class="sk-tb-btn" id="skWrapLeft"   title="Wrap Left"         onclick="skImgSetWrap('left')"><i class="fa-solid fa-object-group fa-flip-horizontal"></i></button>
        <button class="sk-tb-btn" id="skWrapRight"  title="Wrap Right"        onclick="skImgSetWrap('right')"><i class="fa-solid fa-object-group"></i></button>
        <button class="sk-tb-btn" id="skWrapInline" title="Inline"            onclick="skImgSetWrap('inline')"><i class="fa-solid fa-minus"></i></button>
        <div class="sk-tb-sep"></div>
        <span class="sk-tb-label">Align</span>
        <button class="sk-tb-btn" id="skAlignLeft"   title="Align Left"   onclick="skImgSetAlign('left')"><i class="fa-solid fa-align-left"></i></button>
        <button class="sk-tb-btn" id="skAlignCenter" title="Align Center" onclick="skImgSetAlign('center')"><i class="fa-solid fa-align-center"></i></button>
        <button class="sk-tb-btn" id="skAlignRight"  title="Align Right"  onclick="skImgSetAlign('right')"><i class="fa-solid fa-align-right"></i></button>
        <div class="sk-tb-sep"></div>
        <span class="sk-tb-label">Size</span>
        <button class="sk-tb-btn" title="Original Size" onclick="skImgResetSize()"><i class="fa-solid fa-expand-arrows-alt"></i></button>
        <button class="sk-tb-btn" title="Fit to Width"  onclick="skImgFitWidth()"><i class="fa-solid fa-arrows-left-right"></i></button>
        <div class="sk-tb-sep"></div>
        <button class="sk-tb-btn" title="Delete Image" onclick="skImgDelete()" style="color:#f87171;">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
    <!-- ===== END SK IMAGE EDITOR OVERLAY ===== -->

    <!-- ===== WORD-STYLE RIGHT-CLICK CONTEXT MENU ===== -->
    <div id="skCtxMenu">

        <!-- Cut / Copy -->
        <div class="wctx-item" onclick="skCtxDo('cut')">
            <span class="wctx-icon"><i class="fa-solid fa-scissors"></i></span>
            <span>Cut</span>
        </div>
        <div class="wctx-item" onclick="skCtxDo('copy')">
            <span class="wctx-icon"><i class="fa-regular fa-copy"></i></span>
            <span>Copy</span>
        </div>

        <!-- Paste Options -->
        <div class="wctx-sep"></div>
        <div class="wctx-paste-row">
            <span class="wctx-paste-label">Paste Options:</span>
        </div>
        <div class="wctx-paste-row">
            <button class="wctx-paste-btn" title="Keep Source Formatting" onclick="skCtxDo('paste-keep')">
                <i class="fa-solid fa-clipboard"></i>
            </button>
            <button class="wctx-paste-btn" title="Paste as Text" onclick="skCtxDo('paste-text')" style="font-size:11px; font-weight:700; letter-spacing:-0.5px;">A</button>
        </div>
        <div class="wctx-sep"></div>

        <!-- Edit Picture -->
        <div class="wctx-item disabled">
            <span class="wctx-icon"></span>
            <span>Edit Picture</span>
        </div>

        <!-- Change Picture submenu -->
        <div class="wctx-item wctx-submenu">
            <span class="wctx-icon"><i class="fa-solid fa-arrows-rotate"></i></span>
            <span>Change Picture</span>
            <span class="wctx-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <div class="wctx-sub-panel">
                <label class="wctx-item" style="cursor:pointer;">
                    <span class="wctx-icon"><i class="fa-solid fa-folder-open"></i></span>
                    <span>From a File…</span>
                    <input type="file" id="skCtxFileInput" accept="image/*" class="hidden" onchange="skCtxChangePic(this)">
                </label>
                <div class="wctx-item disabled">
                    <span class="wctx-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span>From Online Sources…</span>
                </div>
                <div class="wctx-item disabled">
                    <span class="wctx-icon"><i class="fa-regular fa-clipboard"></i></span>
                    <span>From Clipboard</span>
                </div>
            </div>
        </div>

        <!-- Group (disabled) -->
        <div class="wctx-item wctx-submenu disabled">
            <span class="wctx-icon"><i class="fa-solid fa-object-group"></i></span>
            <span>Group</span>
            <span class="wctx-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>

        <!-- Bring to Front -->
        <div class="wctx-item wctx-submenu">
            <span class="wctx-icon"><i class="fa-solid fa-layer-group"></i></span>
            <span>Bring to Front</span>
            <span class="wctx-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <div class="wctx-sub-panel">
                <div class="wctx-item" onclick="skCtxDo('bring-front')">
                    <span class="wctx-icon"><i class="fa-solid fa-layer-group"></i></span>
                    <span>Bring to Front</span>
                </div>
                <div class="wctx-item" onclick="skCtxDo('bring-forward')">
                    <span class="wctx-icon"><i class="fa-solid fa-arrow-up"></i></span>
                    <span>Bring Forward</span>
                </div>
            </div>
        </div>

        <!-- Send to Back -->
        <div class="wctx-item wctx-submenu">
            <span class="wctx-icon"><i class="fa-solid fa-layer-group" style="transform:scaleY(-1)"></i></span>
            <span>Send to Back</span>
            <span class="wctx-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <div class="wctx-sub-panel">
                <div class="wctx-item" onclick="skCtxDo('send-back')">
                    <span class="wctx-icon"><i class="fa-solid fa-layer-group"></i></span>
                    <span>Send to Back</span>
                </div>
                <div class="wctx-item" onclick="skCtxDo('send-backward')">
                    <span class="wctx-icon"><i class="fa-solid fa-arrow-down"></i></span>
                    <span>Send Backward</span>
                </div>
            </div>
        </div>

        <!-- Link (disabled) -->
        <div class="wctx-item disabled">
            <span class="wctx-icon"><i class="fa-solid fa-link"></i></span>
            <span>Link</span>
        </div>

        <!-- Save as Picture -->
        <div class="wctx-item" onclick="skCtxDo('save-as')">
            <span class="wctx-icon"><i class="fa-regular fa-floppy-disk"></i></span>
            <span>Save as Picture…</span>
        </div>

        <!-- Insert Caption (disabled) -->
        <div class="wctx-item disabled">
            <span class="wctx-icon"><i class="fa-solid fa-tag"></i></span>
            <span>Insert Caption…</span>
        </div>

        <div class="wctx-sep"></div>

        <!-- Wrap Text submenu — full Word options -->
        <div class="wctx-item wctx-submenu wctx-bold" id="skCtxWrapParent">
            <span class="wctx-icon"><i class="fa-solid fa-text-width"></i></span>
            <span>Wrap Text</span>
            <span class="wctx-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <div class="wctx-sub-panel" id="skCtxWrapPanel">
                <div class="wctx-item" id="wctx-inline-text" onclick="skCtxWrap('inline-text')">
                    <span class="wctx-check" id="wcheck-inline-text"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-minus"></i></span>
                    <span>In Line with Text</span>
                </div>
                <div class="wctx-item" id="wctx-square" onclick="skCtxWrap('square')">
                    <span class="wctx-check" id="wcheck-square"></span>
                    <span class="wctx-icon"><i class="fa-regular fa-square"></i></span>
                    <span>Square</span>
                </div>
                <div class="wctx-item" id="wctx-tight" onclick="skCtxWrap('tight')">
                    <span class="wctx-check" id="wcheck-tight"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-compress"></i></span>
                    <span>Tight</span>
                </div>
                <div class="wctx-item" id="wctx-through" onclick="skCtxWrap('through')">
                    <span class="wctx-check" id="wcheck-through"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-align-justify"></i></span>
                    <span>Through</span>
                </div>
                <div class="wctx-item" id="wctx-top-bottom" onclick="skCtxWrap('top-bottom')">
                    <span class="wctx-check" id="wcheck-top-bottom"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-grip-lines"></i></span>
                    <span>Top and Bottom</span>
                </div>
                <div class="wctx-item" id="wctx-behind-text" onclick="skCtxWrap('behind-text')">
                    <span class="wctx-check" id="wcheck-behind-text"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-align-left"></i></span>
                    <span>Behind Text</span>
                </div>
                <div class="wctx-item" id="wctx-in-front" onclick="skCtxWrap('in-front')">
                    <span class="wctx-check" id="wcheck-in-front"></span>
                    <span class="wctx-icon"><i class="fa-solid fa-align-right"></i></span>
                    <span>In Front of Text</span>
                </div>
                <div class="wctx-sep"></div>
                <div class="wctx-item disabled">
                    <span class="wctx-icon"><i class="fa-solid fa-draw-polygon"></i></span>
                    <span>Edit Wrap Points</span>
                </div>
                <div class="wctx-item" id="wctx-move-with" onclick="skCtxWrap('move-with')">
                    <span class="wctx-check" id="wcheck-move-with">✓</span>
                    <span class="wctx-icon"></span>
                    <span>Move with Text</span>
                </div>
                <div class="wctx-item disabled">
                    <span class="wctx-check"></span>
                    <span class="wctx-icon"></span>
                    <span>Fix Position on Page</span>
                </div>
                <div class="wctx-sep"></div>
                <div class="wctx-item disabled">
                    <span class="wctx-icon"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                    <span>More Layout Options…</span>
                </div>
                <div class="wctx-item disabled">
                    <span class="wctx-icon"></span>
                    <span>Set as Default Layout</span>
                </div>
            </div>
        </div>

        <!-- Edit Alt Text -->
        <div class="wctx-item" onclick="skCtxDo('alt-text')">
            <span class="wctx-icon"><i class="fa-solid fa-pen-to-square"></i></span>
            <span>Edit Alt Text…</span>
        </div>

        <!-- Size and Position -->
        <div class="wctx-item" onclick="skCtxDo('size-pos')">
            <span class="wctx-icon"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
            <span>Size and Position…</span>
        </div>

        <!-- Format Picture -->
        <div class="wctx-item" onclick="skCtxDo('format-picture')">
            <span class="wctx-icon"><i class="fa-solid fa-sliders"></i></span>
            <span>Format Picture…</span>
        </div>

    </div>
    <!-- ===== END WORD-STYLE RIGHT-CLICK CONTEXT MENU ===== -->

    <!-- Size & Position Modal -->
    <div id="skSizePosModal">
        <div class="sp-card">
            <div class="sp-head">
                <h4><i class="fa-solid fa-up-right-and-down-left-from-center mr-2 text-[#FFD700]"></i>Size &amp; Position</h4>
                <button onclick="document.getElementById('skSizePosModal').classList.remove('open')" style="background:none;border:none;color:white;cursor:pointer;font-size:16px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="sp-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Width (px)</label>
                        <input type="number" id="skSpWidth" min="10" placeholder="e.g. 300">
                    </div>
                    <div>
                        <label>Height (px)</label>
                        <input type="number" id="skSpHeight" min="10" placeholder="e.g. 200">
                    </div>
                </div>
                <div class="sp-lock">
                    <input type="checkbox" id="skSpLock" checked>
                    <span>Lock aspect ratio</span>
                </div>
                <div class="sp-footer">
                    <button class="sp-cancel" onclick="document.getElementById('skSizePosModal').classList.remove('open')">Cancel</button>
                    <button class="sp-apply" onclick="skApplySizePos()">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alt Text Modal -->
    <div id="skAltTextModal">
        <div class="sp-card" style="width:320px; background:white; border-radius:12px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,0.2);">
            <div class="sp-head">
                <h4><i class="fa-solid fa-pen-to-square mr-2 text-[#FFD700]"></i>Edit Alt Text</h4>
                <button onclick="document.getElementById('skAltTextModal').classList.remove('open')" style="background:none;border:none;color:white;cursor:pointer;font-size:16px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="sp-body">
                <label>Alt Text</label>
                <input type="text" id="skAltInput" placeholder="Describe this image for accessibility…" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:7px 10px; font-size:13px; color:#1e293b; outline:none;">
                <p style="font-size:10px; color:#94a3b8; margin-top:6px;">Alt text helps screen readers describe the image to visually impaired users.</p>
                <div class="sp-footer">
                    <button class="sp-cancel" onclick="document.getElementById('skAltTextModal').classList.remove('open')">Cancel</button>
                    <button class="sp-apply" onclick="skApplyAltText()">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SK IMAGE EDITOR SCRIPT ===== -->
    <script>
    (function() {
        let skSelImg      = null;   // currently selected image
        let skResizing    = false;
        let skHandle      = '';
        let skStartX      = 0, skStartY = 0;
        let skStartW      = 0, skStartH = 0;
        let skStartLeft   = 0, skStartTop = 0; // for NW/W/SW handles (move origin)
        let skAspect      = 1;

        const overlay  = document.getElementById('skImgOverlay');
        const toolbar  = document.getElementById('skImgToolbar');

        /* ── Position overlay & toolbar over selected image ── */
        function skSyncUI() {
            if (!skSelImg) return;
            const r = skSelImg.getBoundingClientRect();

            // Overlay
            overlay.style.left   = r.left + 'px';
            overlay.style.top    = r.top  + 'px';
            overlay.style.width  = r.width  + 'px';
            overlay.style.height = r.height + 'px';

            // Toolbar: centred above image, keep on screen
            const tbW = toolbar.offsetWidth || 420;
            let tbLeft = r.left + r.width / 2;
            tbLeft = Math.max(tbW / 2 + 8, Math.min(window.innerWidth - tbW / 2 - 8, tbLeft));
            let tbTop  = r.top - 44;
            if (tbTop < 8) tbTop = r.bottom + 8;
            toolbar.style.left = tbLeft + 'px';
            toolbar.style.top  = tbTop  + 'px';

            skUpdateWrapButtons();
        }

        function skShowUI(img) {
            overlay.classList.add('active');
            toolbar.classList.add('active');
            skSelImg = img;
            skSyncUI();
        }

        function skHideUI() {
            overlay.classList.remove('active');
            toolbar.classList.remove('active');
            if (skSelImg) { skSelImg.style.outline = ''; skSelImg = null; }
        }

        /* ── Update active state on wrap buttons ── */
        function skUpdateWrapButtons() {
            if (!skSelImg) return;
            const fl = skSelImg.style.float || '';
            const di = skSelImg.style.display || '';
            document.getElementById('skWrapNone').classList.toggle('active',   !fl && di !== 'inline');
            document.getElementById('skWrapLeft').classList.toggle('active',   fl === 'left');
            document.getElementById('skWrapRight').classList.toggle('active',  fl === 'right');
            document.getElementById('skWrapInline').classList.toggle('active', di === 'inline');
        }

        /* ── Wrap text options ── */
        window.skImgSetWrap = function(mode) {
            if (!skSelImg) return;
            const img = skSelImg;
            // Reset
            img.style.float       = '';
            img.style.display     = 'block';
            img.style.marginLeft  = '';
            img.style.marginRight = '';
            img.style.verticalAlign = '';

            if (mode === 'left') {
                img.style.float       = 'left';
                img.style.marginRight = '14px';
                img.style.marginBottom= '6px';
            } else if (mode === 'right') {
                img.style.float       = 'right';
                img.style.marginLeft  = '14px';
                img.style.marginBottom= '6px';
            } else if (mode === 'inline') {
                img.style.display       = 'inline';
                img.style.verticalAlign = 'middle';
            } else {
                // block / no wrap
                img.style.display = 'block';
            }
            requestAnimationFrame(skSyncUI);
        };

        /* ── Alignment (for block images) ── */
        window.skImgSetAlign = function(align) {
            if (!skSelImg) return;
            const img = skSelImg;
            img.style.float       = '';
            img.style.display     = 'block';
            if (align === 'left')   { img.style.marginLeft = '0';    img.style.marginRight = 'auto'; }
            if (align === 'center') { img.style.marginLeft = 'auto'; img.style.marginRight = 'auto'; }
            if (align === 'right')  { img.style.marginLeft = 'auto'; img.style.marginRight = '0'; }
            requestAnimationFrame(skSyncUI);
        };

        /* ── Size shortcuts ── */
        window.skImgResetSize = function() {
            if (!skSelImg) return;
            skSelImg.style.width  = '';
            skSelImg.style.height = '';
            requestAnimationFrame(skSyncUI);
        };

        window.skImgFitWidth = function() {
            if (!skSelImg) return;
            skSelImg.style.width  = '100%';
            skSelImg.style.height = 'auto';
            requestAnimationFrame(skSyncUI);
        };

        /* ── Delete ── */
        window.skImgDelete = function() {
            if (!skSelImg) return;
            skSelImg.remove();
            skHideUI();
        };

        /* ── Click on editor to select / deselect image ── */
        document.addEventListener('click', function(e) {
            const editor = document.getElementById('skFormatEditor');
            if (!editor) return;

            if (e.target.tagName === 'IMG' && editor.contains(e.target)) {
                skShowUI(e.target);
                e.preventDefault();
                return;
            }

            // Clicks inside overlay/toolbar: don't deselect
            if (overlay.contains(e.target) || toolbar.contains(e.target)) return;

            // Anything else: deselect
            if (skSelImg) skHideUI();
        });

        /* ── Resize via handles ── */
        overlay.addEventListener('mousedown', function(e) {
            const handle = e.target.dataset.handle;
            if (!handle || !skSelImg) return;
            e.preventDefault();

            skResizing  = true;
            skHandle    = handle;
            skStartX    = e.clientX;
            skStartY    = e.clientY;
            skStartW    = skSelImg.getBoundingClientRect().width;
            skStartH    = skSelImg.getBoundingClientRect().height;
            skAspect    = skStartW / (skStartH || 1);

            document.body.style.userSelect = 'none';
            document.body.style.cursor = e.target.style.cursor;
        });

        document.addEventListener('mousemove', function(e) {
            if (!skResizing || !skSelImg) return;

            const dx = e.clientX - skStartX;
            const dy = e.clientY - skStartY;
            let newW = skStartW;
            let newH = skStartH;

            // Compute new dimensions based on which handle is dragged
            const h = skHandle;
            const shiftHeld = e.shiftKey; // hold Shift = lock aspect ratio

            if (h === 'e'  || h === 'ne' || h === 'se') newW = Math.max(40, skStartW + dx);
            if (h === 'w'  || h === 'nw' || h === 'sw') newW = Math.max(40, skStartW - dx);
            if (h === 's'  || h === 'se' || h === 'sw') newH = Math.max(40, skStartH + dy);
            if (h === 'n'  || h === 'ne' || h === 'nw') newH = Math.max(40, skStartH - dy);

            if (h === 'e' || h === 'w') {
                // width-only handle → maintain aspect if shift
                if (shiftHeld) newH = newW / skAspect;
            } else if (h === 'n' || h === 's') {
                if (shiftHeld) newW = newH * skAspect;
            } else {
                // corner handle → always lock aspect ratio (like Word)
                const wChange = Math.abs(dx) > Math.abs(dy) ? dx : (dy * skAspect);
                newW = Math.max(40, h.includes('e') ? skStartW + wChange : skStartW - wChange);
                newH = newW / skAspect;
            }

            skSelImg.style.width  = Math.round(newW) + 'px';
            skSelImg.style.height = Math.round(newH) + 'px';

            skSyncUI();
        });

        document.addEventListener('mouseup', function() {
            if (skResizing) {
                skResizing = false;
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
            }
        });

        /* ── Keep overlay in sync on scroll / window resize ── */
        window.addEventListener('scroll',  skSyncUI, true);
        window.addEventListener('resize',  skSyncUI);

        /* ── Init when SK Format editor opens ── */
        const origOpen = window.openSkFormatEditor;
        window.openSkFormatEditor = function() {
            origOpen();
            // Attach click listeners when editor content loads
            setTimeout(function() {
                const editor = document.getElementById('skFormatEditor');
                if (editor) {
                    // Observe for new images inserted later
                    const obs = new MutationObserver(function() { /* handled via document click */ });
                    obs.observe(editor, { childList: true, subtree: true });
                }
            }, 800);
        };

        /* ── Hide overlay when editor closes ── */
        const origClose = window.closeSkFormatEditor;
        window.closeSkFormatEditor = function() {
            skHideUI();
            origClose();
        };
    })();
    </script>
    <!-- ===== END SK IMAGE EDITOR SCRIPT ===== -->

    <!-- ===== WORD-STYLE CONTEXT MENU SCRIPT ===== -->
    <script>
    (function(){
        const ctxMenu  = document.getElementById('skCtxMenu');
        let _ctxImg    = null;   // image that was right-clicked
        let _ctxWrap   = 'inline-text'; // current wrap mode
        let _spNatW    = 0, _spNatH = 0; // natural dims for aspect lock

        /* ─── Open on right-click over editor image ─── */
        document.addEventListener('contextmenu', function(e){
            const editor = document.getElementById('skFormatEditor');
            if (!editor) return;
            if (e.target.tagName === 'IMG' && editor.contains(e.target)){
                e.preventDefault();
                _ctxImg = e.target;

                // Also visually select via existing overlay logic
                _ctxImg.click();

                // Position menu — keep on screen
                const mw = 230, mh = 560;
                let x = e.clientX, y = e.clientY;
                if (x + mw > window.innerWidth  - 4) x = window.innerWidth  - mw - 4;
                if (y + mh > window.innerHeight - 4) y = window.innerHeight - mh - 4;
                if (x < 4) x = 4; if (y < 4) y = 4;
                ctxMenu.style.left = x + 'px';
                ctxMenu.style.top  = y + 'px';
                ctxMenu.classList.add('open');

                // Sync wrap checkmarks
                _syncWrapChecks();
            } else {
                _closeCtx();
            }
        });

        /* ─── Close on outside click / Escape ─── */
        document.addEventListener('click', function(e){
            if (!ctxMenu.contains(e.target)) _closeCtx();
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') _closeCtx();
        });

        function _closeCtx(){ ctxMenu.classList.remove('open'); }

        /* ─── Sync checkmarks in Wrap Text sub-menu ─── */
        const _wrapIds = ['inline-text','square','tight','through','top-bottom','behind-text','in-front','move-with'];
        function _syncWrapChecks(){
            if (!_ctxImg) return;
            const fl = _ctxImg.style.float || '';
            const di = _ctxImg.style.display || 'block';
            if      (di === 'inline') _ctxWrap = 'inline-text';
            else if (fl === 'left')   _ctxWrap = 'square';   // float-left → square
            else if (fl === 'right')  _ctxWrap = 'square';
            else                      _ctxWrap = 'top-bottom';
            _wrapIds.forEach(function(id){
                const el = document.getElementById('wcheck-' + id);
                if (el) el.textContent = (id === _ctxWrap) ? '✓' : '';
            });
        }

        /* ─── Main action dispatcher ─── */
        window.skCtxDo = function(action){
            _closeCtx();
            if (!_ctxImg) return;

            switch(action){
                case 'cut':
                    _ctxImg.remove();
                    if (typeof skHideUI === 'function') skHideUI();
                    _ctxImg = null;
                    _toast('Image cut.', 'success');
                    break;

                case 'copy':
                    _toast('Image copied.', 'success');
                    break;

                case 'paste-keep':
                case 'paste-text':
                    _toast('Paste: use Insert Image to add images.', 'success');
                    break;

                case 'bring-front':
                    _ctxImg.style.zIndex = '999';
                    _ctxImg.style.position = 'relative';
                    _toast('Brought to front.', 'success');
                    break;

                case 'bring-forward':
                    _ctxImg.style.zIndex = String((parseInt(_ctxImg.style.zIndex) || 0) + 1);
                    _ctxImg.style.position = 'relative';
                    _toast('Brought forward.', 'success');
                    break;

                case 'send-back':
                    _ctxImg.style.zIndex = '-1';
                    _ctxImg.style.position = 'relative';
                    _toast('Sent to back.', 'success');
                    break;

                case 'send-backward':
                    _ctxImg.style.zIndex = String((parseInt(_ctxImg.style.zIndex) || 0) - 1);
                    _ctxImg.style.position = 'relative';
                    _toast('Sent backward.', 'success');
                    break;

                case 'save-as':
                    var a = document.createElement('a');
                    a.href = _ctxImg.src;
                    a.download = 'image.png';
                    a.click();
                    _toast('Saving image…', 'success');
                    break;

                case 'alt-text':
                    document.getElementById('skAltInput').value = _ctxImg.alt || '';
                    document.getElementById('skAltTextModal').classList.add('open');
                    break;

                case 'size-pos':
                    _spNatW = _ctxImg.naturalWidth  || _ctxImg.offsetWidth  || 200;
                    _spNatH = _ctxImg.naturalHeight || _ctxImg.offsetHeight || 150;
                    document.getElementById('skSpWidth').value  = Math.round(_ctxImg.getBoundingClientRect().width)  || _spNatW;
                    document.getElementById('skSpHeight').value = Math.round(_ctxImg.getBoundingClientRect().height) || _spNatH;
                    // Wire lock aspect ratio live update
                    var wIn = document.getElementById('skSpWidth');
                    var hIn = document.getElementById('skSpHeight');
                    wIn.oninput = function(){
                        if (document.getElementById('skSpLock').checked){
                            hIn.value = Math.round(parseInt(wIn.value) * (_spNatH / (_spNatW || 1)));
                        }
                    };
                    hIn.oninput = function(){
                        if (document.getElementById('skSpLock').checked){
                            wIn.value = Math.round(parseInt(hIn.value) * (_spNatW / (_spNatH || 1)));
                        }
                    };
                    document.getElementById('skSizePosModal').classList.add('open');
                    break;

                case 'format-picture':
                    _toast('Format Picture: use the toolbar handles to resize.', 'success');
                    break;
            }
        };

        /* ─── Wrap Text actions (full Word list) ─── */
        window.skCtxWrap = function(mode){
            _closeCtx();
            if (!_ctxImg) return;
            const img = _ctxImg;

            // Reset all wrap styles
            img.style.float       = '';
            img.style.display     = 'block';
            img.style.verticalAlign = '';
            img.style.marginLeft  = '';
            img.style.marginRight = '';
            img.style.marginBottom= '';

            switch(mode){
                case 'inline-text':
                    img.style.display       = 'inline';
                    img.style.verticalAlign = 'middle';
                    break;
                case 'square':
                case 'tight':
                case 'through':
                    img.style.float       = 'left';
                    img.style.marginRight = '12px';
                    img.style.marginBottom= '6px';
                    break;
                case 'top-bottom':
                    img.style.display = 'block';
                    img.style.marginLeft  = 'auto';
                    img.style.marginRight = 'auto';
                    break;
                case 'behind-text':
                    img.style.display  = 'block';
                    img.style.opacity  = '0.5';
                    img.style.position = 'relative';
                    img.style.zIndex   = '-1';
                    break;
                case 'in-front':
                    img.style.display  = 'block';
                    img.style.opacity  = '1';
                    img.style.position = 'relative';
                    img.style.zIndex   = '10';
                    break;
                case 'move-with':
                    _toast('Move with Text is active by default.', 'success');
                    return;
            }
            _ctxWrap = mode;
            _syncWrapChecks();

            // Re-sync the overlay position
            if (typeof skSyncUI === 'function') requestAnimationFrame(skSyncUI);

            // Also keep existing toolbar in sync
            if (typeof skImgSetWrap === 'function'){
                const wrapMap = {
                    'inline-text':'inline','square':'left','tight':'left',
                    'through':'left','top-bottom':'none','behind-text':'none','in-front':'none'
                };
                // Don't call skImgSetWrap — we already applied styles above
            }
        };

        /* ─── Change Picture ─── */
        window.skCtxChangePic = function(input){
            if (!_ctxImg || !input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function(ev){
                _ctxImg.src = ev.target.result;
                _toast('Image replaced!', 'success');
            };
            reader.readAsDataURL(input.files[0]);
            input.value = '';
            _closeCtx();
        };

        /* ─── Apply Size & Position ─── */
        window.skApplySizePos = function(){
            if (!_ctxImg) return;
            var w = parseInt(document.getElementById('skSpWidth').value);
            var h = parseInt(document.getElementById('skSpHeight').value);
            if (w > 0) _ctxImg.style.width  = w + 'px';
            if (h > 0) _ctxImg.style.height = h + 'px';
            document.getElementById('skSizePosModal').classList.remove('open');
            if (typeof skSyncUI === 'function') requestAnimationFrame(skSyncUI);
            _toast('Size applied!', 'success');
        };

        /* ─── Apply Alt Text ─── */
        window.skApplyAltText = function(){
            if (_ctxImg) _ctxImg.alt = document.getElementById('skAltInput').value;
            document.getElementById('skAltTextModal').classList.remove('open');
            _toast('Alt text saved!', 'success');
        };

        /* ─── Toast helper ─── */
        function _toast(msg, type){
            if (typeof showToast === 'function'){ showToast(msg, type); return; }
        }

    })();
    </script>
    <!-- ===== END WORD-STYLE CONTEXT MENU SCRIPT ===== -->

</body>
</html>