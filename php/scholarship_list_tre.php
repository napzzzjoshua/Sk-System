<?php
session_start();

// --- Configuration and Constants ---
$upload_dir = 'uploads/scholarship_docs/';

// --- Access Control (Synced with Dashboard) ---
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['SK Official','SK Chairperson','SK Members','SK Treasurer','SK Secretary'])
) {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$barangay = $_SESSION['barangay'];
$position = $_SESSION['position'];
$message = []; 

// --- NEW: Handle Applicant Deletion ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        $sql_del = "DELETE FROM scholarship_applications WHERE id = ? AND barangay = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("is", $delete_id, $barangay);
        if ($stmt_del->execute()) {
            header("Location: scholarship_list.php?deleted=1");
            exit;
        }
    }
}

// --- Helper Function for Time Formatting ---
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y' => 'yr','m' => 'mo','w' => 'wk','d' => 'day','h' => 'hr','i' => 'min','s' => 'sec');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } 
        else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) : 'just now';
}

// --- 1. Handle Document Update Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doc']) && isset($_FILES['new_document'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $document_field = filter_input(INPUT_POST, 'document_field', FILTER_SANITIZE_STRING);
    $file = $_FILES['new_document'];

    if ($application_id && $document_field && $file['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = $document_field . '_' . $application_id . '_' . time() . '.' . $file_extension;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $sql_update = "UPDATE scholarship_applications SET $document_field = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $destination, $application_id);
            if ($stmt_update->execute()) {
                $message = ['type' => 'success', 'text' => 'Document updated successfully.'];
            }
            $stmt_update->close();
        }
    }
}

// --- 2. Handle New Scholar Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_scholar'])) {
    $surname = $_POST['surname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $educational_level = $_POST['educational_level'];
    
    $sql_ins = "INSERT INTO scholarship_applications (surname, firstname, middlename, barangay, educational_level) VALUES (?, ?, ?, ?, ?)";
    $stmt_i = $conn->prepare($sql_ins);
    $stmt_i->bind_param("sssss", $surname, $firstname, $middlename, $barangay, $educational_level);

    if ($stmt_i->execute()) {
        $new_id = $conn->insert_id;
        $doc_fields = ['student_id', 'cor', 'grades', 'voters_id', 'psa'];
        foreach ($doc_fields as $f) {
            if (isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION);
                $dest = $upload_dir . $f . '_' . $new_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES[$f]['tmp_name'], $dest);
                $conn->query("UPDATE scholarship_applications SET $f = '$dest' WHERE id = $new_id");
            }
        }
        header("Location: scholarship_list.php?success=1");
        exit;
    }
}

// Fetch Profile and Logo
$sql = "SELECT email, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email, $profile_photo);
$stmt->fetch();
$stmt->close();

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Notifications Logic ---
$notif_sql = "SELECT id, message, related_link, created_at, is_read FROM sk_notifications WHERE (email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL) OR (email IS NULL AND barangay IS NULL AND position = ?) ORDER BY created_at DESC LIMIT 10";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("sssss", $user_email, $barangay, $position, $barangay, $position);
$stmt_notif->execute();
$result_notif = $stmt_notif->get_result();
$notifications = [];
while ($row = $result_notif->fetch_assoc()) { $notifications[] = $row; }
$stmt_notif->close();

$unread_count_sql = "SELECT COUNT(*) AS unread_count FROM sk_notifications WHERE (is_read = 0 AND email = ?) OR (is_read = 0 AND barangay = ? AND position = ?) OR (is_read = 0 AND barangay = ? AND position IS NULL) OR (is_read = 0 AND email IS NULL AND barangay IS NULL AND position = ?)";
$stmt_count = $conn->prepare($unread_count_sql);
$stmt_count->bind_param("sssss", $user_email, $barangay, $position, $barangay, $position);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'] ?? 0;
$stmt_count->close();

// --- Scholarship Data Logic ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';

$query = "SELECT * FROM scholarship_applications WHERE barangay = ?";
$params = [$barangay];
$types = "s";

if ($search) {
    $query .= " AND (firstname LIKE ? OR surname LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($level_filter) {
    $query .= " AND educational_level = ?";
    $params[] = $level_filter;
    $types .= "s";
}

$query .= " ORDER BY date_submitted DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$scholarships = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholars List | SK Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .main-content { flex: 1; padding: 25px 30px; background: #f1f5f9; overflow-y: auto; }
        
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        
        .content-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); margin-bottom: 20px; }
        
        .scholars-table { width: 100%; border-collapse: collapse; }
        .scholars-table thead tr { background: #f8fafc; }
        .scholars-table th { padding: 11px 16px; text-align: left; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .scholars-table tbody tr { background: #ffffff; border-bottom: 1px solid #e2e8f0; transition: background 0.15s; }
        .scholars-table tbody tr:last-child { border-bottom: none; }
        .scholars-table tbody tr:hover { background: #f0f4ff; }
        .scholars-table td { padding: 13px 16px; vertical-align: middle; color: #334155; font-size: 13px; }

        #notificationDropdown { position: absolute; top: 60px; right: 0; width: 300px; background: white; border-radius: 18px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #E5E9F0; z-index: 1000; overflow: hidden; display: none; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
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
            
            <?php if (strcasecmp($position, 'SK Treasurer') === 0): ?>
                <a href="financial_aid_tre.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list_tre.php" class="nav-link active"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php else: ?>
                <a href="sk_list.php" class="nav-link"><i class="fas fa-users-viewfinder"></i> SK Members</a>
                <a href="document_submissions.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="submit_proposal.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
                <a href="financial_aid.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list.php" class="nav-link active"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php endif; ?>
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
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-xl">Scholarship Applications</h1>
                <p id="current-time" class="text-xs text-slate-400 font-medium"></p>
            </div>
            <div class="flex items-center gap-3 relative">
                <button onclick="document.getElementById('addScholarModal').style.display='flex'" class="bg-[#1B1B4B] text-white px-4 py-2 rounded-xl text-xs font-bold shadow-lg shadow-navy-900/20 hover:bg-slate-800 transition">
                    <i class="fas fa-plus mr-2 text-[10px]"></i>New Scholar
                </button>
                
                <button id="notificationBtn" class="relative bg-white p-2.5 rounded-xl shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                    <i class="fas fa-bell text-[#1B1B4B] text-xs"></i>
                    <?php if($unread_count > 0): ?>
                        <span id="notif-badge" class="absolute top-2 right-2 w-2 h-2 bg-[#ea580c] rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <span class="font-bold text-[#1B1B4B]">Notifications</span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase"><?= $unread_count ?> Unread</span>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <p class="p-4 text-center text-slate-400 text-xs">No notifications yet.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="p-3 border-b hover:bg-slate-50 transition cursor-pointer <?= $n['is_read'] ? '' : 'bg-orange-50/30' ?>">
                                    <p class="text-[11px] text-slate-600 leading-tight mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                    <span class="text-[9px] text-slate-400"><?= time_ago($n['created_at']) ?> ago</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-card">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1.5 block px-1">Search Applicant</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name..." 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-none focus:border-[#FFD700] transition">
                    </div>
                </div>
                <div class="w-48">
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1.5 block px-1">Education Level</label>
                    <select name="level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-[#FFD700] cursor-pointer">
                        <option value="">All Levels</option>
                        <option value="Senior High" <?= $level_filter == 'Senior High' ? 'selected' : '' ?>>Senior High</option>
                        <option value="College" <?= $level_filter == 'College' ? 'selected' : '' ?>>College</option>
                    </select>
                </div>
                <button type="submit" class="bg-[#FFD700] text-[#1B1B4B] px-8 py-2.5 rounded-xl text-xs font-extrabold hover:opacity-90 transition">
                    Filter
                </button>
            </form>
        </div>

        <div class="content-card" style="padding:0; overflow:hidden;">
        <div class="overflow-x-auto">
            <table class="scholars-table">
                <thead>
                    <tr>
                        <th>Applicant Name</th>
                        <th>Level</th>
                        <th>Barangay</th>
                        <th>Requirement Checklist</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $doc_map = [
                        'student_id' => ['icon' => 'fas fa-id-badge', 'name' => 'Student ID'],
                        'cor'        => ['icon' => 'fas fa-file-invoice', 'name' => 'COR'],
                        'grades'     => ['icon' => 'fas fa-chart-line', 'name' => 'Grades'],
                        'voters_id'  => ['icon' => 'fas fa-id-card', 'name' => 'Voters ID'],
                        'psa'        => ['icon' => 'fas fa-stamp', 'name' => 'PSA']
                    ];
                    if ($scholarships->num_rows > 0): ?>
                        <?php while ($row = $scholarships->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="font-bold text-[#1B1B4B] text-[13px]"><?= htmlspecialchars($row['firstname'] . ' ' . $row['surname']) ?></div>
                                <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                    <i class="far fa-calendar-alt text-[9px]"></i> <?= date('M j, Y', strtotime($row['date_submitted'])) ?>
                                </div>
                            </td>
                            <td>
                                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 text-[10px] font-bold uppercase tracking-wider">
                                    <?= htmlspecialchars($row['educational_level']) ?>
                                </span>
                            </td>
                            <td class="text-slate-500 font-medium"><?= htmlspecialchars($row['barangay']) ?></td>
                            <td>
                                <div class="flex gap-2">
                                    <?php foreach ($doc_map as $field => $data): 
                                        $doc_path = !empty($row[$field]) ? htmlspecialchars($row[$field]) : '#';
                                        $is_uploaded = $doc_path !== '#';
                                    ?>
                                        <button type="button" class="doc-trigger group relative flex items-center justify-center w-8 h-8 rounded-lg <?= $is_uploaded ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-300' ?> hover:scale-105 transition"
                                                data-id="<?= $row['id'] ?>" 
                                                data-field="<?= $field ?>" 
                                                data-name="<?= $data['name'] ?>" 
                                                data-path="<?= $doc_path ?>">
                                            <i class="<?= $data['icon'] ?> text-xs"></i>
                                            <?php if($is_uploaded): ?>
                                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-green-500 border-2 border-white rounded-full"></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-right">
                                <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstname'] . ' ' . $row['surname']) ?>')" 
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 text-red-500 hover:bg-red-500 hover:text-white shadow-sm transition-all duration-300">
                                    <i class="fas fa-trash-alt text-[11px]"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-16">
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" class="w-12 h-12 mx-auto opacity-20 mb-3 grayscale">
                                <p class="text-slate-400 font-medium">No scholar applications found for this criteria.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </main>
</div>

<div id="deleteModal" class="modal">
    <div class="bg-white w-full p-8 rounded-[30px] shadow-2xl relative text-center" style="max-width:420px;">
        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-2xl"></i>
        </div>
        <h3 class="font-black text-[#1B1B4B] text-lg mb-2">Delete Applicant?</h3>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">Are you sure you want to delete <span id="deleteTargetName" class="font-bold text-red-500"></span>? This process cannot be undone.</p>
        
        <div class="flex gap-3">
            <button onclick="document.getElementById('deleteModal').style.display='none'" 
                    class="flex-1 bg-slate-100 py-3 rounded-2xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
            <a id="confirmDeleteBtn" href="#" 
               class="flex-1 bg-red-500 text-white py-3 rounded-2xl text-xs font-bold shadow-lg shadow-red-500/20 hover:bg-red-600 transition flex items-center justify-center">Confirm Delete</a>
        </div>
    </div>
</div>

<div id="documentModal" class="modal" style="padding:0;">
    <div class="bg-white w-full h-full flex flex-col" style="border-radius:0; max-width:100%; max-height:100%; height:100vh;">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 flex-shrink-0">
            <div>
                <h3 class="font-black text-[#1B1B4B] text-lg">Document Preview</h3>
                <p id="docTitle" class="text-[11px] text-[#ea580c] font-bold uppercase tracking-widest"></p>
            </div>
            <button onclick="document.getElementById('documentModal').style.display='none'" class="w-9 h-9 flex items-center justify-center bg-slate-100 rounded-full text-slate-400 hover:bg-slate-200 transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Image Viewer - Full Screen Area -->
        <div class="relative flex-1 bg-slate-900 flex items-center justify-center overflow-hidden">
            <img id="docViewerImg" src="" alt="Document Preview" class="hidden" style="display:none; max-width:100%; max-height:100%; object-fit:contain; transition:transform 0.2s; transform-origin:center center;" />
            <div id="noDocMsg" class="text-center">
                <i class="fas fa-file-circle-exclamation text-5xl text-slate-600 mb-3"></i>
                <p class="text-sm text-slate-500 font-bold">NO FILE UPLOADED</p>
            </div>
            <!-- Zoom Controls -->
            <div id="zoomControls" class="absolute bottom-4 right-4 flex gap-2">
                <button type="button" id="zoomOutBtn" class="bg-white/20 hover:bg-white/40 backdrop-blur rounded-full w-10 h-10 flex items-center justify-center text-white font-bold text-xl transition" title="Zoom Out">-</button>
                <button type="button" id="zoomResetBtn" class="bg-white/20 hover:bg-white/40 backdrop-blur rounded-full w-10 h-10 flex items-center justify-center text-white font-bold text-xs transition" title="Reset Zoom">1:1</button>
                <button type="button" id="zoomInBtn" class="bg-white/20 hover:bg-white/40 backdrop-blur rounded-full w-10 h-10 flex items-center justify-center text-white font-bold text-xl transition" title="Zoom In">+</button>
            </div>
        </div>

        <!-- Upload Form Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-white flex-shrink-0">
            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="update_doc" value="1">
                <input type="hidden" name="application_id" id="modalAppId">
                <input type="hidden" name="document_field" id="modalField">
                <div class="bg-slate-50 px-4 py-3 rounded-2xl border border-slate-100 flex-1 min-w-[220px]">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Replace Document (JPG/PNG)</label>
                    <input type="file" name="new_document" required class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-[#1B1B4B] file:text-white hover:file:bg-slate-800 cursor-pointer">
                </div>
                <button type="submit" class="bg-[#ea580c] text-white px-6 py-3 rounded-2xl text-xs font-bold shadow-lg shadow-orange-500/20 hover:opacity-90 transition whitespace-nowrap">
                    Upload & Update Document
                </button>
            </form>
        </div>
    </div>
</div>

<div id="addScholarModal" class="modal">
    <div class="bg-white w-full max-w-xl p-8 rounded-[35px] shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="mb-6">
            <h3 class="font-black text-[#1B1B4B] text-xl">Register New Scholar</h3>
            <p class="text-xs text-slate-400">Fill in the details to add a new beneficiary to the list.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-2 gap-5">
            <input type="hidden" name="add_scholar" value="1">
            <div class="col-span-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5 px-1">First Name</label>
                <input type="text" name="firstname" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:border-[#FFD700] outline-none">
            </div>
            <div class="col-span-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5 px-1">Surname</label>
                <input type="text" name="surname" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:border-[#FFD700] outline-none">
            </div>
            <div class="col-span-2">
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5 px-1">Middle Name</label>
                <input type="text" name="middlename" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:border-[#FFD700] outline-none">
            </div>
            <div class="col-span-2">
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5 px-1">Education Level</label>
                <select name="educational_level" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:border-[#FFD700] outline-none cursor-pointer">
                    <option value="Elementary">Elementary</option>
                    <option value="High School">High School</option>
                    <option value="Senior High">Senior High</option>
                    <option value="College">College</option>
                </select>
            </div>
            
            <div class="col-span-2 mt-2">
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-px bg-slate-100 flex-1"></div>
                    <p class="text-[10px] font-extrabold text-[#ea580c] uppercase tracking-widest">Upload Requirements</p>
                    <div class="h-px bg-slate-100 flex-1"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <?php foreach ($doc_map as $f => $d): ?>
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="text-[9px] font-bold text-slate-500 uppercase block mb-2"><?= $d['name'] ?></label>
                        <input type="file" name="<?= $f ?>" class="text-[9px] block w-full file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-white file:text-[9px] file:font-bold">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-span-2 flex gap-3 mt-6 pt-6 border-t border-slate-50">
                <button type="button" onclick="document.getElementById('addScholarModal').style.display='none'" class="flex-1 bg-slate-100 py-3.5 rounded-2xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 bg-[#1B1B4B] text-white py-3.5 rounded-2xl text-xs font-bold shadow-lg shadow-navy-900/20 hover:bg-slate-800 transition">Save Application</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Zoom controls
    let currentZoom = 1;
    const img = document.getElementById('docViewerImg');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');
    if (zoomInBtn && zoomOutBtn && img) {
        zoomInBtn.onclick = function() {
            currentZoom = Math.min(currentZoom + 0.25, 5);
            img.style.transform = `scale(${currentZoom})`;
        };
        zoomOutBtn.onclick = function() {
            currentZoom = Math.max(currentZoom - 0.25, 0.2);
            img.style.transform = `scale(${currentZoom})`;
        };
        if (zoomResetBtn) {
            zoomResetBtn.onclick = function() {
                currentZoom = 1;
                img.style.transform = 'scale(1)';
            };
        }
    }
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    function openDeleteModal(id, name) {
        document.getElementById('deleteTargetName').textContent = name;
        document.getElementById('confirmDeleteBtn').href = "scholarship_list.php?delete_id=" + id;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    document.querySelectorAll('.doc-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
            const { id, field, name, path } = btn.dataset;
            document.getElementById('modalAppId').value = id;
            document.getElementById('modalField').value = field;
            document.getElementById('docTitle').textContent = name;
            currentZoom = 1;
            if(img) img.style.transform = 'scale(1)';
            const noDoc = document.getElementById('noDocMsg');
            if(path && path !== '#' && path !== '') {
                img.src = path;
                img.style.display = 'block';
                img.classList.remove('hidden');
                noDoc.classList.add('hidden');
                img.onerror = function() {
                    img.style.display = 'none';
                    img.classList.add('hidden');
                    noDoc.classList.remove('hidden');
                };
            } else {
                img.src = '';
                img.style.display = 'none';
                img.classList.add('hidden');
                noDoc.classList.remove('hidden');
            }
            document.getElementById('documentModal').style.display = 'flex';
        });
    });

    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
    });
    document.addEventListener('click', () => { notifDropdown.style.display = 'none'; });

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>
</body>
</html>