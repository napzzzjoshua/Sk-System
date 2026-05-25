<?php
session_start();

// --- Access Control (Preserved from original) ---
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['SK Official','SK Chairperson','SK Members','SK Treasurer','SK Secretary'])
) {
    header("Location: login.php");
    exit;
}

require_once 'db_conn.php';

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Initialize variables
$barangay = null;
$position = null;
$profile_photo = null;
$user_email = null;

// --- Fetch User Details (Preserved from original) ---
if (isset($conn)) {
    $sql  = "SELECT barangay, position, profile_photo, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($barangay_temp, $position_temp, $profile_photo_temp, $user_email_temp);
        if ($stmt->fetch()) {
            $barangay = $barangay_temp;
            $position = $position_temp;
            $profile_photo = $profile_photo_temp;
            $user_email = $user_email_temp;
        }
        $stmt->close();
    }
}

if (empty($barangay)) { $barangay = "Unknown Barangay"; }

// --- Photo Path Helper ---
function get_dummy_photo($filename) {
    $defaultPath = "uploads/profiles/default-avatar.png";
    $directoryPrefix = "uploads/profiles/";
    if (empty($filename) || strpos($filename, basename($defaultPath)) !== false) return $defaultPath;
    if (strpos($filename, $directoryPrefix) === 0) return $filename; 
    return $directoryPrefix . $filename; 
}

$profilePath = get_dummy_photo($profile_photo);

// --- Logo Logic (Matched to Dashboard) ---
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Notifications Logic (Matched to Dashboard) ---
$notif_sql = "SELECT id, message, related_link, created_at, is_read FROM sk_notifications WHERE (email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL) OR (email IS NULL AND barangay IS NULL AND position = ?) ORDER BY created_at DESC LIMIT 5";
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

// --- Fetch SK Officials (Preserved logic) ---
$sk_officials_list = [];
if (isset($conn) && !empty($barangay)) {
    $sql = "SELECT id, first_name, middle_name, last_name, position, phone_number AS contact, email, profile_photo AS photo FROM sk_list WHERE barangay = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $barangay);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['name'] = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
            $sk_officials_list[] = $row;
        }
        $stmt->close();
    }
}

// --- Sorting Logic ---
$position_order = ['SK Chairman'=>1,'SK Kagawad (1st)'=>2,'SK Kagawad (2nd)'=>3,'SK Kagawad (3rd)'=>4,'SK Kagawad (4th)'=>5,'SK Kagawad (5th)'=>6,'SK Kagawad (6th)'=>7,'SK Kagawad (7th)'=>8,'SK Secretary'=>9,'SK Treasurer'=>10];
usort($sk_officials_list, function ($a, $b) use ($position_order) {
    return ($position_order[$a['position']] ?? 99) <=> ($position_order[$b['position']] ?? 99);
});

// Helper for relative time (Dashboard Style)
function time_ago($datetime) {
    $now = new DateTime; $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return $diff->h . ' hr' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '');
    return 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK List | <?= htmlspecialchars($barangay) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; margin: 0; font-size: 13px; color: #1e293b; }
        
        .app-wrapper { display: flex; min-height: 100vh; position: relative; }
        
        /* Sidebar - Widened slightly as requested */
        .sidebar { 
            width: 260px; 
            background: #1B1B4B; 
            padding: 24px 16px; 
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            position: sticky; 
            top: 0; 
            height: 100vh;
            z-index: 1001; 
        }

        /* Sticky Header */
        .sticky-header {
            position: relative;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Sticky only on mobile */
        @media (max-width: 768px) {
            .sticky-header {
                position: sticky;
                top: 0;
            }
        }

        .main-container { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .main-content { padding: 24px; flex: 1; }

        /* Content Card */
        .content-card { background: white; border-radius: 24px; padding: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .official-card { transition: all 0.3s ease; border: 1px solid transparent; }
        .official-card:hover { transform: translateY(-5px); border-color: #FFD700; box-shadow: 0 12px 30px rgba(0,0,0,0.05); }

        /* Navigation */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }

        /* Modern Notification Button */
        .notif-btn {
            position: relative;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .notif-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 20px;
            border: 2px solid white;
            min-width: 20px;
            text-align: center;
        }

        #notificationDropdown { 
            position: absolute; top: 65px; right: 24px; width: 320px; 
            background: white; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            border: 1px solid #e2e8f0; z-index: 1002; display: none; overflow: hidden; 
        }

        /* Mobile Adjustments */
        .mobile-menu-btn { display: none; background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; }
        
        @media (max-width: 1024px) {
            .right-panel { display: none; } 
            .right-panel.active { display: block; position: fixed; right: 0; z-index: 1005; box-shadow: -10px 0 30px rgba(0,0,0,0.1); }
        }

        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; height: 100vh; }
            .sidebar.active { left: 0; }
            .mobile-menu-btn { display: block; }
            .sticky-header { padding: 10px 16px; }
            .main-content { padding: 16px; }
            .header-title-wrapper { display: flex; flex-direction: column; }
            .header-title-wrapper h1 { font-size: 16px !important; }
        }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 1000; }
        .sidebar-overlay.active { display: block; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* Hide header when modal is open */
        body.modal-open .sticky-header { display: none; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="flex items-center gap-3 mb-10 px-2">
            <img src="<?= htmlspecialchars($logoPath) ?>" class="w-10 h-10 rounded-xl shadow-lg object-cover">
            <div>
                <h2 class="font-extrabold text-white text-base leading-tight">SK System</h2>
                <span class="text-[10px] text-[#FFD700] font-bold uppercase tracking-widest"><?= htmlspecialchars($barangay) ?></span>
            </div>
        </div>

        <nav class="flex-grow">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4 px-4">Menu</p>
            <a href="sk_dashboard.php" class="nav-link">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            
            <?php if (strcasecmp($position, 'SK Treasurer') === 0): ?>
                <a href="financial_aid_tre.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list_tre.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php elseif (strcasecmp($position, 'SK Secretary') === 0): ?>
                <a href="document_submissions_sec.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="submit_proposal_sec.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
            <?php else: ?>
                <a href="sk_list.php" class="nav-link active"><i class="fas fa-users"></i> SK Members</a>
                <a href="document_submissions.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="submit_proposal.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
                <a href="financial_aid.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto bg-white/5 rounded-2xl p-4 border border-white/10">
            <div class="flex items-center gap-3 mb-4">
                <img src="<?= htmlspecialchars($profilePath) ?>" class="w-10 h-10 rounded-xl object-cover border-2 border-[#FFD700]/30">
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($position) ?></p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="settings.php" class="flex-1 text-center py-2 bg-white/10 rounded-lg text-[10px] text-white font-bold hover:bg-white/20 transition">Settings</a>
                <a href="login.php" class="flex-1 text-center py-2 bg-orange-600/20 rounded-lg text-[10px] text-orange-400 font-bold hover:bg-orange-600/30 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <div class="main-container">
        <header class="sticky-header">
            <div class="flex items-center gap-4">
                <button id="sidebarToggle" class="mobile-menu-btn text-[#1B1B4B]">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="header-title-wrapper">
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">Official Roster</h1>
                    <p id="current-time" class="text-[11px] text-slate-500 font-semibold"></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="openAddModal()" class="bg-[#1B1B4B] text-white text-[11px] font-bold px-5 py-2.5 rounded-xl hover:bg-slate-800 transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Official
                </button>

                <button id="notificationBtn" class="notif-btn">
                    <i class="fa-regular fa-bell text-slate-600"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <div id="notificationDropdown">
                <div class="p-4 border-b flex justify-between items-center bg-slate-50/50">
                    <span class="font-bold text-sm">Notifications</span>
                    <span class="text-[10px] bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full font-bold"><?= $unread_count ?> New</span>
                </div>
                <div class="max-h-[300px] overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                        <div class="p-8 text-center">
                            <i class="fa-regular fa-envelope-open text-slate-300 text-2xl mb-2"></i>
                            <p class="text-xs text-slate-400">All caught up!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="p-4 border-b hover:bg-slate-50 transition cursor-pointer <?= $n['is_read'] ? '' : 'bg-blue-50/30' ?>">
                                <p class="text-xs text-slate-700 leading-normal mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                <span class="text-[10px] text-slate-400 font-medium"><i class="fa-regular fa-clock mr-1"></i><?= time_ago($n['created_at']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="notifications.php" class="block p-3 text-center text-xs font-bold text-[#1B1B4B] hover:bg-slate-50">View All Activities</a>
            </div>
        </header>

        <main class="main-content">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-6">
            <?php foreach ($sk_officials_list as $official): ?>
                <div class="content-card official-card flex flex-col items-center text-center p-6" id="official-card-<?= $official['id'] ?>">
                    <div class="relative mb-4">
                        <img src="<?= htmlspecialchars(get_dummy_photo($official['photo'])) ?>" class="w-20 h-20 rounded-2xl object-cover shadow-sm border-4 border-slate-50">
                        <div class="absolute -bottom-2 -right-2 bg-[#FFD700] text-[#1B1B4B] w-7 h-7 rounded-lg flex items-center justify-center border-4 border-white shadow-sm">
                            <i class="fas fa-shield-halved text-[10px]"></i>
                        </div>
                        <button onclick="deleteOfficial(<?= $official['id'] ?>)" title="Delete Official" style="position:absolute;top:-10px;right:-10px;background:#ea580c;border:none;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;">
                            <i class="fas fa-trash text-white text-xs"></i>
                        </button>
                    </div>
                    <h3 class="font-extrabold text-[#1B1B4B] text-sm truncate w-full"><?= htmlspecialchars($official['name']) ?></h3>
                    <p class="text-[10px] font-bold text-[#ea580c] uppercase tracking-wider mb-4"><?= htmlspecialchars($official['position']) ?></p>
                    
                    <div class="w-full space-y-2 mb-5">
                        <div class="flex items-center justify-center gap-2 text-slate-500">
                            <i class="fas fa-phone text-[9px]"></i>
                            <span class="text-[11px] font-medium"><?= htmlspecialchars($official['contact']) ?></span>
                        </div>
                        <div class="flex items-center justify-center gap-2 text-slate-500">
                            <i class="fas fa-envelope text-[9px]"></i>
                            <span class="text-[11px] font-medium truncate px-2"><?= htmlspecialchars($official['email']) ?></span>
                        </div>
                    </div>

                    <button onclick='openEditModal(<?= json_encode($official) ?>)' class="w-full py-2.5 bg-slate-50 text-[#1B1B4B] text-[11px] font-bold rounded-xl border border-slate-200 hover:bg-[#1B1B4B] hover:text-white transition">
                        Edit Official
                    </button>
                </div>
            <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<div id="addOfficialModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-50 p-6">
    <div class="bg-white rounded-[30px] p-8 w-full max-w-md shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-xl font-black text-[#1B1B4B]">Add New Official</h2>
                <p class="text-[10px] text-[#ea580c] font-bold uppercase tracking-widest mt-1">Information Details</p>
            </div>
            <button onclick="closeAddModal()" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 transition flex-shrink-0">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addOfficialForm" action="add_official.php" method="post" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="barangay" value="<?= htmlspecialchars($barangay) ?>">

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-2">Profile Picture (Optional)</label>
                <input type="file" name="profile_photo" class="w-full text-[10px] text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-[#FFD700] file:text-[#1B1B4B] file:cursor-pointer hover:file:bg-yellow-500">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Position</label>
                <select name="position" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
                    <option value="">Select Position</option>
                    <option value="SK Chairman">SK Chairman</option>
                    <option value="SK Kagawad (1st)">SK Kagawad (1st)</option>
                    <option value="SK Kagawad (2nd)">SK Kagawad (2nd)</option>
                    <option value="SK Kagawad (3rd)">SK Kagawad (3rd)</option>
                    <option value="SK Kagawad (4th)">SK Kagawad (4th)</option>
                    <option value="SK Kagawad (5th)">SK Kagawad (5th)</option>
                    <option value="SK Kagawad (6th)">SK Kagawad (6th)</option>
                    <option value="SK Kagawad (7th)">SK Kagawad (7th)</option>
                    <option value="SK Secretary">SK Secretary</option>
                    <option value="SK Treasurer">SK Treasurer</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">First Name</label>
                    <input type="text" name="first_name" required placeholder="Enter first name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Last Name</label>
                    <input type="text" name="last_name" required placeholder="Enter last name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
                </div>
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Middle Name (Optional)</label>
                <input type="text" name="middle_name" placeholder="Enter middle name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Email Address</label>
                <input type="email" name="email" required placeholder="official@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Phone Number (11 Digits)</label>
                <input type="tel" name="phone_number" required placeholder="09123456789" maxlength="11" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeAddModal()" class="flex-1 py-3 bg-slate-100 text-[#1B1B4B] rounded-xl text-[11px] font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 py-3 bg-[#ea580c] text-white rounded-xl text-[11px] font-bold hover:bg-orange-700 transition">Save Official</button>
            </div>
        </form>
    </div>
</div>

<div id="editOfficialModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-50 p-6">
    <div class="bg-white rounded-[30px] p-8 w-full max-w-md shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-xl font-black text-[#1B1B4B]">Edit Profile</h2>
                <p id="edit_pos_label" class="text-[10px] text-[#ea580c] font-bold uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="closeEditModal()" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 transition flex-shrink-0">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editOfficialForm" action="update_official.php" method="post" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" id="official_id" name="id">
            
            <div class="flex justify-center mb-6">
                <img id="current_photo_preview" src="" class="w-24 h-24 rounded-2xl object-cover border-4 border-slate-100 shadow-md">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">First Name</label>
                    <input type="text" id="edit_first_name" name="first_name" required placeholder="Enter first name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Last Name</label>
                    <input type="text" id="edit_last_name" name="last_name" required placeholder="Enter last name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
                </div>
            </div>
            
            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Middle Name (Optional)</label>
                <input type="text" id="edit_middle_name" name="middle_name" placeholder="Enter middle name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Email Address</label>
                <input type="email" id="edit_email" name="email" required placeholder="official@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-1">Phone Number (11 Digits)</label>
                <input type="tel" id="edit_phone" name="phone_number" required placeholder="09123456789" maxlength="11" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#FFD700]">
            </div>
            
            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase ml-1 block mb-2">Change Profile Picture</label>
                <input type="file" name="profile_photo" class="w-full text-[10px] text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-[#FFD700] file:text-[#1B1B4B] file:cursor-pointer hover:file:bg-yellow-500">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeEditModal()" class="flex-1 py-3 bg-slate-100 text-[#1B1B4B] rounded-xl text-[11px] font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 py-3 bg-[#1B1B4B] text-white rounded-xl text-[11px] font-bold hover:bg-slate-800 transition">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteOfficialModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-8 w-full max-w-xs shadow-2xl text-center">
        <i class="fas fa-exclamation-triangle text-[#ea580c] text-3xl mb-3"></i>
        <h2 class="text-lg font-black text-[#1B1B4B] mb-2">Delete Official?</h2>
        <p class="text-[11px] text-slate-500 mb-6">Are you sure you want to delete this official? This action cannot be undone.</p>
        <div class="flex gap-2">
            <button onclick="hideDeleteModal()" class="flex-1 py-2 bg-slate-100 text-[#1B1B4B] rounded-xl text-xs font-bold hover:bg-slate-200 transition">Cancel</button>
            <button onclick="confirmDeleteOfficial()" class="flex-1 py-2 bg-[#ea580c] text-white rounded-xl text-xs font-bold hover:bg-orange-700 transition">Delete</button>
        </div>
    </div>
</div>

<script>
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });

    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');

    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
    });

    document.addEventListener('click', () => { notifDropdown.style.display = 'none'; });

    // Modals (Preserved behavior with new design)
    function openAddModal() { 
        document.getElementById('addOfficialModal').classList.remove('hidden'); 
        document.body.classList.add('modal-open');
    }
    function closeAddModal() { 
        document.getElementById('addOfficialModal').classList.add('hidden'); 
        document.body.classList.remove('modal-open');
    }

    function openEditModal(data) {
        document.getElementById('official_id').value = data.id;
        document.getElementById('edit_first_name').value = data.first_name;
        document.getElementById('edit_middle_name').value = data.middle_name || '';
        document.getElementById('edit_last_name').value = data.last_name;
        document.getElementById('edit_phone').value = data.contact;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_pos_label').textContent = data.position;
        
        let photoSrc = 'uploads/profiles/default-avatar.png';
        if (data.photo) photoSrc = data.photo.startsWith('uploads/') ? data.photo : 'uploads/profiles/' + data.photo;
        document.getElementById('current_photo_preview').src = photoSrc;

        document.getElementById('editOfficialModal').classList.remove('hidden');
        document.body.classList.add('modal-open');
    }

    function closeEditModal() { 
        document.getElementById('editOfficialModal').classList.add('hidden'); 
        document.body.classList.remove('modal-open');
    }

    // Modal for delete confirmation
    let deleteOfficialId = null;
    function showDeleteModal(id) {
        deleteOfficialId = id;
        document.getElementById('deleteOfficialModal').classList.remove('hidden');
        document.body.classList.add('modal-open');
    }
    function hideDeleteModal() {
        deleteOfficialId = null;
        document.getElementById('deleteOfficialModal').classList.add('hidden');
        document.body.classList.remove('modal-open');
    }
    function confirmDeleteOfficial() {
        if (!deleteOfficialId) return;
        fetch('delete_official.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(deleteOfficialId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById('official-card-' + deleteOfficialId);
                if (card) card.remove();
                hideDeleteModal();
                showToast('Official deleted successfully.', 'success');
            } else {
                showToast('Failed to delete official.', 'error');
            }
        })
        .catch(() => showToast('Error deleting official.', 'error'));
    }
    // Replace old deleteOfficial with modal
    function deleteOfficial(id) {
        showDeleteModal(id);
    }
</script>

<!-- Toast notification -->
<div id="toastNotification" style="position:fixed;top:30px;right:30px;zIndex:9999;minWidth:220px;maxWidth:320px;padding:16px 24px;borderRadius:14px;fontWeight:bold;fontSize:14px;boxShadow:0 4px 24px rgba(0,0,0,0.10);display:none;"></div>

</body>
</html>