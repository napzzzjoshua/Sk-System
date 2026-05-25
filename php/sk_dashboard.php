<?php
session_start();

// --- Access Control ---
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

$sql = "SELECT email, barangay, position, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email, $barangay, $position, $profile_photo);
$stmt->fetch();
$stmt->close();

$_SESSION['email'] = $user_email;
$_SESSION['barangay'] = $barangay;
$_SESSION['position'] = $position;

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

// --- Analytics & Year Logic ---
$years = [];
$year_query = "SELECT DISTINCT YEAR(created_at) as year FROM (SELECT created_at FROM financial_aid_requests UNION ALL SELECT created_at FROM submissions UNION ALL SELECT published_at as created_at FROM announcements) as all_years ORDER BY year DESC";
$year_result = $conn->query($year_query);
if ($year_result) { while ($row = $year_result->fetch_assoc()) { $years[] = (int)$row['year']; } }
$current_real_year = (int)date('Y');
if (count($years) === 0) { $years[] = $current_real_year; } 
else { if ($current_real_year > max($years)) { array_unshift($years, $current_real_year); } }
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $years[0];

// Scholar Analytics
$scholar_levels = ['Elementary', 'High School', 'Senior High', 'College'];
$scholars_by_level = [];
foreach ($scholar_levels as $level) {
    $sql = "SELECT COUNT(*) AS count FROM scholarship_applications WHERE barangay = ? AND educational_level = ? AND YEAR(date_submitted) = ?";
    $stmt = $conn->prepare($sql); $stmt->bind_param("ssi", $barangay, $level, $selected_year); $stmt->execute();
    $scholars_by_level[$level] = $stmt->get_result()->fetch_assoc()['count']; $stmt->close();
}

$sql_proposals = "SELECT COUNT(*) AS total FROM submissions WHERE status = 'Approved' AND barangay = ? AND YEAR(created_at) = ?";
$stmt = $conn->prepare($sql_proposals); $stmt->bind_param("si", $barangay, $selected_year); $stmt->execute();
$total_proposals = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

$sql_aid = "SELECT COUNT(*) AS total, SUM(total_amount) AS sum FROM financial_aid_requests WHERE status = 'Approved' AND barangay = ? AND YEAR(created_at) = ?";
$stmt = $conn->prepare($sql_aid); $stmt->bind_param("si", $barangay, $selected_year); $stmt->execute();
$res_aid = $stmt->get_result()->fetch_assoc();
$total_aid_requests = $res_aid['total'] ?? 0;
$total_financial_aid = $res_aid['sum'] ?? 0; 
$stmt->close();

$sql_ann = "SELECT title, content, published_at FROM announcements WHERE status = 'Published' AND YEAR(published_at) = ? ORDER BY published_at DESC LIMIT 4";
$stmt = $conn->prepare($sql_ann); $stmt->bind_param("i", $selected_year); $stmt->execute();
$result_ann = $stmt->get_result();
$recent_announcements = [];
while($row = $result_ann->fetch_assoc()) {
    $recent_announcements[] = ['title' => $row['title'], 'content' => $row['content'], 'date' => date('M j, Y', strtotime($row['published_at']))];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Dashboard | Modern Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
            position: sticky;
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

        .main-container { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .main-content { padding: 24px; flex: 1; }
        
        /* Panel Styles */
        .right-panel { 
            width: 320px; 
            background: #FFFFFF; 
            padding: 24px; 
            border-left: 1px solid #e2e8f0; 
            flex-shrink: 0; 
            transition: all 0.3s ease; 
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* Cards */
        .stat-card { border-radius: 24px; padding: 20px; color: white; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .card-orange { background: linear-gradient(135deg, #1B1B4B 0%, #31317a 100%); }
        .card-green { background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); }
        .card-blue { background: linear-gradient(135deg, #FFD700 0%, #facc15 100%); color: #1B1B4B; }
        
        .content-card { background: white; border-radius: 24px; padding: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }

        /* Navigation */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

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
            .right-panel { display: none; } /* Hide summary on tablets to save space, can be toggled */
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
            <a href="sk_dashboard.php" class="nav-link active">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            
            <?php if (strcasecmp($position, 'SK Treasurer') === 0): ?>
                <a href="financial_aid_tre.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list_tre.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php elseif (strcasecmp($position, 'SK Secretary') === 0): ?>
                <a href="document_submissions_sec.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="submit_proposal_sec.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
            <?php else: ?>
                <a href="sk_list.php" class="nav-link"><i class="fas fa-users"></i> SK Members</a>
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
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">Dashboard</h1>
                    <p id="current-time" class="text-[11px] text-slate-500 font-semibold"></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <form id="yearForm" method="get" class="hidden sm:block">
                    <select name="year" onchange="this.form.submit()" class="bg-white border border-slate-200 text-xs font-bold px-3 py-2 rounded-xl outline-none focus:ring-2 focus:ring-[#FFD700]">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>>FY <?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <button id="notificationBtn" class="notif-btn">
                    <i class="fa-regular fa-bell text-slate-600"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>

                <button id="rightPanelToggle" class="lg:hidden notif-btn">
                    <i class="fas fa-chart-line text-slate-600"></i>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card card-orange shadow-lg shadow-blue-900/10">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80 mb-1">Total Scholars</p>
                            <h3 class="text-3xl font-black"><?= array_sum($scholars_by_level) ?></h3>
                        </div>
                        <div class="bg-white/10 p-3 rounded-2xl"><i class="fas fa-user-graduate text-xl"></i></div>
                    </div>
                </div>
                <div class="stat-card card-green shadow-lg shadow-orange-900/10">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80 mb-1">Approved Aid</p>
                            <h3 class="text-3xl font-black"><?= $total_aid_requests ?></h3>
                        </div>
                        <div class="bg-white/10 p-3 rounded-2xl"><i class="fas fa-hand-holding-dollar text-xl"></i></div>
                    </div>
                </div>
                <div class="stat-card card-blue shadow-lg shadow-yellow-600/10">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80 mb-1">Approved Projects</p>
                            <h3 class="text-3xl font-black"><?= $total_proposals ?></h3>
                        </div>
                        <div class="bg-black/5 p-3 rounded-2xl"><i class="fas fa-file-circle-check text-xl"></i></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <div class="content-card">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="font-bold text-[#1B1B4B]">Financial Statistics</h4>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Requests vs Approved</span>
                    </div>
                    <div class="h-64">
                        <canvas id="dashboardChart"></canvas>
                    </div>
                </div>
                <div class="content-card">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="font-bold text-[#1B1B4B]">Scholar Distribution</h4>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">By Level</span>
                    </div>
                    <div class="h-64">
                        <canvas id="scholarChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-[#1B1B4B]">Latest Announcements</h4>
                    <a href="#" class="text-xs font-bold text-orange-600">View All</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($recent_announcements as $ann): ?>
                        <div class="bg-white p-5 rounded-[24px] border border-slate-100 flex items-start gap-4 hover:shadow-md transition cursor-pointer sk-announcement-item"
                             data-title="<?= htmlspecialchars($ann['title']) ?>"
                             data-content="<?= htmlspecialchars($ann['content']) ?>"
                             data-date="<?= htmlspecialchars($ann['date']) ?>">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 flex-shrink-0 flex items-center justify-center text-orange-600">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="min-w-0">
                                <h5 class="font-bold text-[#1B1B4B] text-sm truncate"><?= htmlspecialchars($ann['title']) ?></h5>
                                <p class="text-[11px] text-slate-400 font-medium"><?= $ann['date'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <aside class="right-panel" id="rightPanel">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-lg font-extrabold text-[#1B1B4B]">Budget Summary</h3>
            <button id="closeSummaryBtn" class="lg:hidden text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="bg-[#1B1B4B] rounded-[32px] p-6 mb-8 text-white shadow-xl shadow-blue-900/20 relative overflow-hidden">
            <div class="relative z-10">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Available Budget</p>
                <?php $available_budget = ($total_financial_aid ?? 0) + ($total_submission_budget ?? 0); ?>
                <h2 class="text-2xl font-black mb-6">₱ <?= number_format($available_budget, 2) ?></h2>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] bg-green-500 text-white px-3 py-1 rounded-full font-bold">STATUS: LIVE</span>
                    <span class="text-[10px] text-slate-400 font-bold"><?= $selected_year ?></span>
                </div>
            </div>
            <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-white/5 rounded-full"></div>
        </div>

        <div class="mb-8">
            <h4 class="text-xs font-bold text-[#1B1B4B] mb-6 uppercase tracking-wider">Recent Activity</h4>
            <div class="space-y-6">
                <?php foreach (array_slice($notifications, 0, 4) as $notif): ?>
                    <div class="flex gap-4">
                        <div class="relative">
                            <div class="w-2 h-2 rounded-full mt-1.5 <?= $notif['is_read'] ? 'bg-slate-200' : 'bg-yellow-400' ?>"></div>
                            <div class="absolute top-4 left-[3px] w-[1px] h-full bg-slate-100"></div>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 font-medium leading-relaxed mb-1"><?= htmlspecialchars($notif['message']) ?></p>
                            <span class="text-[10px] text-slate-400 font-bold"><?= time_ago($notif['created_at']) ?> ago</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-100 rounded-[28px] p-6 text-center">
            <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                <i class="fas fa-headset text-[#1B1B4B]"></i>
            </div>
            <h5 class="text-xs font-bold text-[#1B1B4B] mb-1">Need help?</h5>
            <p class="text-[10px] text-slate-500 mb-6">Our admin support is available 24/7 for technical issues.</p>
            <a href="chat.php" class="block bg-[#1B1B4B] text-white text-xs font-bold py-3 rounded-xl hover:bg-slate-800 transition">Contact Support</a>
        </div>
    </aside>
</div>

<div id="announcementModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center z-[2000] p-4">
    <div class="bg-white rounded-[40px] p-8 w-full max-w-lg shadow-2xl transition-all transform scale-100">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 id="modalAnnouncementTitle" class="text-xl font-black text-[#1B1B4B]"></h2>
                <p id="modalAnnouncementDate" class="text-[11px] text-orange-600 font-bold uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="document.getElementById('announcementModal').classList.add('hidden')" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalAnnouncementContent" class="text-slate-600 text-sm leading-relaxed mb-8 max-h-[50vh] overflow-y-auto pr-2"></div>
        <button onclick="document.getElementById('announcementModal').classList.add('hidden')" class="w-full py-4 bg-[#1B1B4B] text-white rounded-2xl text-xs font-bold hover:shadow-lg transition">Dismiss</button>
    </div>
</div>

<script>
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    const sidebar = document.getElementById('sidebar');
    const rightPanel = document.getElementById('rightPanel');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const rightPanelToggle = document.getElementById('rightPanelToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSummaryBtn = document.getElementById('closeSummaryBtn');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });

    rightPanelToggle.addEventListener('click', () => {
        rightPanel.classList.toggle('active');
        if(rightPanel.classList.contains('active')) sidebarOverlay.classList.add('active');
    });

    closeSummaryBtn.addEventListener('click', () => {
        rightPanel.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        rightPanel.classList.remove('active');
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

    // Charts
    const commonOptions = { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false } },
        scales: { 
            y: { grid: { display: false }, ticks: { font: { size: 10, weight: '600' }, color: '#94a3b8' } },
            x: { grid: { display: false }, ticks: { font: { size: 10, weight: '600' }, color: '#94a3b8' } }
        }
    };

    new Chart(document.getElementById('dashboardChart'), {
        type: 'bar',
        data: { 
            labels: ['Approved Proposals', 'Aid Requests'], 
            datasets: [{ 
                data: [<?= $total_proposals ?>, <?= $total_aid_requests ?>], 
                backgroundColor: ['#1B1B4B', '#FFD700'], 
                borderRadius: 12, 
                barThickness: 40 
            }] 
        },
        options: commonOptions
    });

    new Chart(document.getElementById('scholarChart'), {
        type: 'doughnut',
        data: { 
            labels: <?= json_encode($scholar_levels) ?>, 
            datasets: [{ 
                data: <?= json_encode(array_values($scholars_by_level)) ?>, 
                backgroundColor: ['#1B1B4B', '#FFD700', '#ea580c', '#64748b'], 
                borderWidth: 0,
                hoverOffset: 10
            }] 
        },
        options: { 
            ...commonOptions, 
            plugins: { 
                legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 20, font: { size: 10, weight: '700' } } } 
            }, 
            cutout: '70%' 
        }
    });

    document.querySelectorAll('.sk-announcement-item').forEach(item => {
        item.onclick = function() {
            document.getElementById('modalAnnouncementTitle').textContent = this.dataset.title;
            document.getElementById('modalAnnouncementDate').textContent = this.dataset.date;
            document.getElementById('modalAnnouncementContent').innerHTML = this.dataset.content;
            document.getElementById('announcementModal').classList.remove('hidden');
        }
    });
</script>
</body>
</html>