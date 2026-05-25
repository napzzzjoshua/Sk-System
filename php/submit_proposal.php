<?php
session_start();
require_once 'db_conn.php';

// Only allow SK Officials access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SK Official', 'SK Chairperson', 'SK Members', 'SK Treasurer', 'SK Secretary'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$submissions = [];
$error_message = '';

// --- Fetch user details: fullname, role, and barangay ---
$sql = "SELECT email, position, barangay, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email, $position, $barangay, $profile_photo);
$stmt->fetch();
$stmt->close();

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

// --- Barangay-based SK logo logic ---
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";

// --- Notifications Logic ---
$notif_sql = "SELECT id, message, created_at, is_read FROM sk_notifications WHERE (email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL) ORDER BY created_at DESC LIMIT 5";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("ssss", $user_email, $barangay, $position, $barangay);
$stmt_notif->execute();
$result_notif = $stmt_notif->get_result();
$notifications = [];
while ($row = $result_notif->fetch_assoc()) { $notifications[] = $row; }
$stmt_notif->close();

$unread_count_sql = "SELECT COUNT(*) AS unread_count FROM sk_notifications WHERE (is_read = 0 AND email = ?) OR (is_read = 0 AND barangay = ? AND position = ?)";
$stmt_count = $conn->prepare($unread_count_sql);
$stmt_count->bind_param("sss", $user_email, $barangay, $position);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'] ?? 0;
$stmt_count->close();

// --- Logic to Fetch Submission Data ---
try {
    $query = "SELECT title, description, budget, objectives, expected_outcome, submitted_by, document_path, admin_doc, status, barangay, rejection_reason, created_at 
              FROM submissions 
              WHERE barangay = ? 
              ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay);
    $stmt->execute();
    $result = $stmt->get_result(); 
    if ($result) {
        while ($row = $result->fetch_assoc()) { $submissions[] = $row; }
        $result->free();
    }
    $stmt->close();
} catch (Exception $e) {
    $error_message = "An unexpected error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Proposals | SK System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Courier+Prime&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; font-size: 13px; color: #1e293b; }

        /* ── Layout Shell ── */
        .app-wrapper { display: flex; min-height: 100vh; position: relative; }

        /* ── Sidebar (Dashboard Style) ── */
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

        /* ── Sticky Header (Dashboard Style) ── */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* ── Main Container ── */
        .main-container { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .main-content { flex: 1; padding: 24px; background: #f1f5f9; overflow-y: auto; }

        /* ── Navigation Links ── */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

        /* ── Notification Button (Dashboard Style) ── */
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
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
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

        /* ── Notification Dropdown (Dashboard Style) ── */
        #notificationDropdown {
            position: absolute;
            top: 65px;
            right: 24px;
            width: 320px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            z-index: 1002;
            display: none;
            overflow: hidden;
        }

        /* ── Mobile Menu Button (Dashboard Style) ── */
        .mobile-menu-btn {
            display: none;
            background: white;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 12px;
            cursor: pointer;
            color: #1B1B4B;
            transition: all 0.2s;
        }
        .mobile-menu-btn:hover { background: #f1f5f9; }

        /* ── Sidebar Overlay (Dashboard Style) ── */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 1000; }
        .sidebar-overlay.active { display: block; }

        /* ── Cards ── */
        .content-card { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); }

        /* ── Status Badges ── */
        .status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 10px; text-transform: uppercase; }
        .status-Pending { background: #fff7ed; color: #c2410c; }
        .status-Approved { background: #f0fdf4; color: #15803d; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; }
        .status-ViewbyAdmin { background: #e0f2fe; color: #2563eb; }

        /* ── Receipt Style ── */
        .receipt-paper {
            background: #fff;
            font-family: 'Courier Prime', monospace;
            color: #1a1a1a;
            width: 400px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            position: relative;
            border-top: 10px solid #FFD700;
        }
        .receipt-paper::after {
            content: "";
            position: absolute;
            bottom: -10px; left: 0;
            width: 100%; height: 10px;
            background: linear-gradient(-45deg, transparent 5px, #fff 5px), linear-gradient(45deg, transparent 5px, #fff 5px);
            background-size: 10px 10px;
        }
        .receipt-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: block;
        }

        /* ── Header hide when modal is open ── */
        .sticky-header {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .sticky-header.modal-open {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* ── Tablet (≤1024px) ── */
        @media (max-width: 1024px) {
            .sidebar { width: 230px; padding: 20px 12px; }
            .main-content { padding: 20px; }
        }

        /* ── Mobile (≤768px) — Dashboard Approach ── */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                height: 100vh;
                top: 0;
            }
            .sidebar.active { left: 0; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
            .sticky-header { padding: 10px 16px; }
            .main-content { padding: 16px; }
            .header-title-wrapper h1 { font-size: 16px !important; }
            .content-card { padding: 16px; }
            table { font-size: 11px !important; }
            table td, table th { padding: 8px !important; }
            .filter-btn { padding: 5px 10px; font-size: 9px; }
        }

        /* ── Small Mobile (≤480px) ── */
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .content-card { padding: 12px; }
            table { font-size: 10px !important; }
            table td, table th { padding: 6px !important; }
            .status-badge { padding: 3px 8px; font-size: 9px; }
            .filter-btn { padding: 3px 6px; font-size: 8px; }
            .receipt-paper { width: 320px; padding: 28px; }
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar Overlay (Dashboard Style) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ── Sidebar (Dashboard Style) ── -->
    <aside class="sidebar" id="sidebar">
        <div class="flex items-center gap-3 mb-10 px-2">
            <img src="<?= htmlspecialchars($logoPath) ?>" class="w-10 h-10 rounded-xl shadow-lg object-cover">
            <div>
                <h2 class="font-extrabold text-white text-base leading-tight">SK System</h2>
                <span class="text-[10px] text-[#FFD700] font-bold uppercase tracking-widest"><?= htmlspecialchars($barangay) ?></span>
            </div>
        </div>

        <nav class="flex-grow">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4 px-4">Main Navigation</p>
            <a href="sk_dashboard.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="sk_list.php" class="nav-link"><i class="fas fa-users-viewfinder"></i> SK Members</a>
            <a href="document_submissions.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="submit_proposal.php" class="nav-link active"><i class="fas fa-paper-plane"></i> Proposals</a>
            <a href="financial_aid.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
            <a href="scholarship_list.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
        </nav>

        <!-- User Profile Box (Dashboard Style) -->
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

    <!-- ── Main Container (Dashboard Style) ── -->
    <div class="main-container">

        <!-- ── Sticky Header (Dashboard Style) ── -->
        <header class="sticky-header">
            <div class="flex items-center gap-4">
                <!-- Mobile menu toggle -->
                <button id="sidebarToggle" class="mobile-menu-btn" title="Toggle Sidebar">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="header-title-wrapper">
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">Project Proposals</h1>
                    <p id="current-time" class="text-[11px] text-slate-500 font-semibold"></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- New Proposal Button -->
                <a href="submit_project_proposal.php" class="bg-[#1B1B4B] text-white px-4 py-2 rounded-xl text-[11px] font-bold hover:bg-opacity-90 transition flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span class="hidden sm:inline">New Proposal</span>
                </a>

                <!-- Notification Button (Dashboard Style) -->
                <button id="notificationBtn" class="notif-btn">
                    <i class="fa-regular fa-bell text-slate-600"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Notification Dropdown (Dashboard Style) -->
            <div id="notificationDropdown">
                <div class="p-4 border-b flex justify-between items-center bg-slate-50/50">
                    <span class="font-bold text-sm">Notifications</span>
                    <span class="text-[10px] bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full font-bold"><?= $unread_count ?> Unread</span>
                </div>
                <div class="max-h-[300px] overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                        <div class="p-8 text-center">
                            <i class="fa-regular fa-envelope-open text-slate-300 text-2xl mb-2"></i>
                            <p class="text-xs text-slate-400">No notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="p-4 border-b hover:bg-slate-50 transition cursor-pointer <?= $n['is_read'] ? '' : 'bg-blue-50/30' ?>">
                                <p class="text-xs text-slate-700 leading-normal mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                <span class="text-[10px] text-slate-400 font-medium"><i class="fa-regular fa-clock mr-1"></i><?= time_ago($n['created_at']) ?> ago</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="notifications.php" class="block p-3 text-center text-xs font-bold text-[#1B1B4B] hover:bg-slate-50">View All Activities</a>
            </div>
        </header>

        <!-- ── Main Content ── -->
        <main class="main-content">
            <div class="content-card">
                <div class="flex flex-wrap gap-2 mb-6">
                    <button class="filter-btn px-3 py-1 rounded-lg text-[10px] font-bold border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition" data-status="All">All Projects</button>
                    <button class="filter-btn px-3 py-1 rounded-lg text-[10px] font-bold border border-orange-100 bg-orange-50 text-orange-600 hover:bg-orange-100 transition" data-status="Pending">Pending</button>
                    <button class="filter-btn px-3 py-1 rounded-lg text-[10px] font-bold border border-green-100 bg-green-50 text-green-600 hover:bg-green-100 transition" data-status="Approved">Approved</button>
                    <button class="filter-btn px-3 py-1 rounded-lg text-[10px] font-bold border border-red-100 bg-red-50 text-red-600 hover:bg-red-100 transition" data-status="Rejected">Rejected</button>
                </div>

                <div class="overflow-x-auto">
                    <table id="submissions-table" class="w-full text-left">
                        <thead>
                            <tr class="text-slate-400 text-[10px] font-bold uppercase tracking-widest border-b border-slate-100">
                                <th class="pb-4 px-2">Project Title</th>
                                <th class="pb-4 px-2">Proponent</th>
                                <th class="pb-4 px-2">Status</th>
                                <th class="pb-4 px-2">Time</th>
                                <th class="pb-4 px-2 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($submissions)): ?>
                                <tr><td colspan="4" class="py-10 text-center text-slate-400">No proposals found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr class="hover:bg-slate-50/50 transition" data-status="<?= htmlspecialchars($submission['status']) ?>">
                                        <td class="py-4 px-2 font-bold text-[#1B1B4B]"><?= htmlspecialchars($submission['title']) ?></td>
                                        <td class="py-4 px-2 text-slate-500 font-medium"><?= htmlspecialchars($submission['submitted_by']) ?></td>
                                        <td class="py-4 px-2">
                                            <?php
                                                $status = htmlspecialchars($submission['status']);
                                                $statusClass = 'status-' . str_replace(' ', '', $status);
                                                $statusLabel = $status;
                                                if (strcasecmp($status, 'View by Admin') === 0) {
                                                    $statusClass = 'status-ViewbyAdmin';
                                                    $statusLabel = 'View by Admin';
                                                }
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>" title="This proposal is currently being reviewed by an admin.">
                                                <?= $statusLabel ?>
                                            </span>
                                            <?php if (!empty($submission['created_at'])): ?>
                                                <span class="block text-[10px] text-slate-400 mt-1">(<?= date('M d, Y', strtotime($submission['created_at'])) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-2">
                                            <?php if (!empty($submission['created_at'])): ?>
                                                <?php
                                                    $time = strtotime($submission['created_at']);
                                                    echo '<span class="block text-[10px] text-slate-500">' . date('g:i a', $time) . '</span>';
                                                ?>
                                            <?php else: ?>
                                                <span class="block text-[10px] text-slate-500">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-2 text-center flex items-center justify-center gap-2">
                                            <button onclick="showDetails('<?= htmlspecialchars(json_encode($submission), ENT_QUOTES) ?>')" class="w-8 h-8 rounded-lg bg-slate-100 text-[#1B1B4B] hover:bg-[#FFD700] transition flex items-center justify-center"><i class="fas fa-eye text-[11px]"></i></button>
                                            <button onclick="openReceipt('<?= htmlspecialchars(json_encode($submission), ENT_QUOTES) ?>')" class="w-8 h-8 rounded-lg bg-slate-100 text-[#1B1B4B] hover:bg-[#1B1B4B] hover:text-white transition flex items-center justify-center"><i class="fas fa-receipt text-[11px]"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div><!-- /.main-container -->
</div><!-- /.app-wrapper -->

<!-- ── Receipt Modal (unchanged) ── -->
<div id="receipt-modal" class="fixed inset-0 bg-[#1b1b4b]/60 backdrop-blur-sm hidden items-center justify-center z-[100] transition-all">
    <div class="flex flex-col items-center">
        <div id="receipt-to-pdf" class="receipt-paper transform scale-90 opacity-0 transition-all duration-300">
            <div id="receipt-box">
                <img src="<?= htmlspecialchars($logoPath) ?>" class="receipt-logo">
                
                <div class="text-center mb-6">
                    <h2 class="font-bold text-lg uppercase tracking-tight">SK BARANGAY <?= strtoupper(htmlspecialchars($barangay)) ?></h2>
                    <p class="text-[10px]">Official Proposal Receipt</p>
                </div>
                
                <div class="border-t border-dashed border-slate-300 my-4"></div>
                
                <div class="text-[11px] space-y-1">
                    <div class="flex justify-between"><span>DATE:</span> <span id="r-date"></span></div>
                    <div class="flex justify-between"><span>TRANS ID:</span> <span id="r-id"></span></div>
                    <div class="flex justify-between"><span>OFFICER:</span> <span class="truncate ml-4"><?= strtoupper(htmlspecialchars($fullname)) ?></span></div>
                </div>

                <div class="border-t border-dashed border-slate-300 my-4"></div>

                <div class="py-2">
                    <p class="font-bold text-[14px] uppercase mb-1" id="r-title"></p>
                    <p class="text-[11px] text-slate-500 mb-3">PROPONENT: <span id="r-proponent"></span></p>
                    
                    <div class="flex justify-between items-center font-bold text-[16px] mt-4">
                        <span>TOTAL BUDGET</span>
                        <span id="r-budget"></span>
                    </div>
                </div>

                <div class="border-t border-dashed border-slate-300 my-4"></div>

                <div class="text-[10px] text-center space-y-1">
                    <p class="font-bold">STATUS: <span id="r-status"></span></p>
                    <p class="italic text-slate-400 mt-4">"Service for the youth, by the youth"</p>
                </div>
            </div>
        </div>

        <div class="mt-8 flex items-center justify-center gap-4">
            <button onclick="downloadReceiptPDF()" class="w-14 h-14 bg-green-500 text-white rounded-2xl shadow-lg hover:bg-green-600 hover:-translate-y-1 transition-all duration-300 flex items-center justify-center group" title="Download PDF">
                <i class="fas fa-file-pdf text-xl group-hover:scale-110 transition"></i>
            </button>
            <button onclick="closeReceipt()" class="w-14 h-14 bg-slate-800 text-white rounded-2xl shadow-lg hover:bg-black hover:-translate-y-1 transition-all duration-300 flex items-center justify-center group" title="Dismiss">
                <i class="fas fa-xmark text-xl group-hover:rotate-90 transition"></i>
            </button>
        </div>
    </div>
</div>

<!-- ── Details Modal (unchanged) ── -->
<div id="details-modal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm hidden items-center justify-center z-50 p-6">
    <div class="bg-white rounded-2xl p-0 w-full max-w-lg shadow-2xl transition-all transform scale-95 opacity-0 flex flex-col items-center justify-center" id="modal-content" style="overflow:hidden; margin:auto; min-width:340px;">
        <div class="bg-gradient-to-r from-[#1B1B4B] to-[#FFD700] p-4 flex items-center justify-between w-full">
            <div>
                <h3 id="modal-title" class="text-xl font-extrabold text-white tracking-tight mb-1"></h3>
                <p class="text-xs text-[#FFD700] font-bold uppercase tracking-widest">Project Proposal Details</p>
            </div>
            <span id="modal-status-badge" class="status-badge text-xs px-4 py-2 font-bold"></span>
        </div>
        <div class="p-4 w-full">
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-3">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Submitted By</p>
                    <p id="modal-submitted-by" class="text-sm font-bold text-[#ea580c]"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-3">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Total Budget</p>
                    <p id="modal-budget" class="text-sm font-bold text-emerald-600"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-3">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Objectives</p>
                    <p id="modal-objectives" class="text-xs text-slate-700"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-3">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Expected Outcome</p>
                    <p id="modal-outcome" class="text-xs text-slate-700"></p>
                </div>
            </div>
            <div class="mb-4">
                <p class="text-[10px] text-gray-400 uppercase font-bold mb-2 ml-1">Description / Narrative</p>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <p id="modal-description" class="text-xs text-slate-600 leading-relaxed"></p>
                </div>
            </div>
            <div id="modal-docs-section" class="mb-4" style="display:none;">
                <p class="text-[10px] text-blue-500 uppercase font-bold mb-2 ml-1">Supporting Document</p>
                <div class="p-3 bg-blue-50 rounded-xl border border-blue-100 flex items-center gap-3">
                    <a id="modal-doc-link" href="#" target="_blank" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-file-lines"></i> View Approval Document
                    </a>
                </div>
            </div>
            <div id="modal-rejection" class="mb-4" style="display:none;">
                <p class="text-[10px] text-red-500 uppercase font-bold mb-2 ml-1">Rejection Reason</p>
                <div class="p-3 bg-red-50 rounded-xl border border-red-100">
                    <p id="modal-rejection-reason" class="text-xs text-red-600 leading-relaxed font-semibold"></p>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="hideDetails()" class="px-6 py-2 bg-[#1B1B4B] text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition shadow-lg">Close Details</button>
            </div>
        </div>
    </div>
</div>

<script>
    // =============================================
    // SIDEBAR TOGGLE — Dashboard Style
    // =============================================
    const sidebar        = document.getElementById('sidebar');
    const sidebarToggle  = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    sidebarToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });

    // Close sidebar when clicking nav links on mobile
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    });

    // Reset on resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });

    // =============================================
    // TIME & NOTIFICATIONS — Dashboard Style
    // =============================================
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    const notifBtn      = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');

    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
    });
    document.addEventListener('click', () => { notifDropdown.style.display = 'none'; });

    // =============================================
    // HEADER VISIBILITY HELPERS
    // =============================================
    const stickyHeader = document.querySelector('.sticky-header');
    function hideHeader() { stickyHeader.classList.add('modal-open'); }
    function showHeader() { stickyHeader.classList.remove('modal-open'); }

    // =============================================
    // DETAILS MODAL (unchanged)
    // =============================================
    const modal = document.getElementById('details-modal');
    const modalContent = document.getElementById('modal-content');
    function showDetails(json) {
        hideHeader();
        const data = JSON.parse(json);
        document.getElementById('modal-title').innerText = data.title;
        document.getElementById('modal-submitted-by').innerText = data.submitted_by;
        document.getElementById('modal-budget').innerText = data.budget ? '₱' + parseFloat(data.budget).toLocaleString() : 'N/A';
        document.getElementById('modal-objectives').innerText = data.objectives || 'No objectives provided.';
        document.getElementById('modal-outcome').innerText = data.expected_outcome || 'No expected outcome provided.';
        const descElem = document.getElementById('modal-description');
        descElem.innerText = data.description || 'No description provided.';
        const badge = document.getElementById('modal-status-badge');
        badge.innerText = data.status;
        badge.className = 'status-badge text-xs px-4 py-2 font-bold status-' + data.status.replace(/\s/g, '');
        // Rejection Reason
        const rejectionDiv = document.getElementById('modal-rejection');
        if (data.status === 'Rejected' && data.rejection_reason) {
            document.getElementById('modal-rejection-reason').innerText = data.rejection_reason;
            rejectionDiv.style.display = '';
        } else {
            rejectionDiv.style.display = 'none';
        }
        // Document link logic
        const docsSection = document.getElementById('modal-docs-section');
        const docLink = document.getElementById('modal-doc-link');
        if (data.admin_doc && data.admin_doc !== '') {
            docsSection.style.display = '';
            let filePath = data.admin_doc.trim();
            // Strip any leading '../', './' or '/'
            filePath = filePath.replace(/^(\.\.\/|\.\/|\/)+/, '');
            // Build direct URL - files are stored under uploads/ inside public_html
            docLink.href = window.location.origin + '/' + filePath;
            docLink.setAttribute('target', '_blank');
            docLink.removeAttribute('download');
        } else if (data.document_path && data.document_path !== '') {
            docsSection.style.display = '';
            let filePath = data.document_path.trim();
            filePath = filePath.replace(/^(\.\.\/|\.\/|\/)+/, '');
            docLink.href = window.location.origin + '/' + filePath;
            docLink.setAttribute('target', '_blank');
            docLink.removeAttribute('download');
        } else {
            docsSection.style.display = 'none';
        }
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { modalContent.classList.remove('scale-95', 'opacity-0'); modalContent.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function hideDetails() {
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); showHeader(); }, 200);
    }

    // =============================================
    // RECEIPT LOGIC (unchanged)
    // =============================================
    const rModal = document.getElementById('receipt-modal');
    const rToPdf = document.getElementById('receipt-to-pdf');
    function openReceipt(json) {
        hideHeader();
        const data = JSON.parse(json);
        const now = new Date();
        document.getElementById('r-title').innerText = data.title;
        document.getElementById('r-proponent').innerText = data.submitted_by;
        document.getElementById('r-budget').innerText = '₱' + parseFloat(data.budget).toLocaleString();
        document.getElementById('r-status').innerText = data.status.toUpperCase();
        document.getElementById('r-date').innerText = now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        document.getElementById('r-id').innerText = 'SK-' + Math.floor(100000 + Math.random() * 900000);
        
        rModal.classList.remove('hidden'); rModal.classList.add('flex');
        setTimeout(() => { rToPdf.classList.remove('scale-90', 'opacity-0'); rToPdf.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function closeReceipt() {
        rToPdf.classList.add('scale-90', 'opacity-0');
        setTimeout(() => { rModal.classList.add('hidden'); rModal.classList.remove('flex'); showHeader(); }, 200);
    }

    // PDF Export Function (unchanged)
    function downloadReceiptPDF() {
        const element = document.getElementById('receipt-to-pdf');
        const filename = 'Receipt_' + document.getElementById('r-id').innerText + '.pdf';
        
        const opt = {
            margin:       10,
            filename:     filename,
            image:        { type: 'jpeg', quality: 1.0 },
            html2canvas:  { 
                scale: 3, 
                useCORS: true,
                letterRendering: true
            },
            jsPDF:        { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait',
                compress: true
            }
        };

        html2pdf().from(element).set(opt).save();
    }

    // =============================================
    // FILTER LOGIC (unchanged)
    // =============================================
    const filterButtons = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('#submissions-table tbody tr');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const status = btn.dataset.status;
            rows.forEach(row => {
                if(status === 'All' || row.dataset.status === status) row.style.display = '';
                else row.style.display = 'none';
            });
        });
    });
</script>
</body>
</html>