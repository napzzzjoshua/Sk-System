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
$current_page = basename(__FILE__);

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

// --- FETCH ANNOUNCEMENTS DATA ---
$announcements = [];
$sql = "SELECT announcement_id AS id, title, content, status, created_at, published_at, user_id
        FROM announcements 
        ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Search filter logic
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search_query !== '') {
    $filtered_announcements = array_filter($announcements, function($a) use ($search_query) {
        return stripos($a['title'], $search_query) !== false;
    });
    $announcements = array_values($filtered_announcements);
}

// Pagination logic
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total_announcements = count($announcements);
$total_pages = max(1, ceil($total_announcements / $per_page));
$start_index = ($page - 1) * $per_page;
$paged_announcements = array_slice($announcements, $start_index, $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Management</title>
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

        .modal-bg { background-color: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); }
        .template-card { transition: all 0.3s; cursor: pointer; border: 1px solid #f1f5f9; }
        .template-card:hover { transform: translateY(-5px); border-color: var(--gold-accent); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
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
            <a href="form_management.php" class="nav-item active"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-bold text-[#1B1B4B]">Content Management</h2>
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Announcements & Resident Forms</p>
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

        <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
            <button id="newPostBtn" class="px-5 py-2.5 bg-[#1B1B4B] text-[#FFD700] text-xs font-bold rounded-xl shadow-md hover:opacity-90 transition transform active:scale-95">
                <i class="fas fa-plus-circle mr-2"></i> Create New Post
            </button>
            <form method="get" class="flex items-center bg-white rounded-xl px-3 py-1.5 border border-gray-200 shadow-sm focus-within:border-[#1B1B4B] transition">
                <i class="fas fa-search text-gray-300 text-xs mr-2"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search posts..." class="outline-none bg-transparent text-xs w-48">
                <button type="submit" class="hidden"></button>
            </form>
        </div>

        <div class="card-white overflow-hidden">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-[#1B1B4B]">Recent Announcements</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 text-[10px] uppercase tracking-wider border-b border-gray-50">
                            <th class="px-4 py-3 font-bold">Post Title</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Date Created</th>
                            <th class="px-4 py-3 font-bold text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <?php if (empty($paged_announcements)): ?>
                            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No content found matches your search.</td></tr>
                        <?php else: ?>
                            <?php foreach ($paged_announcements as $announcement): ?>
                                <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                                    <td class="px-4 py-4 font-semibold text-slate-700"><?= htmlspecialchars($announcement['title']) ?></td>
                                    <td class="px-4 py-4">
                                        <?php 
                                            $status_class = $announcement['status'] == 'Published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600';
                                        ?>
                                        <span class="px-2.5 py-1 rounded-md text-[9px] font-bold uppercase <?= $status_class ?>">
                                            <?= htmlspecialchars($announcement['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-gray-400"><?= date('M d, Y', strtotime($announcement['created_at'])) ?></td>
                                    <td class="px-4 py-4 text-center">
                                        <button class="edit-announcement-btn w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-[#1B1B4B] hover:text-white transition"
                                                data-id="<?= htmlspecialchars($announcement['id']) ?>"
                                                data-title="<?= htmlspecialchars($announcement['title']) ?>"
                                                data-content="<?= htmlspecialchars($announcement['content']) ?>"
                                                data-status="<?= htmlspecialchars($announcement['status']) ?>">
                                            <i class="fas fa-pen-to-square text-[10px]"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <p class="text-[10px] font-bold text-gray-400 uppercase">Showing <?= count($paged_announcements) ?> of <?= $total_announcements ?></p>
                <div class="flex gap-1">
                    <?php
                    $qp = $_GET;
                    $qp['page'] = $page - 1;
                    $prev_url = '?' . http_build_query($qp);
                    $qp['page'] = $page + 1;
                    $next_url = '?' . http_build_query($qp);
                    ?>
                    <a href="<?= $prev_url ?>" class="px-3 py-1.5 border rounded-lg text-[10px] font-bold hover:bg-gray-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : 'bg-white' ?>">Prev</a>
                    <a href="<?= $next_url ?>" class="px-3 py-1.5 border rounded-lg text-[10px] font-bold hover:bg-gray-50 <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : 'bg-white' ?>">Next</a>
                </div>
            </div>
        </div>
    </main>

    <div id="announcementModal" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4 modal-bg">
        <div class="bg-white p-6 rounded-[24px] shadow-2xl w-full max-w-xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-md font-bold text-[#1B1B4B]">Select Content Template</h3>
                <button id="closeModalBtn" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div data-template="General Announcement" class="template-card p-4 rounded-xl bg-blue-50">
                    <i class="fas fa-bullhorn text-xl text-blue-600 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">General Notice</h4>
                    <p class="text-[10px] text-gray-500">Holiday schedules or reminders.</p>
                </div>
                <div data-template="Community Event" class="template-card p-4 rounded-xl bg-emerald-50">
                    <i class="fas fa-calendar-alt text-xl text-emerald-600 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">Community Event</h4>
                    <p class="text-[10px] text-gray-500">Meetings, sports, or gatherings.</p>
                </div>
                <div data-template="Urgent Advisory" class="template-card p-4 rounded-xl bg-red-50">
                    <i class="fas fa-triangle-exclamation text-xl text-red-600 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">Urgent Advisory</h4>
                    <p class="text-[10px] text-gray-500">Weather alerts or safety warnings.</p>
                </div>
                <div data-template="Simple Form" class="template-card p-4 rounded-xl bg-amber-50">
                    <i class="fas fa-file-signature text-xl text-amber-600 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">Survey Form</h4>
                    <p class="text-[10px] text-gray-500">Data collection or feedback.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="editAnnouncementModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-bg">
        <div class="bg-white rounded-[24px] shadow-2xl w-full max-w-md p-6 relative">
            <h2 class="text-md font-bold mb-5 text-[#1B1B4B] flex items-center"><i class="fas fa-pen-nib mr-2 text-gold-accent"></i>Edit Post</h2>
            <form id="editAnnouncementForm">
                <input type="hidden" name="id" id="editAnnouncementId">
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">Title</label>
                    <input type="text" name="title" id="editAnnouncementTitle" class="w-full px-3 py-2 bg-slate-50 border border-gray-100 rounded-xl text-xs focus:border-[#1B1B4B] outline-none" required>
                </div>
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">Message Body</label>
                    <textarea name="content" id="editAnnouncementContent" rows="4" class="w-full px-3 py-2 bg-slate-50 border border-gray-100 rounded-xl text-xs focus:border-[#1B1B4B] outline-none" required></textarea>
                </div>
                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">Status</label>
                    <select name="status" id="editAnnouncementStatus" class="w-full px-3 py-2 bg-slate-50 border border-gray-100 rounded-xl text-xs outline-none">
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="button" id="cancelEditAnnouncement" class="flex-grow py-2.5 border border-gray-100 rounded-xl text-xs font-bold text-gray-400 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-grow py-2.5 bg-[#1B1B4B] text-white rounded-xl text-xs font-bold shadow-md hover:opacity-90 transition">Save Changes</button>
                </div>
                <div id="editAnnouncementMsg" class="mt-4 text-center text-[11px] font-bold"></div>
            </form>
        </div>
    </div>

    <script>
        // Dropdown Handlers (Synced with admin_dashboard.php logic)
        const notifButton = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');
        const adminBtn = document.getElementById('adminProfileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        const notifBadge = document.getElementById('notifBadge');

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

        // Modal Controls
        const newPostBtn = document.getElementById('newPostBtn');
        const modal = document.getElementById('announcementModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        
        newPostBtn.onclick = () => modal.classList.remove('hidden');
        closeModalBtn.onclick = () => modal.classList.add('hidden');

        // Template Redirection
        document.querySelectorAll('.template-card').forEach(card => {
            card.onclick = () => {
                const templateName = card.getAttribute('data-template');
                window.location.href = `create_announcement.php?template=${encodeURIComponent(templateName)}`;
            };
        });

        // Edit Announcement Logic
        const editModal = document.getElementById('editAnnouncementModal');
        const editForm = document.getElementById('editAnnouncementForm');
        const editMsg = document.getElementById('editAnnouncementMsg');

        document.querySelectorAll('.edit-announcement-btn').forEach(btn => {
            btn.onclick = function() {
                document.getElementById('editAnnouncementId').value = this.dataset.id;
                document.getElementById('editAnnouncementTitle').value = this.dataset.title;
                document.getElementById('editAnnouncementContent').value = this.dataset.content;
                document.getElementById('editAnnouncementStatus').value = this.dataset.status;
                editMsg.textContent = '';
                editModal.classList.remove('hidden');
            };
        });

        document.getElementById('cancelEditAnnouncement').onclick = () => editModal.classList.add('hidden');

        editForm.onsubmit = (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            fetch('update_announcement.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    editMsg.className = "mt-4 text-center text-emerald-500 font-bold";
                    editMsg.textContent = "Successfully updated!";
                    setTimeout(() => location.reload(), 1000);
                } else {
                    editMsg.className = "mt-4 text-center text-red-500 font-bold";
                    editMsg.textContent = data.message || "Update failed.";
                }
            });
        };

        // Close dropdowns and modals on outside click
        window.onclick = (e) => {
            if (e.target === modal) modal.classList.add('hidden');
            if (e.target === editModal) editModal.classList.add('hidden');
            
            // Standard click-away for dropdowns
            if (!notifButton.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('show');
            }
            if (!adminBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        };
    </script>
</body>
</html>