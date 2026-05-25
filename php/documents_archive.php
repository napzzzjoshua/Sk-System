<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$fullname = $_SESSION['fullname'];
$current_admin_id = $_SESSION['user_id'];

// --- START RESTORE FUNCTIONALITY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_document'])) {
    $doc_id = intval($_POST['doc_id']);

    $fetch_query = "SELECT user_id, barangay, document_category, title, file_path, submitted_at FROM document_archive WHERE id = ?";
    $stmt_fetch = mysqli_prepare($conn, $fetch_query);
    mysqli_stmt_bind_param($stmt_fetch, "i", $doc_id);
    mysqli_stmt_execute($stmt_fetch);
    $result_fetch = mysqli_stmt_get_result($stmt_fetch);
    $document = mysqli_fetch_assoc($result_fetch);
    mysqli_stmt_close($stmt_fetch);
    
    if ($document) {
        $original_user_id = $document['user_id'];
        $target_user_id = $original_user_id;

        $user_check_query = "SELECT id FROM users WHERE id = ?";
        $stmt_user_check = mysqli_prepare($conn, $user_check_query);
        mysqli_stmt_bind_param($stmt_user_check, "i", $original_user_id);
        mysqli_stmt_execute($stmt_user_check);
        mysqli_stmt_store_result($stmt_user_check);

        if (mysqli_stmt_num_rows($stmt_user_check) === 0) {
            $target_user_id = $current_admin_id;
            error_log("Warning: Original user_id '{$original_user_id}' not found. Assigning to Admin.");
        }
        mysqli_stmt_close($stmt_user_check);

        $category_to_insert = $document['document_category'];
        $valid_categories = ['Minutes of Meeting', 'SK Resolution', 'Disbursement File', 'Project Proposal'];
        if (!in_array($category_to_insert, $valid_categories)) {
            $category_to_insert = 'Other'; 
        }
        
        $status = 'Pending';
        $insert_query = "INSERT INTO document_submissions (user_id, barangay, document_category, title, file_path, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt_insert, "issssss", $target_user_id, $document['barangay'], $category_to_insert, $document['title'], $document['file_path'], $status, $document['submitted_at']);
        $insert_success = mysqli_stmt_execute($stmt_insert);
        mysqli_stmt_close($stmt_insert);

        if ($insert_success) {
            $delete_query = "DELETE FROM document_archive WHERE id = ?";
            $stmt_delete = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($stmt_delete, "i", $doc_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);
            header("Location: documents_archive.php?status=restored_success");
            exit;
        } else {
            header("Location: documents_archive.php?status=restored_error_insert");
            exit;
        }
    } else {
        header("Location: documents_archive.php?status=restored_error_not_found");
        exit;
    }
}

// --- DATABASE INTEGRATION ---
$documents = [];
$documents_query = "SELECT id, title, document_category, barangay, submitted_at, file_path FROM document_archive ORDER BY submitted_at DESC";
$result = mysqli_query($conn, $documents_query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) { $documents[] = $row; }
    mysqli_free_result($result);
}

// Stats aggregation
$count_minutes = 0; $count_resolutions = 0; $count_disbursements = 0; $count_other = 0;
foreach ($documents as $doc) {
    if ($doc['document_category'] == 'Minutes of Meeting') $count_minutes++;
    elseif ($doc['document_category'] == 'SK Resolution') $count_resolutions++;
    elseif ($doc['document_category'] == 'Disbursement File') $count_disbursements++;
    else $count_other++;
}

// Sidebar/Header logic
$unread_messages_count = $conn->query("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = '$current_admin_id' AND is_read = 0")->fetch_assoc()['count'] ?? 0;
$notifications = [];
$unread_notifications_count = 0;
$notif_res = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if($notif_res){
    while ($row = $notif_res->fetch_assoc()) {
        $notifications[] = $row;
        if ($row['is_read'] == 0) $unread_notifications_count++;
    }
}

// Badge color helper
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

// Toast logic
$toast_data = null;
if (isset($_GET['status'])) {
    $msg_map = [
        'restored_success' => ["Success", "Document successfully restored.", "success"],
        'restored_error_insert' => ["Error", "Restoration failed. DB insertion error.", "error"],
        'restored_error_not_found' => ["Error", "Document not found.", "error"]
    ];
    if(isset($msg_map[$_GET['status']])) {
        $toast_data = ['title' => $msg_map[$_GET['status']][0], 'message' => $msg_map[$_GET['status']][1], 'type' => $msg_map[$_GET['status']][2]];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Archive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Profile Dropdown Styles */
        .user-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #4A5568;
            transition: all 0.2s;
            cursor: pointer;
        }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }

        /* Modal CSS */
        .modal-overlay { 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(8px); 
            z-index: 100; 
            transition: opacity 0.3s ease;
        }
        .modal-container {
            z-index: 101;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
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
            <div>
                <h2 class="text-xl font-bold text-[#1B1B4B]">Document Archive</h2>
                <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Historical records and deleted submissions</p>
            </div>
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
            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-file-invoice text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Minutes</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($count_minutes) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-scroll text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Resolutions</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($count_resolutions) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-money-bill-transfer text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Disbursements</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($count_disbursements) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-folder text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Other Docs</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($count_other) ?></h3>
                </div>
            </div>
        </div>

        <div class="card-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-sm font-bold text-[#1B1B4B]">Archived File List</h3>
                <a href="documents.php" class="px-3 py-1 text-[10px] font-bold bg-gray-100 rounded-lg hover:bg-gray-200 transition">Back to Active Documents</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-gray-400 uppercase border-b border-gray-100">
                            <th class="pb-4 font-bold tracking-wider">Document Title</th>
                            <th class="pb-4 font-bold tracking-wider">Category</th>
                            <th class="pb-4 font-bold tracking-wider">Barangay</th>
                            <th class="pb-4 font-bold tracking-wider">Archived Date</th>
                            <th class="pb-4 font-bold tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (!empty($documents)): foreach ($documents as $doc): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-4">
                                <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($doc['title']) ?></p>
                                <p class="text-[10px] text-gray-400 truncate max-w-[200px]"><?= htmlspecialchars($doc['file_path']) ?></p>
                            </td>
                            <td class="py-4">
                                <span class="px-2 py-1 text-[9px] font-bold rounded-md uppercase <?= getTypeBadgeColor($doc['document_category']) ?>"><?= htmlspecialchars($doc['document_category']) ?></span>
                            </td>
                            <td class="py-4 text-xs font-medium text-gray-500"><?= $doc['barangay'] ?></td>
                            <td class="py-4 text-xs text-gray-400"><?= date('M d, Y', strtotime($doc['submitted_at'])) ?></td>
                            <td class="py-4">
                                <div class="flex justify-center gap-2">
                                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" 
                                       class="w-8 h-8 rounded-lg flex items-center justify-center bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100" 
                                       title="View File">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>
                                    <button onclick="showRestoreModal(<?= $doc['id'] ?>)" 
                                            class="w-8 h-8 rounded-lg flex items-center justify-center bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition shadow-sm border border-emerald-100" 
                                            title="Restore Document">
                                        <i class="fa-solid fa-rotate-left text-xs"></i>
                                    </button>
                                    <form id="restoreForm-<?= $doc['id'] ?>" method="POST" class="hidden">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="restore_document" value="1">
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="py-10 text-center text-gray-400 text-xs">No archived documents found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="restoreModal" class="hidden fixed inset-0 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-[32px] p-8 max-w-sm w-full shadow-2xl modal-container text-center border border-gray-100">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-6 mx-auto">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h3 class="text-xl font-bold text-[#1B1B4B] mb-2 tracking-tight">Restore Document?</h3>
            <p class="text-xs text-gray-400 leading-relaxed mb-8 px-2">Are you sure you want to restore this file? It will be moved back to the active queue for standard processing and tracking.</p>
            <div class="flex gap-3">
                <button onclick="closeRestoreModal()" class="flex-1 py-3.5 text-xs font-bold text-gray-400 hover:bg-gray-50 rounded-2xl transition-all active:scale-95">Cancel</button>
                <button id="confirmBtn" class="flex-1 py-3.5 text-xs font-bold bg-[#1B1B4B] text-[#FFD700] rounded-2xl shadow-xl shadow-indigo-100 hover:brightness-110 transition-all active:scale-95">Confirm Restoration</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed top-8 right-8 z-[100] flex flex-col gap-4 w-80"></div>

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
                if(notifBadge) {
                    notifBadge.remove();
                    fetch('mark_notifications_read.php'); 
                }
            });
        }

        if(adminBtn) {
            adminBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
                notifDropdown.classList.remove('show');
            });
        }

        // Restore Modal Logic
        let currentFormId = null;
        function showRestoreModal(id) {
            currentFormId = `restoreForm-${id}`;
            document.getElementById('restoreModal').classList.remove('hidden');
        }
        function closeRestoreModal() { document.getElementById('restoreModal').classList.add('hidden'); }
        
        document.getElementById('confirmBtn').onclick = () => { if(currentFormId) document.getElementById(currentFormId).submit(); };

        // Global Click Logic
        window.onclick = (event) => {
            if (!event.target.closest('#notifButton')) notifDropdown.classList.remove('show');
            if (!event.target.closest('#adminProfileBtn')) profileDropdown.classList.remove('show');
            
            const modal = document.getElementById('restoreModal');
            if (event.target === modal) closeRestoreModal();
        }

        function showToast(title, msg, type) {
            const container = document.getElementById('toast-container');
            const color = type === 'success' ? 'border-green-500' : 'border-red-500';
            const icon = type === 'success' ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-500';
            
            const el = document.createElement('div');
            el.className = `bg-white p-4 rounded-2xl shadow-xl border-l-4 ${color} flex gap-4 transform translate-x-full transition-all duration-500`;
            el.innerHTML = `<i class="fas ${icon} mt-1"></i><div><p class="text-xs font-bold">${title}</p><p class="text-[10px] text-gray-500">${msg}</p></div>`;
            
            container.appendChild(el);
            setTimeout(() => el.classList.remove('translate-x-full'), 100);
            setTimeout(() => {
                el.classList.add('translate-x-full');
                setTimeout(() => el.remove(), 500);
            }, 4000);
        }

        <?php if($toast_data): ?>
            showToast("<?= $toast_data['title'] ?>", "<?= $toast_data['message'] ?>", "<?= $toast_data['type'] ?>");
        <?php endif; ?>
    </script>
</body>
</html>