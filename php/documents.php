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

// --- START: ARCHIVE DOCUMENT LOGIC (EXISTING) ---
// --- START: VIEW BY ADMIN LOGIC (NEW) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_by_admin']) && isset($_POST['document_id'])) {
    if (!isset($conn)) {
        header("Location: documents.php?status=error&message=" . urlencode("Database connection not available."));
        exit;
    }
    $document_id = (int)$_POST['document_id'];
    $message = "An unknown error occurred while updating status.";
    $status = "error";
    try {
        $sql_update = "UPDATE document_submissions SET status = ?, updated_at = NOW() WHERE id = ?";
        $new_status = 'View by Admin';
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt_update->bind_param("si", $new_status, $document_id);
        if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
            $message = "Document status updated to 'View by Admin'.";
            $status = "success";
        } else {
            $message = "No update made. Document may not exist. SQL Error: " . $stmt_update->error;
            error_log($message);
        }
        $stmt_update->close();
    } catch (Exception $e) {
        error_log("Status Update Failed: " . $e->getMessage());
        $message = "Error: " . $e->getMessage();
    }
    header("Location: documents.php?status=" . urlencode($status) . "&message=" . urlencode($message));
    exit;
}
// --- END: VIEW BY ADMIN LOGIC (NEW) ---

// --- START: ARCHIVE DOCUMENT LOGIC (EXISTING) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['archive_document']) && isset($_POST['document_id'])) {
    if (!isset($conn)) {
        header("Location: documents.php?status=error&message=" . urlencode("Database connection not available."));
        exit;
    }

    $document_id = (int)$_POST['document_id'];
    $message = "An unknown error occurred during archival.";
    $status = "error";
    $document_title = "Document";

    try {
        $conn->begin_transaction();
        $sql_max_id = "SELECT COALESCE(MAX(id), 0) AS max_id FROM document_archive";
        $result_max = $conn->query($sql_max_id);
        if (!$result_max) throw new Exception("Error querying max ID: " . $conn->error);
        $row_max = $result_max->fetch_assoc();
        $next_id = (int)$row_max['max_id'] + 1; 

        $sql_fetch = "SELECT user_id, barangay, document_category, title, file_path, status, submitted_at
                      FROM document_submissions WHERE id = ?";
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bind_param("i", $document_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        $document_data = $result->fetch_assoc();
        $stmt_fetch->close();

        if ($document_data) {
            $document_title = $document_data['title'];
            $sql_insert = "INSERT INTO document_archive (id, user_id, barangay, document_category, title, file_path, status, submitted_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iissssss", $next_id, $document_data['user_id'], $document_data['barangay'], $document_data['document_category'], $document_data['title'], $document_data['file_path'], $document_data['status'], $document_data['submitted_at']);
            
            if (!$stmt_insert->execute()) throw new Exception("Insert failed: " . $stmt_insert->error);
            $stmt_insert->close();

            $sql_delete = "DELETE FROM document_submissions WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $document_id);
            if (!$stmt_delete->execute()) throw new Exception("Delete failed: " . $stmt_delete->error);
            $stmt_delete->close();

            if ($conn->commit()) {
                $message = "Document '{$document_title}' archived successfully.";
                $status = "success";
            } else {
                $conn->rollback(); 
                throw new Exception("Transaction commit failed.");
            }
        } else {
            throw new Exception("Document not found.");
        }
    } catch (Exception $e) {
        if (isset($conn)) {
            try { $conn->rollback(); } catch (Exception $rb) { error_log($rb->getMessage()); }
        }
        error_log("Archival Failed: " . $e->getMessage());
        $message = "Error: " . $e->getMessage();
        $status = "error";
    }
    header("Location: documents.php?status=" . urlencode($status) . "&message=" . urlencode($message));
    exit;
}

// --- DATA FETCHING (EXISTING) ---

$documents = [];
$category_counts = ['Minutes of Meeting' => 0, 'SK Resolution' => 0, 'Disbursement File' => 0, 'Other Documents' => 0];
$total_documents = 0;

$barangay_list = [
    "Amonoy", "Bakia", "Balanac", "Balayong", "Banilad", "Banti", "Bitaoy", "Botocan", "Bukal", "Burgos", "Burol", "Coralao", "Gagalot", "Ibabang Banga", "Ibabang Bayucain", "Ilayang Banga", "Ilayang Bayucain", "Isabang", "Malinao", "May-It", "Munting Kawayan", "Olla", "Oobi", "Origuel (Poblacion)", "Panalaban", "Pangil", "Panglan", "Piit", "Pook", "Rizal", "San Francisco (Poblacion)", "San Isidro", "San Miguel (Poblacion)", "San Roque", "Santa Catalina (Poblacion)", "Suba", "Talortor", "Tanawan", "Taytay", "Villa Nogales"
];

$selected_barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

if (isset($conn)) {
    $documents_query = "SELECT id, title, document_category, barangay, submitted_at, file_path FROM document_submissions";
    if ($selected_barangay && in_array($selected_barangay, $barangay_list)) {
        $documents_query .= " WHERE barangay = '" . $conn->real_escape_string($selected_barangay) . "'";
    }
    $documents_query .= " ORDER BY submitted_at DESC";
    $documents_result = $conn->query($documents_query);
    if ($documents_result) {
        $documents = $documents_result->fetch_all(MYSQLI_ASSOC);
        $total_documents = count($documents);
    }
    $counts_query = "SELECT document_category, COUNT(id) as count FROM document_submissions WHERE document_category IN ('Minutes of Meeting', 'SK Resolution', 'Disbursement File')";
    if ($selected_barangay && in_array($selected_barangay, $barangay_list)) {
        $counts_query .= " AND barangay = '" . $conn->real_escape_string($selected_barangay) . "'";
    }
    $counts_query .= " GROUP BY document_category";
    $counts_result = $conn->query($counts_query);
    $counted_categories_sum = 0;
    if ($counts_result) {
        while ($row = $counts_result->fetch_assoc()) {
            $category_counts[$row['document_category']] = (int)$row['count'];
            $counted_categories_sum += (int)$row['count'];
        }
    }
    $category_counts['Other Documents'] = $total_documents - $counted_categories_sum;

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
}

function getTypeBadgeColor($type) {
    switch ($type) {
        case 'Minutes of Meeting': return 'bg-blue-100 text-blue-700';
        case 'SK Resolution':      return 'bg-emerald-100 text-emerald-700';
        case 'Disbursement File':  return 'bg-amber-100 text-amber-700';
        case 'Attendance':         return 'bg-violet-100 text-violet-700';
        case 'Report':             return 'bg-rose-100 text-rose-700';
        case 'Transmittal':        return 'bg-cyan-100 text-cyan-700';
        default:                   return 'bg-slate-100 text-slate-600';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Documents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --navy-primary: #1B1B4B; --gold-accent: #FFD700; --bg-light: #f1f5f9; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--navy-primary); overflow-x: hidden; }
        
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E6E8F0; position: fixed; height: 100vh; z-index: 40; display: flex; flex-direction: column; overflow: hidden; }
        .nav-item { display: flex; align-items: center; padding: 0.6rem 1.25rem; margin: 0.15rem 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; }
        .nav-item i { width: 20px; font-size: 1rem; margin-right: 1rem; display: flex; justify-content: center; }
        .tool-label { font-size: 0.65rem; font-weight: 700; color: #ABB1D1; letter-spacing: 0.05em; padding: 0.75rem 1.5rem 0.25rem; text-transform: uppercase; }
        .nav-tool-item { display: flex; align-items: center; padding: 0.5rem 1.25rem; margin: 0.1rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; position: relative; }
        .nav-tool-item i { font-size: 0.85rem; margin-right: 1rem; width: 20px; text-align: center; }
        .nav-tool-item:hover { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item:hover:not(.active) { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item.active { background: var(--navy-primary); color: white; box-shadow: 0 4px 10px rgba(27, 27, 75, 0.15); border-right: 3px solid var(--gold-accent); }

        .main-content { margin-left: 260px; padding: 1.5rem; min-height: 100vh; }
        .card-white { background: white; border-radius: 20px; border: 1px solid #F0F1F7; padding: 1.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }
        
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }

        .user-menu-item { display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: #4A5568; transition: all 0.2s; cursor: pointer; }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }

        .modal-container { display: none; position: fixed; inset: 0; z-index: 200; align-items: center; justify-content: center; padding: 1rem; }
        .modal-container.show { display: flex; }
        .modal-overlay { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); }
        .modal-content { position: relative; background: white; width: 100%; max-width: 400px; border-radius: 24px; padding: 2rem; text-align: center; transform: scale(0.95); opacity: 0; transition: all 0.3s ease; }
        .modal-container.show .modal-content { transform: scale(1); opacity: 1; }

        #notification-toast { position: fixed; top: 20px; right: 20px; z-index: 100; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        #notification-toast.show { transform: translateX(0); }
    </style>
</head>
<body class="flex">

    <div id="archiveModal" class="modal-container">
        <div class="modal-overlay" onclick="closeArchiveModal()"></div>
        <div class="modal-content shadow-2xl">
            <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fas fa-box-archive"></i></div>
            <h3 class="text-xl font-bold text-[#1B1B4B] mb-2">Archive Document?</h3>
            <p class="text-slate-500 text-sm mb-6">Move <span id="modalDocTitle" class="font-bold text-slate-800"></span> to archive?</p>
            <div class="flex gap-3">
                <button onclick="closeArchiveModal()" class="flex-1 px-4 py-3 rounded-xl border border-gray-200 text-slate-600 font-semibold hover:bg-gray-50 transition">Cancel</button>
                <form id="modalArchiveForm" method="POST" class="flex-1">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <input type="hidden" name="archive_document" value="1">
                    <button type="submit" class="w-full px-4 py-3 rounded-xl bg-[#1B1B4B] text-[#FFD700] font-semibold hover:bg-[#2a2a6b] transition">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <div id="notification-toast" class="card-white border-l-4 p-4 min-w-[300px]">
        <div class="flex items-center gap-3">
            <div id="toast-icon" class="text-xl"></div>
            <div><p id="toast-title" class="font-bold text-sm"></p><p id="toast-message" class="text-xs text-slate-500"></p></div>
        </div>
    </div>

    <aside class="sidebar">
        <div class="p-6 text-lg font-bold text-slate-800 flex items-center gap-3">
            <div class="w-8 h-8 bg-[#1B1B4B] rounded-lg flex items-center justify-center text-[#FFD700]"><i class="fas fa-shield-halved text-sm"></i></div>
            <span class="tracking-tight">MAJAYJAY <span style="color: #FFD700;">SK</span></span>
        </div>
        <nav class="flex-grow">
            <a href="admin_dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
            <div class="tool-label">Tools</div>
            <a href="admin_chat.php" class="nav-tool-item">
                <i class="fa-solid fa-comment-dots"></i><span>Messages</span>
                <?php if($unread_messages_count > 0): ?><span class="tool-badge"><?= $unread_messages_count > 99 ? '99+' : $unread_messages_count ?></span><?php endif; ?>
            </a>
            <a href="uploads/charter/citizen_charter.pdf" target="_blank" class="nav-tool-item"><i class="fa-solid fa-book-open-reader"></i><span>Citizen Charter</span></a>
            <div class="tool-label">Main Menu</div>
            <a href="geo_mapping.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
            <a href="manage_users.php" class="nav-item"><i class="fa-solid fa-users-gear"></i><span>Manage Users</span></a>
            <a href="requests.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i><span>Requests</span></a>
            <a href="documents.php" class="nav-item active"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="form_management.php" class="nav-item"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>
        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-[#1B1B4B]">Documents Repository</h2>
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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php 
            $stats_config = [
                'Minutes' => ['key' => 'Minutes of Meeting', 'icon' => 'fa-users', 'color' => 'bg-blue-50 text-blue-600'],
                'Resolutions' => ['key' => 'SK Resolution', 'icon' => 'fa-file-contract', 'color' => 'bg-emerald-50 text-emerald-600'],
                'Disbursements' => ['key' => 'Disbursement File', 'icon' => 'fa-money-bill-transfer', 'color' => 'bg-amber-50 text-[#FFD700]'],
                'Total Files' => ['key' => 'Total', 'icon' => 'fa-folder-open', 'color' => 'bg-indigo-50 text-[#1B1B4B]']
            ];
            foreach($stats_config as $label => $cfg): 
                $val = ($label === 'Total Files') ? $total_documents : ($category_counts[$cfg['key']] ?? 0);
            ?>
                <div class="card-white flex items-center p-4">
                    <div class="w-10 h-10 <?= $cfg['color'] ?> rounded-xl flex items-center justify-center mr-3"><i class="fas <?= $cfg['icon'] ?> text-sm"></i></div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"><?= $label ?></p>
                        <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($val) ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-sm font-bold text-[#1B1B4B]">Official Files</h3>
                <div class="flex items-center gap-2">
                    <form method="get" id="barangaySortForm">
                        <select name="barangay" onchange="document.getElementById('barangaySortForm').submit()" class="bg-white border border-gray-200 text-[10px] font-bold px-3 py-1.5 rounded-lg outline-none mr-2">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangay_list as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $selected_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a href="documents_archive.php" class="px-3 py-1.5 text-[10px] font-bold bg-gray-100 rounded-lg hover:bg-gray-200 transition flex items-center gap-2">
                        <i class="fas fa-archive"></i> Archive Vault
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 text-[10px] uppercase tracking-wider border-b border-gray-50">
                            <th class="pb-3 font-bold">Document Details</th>
                            <th class="pb-3 font-bold">Category</th>
                            <th class="pb-3 font-bold">Barangay</th>
                            <th class="pb-3 font-bold">Date Filed</th>
                            <th class="pb-3 text-center font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <?php if (!empty($documents)): foreach ($documents as $doc): ?>
                            <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                                <td class="py-4">
                                    <p class="font-bold text-slate-700"><?= htmlspecialchars($doc['title']) ?></p>
                                    <p class="text-[10px] text-gray-400 italic"><?= basename($doc['file_path']) ?></p>
                                </td>
                                <td class="py-4">
                                    <span class="px-2 py-1 rounded-full text-[9px] font-bold uppercase <?= getTypeBadgeColor($doc['document_category']) ?>">
                                        <?= htmlspecialchars($doc['document_category']) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-slate-600 font-semibold"><?= htmlspecialchars($doc['barangay']) ?></td>
                                <td class="py-4 text-gray-400"><?= date('M d, Y', strtotime($doc['submitted_at'])) ?></td>
                                <td class="py-4">
                                    <div class="flex justify-center gap-2">
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" download 
                                           class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition shadow-sm border border-blue-100">
                                            <i class="fa-solid fa-file-arrow-down text-xs"></i>
                                        </a>
                                        <button 
                                            type="button"
                                            title="View/Download & Mark as Viewed"
                                            class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100"
                                            onclick="viewAndMarkByAdmin('<?= $doc['id'] ?>', '<?= htmlspecialchars($doc['file_path']) ?>')"
                                            data-filepath="<?= htmlspecialchars($doc['file_path']) ?>"
                                        >
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </button>
                                        <button onclick="openArchiveModal('<?= $doc['id'] ?>', '<?= addslashes(htmlspecialchars($doc['title'])) ?>')" 
                                                class="w-8 h-8 flex items-center justify-center bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-600 hover:text-white transition shadow-sm border border-amber-100">
                                            <i class="fa-solid fa-box-archive text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="py-10 text-center text-gray-400">No documents found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Dropdown Logic
        const notifButton = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        const notifBadge = document.getElementById('notifBadge');

        if(notifButton) {
            notifButton.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
                profileDropdown.classList.remove('show');
                if(notifBadge) { notifBadge.remove(); fetch('mark_notifications_read.php'); }
            });
        }

        if(adminBtn) {
            adminBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
                notifDropdown.classList.remove('show');
            });
        }

        window.onclick = () => {
            if(notifDropdown) notifDropdown.classList.remove('show');
            if(profileDropdown) profileDropdown.classList.remove('show');
        }

        // Archive Modal Logic
        function openArchiveModal(id, title) {
            document.getElementById('modalDocId').value = id;
            document.getElementById('modalDocTitle').innerText = title;
            document.getElementById('archiveModal').classList.add('show');
        }
        function closeArchiveModal() { document.getElementById('archiveModal').classList.remove('show'); }
        
        window.onload = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            if (status && message) {
                const toast = document.getElementById('notification-toast');
                document.getElementById('toast-title').innerText = status === 'success' ? 'Success' : 'Attention';
                document.getElementById('toast-message').innerText = message;
                toast.classList.add(status === 'success' ? 'border-emerald-500' : 'border-red-500');
                document.getElementById('toast-icon').innerHTML = status === 'success' ? '<i class="fas fa-circle-check text-emerald-500"></i>' : '<i class="fas fa-circle-exclamation text-red-500"></i>';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 4000);
            }
        };
        // AJAX function to update status and view/download file
        function viewAndMarkByAdmin(documentId, filePath) {
            // Prepare form data
            const formData = new FormData();
            formData.append('document_id', documentId);
            formData.append('view_by_admin', '1');

            fetch('documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Determine file type
                const ext = filePath.split('.').pop().toLowerCase();
                if (ext === 'pdf') {
                    window.open(filePath, '_blank'); // View PDF in new tab
                } else if (ext === 'doc' || ext === 'docx') {
                    // Download Word file
                    const link = document.createElement('a');
                    link.href = filePath;
                    link.download = filePath.split('/').pop();
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    // Default: open in new tab
                    window.open(filePath, '_blank');
                }
                // Optionally, reload the page or update the status in the UI
                setTimeout(() => location.reload(), 1000);
            })
            .catch(error => {
                alert('Error updating status: ' + error);
            });
        }
    </script>
</body>
</html>