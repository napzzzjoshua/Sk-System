<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';
$admin_fullname = $_SESSION['fullname']; 
$admin_id = $_SESSION['user_id']; 
$current_page = basename($_SERVER['PHP_SELF']);

// --- 1. DATA LOGIC: NOTIFICATIONS (FROM DASHBOARD) ---
$notifications = [];
$unread_notifications_count = 0;
$notif_res = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if($notif_res){
    while ($row = $notif_res->fetch_assoc()) {
        $notifications[] = $row;
        if ($row['is_read'] == 0) $unread_notifications_count++;
    }
}

// --- 2. LOGIC: IDENTIFY SELECTED RECIPIENT ---
$selected_recipient_name = $_GET['recipient'] ?? null;
$is_recipient_selected = ($selected_recipient_name !== null);
$selected_recipient_id = null; 

// --- 3. FETCH UNIQUE SENDERS (LEFT PANEL) ---
$unique_senders = [];
if (isset($conn)) {
    // Updated: Fetch profile_photo from users table
    $sql_senders = "SELECT 
        u.sender_fullname, 
        u.barangay, 
        u.sender_id,
        (SELECT profile_photo FROM users WHERE id = u.sender_id) as profile_photo,
        (SELECT COUNT(*) 
         FROM chat_messages cm_unread 
         WHERE cm_unread.sender_id = u.sender_id 
           AND cm_unread.receiver_id = ? 
           AND cm_unread.is_read = 0
        ) as unread_count
    FROM (
        SELECT DISTINCT sender_fullname, barangay, sender_id
        FROM chat_messages 
        WHERE sender_fullname != ? AND sender_id IS NOT NULL AND barangay IS NOT NULL 
    ) as u
    ORDER BY u.sender_fullname ASC";

    $stmt_senders = $conn->prepare($sql_senders);
    $stmt_senders->bind_param("is", $admin_id, $admin_fullname); 
    $stmt_senders->execute();
    $result_senders = $stmt_senders->get_result();

    while ($row = $result_senders->fetch_assoc()) {
        $profilePath = $row['profile_photo'] ? "uploads/profiles/" . basename($row['profile_photo']) : "uploads/profiles/default-avatar.png";
        $unique_senders[] = [
            'sender_fullname' => $row['sender_fullname'],
            'barangay' => $row['barangay'],
            'sender_id' => $row['sender_id'],
            'unread_count' => $row['unread_count'],
            'profile_photo' => $profilePath
        ];
        if ($row['sender_fullname'] === $selected_recipient_name) {
             $selected_recipient_id = $row['sender_id'];
        }
    }
    $stmt_senders->close();
}

// --- 4. FETCH LIVE CHAT MESSAGES ---
$live_chat_history = [];
if ($is_recipient_selected && $selected_recipient_id !== null && isset($conn)) {
    // Mark messages as read
    $sql_mark_read = "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    if ($stmt_read = $conn->prepare($sql_mark_read)) {
        $stmt_read->bind_param("ii", $selected_recipient_id, $admin_id);
        $stmt_read->execute();
        $stmt_read->close();
    }

    $sql = "SELECT sender_id, sender_fullname, barangay, messages_content, created_at FROM chat_messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $selected_recipient_id, $admin_id, $admin_id, $selected_recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['time_formatted'] = date('h:i A', strtotime($row['created_at']));
        $live_chat_history[] = $row;
    }
    $stmt->close();
}

$js_recipient_id = $selected_recipient_id ?? 'null';
$js_recipient_name = $selected_recipient_name ?? 'Select a User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --navy-primary: #1B1B4B;
            --gold-accent: #FFD700;
            --bg-light: #f1f5f9;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--navy-primary); overflow: hidden; }
        
        /* Sidebar Styles */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E6E8F0; position: fixed; height: 100vh; z-index: 40; display: flex; flex-direction: column; }
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
        .nav-item:hover:not(.active), .nav-tool-item:hover:not(.active) { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item.active { background: var(--navy-primary); color: white; box-shadow: 0 4px 10px rgba(27, 27, 75, 0.15); border-right: 3px solid var(--gold-accent); }
        .nav-tool-item.active { color: var(--navy-primary); background-color: #F4F5FF; font-weight: 700; }

        /* Notification & Dropdown UI */
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }

        /* Profile Dropdown Specific Styles */
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

        /* Chat Layout */
        .main-content { margin-left: 260px; height: 100vh; display: flex; flex-direction: column; }
        .chat-container { display: flex; flex-grow: 1; overflow: hidden; background: white; margin: 0 1.5rem 1.5rem 1.5rem; border-radius: 20px; border: 1px solid #F0F1F7; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .chat-list { width: 320px; border-right: 1px solid #F0F1F7; display: flex; flex-direction: column; }
        .chat-window { flex-grow: 1; display: flex; flex-direction: column; background: #fafafa; }
        .admin-bubble { background: var(--navy-primary); color: white; border-radius: 15px 15px 2px 15px; }
        .user-bubble { background: white; color: var(--navy-primary); border-radius: 15px 15px 15px 2px; border: 1px solid #E6E8F0; }

        /* ── Custom Confirm Modal ── */
        #customModal {
            display: none;
            position: fixed; inset: 0; z-index: 9999;
            align-items: center; justify-content: center;
        }
        #customModal.open { display: flex; }
        .modal-backdrop {
            position: absolute; inset: 0;
            background: rgba(15, 15, 40, 0.55);
            backdrop-filter: blur(4px);
            animation: backdropIn 0.22s ease;
        }
        @keyframes backdropIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-card {
            position: relative; z-index: 1;
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem 2rem 1.5rem;
            width: 100%; max-width: 380px;
            box-shadow: 0 25px 60px rgba(27,27,75,0.22), 0 8px 20px rgba(0,0,0,0.08);
            animation: modalPop 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.88) translateY(16px); }
            to   { opacity: 1; transform: scale(1)    translateY(0);     }
        }
        .modal-icon-ring {
            width: 52px; height: 52px; border-radius: 50%;
            background: #FFF1F1; border: 2px solid #FFDDDD;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }
        .modal-icon-ring i { color: #E53E3E; font-size: 1.2rem; }
        .modal-title {
            text-align: center; font-size: 0.95rem; font-weight: 700;
            color: var(--navy-primary); margin-bottom: 0.4rem;
        }
        .modal-desc {
            text-align: center; font-size: 0.75rem; color: #718096;
            line-height: 1.5; margin-bottom: 1.5rem;
        }
        .modal-actions { display: flex; gap: 0.75rem; }
        .modal-btn-cancel {
            flex: 1; padding: 0.6rem; border-radius: 10px;
            font-size: 0.8rem; font-weight: 600;
            border: 1.5px solid #E2E8F0; background: #F8FAFC;
            color: #4A5568; cursor: pointer; transition: all 0.18s;
        }
        .modal-btn-cancel:hover { background: #EDF2F7; }
        .modal-btn-confirm {
            flex: 1; padding: 0.6rem; border-radius: 10px;
            font-size: 0.8rem; font-weight: 600;
            border: none; background: #E53E3E;
            color: #fff; cursor: pointer; transition: all 0.18s;
        }
        .modal-btn-confirm:hover { background: #C53030; }

        /* ── Toast Notification ── */
        #toastContainer {
            position: fixed; left: 1.25rem; bottom: 1.5rem;
            z-index: 9998; display: flex; flex-direction: column; gap: 0.6rem;
            pointer-events: none;
        }
        .toast {
            display: flex; align-items: center; gap: 0.75rem;
            background: #ffffff;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            min-width: 260px; max-width: 320px;
            box-shadow: 0 8px 30px rgba(27,27,75,0.15), 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #48BB78;
            animation: toastIn 0.35s cubic-bezier(0.34, 1.4, 0.64, 1);
            pointer-events: all;
        }
        .toast.toast-error { border-left-color: #E53E3E; }
        .toast.toast-hide {
            animation: toastOut 0.4s ease forwards;
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(-60px); }
            to   { opacity: 1; transform: translateX(0);      }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateX(0);       max-height: 80px; margin-bottom: 0; }
            to   { opacity: 0; transform: translateX(-60px);   max-height: 0;    margin-bottom: -0.6rem; }
        }
        .toast-icon {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: #F0FFF4; color: #38A169; font-size: 0.85rem;
        }
        .toast.toast-error .toast-icon { background: #FFF5F5; color: #E53E3E; }
        .toast-body { flex-grow: 1; }
        .toast-title { font-size: 0.78rem; font-weight: 700; color: var(--navy-primary); }
        .toast-msg   { font-size: 0.7rem;  color: #718096; margin-top: 1px; }
        .toast-close {
            background: none; border: none; cursor: pointer;
            color: #CBD5E0; font-size: 0.75rem; padding: 2px; flex-shrink: 0;
        }
        .toast-close:hover { color: #718096; }
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
            <a href="admin_chat.php" class="nav-tool-item active">
                <i class="fa-solid fa-comment-dots"></i>
                <span>Messages</span>
            </a>
            <a href="uploads/charter/citizen_charter.pdf" target="_blank" class="nav-tool-item">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>Citizen Charter</span>
            </a>
            
            <div class="tool-label">Main Menu</div>
            <a href="geo_mapping.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
            <a href="manage_users.php" class="nav-item"><i class="fa-solid fa-users-gear"></i><span>Manage Users</span></a>
            <a href="requests.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i><span>Requests</span></a>
            <a href="documents.php" class="nav-item"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="form_management.php" class="nav-item"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center p-6">
            <h2 class="text-xl font-bold text-[#1B1B4B]">Communication Center</h2>
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <button id="notifButton" class="relative text-gray-400 hover:text-[#1B1B4B] transition outline-none">
                        <i class="far fa-bell text-lg"></i>
                        <?php if($unread_notifications_count > 0): ?>
                            <span id="notifBadge" class="badge-count"><?= $unread_notifications_count ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifDropdown" class="dropdown-menu mt-4">
                        <div class="p-4 border-b border-gray-100"><h4 class="font-bold text-sm text-[#1B1B4B]">Notifications</h4></div>
                        <div class="max-h-60 overflow-y-auto p-2">
                            <?php if(empty($notifications)): ?>
                                <p class="p-3 text-[10px] text-gray-400 text-center uppercase font-bold tracking-widest">No new notifications</p>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="p-3 border-b border-gray-50 text-[11px] text-gray-600 hover:bg-gray-50 rounded-lg transition cursor-default">
                                        <?= htmlspecialchars($n['message']) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button id="adminProfileBtn" class="flex items-center gap-3 border-l pl-6 border-gray-200 focus:outline-none hover:opacity-80 transition">
                        <div class="text-right">
                            <p class="text-xs font-bold leading-none"><?= htmlspecialchars($admin_fullname) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Administrator <i class="fa-solid fa-chevron-down ml-1"></i></p>
                        </div>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_fullname) ?>&background=1B1B4B&color=FFD700" class="w-8 h-8 rounded-full border border-gray-200">
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

        <div class="chat-container">
            <div class="chat-list">
                <div class="p-5 border-b border-gray-50">
                    <h3 class="text-xs font-bold text-[#1B1B4B] uppercase tracking-widest">Inbox</h3>
                </div>
                <div class="overflow-y-auto flex-grow p-2">
                    <?php if (!empty($unique_senders)): ?>
                        <?php foreach ($unique_senders as $sender): ?>
                            <?php $is_active = ($selected_recipient_name === $sender['sender_fullname']); ?>
                            <a href="admin_chat.php?recipient=<?= urlencode($sender['sender_fullname']) ?>" 
                               class="flex items-center p-3 mb-1 rounded-xl transition-all <?= $is_active ? 'bg-[#F4F5FF] border-l-4 border-[#1B1B4B]' : 'hover:bg-gray-50' ?>">
                                <img src="<?= htmlspecialchars($sender['profile_photo']) ?>"
                                     class="w-10 h-10 rounded-full object-cover mr-3 border border-gray-200"
                                     alt="Profile"
                                     onerror="this.onerror=null;this.src='uploads/profiles/default-avatar.png';">
                                <div class="flex-grow overflow-hidden">
                                    <p class="text-xs font-bold text-[#1B1B4B] truncate"><?= htmlspecialchars($sender['sender_fullname']) ?></p>
                                    <p class="text-[10px] text-gray-400 truncate"><?= htmlspecialchars($sender['barangay'] ?? 'N/A') ?></p>
                                </div>
                                <?php if ($sender['unread_count'] > 0): ?>
                                    <span class="tool-badge"><?= $sender['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-[10px] text-gray-400 mt-10">No recent messages.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-window">
                <div class="p-4 bg-white border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-[#1B1B4B]"><?= htmlspecialchars($js_recipient_name) ?></h4>
                        <p class="text-[10px] text-emerald-500 font-medium"><?= $is_recipient_selected ? 'Conversation active' : 'Select a user to chat' ?></p>
                    </div>
                    <?php if ($is_recipient_selected): ?>
                    <button id="deleteConversationBtn" class="text-xs text-red-600 border border-red-200 bg-red-50 px-3 py-1 rounded-lg font-bold hover:bg-red-100 transition flex items-center gap-1" title="Delete Conversation">
                        <i class="fa-solid fa-trash"></i> Delete Conversation
                    </button>
                    <?php endif; ?>
                </div>

                <div id="chat-messages" class="flex-grow p-6 overflow-y-auto space-y-4">
                    <?php if ($is_recipient_selected): ?>
                        <?php if (!empty($live_chat_history)): ?>
                            <?php 
                            // Get recipient's profile photo for chat bubbles
                            $recipient_profile_photo = null;
                            foreach ($unique_senders as $sender) {
                                if ($sender['sender_id'] == $selected_recipient_id) {
                                    $recipient_profile_photo = $sender['profile_photo'];
                                    break;
                                }
                            }
                            ?>
                            <?php foreach ($live_chat_history as $chat): 
                                $is_admin = ($chat['sender_id'] == $admin_id); ?>
                                <div class="flex <?= $is_admin ? 'justify-end' : 'justify-start' ?> items-end">
                                    <?php if (!$is_admin): ?>
                                        <img src="<?= htmlspecialchars($recipient_profile_photo) ?>" class="w-8 h-8 rounded-full object-cover mr-2 border border-gray-200" alt="Profile" onerror="this.onerror=null;this.src='uploads/profiles/default-avatar.png';">
                                    <?php endif; ?>
                                    <div class="max-w-[70%] p-3 shadow-sm text-xs <?= $is_admin ? 'admin-bubble' : 'user-bubble' ?>">
                                        <p><?= htmlspecialchars($chat['messages_content']) ?></p>
                                        <span class="block text-[9px] mt-1 opacity-60 text-right"><?= $chat['time_formatted'] ?></span>
                                    </div>
                                    <?php if ($is_admin): ?>
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_fullname) ?>&background=1B1B4B&color=FFD700" class="w-8 h-8 rounded-full object-cover ml-2 border border-gray-200" alt="Admin">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-20 text-gray-400 text-xs">Select a resident from the left to start messaging.</div>
                    <?php endif; ?>
                </div>

                <form id="sendMessageForm" class="p-4 bg-white border-t border-gray-100">
                    <?php if ($is_recipient_selected): ?>
                        <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-xl border border-gray-100 focus-within:border-[#1B1B4B]/30 transition">
                            <input type="hidden" id="receiverIdInput" value="<?= $js_recipient_id ?>">
                            <input type="text" id="messageInput" placeholder="Write a message..." class="flex-grow bg-transparent p-2 text-xs outline-none" required>
                            <button type="submit" id="sendButton" class="w-8 h-8 bg-[#1B1B4B] text-[#FFD700] rounded-lg flex items-center justify-center hover:opacity-90">
                                <i class="fas fa-paper-plane text-xs"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <!-- ── Custom Confirm Modal ── -->
    <div id="customModal" role="dialog" aria-modal="true">
        <div class="modal-backdrop" id="modalBackdrop"></div>
        <div class="modal-card">
            <div class="modal-icon-ring"><i class="fas fa-trash-can"></i></div>
            <p class="modal-title">Delete Conversation?</p>
            <p class="modal-desc">This action is permanent and cannot be undone. All messages in this conversation will be removed.</p>
            <div class="modal-actions">
                <button class="modal-btn-cancel" id="modalCancelBtn">Cancel</button>
                <button class="modal-btn-confirm" id="modalConfirmBtn"><i class="fas fa-trash-can mr-1"></i>Delete</button>
            </div>
        </div>
    </div>

    <!-- ── Toast Notification Container ── -->
    <div id="toastContainer"></div>

    <script>
        // ── Toast Helper ──
        function showToast(title, msg, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast' + (type === 'error' ? ' toast-error' : '');
            toast.innerHTML = `
                <div class="toast-icon"><i class="fas ${type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check'}"></i></div>
                <div class="toast-body">
                    <p class="toast-title">${title}</p>
                    <p class="toast-msg">${msg}</p>
                </div>
                <button class="toast-close" onclick="dismissToast(this.parentElement)"><i class="fas fa-xmark"></i></button>
            `;
            container.appendChild(toast);
            setTimeout(() => dismissToast(toast), 5000);
        }
        function dismissToast(toast) {
            if (!toast || toast.classList.contains('toast-hide')) return;
            toast.classList.add('toast-hide');
            setTimeout(() => toast.remove(), 420);
        }

        // ── Modal Helper ──
        function openModal() {
            return new Promise(resolve => {
                const modal = document.getElementById('customModal');
                modal.classList.add('open');
                const onConfirm = () => { closeModal(); resolve(true); };
                const onCancel  = () => { closeModal(); resolve(false); };
                document.getElementById('modalConfirmBtn').addEventListener('click', onConfirm, { once: true });
                document.getElementById('modalCancelBtn').addEventListener('click', onCancel, { once: true });
                document.getElementById('modalBackdrop').addEventListener('click', onCancel, { once: true });
            });
        }
        function closeModal() {
            document.getElementById('customModal').classList.remove('open');
        }

        // Dropdown Handlers
        const notifButton = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        const notifBadge = document.getElementById('notifBadge');

        // Notification Toggle
        if(notifButton) {
            notifButton.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
                profileDropdown.classList.remove('show'); // Hide profile if notif clicked
                if(notifBadge) {
                    notifBadge.remove();
                    fetch('mark_notifications_read.php'); 
                }
            });
        }

        // Profile Toggle
        if(adminBtn) {
            adminBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
                notifDropdown.classList.remove('show'); // Hide notif if profile clicked
            });
        }

        // Global Close
        window.onclick = () => {
            if(notifDropdown) notifDropdown.classList.remove('show');
            if(profileDropdown) profileDropdown.classList.remove('show');
        }

        // Chat Logic
        const ADMIN_FULLNAME = "<?= $admin_fullname ?>";
        const CHAT_MESSAGES_CONTAINER = document.getElementById('chat-messages');
        const MESSAGE_FORM = document.getElementById('sendMessageForm');
        const MESSAGE_INPUT = document.getElementById('messageInput');
        const RECEIVER_ID_INPUT = document.getElementById('receiverIdInput');

        function scrollToBottom() { 
            if(CHAT_MESSAGES_CONTAINER) CHAT_MESSAGES_CONTAINER.scrollTop = CHAT_MESSAGES_CONTAINER.scrollHeight; 
        }

        if (MESSAGE_FORM) {
            MESSAGE_FORM.addEventListener('submit', async function(e) {
                e.preventDefault();
                const content = MESSAGE_INPUT.value.trim();
                const receiverId = RECEIVER_ID_INPUT.value;
                if (!content || receiverId === 'null') return;

                try {
                    const formData = new FormData();
                    formData.append('message_content', content);
                    formData.append('sender_fullname', ADMIN_FULLNAME);
                    formData.append('receiver_id', receiverId);
                    
                    const response = await fetch('send_message.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        const now = new Date();
                        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const html = `<div class="flex justify-end"><div class="max-w-[70%] p-3 text-xs admin-bubble shadow-sm"><p>${content}</p><span class="block text-[9px] mt-1 opacity-60 text-right">${time}</span></div></div>`;
                        CHAT_MESSAGES_CONTAINER.insertAdjacentHTML('beforeend', html);
                        MESSAGE_INPUT.value = '';
                        scrollToBottom();
                    }
                } catch(e) { console.error(e); }
            });
        }

        // Delete Conversation logic
        const deleteBtn = document.getElementById('deleteConversationBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async function() {
                const confirmed = await openModal();
                if (!confirmed) return;
                const recipientId = "<?= $js_recipient_id ?>";
                try {
                    const formData = new FormData();
                    formData.append('recipient_id', recipientId);
                    const response = await fetch('delete_conversation.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        // Clear chat messages
                        if (CHAT_MESSAGES_CONTAINER) CHAT_MESSAGES_CONTAINER.innerHTML = '<div class="text-center py-20 text-gray-400 text-xs">No messages. Conversation deleted.</div>';
                        showToast('Conversation Deleted', 'The conversation has been permanently removed.');
                    } else {
                        showToast('Delete Failed', 'Could not delete the conversation. Please try again.', 'error');
                    }
                } catch (e) {
                    showToast('Network Error', 'Something went wrong. Please check your connection.', 'error');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', scrollToBottom);
    </script>
</body>
</html>