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

// --- DATA LOGIC: NOTIFICATIONS & CHAT (Same as dashboard) ---
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

// --- DATA LOGIC: FETCH SECURITY LOGS ---
$logs = [];
$logs_res = $conn->query("SELECT action, ip_address, device, status, created_at FROM security_logs ORDER BY created_at DESC LIMIT 15");

if($logs_res && $logs_res->num_rows > 0) {
    while($row = $logs_res->fetch_assoc()) { $logs[] = $row; }
} else {
    // Dummy data if table is empty
    $logs = [
        ['action' => 'Admin Login Success', 'ip_address' => '192.168.1.1', 'device' => 'Chrome on Windows', 'status' => 'Success', 'created_at' => date('Y-m-d H:i:s')],
        ['action' => 'Modified SK Official Record', 'ip_address' => '192.168.1.1', 'device' => 'Chrome on Windows', 'status' => 'Success', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
        ['action' => 'Failed Login Attempt', 'ip_address' => '110.54.21.8', 'device' => 'Firefox on Android', 'status' => 'Blocked', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))],
        ['action' => 'Exported Financial Reports', 'ip_address' => '192.168.1.1', 'device' => 'Chrome on Windows', 'status' => 'Success', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Security Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --navy-primary: #1B1B4B; --gold-accent: #FFD700; --bg-light: #f1f5f9; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--navy-primary); overflow-x: hidden; }
        
        /* Sidebar Styles from dashboard */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E6E8F0; position: fixed; height: 100vh; z-index: 40; display: flex; flex-direction: column; overflow: hidden; }
        .nav-item { display: flex; align-items: center; padding: 0.6rem 1.25rem; margin: 0.15rem 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; }
        .nav-item i { width: 20px; font-size: 1rem; margin-right: 1rem; display: flex; justify-content: center; }
        .nav-item:hover:not(.active) { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item.active { background: var(--navy-primary); color: white; box-shadow: 0 4px 10px rgba(27, 27, 75, 0.15); border-right: 3px solid var(--gold-accent); }
        
        .tool-label { font-size: 0.65rem; font-weight: 700; color: #ABB1D1; letter-spacing: 0.05em; padding: 0.75rem 1.5rem 0.25rem; text-transform: uppercase; }
        .nav-tool-item { display: flex; align-items: center; padding: 0.5rem 1.25rem; margin: 0.1rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; position: relative; }
        .nav-tool-item i { font-size: 0.85rem; margin-right: 1rem; width: 20px; text-align: center; }
        .nav-tool-item:hover { background-color: #F4F5FF; color: var(--navy-primary); }
        
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }
        
        .main-content { margin-left: 260px; padding: 1.5rem; min-height: 100vh; }
        .card-white { background: white; border-radius: 20px; border: 1px solid #F0F1F7; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        /* Dropdowns */
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        
        .user-menu-item { display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: #4A5568; transition: all 0.2s; cursor: pointer; text-decoration: none; }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }
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
            <a href="documents.php" class="nav-item"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="form_management.php" class="nav-item"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-[#1B1B4B]">Security Logs</h2>
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <button id="notifButton" class="relative text-gray-400 hover:text-[#1B1B4B] transition outline-none">
                        <i class="far fa-bell text-lg"></i>
                        <?php if($unread_notifications_count > 0): ?>
                            <span class="badge-count"><?= $unread_notifications_count ?></span>
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
                            <a href="admin_security_logs.php" class="user-menu-item" style="background: #F4F5FF; color: var(--navy-primary);">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Security Logs</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="card-white overflow-hidden">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-sm font-bold">System Integrity Logs</h3>
                    <p class="text-[10px] text-gray-400">Monitoring all administrative access and sensitive changes.</p>
                </div>
                <button onclick="window.print()" class="bg-slate-50 border border-slate-200 text-[10px] font-bold px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
                    <i class="fa-solid fa-file-export mr-1"></i> Export PDF
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-50">
                            <th class="pb-3 pl-2">Event / Action</th>
                            <th class="pb-3">IP Address</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($logs as $log): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-4 pl-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 text-[10px]">
                                        <i class="fa-solid <?= strpos($log['action'], 'Login') !== false ? 'fa-key' : 'fa-shield-halved' ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($log['action']) ?></p>
                                        <p class="text-[9px] text-gray-400"><?= htmlspecialchars($log['device']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 text-[11px] font-mono text-gray-500"><?= $log['ip_address'] ?></td>
                            <td class="py-4">
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase <?= $log['status'] == 'Success' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' ?>">
                                    <?= $log['status'] ?>
                                </span>
                            </td>
                            <td class="py-4 text-[10px] text-gray-400 font-medium">
                                <?= date('M d, Y • h:i A', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const notifButton = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            profileDropdown.classList.remove('show');
        });

        adminBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            notifDropdown.classList.remove('show');
        });

        window.onclick = () => {
            notifDropdown.classList.remove('show');
            profileDropdown.classList.remove('show');
        }
    </script>
</body>
</html>