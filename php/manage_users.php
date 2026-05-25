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
$current_page = basename($_SERVER['PHP_SELF']);

// --- DATA LOGIC: PENDING USERS ---
$combined_pending_users = [];
$users_pending_result = $conn->query("SELECT * FROM users WHERE status = 'Pending'");
if ($users_pending_result) {
    while ($user = $users_pending_result->fetch_assoc()) {
        $user['source_table'] = 'users';
        $combined_pending_users[] = $user;
    }
}
$sec_users_pending_result = $conn->query("SELECT id, surname, firstname, middlename, position, barangay, status FROM sec_users WHERE status = 'Pending'");
if ($sec_users_pending_result) {
    while ($user = $sec_users_pending_result->fetch_assoc()) {
        $user['email'] = 'N/A';
        $user['profile_photo'] = null;
        $user['source_table'] = 'sec_users';
        $combined_pending_users[] = $user;
    }
}

// --- DATA LOGIC: NOTIFICATIONS & CHAT ---
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

// --- DATA LOGIC: APPROVED USERS ---
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$query = "SELECT * FROM users WHERE status = 'Approve' AND position IN ('SK Chairman', 'SK Treasurer', 'SK Secretary')";
if (!empty($search_query)) {
    $search_term = "%$search_query%";
    $query .= " AND (CONCAT(firstname, ' ', middlename, ' ', surname) LIKE '$search_term' OR barangay LIKE '$search_term' OR position LIKE '$search_term')";
}
$query .= " ORDER BY barangay ASC";
$approved_users_result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Manage Users</title>
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
        
        /* Sidebar Styles */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E6E8F0; position: fixed; height: 100vh; z-index: 40; display: flex; flex-direction: column; overflow: hidden; }
        .nav-item { display: flex; align-items: center; padding: 0.6rem 1.25rem; margin: 0.15rem 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; }
        .nav-item i { width: 20px; font-size: 1rem; margin-right: 1rem; display: flex; justify-content: center; }
        .nav-item:hover:not(.active) { background-color: #F4F5FF; color: var(--navy-primary); }
        .nav-item.active { background: var(--navy-primary); color: white; box-shadow: 0 4px 10px rgba(27, 27, 75, 0.15); border-right: 3px solid var(--gold-accent); }
        
        .tool-label { font-size: 0.65rem; font-weight: 700; color: #ABB1D1; letter-spacing: 0.05em; padding: 0.75rem 1.5rem 0.25rem; text-transform: uppercase; }
        .nav-tool-item { display: flex; align-items: center; padding: 0.5rem 1.25rem; margin: 0.1rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500; color: #8E92BC; transition: all 0.2s; text-decoration: none; position: relative; }
        .nav-tool-item i { width: 20px; font-size: 1rem; margin-right: 1rem; display: flex; justify-content: center; }
        
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }

        .main-content { margin-left: 260px; padding: 1.5rem; min-height: 100vh; }
        .card-white { background: white; border-radius: 20px; border: 1px solid #F0F1F7; padding: 1.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }

        /* Profile Dropdown Specific Styles */
        .user-menu-item { display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: #4A5568; transition: all 0.2s; cursor: pointer; }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }

        .tab-btn { transition: all 0.3s ease; }
        .tab-btn.active { background-color: var(--navy-primary); color: white; box-shadow: 0 4px 10px rgba(27, 27, 75, 0.1); }
        
        .section-container { display: none; }
        .section-container.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .modal-blur { background: rgba(27, 27, 75, 0.3); backdrop-filter: blur(4px); transition: all 0.3s ease; }
        .modal-scale { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
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
                <i class="fa-solid fa-comment-dots"></i><span>Messages</span>
                <?php if($unread_messages_count > 0): ?><span class="tool-badge"><?= $unread_messages_count ?></span><?php endif; ?>
            </a>
            <a href="uploads/charter/citizen_charter.pdf" target="_blank" class="nav-tool-item">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>Citizen Charter</span>
            </a>
            <div class="tool-label">Main Menu</div>
            <a href="geo_mapping.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
            <a href="manage_users.php" class="nav-item active"><i class="fa-solid fa-users-gear"></i><span>Manage Users</span></a>
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
            <div>
                <h2 class="text-xl font-bold text-[#1B1B4B]">User Management</h2>
                <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Review and verify community members</p>
            </div>
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <button id="notifButton" class="relative text-gray-400 hover:text-[#1B1B4B] transition outline-none">
                        <i class="far fa-bell text-lg"></i>
                        <?php if($unread_notifications_count > 0): ?><span id="notifBadge" class="badge-count"><?= $unread_notifications_count ?></span><?php endif; ?>
                    </button>
                    <div id="notifDropdown" class="dropdown-menu mt-4">
                        <div class="p-4 border-b border-gray-100"><h4 class="font-bold text-sm">Notifications</h4></div>
                        <div class="max-h-60 overflow-y-auto p-2">
                            <?php if(empty($notifications)): ?><p class="p-3 text-xs text-gray-400 text-center">No new notifications</p>
                            <?php else: foreach ($notifications as $n): ?>
                                <div class="p-3 border-b border-gray-50 text-xs text-gray-600"><?= htmlspecialchars($n['message']) ?></div>
                            <?php endforeach; endif; ?>
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

        <div class="flex bg-white p-1 rounded-xl w-fit mb-6 shadow-sm border border-gray-100">
            <button onclick="switchTab('pending')" id="tab-pending" class="tab-btn active px-5 py-2 rounded-lg text-[11px] font-bold flex items-center gap-2 uppercase tracking-wide">
                <i class="fas fa-clock"></i> Pending Approvals
                <span class="bg-amber-100 text-amber-600 px-2 py-0.5 rounded-md text-[9px]"><?= count($combined_pending_users) ?></span>
            </button>
            <button onclick="switchTab('approved')" id="tab-approved" class="tab-btn px-5 py-2 rounded-lg text-[11px] font-bold flex items-center gap-2 uppercase tracking-wide">
                <i class="fas fa-check-circle"></i> Verified Members
            </button>
        </div>

        <div id="pending-section" class="section-container active">
            <div class="card-white">
                <h3 class="text-sm font-bold text-[#1B1B4B] mb-6">Pending Registration Requests</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-bold text-gray-400 border-b border-gray-100 uppercase tracking-widest">
                                <th class="pb-4 px-2">Full Name</th>
                                <th class="pb-4 px-2">Position</th>
                                <th class="pb-4 px-2">Barangay</th>
                                <th class="pb-4 px-2 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            <?php if (empty($combined_pending_users)): ?>
                                <tr><td colspan="4" class="py-12 text-center text-gray-400 font-medium">No pending approvals found.</td></tr>
                            <?php else: foreach ($combined_pending_users as $user): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                                    <td class="py-4 px-2 font-semibold text-slate-700"><?= htmlspecialchars($user['firstname'] . ' ' . $user['surname']) ?></td>
                                    <td class="py-4 px-2 text-gray-500 font-medium"><?= htmlspecialchars($user['position']) ?></td>
                                    <td class="py-4 px-2 text-gray-500 font-medium"><?= htmlspecialchars($user['barangay']) ?></td>
                                    <td class="py-4 px-2">
                                        <div class="flex justify-center gap-2">
                                            <button onclick="openViewModal(this)" 
                                                    data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>'
                                                    class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition shadow-sm">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="approve_user.php?id=<?= $user['id'] ?>&source=<?= $user['source_table'] ?>&position=<?= urlencode($user['position']) ?>" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition shadow-sm"><i class="fas fa-check text-xs"></i></a>
                                            <a href="reject_user.php?id=<?= $user['id'] ?>&source=<?= $user['source_table'] ?>" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition shadow-sm"><i class="fas fa-times text-xs"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="approved-section" class="section-container">
            <div class="card-white">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <h3 class="text-sm font-bold text-[#1B1B4B]">Verified Members Directory</h3>
                    <form action="manage_users.php" method="GET" class="flex w-full md:w-auto gap-2">
                        <div class="relative flex-grow min-w-[280px]">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" name="search" placeholder="Search name, position or barangay..." value="<?= htmlspecialchars($search_query) ?>" class="pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs w-full focus:ring-2 focus:ring-[#1B1B4B] outline-none transition">
                        </div>
                        <button type="submit" class="bg-[#1B1B4B] text-white px-5 py-2 rounded-xl text-xs font-bold hover:opacity-90 transition">Search</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-bold text-gray-400 border-b border-gray-100 uppercase tracking-widest">
                                <th class="pb-4 px-2">Full Name</th>
                                <th class="pb-4 px-2">Position</th>
                                <th class="pb-4 px-2">Barangay</th>
                                <th class="pb-4 px-2 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            <?php if ($approved_users_result->num_rows > 0): while ($user = $approved_users_result->fetch_assoc()): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                                    <td class="py-4 px-2 font-semibold text-slate-700"><?= htmlspecialchars($user['firstname'] . ' ' . $user['surname']) ?></td>
                                    <td class="py-4 px-2 text-gray-500 font-medium"><?= htmlspecialchars($user['position']) ?></td>
                                    <td class="py-4 px-2 text-gray-500 font-medium"><?= htmlspecialchars($user['barangay']) ?></td>
                                    <td class="py-4 px-2">
                                        <div class="flex justify-center gap-2">
                                            <button onclick="openViewModal(this)" 
                                                    data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>'
                                                    class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-600 hover:text-white transition shadow-sm">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <button onclick="showDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname'] . ' ' . $user['surname']) ?>')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition shadow-sm"><i class="fas fa-trash-alt text-xs"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="py-12 text-center text-gray-400 font-medium">No verified members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="viewModal" class="fixed inset-0 z-[60] flex items-center justify-center hidden modal-blur opacity-0 p-4">
        <div id="viewModalContent" class="bg-white rounded-[28px] w-full max-w-md shadow-2xl overflow-hidden transform scale-90 modal-scale">
            <div class="bg-[#1B1B4B] p-8 text-center relative">
                <button onclick="closeViewModal()" class="absolute top-4 right-4 text-white/50 hover:text-white transition"><i class="fas fa-times"></i></button>
                <div class="w-20 h-20 bg-white/10 rounded-3xl flex items-center justify-center mx-auto mb-4 border border-white/20 overflow-hidden" id="viewAvatarWrapper">
                    <img id="viewProfileImg" src="" alt="Profile" class="w-full h-full object-cover rounded-3xl hidden">
                    <i id="viewProfileIcon" class="fas fa-user-circle text-4xl text-[#FFD700]"></i>
                </div>
                <h3 id="viewName" class="text-white text-lg font-bold leading-tight">User Name</h3>
                <span id="viewStatus" class="inline-block mt-2 px-3 py-1 bg-[#FFD700] text-[#1B1B4B] text-[9px] font-black uppercase tracking-tighter rounded-full">STATUS</span>
            </div>
            
            <div class="p-6 space-y-3 max-h-[55vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">First Name</span>
                    <span id="viewFirstname" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Middle Name</span>
                    <span id="viewMiddlename" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Last Name</span>
                    <span id="viewSurname" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Email Address</span>
                    <span id="viewEmail" class="text-sm font-bold text-[#1B1B4B] break-all text-right max-w-[60%]">-</span>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Designated Position</span>
                    <span id="viewPos" class="text-sm font-bold text-[#1B1B4B] text-right max-w-[60%]">-</span>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Resident Barangay</span>
                    <span id="viewBrgy" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewRoleRow" class="flex items-center justify-between border-b border-gray-50 pb-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">System Role</span>
                    <span id="viewRole" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewContactRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Contact Number</span>
                    <span id="viewContact" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewBirthdateRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Birthdate</span>
                    <span id="viewBirthdate" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewAgeRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Age</span>
                    <span id="viewAge" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewGenderRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Gender</span>
                    <span id="viewGender" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewAddressRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Address</span>
                    <span id="viewAddress" class="text-sm font-bold text-[#1B1B4B] text-right max-w-[60%]">-</span>
                </div>
                <div id="viewVoterRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Voter Status</span>
                    <span id="viewVoter" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                <div id="viewCreatedRow" class="flex items-center justify-between border-b border-gray-50 pb-3 hidden">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Registered On</span>
                    <span id="viewCreated" class="text-sm font-bold text-[#1B1B4B]">-</span>
                </div>
                
                <div id="viewIdDocRow" class="hidden">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">ID Document</p>
                    <div class="relative w-full rounded-2xl overflow-hidden bg-gray-50 border border-gray-100">
                        <img id="viewIdDocImg" src="" alt="ID Document" class="w-full object-contain max-h-52 rounded-2xl">
                        <a id="viewIdDocLink" href="#" target="_blank"
                           class="absolute bottom-2 right-2 bg-[#1B1B4B]/80 text-white text-[9px] font-bold px-3 py-1 rounded-full hover:bg-[#1B1B4B] transition">
                            <i class="fas fa-expand-alt mr-1"></i>View Full
                        </a>
                    </div>
                </div>
                <div id="viewIdDocNone" class="hidden">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">ID Document</p>
                    <div class="flex items-center gap-3 bg-gray-50 border border-dashed border-gray-200 rounded-2xl p-4">
                        <i class="fas fa-id-card text-gray-300 text-2xl"></i>
                        <span class="text-xs text-gray-400 font-medium">No ID document uploaded</span>
                    </div>
                </div>

                <button onclick="closeViewModal()" class="w-full mt-4 py-4 bg-[#1B1B4B] text-white rounded-2xl text-[11px] font-black uppercase tracking-widest hover:opacity-90 transition shadow-lg shadow-indigo-100">
                    Close Profile View
                </button>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-[60] flex items-center justify-center hidden modal-blur p-4">
        <div class="bg-white rounded-[24px] p-8 max-w-sm w-full shadow-2xl">
            <div class="text-center">
                <div class="w-14 h-14 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-5"><i class="fas fa-trash-can text-xl"></i></div>
                <h3 class="text-lg font-bold text-[#1B1B4B]">Confirm Deletion</h3>
                <p class="text-gray-400 text-[13px] font-medium mt-2">Are you sure you want to remove <br><span id="userName" class="text-slate-700 font-bold"></span>?</p>
                <div class="flex gap-3 mt-8">
                    <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold hover:bg-gray-200 transition">Cancel</button>
                    <a href="#" id="confirmDelete" class="flex-1 py-3 bg-red-500 text-white rounded-xl text-xs font-bold text-center hover:bg-red-600 transition shadow-lg shadow-red-100">Delete Account</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown Logic
        const notifButton = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            profileDropdown.classList.remove('show');
            if(document.getElementById('notifBadge')) {
                document.getElementById('notifBadge').remove();
                fetch('mark_notifications_read.php'); 
            }
        });

        adminBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            notifDropdown.classList.remove('show');
        });

        // Tab Switching Logic
        function switchTab(tabName) {
            document.querySelectorAll('.section-container').forEach(sec => sec.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`${tabName}-section`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        window.onload = () => { if ("<?= !empty($search_query) ? 'true' : 'false' ?>" === 'true') switchTab('approved'); };

        // Delete Modal Logic
        function showDeleteModal(userId, name) {
            document.getElementById('userName').textContent = name;
            document.getElementById('confirmDelete').href = `delete_user.php?id=${userId}`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        // View Modal Functions
        function openViewModal(btn) {
            const user = JSON.parse(btn.getAttribute('data-user'));
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewModalContent');

            // Helper: set field value, show/hide its row
            function setField(id, value, rowId) {
                const el = document.getElementById(id);
                const row = rowId ? document.getElementById(rowId) : null;
                const val = (value !== null && value !== undefined && String(value).trim() !== '') ? String(value) : null;
                if (el) el.textContent = val || '-';
                if (row) row.classList.toggle('hidden', !val);
            }

            // Always-visible fields
            document.getElementById('viewName').textContent =
                [user.firstname, user.middlename, user.surname].filter(Boolean).join(' ');
            document.getElementById('viewStatus').textContent = user.status || '-';
            setField('viewFirstname', user.firstname);
            setField('viewMiddlename', user.middlename);
            setField('viewSurname', user.surname);
            setField('viewEmail', user.email);
            setField('viewPos', user.position);
            setField('viewBrgy', user.barangay);
            setField('viewRole', user.role, 'viewRoleRow');

            // Optional fields — shown only if data exists
            setField('viewContact', user.contact_number || user.contact || user.phone, 'viewContactRow');
            setField('viewBirthdate', user.birthdate || user.birth_date || user.dob, 'viewBirthdateRow');
            setField('viewAge', user.age, 'viewAgeRow');
            setField('viewGender', user.gender, 'viewGenderRow');
            setField('viewAddress', user.address, 'viewAddressRow');
            setField('viewVoter', user.voter_status || user.voter, 'viewVoterRow');

            // Format created_at date nicely if present
            if (user.created_at) {
                const d = new Date(user.created_at);
                const formatted = isNaN(d) ? user.created_at : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
                setField('viewCreated', formatted, 'viewCreatedRow');
            } else {
                setField('viewCreated', null, 'viewCreatedRow');
            }

            // Handle profile photo (supports jpg, png, jfif)
            const imgEl = document.getElementById('viewProfileImg');
            const iconEl = document.getElementById('viewProfileIcon');
            const allowedExts = ['jpg', 'jpeg', 'png', 'jfif', 'gif', 'webp'];
            const profilePic = user.profile_photo || '';

            if (profilePic.trim() !== '') {
                const ext = profilePic.split('.').pop().toLowerCase();
                if (allowedExts.includes(ext)) {
                    imgEl.src = profilePic;
                    imgEl.onload = function() { imgEl.classList.remove('hidden'); iconEl.classList.add('hidden'); };
                    imgEl.onerror = function() { imgEl.classList.add('hidden'); iconEl.classList.remove('hidden'); };
                } else {
                    imgEl.classList.add('hidden'); iconEl.classList.remove('hidden');
                }
            } else {
                imgEl.classList.add('hidden'); iconEl.classList.remove('hidden');
            }

            // Handle ID document (supports jpg, png, jfif)
            const idDocImg = document.getElementById('viewIdDocImg');
            const idDocRow = document.getElementById('viewIdDocRow');
            const idDocNone = document.getElementById('viewIdDocNone');
            const idDocLink = document.getElementById('viewIdDocLink');
            const idDoc = user.id_document || '';

            if (idDoc.trim() !== '') {
                const ext2 = idDoc.split('.').pop().toLowerCase();
                if (allowedExts.includes(ext2)) {
                    idDocImg.src = idDoc;
                    idDocLink.href = idDoc;
                    idDocRow.classList.remove('hidden');
                    idDocNone.classList.add('hidden');
                } else {
                    idDocRow.classList.add('hidden');
                    idDocNone.classList.remove('hidden');
                }
            } else {
                idDocRow.classList.add('hidden');
                idDocNone.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.replace('opacity-0', 'opacity-100');
                content.classList.replace('scale-90', 'scale-100');
            }, 10);
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewModalContent');
            modal.classList.replace('opacity-100', 'opacity-0');
            content.classList.replace('scale-100', 'scale-90');
            // Reset image state for next open
            document.getElementById('viewProfileImg').classList.add('hidden');
            document.getElementById('viewProfileImg').src = '';
            document.getElementById('viewProfileIcon').classList.remove('hidden');
            // Reset ID document
            document.getElementById('viewIdDocImg').src = '';
            document.getElementById('viewIdDocRow').classList.add('hidden');
            document.getElementById('viewIdDocNone').classList.add('hidden');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        window.onclick = (e) => {
            if(notifDropdown && !notifDropdown.contains(e.target) && e.target !== notifButton) {
                notifDropdown.classList.remove('show');
            }
            if(profileDropdown && !profileDropdown.contains(e.target) && e.target !== adminBtn) {
                profileDropdown.classList.remove('show');
            }
            if(e.target === document.getElementById('deleteModal')) {
                document.getElementById('deleteModal').classList.add('hidden');
            }
            if(e.target === document.getElementById('viewModal')) {
                closeViewModal();
            }
        };
    </script>
</body>
</html>