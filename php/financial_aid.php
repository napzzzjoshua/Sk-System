<?php
session_start();
require_once 'db_conn.php';

// Check if user is logged in and has the 'SK' role
if (!isset($_SESSION['user_id']) || strpos($_SESSION['role'], 'SK') === false) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$success_message = '';
$error_message = '';

// --- Fetch user details ---
$sql = "SELECT email, role, barangay, position, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email, $role, $barangay, $position, $profile_photo);
$stmt->fetch();
$stmt->close();

// Set session variables for consistency with dashboard
$_SESSION['email'] = $user_email;
$_SESSION['barangay'] = $barangay;
$_SESSION['position'] = $position;

// Paths for assets
$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Helper Function for Time Formatting (Matching Dashboard) ---
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

// --- Notifications Logic (Matching Dashboard) ---
$notif_sql = "SELECT id, message, created_at, is_read FROM sk_notifications WHERE (email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL) ORDER BY created_at DESC LIMIT 10";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("ssss", $user_email, $barangay, $position, $barangay);
$stmt_notif->execute();
$result_notif = $stmt_notif->get_result();
$notifications = [];
while ($row = $result_notif->fetch_assoc()) { $notifications[] = $row; }
$stmt_notif->close();

$unread_count_sql = "SELECT COUNT(*) AS unread_count FROM sk_notifications WHERE is_read = 0 AND ((email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL))";
$stmt_count = $conn->prepare($unread_count_sql);
$stmt_count->bind_param("ssss", $user_email, $barangay, $position, $barangay);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'] ?? 0;
$stmt_count->close();

// --- Fetch financial aid requests for the current SK Official's BARANGAY ---
$requests = [];

$sql = "SELECT id, student_name, aid_type, reason, total_amount, status, submitted_by, type_or_value, rejection_reason, admin_doc, created_at FROM financial_aid_requests WHERE barangay = ? ORDER BY id DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $barangay); 
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    if (empty($requests)) {
        $error_message = "No financial aid requests found for " . htmlspecialchars($barangay) . ".";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Aid | SK System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; margin: 0; font-size: 13px; color: #1e293b; }

        .app-wrapper { display: flex; min-height: 100vh; position: relative; }

        /* ── Sidebar (from sk_dashboard) ── */
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

        /* ── Sticky Header (from sk_dashboard) ── */
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
        .main-content { padding: 24px; flex: 1; overflow-y: auto; }

        /* ── Navigation (from sk_dashboard) ── */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

        /* ── Notification Button (from sk_dashboard) ── */
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
            cursor: pointer;
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

        /* ── Mobile Toggle Button (from sk_dashboard) ── */
        .mobile-menu-btn { display: none; background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; cursor: pointer; }

        /* ── Sidebar Overlay (from sk_dashboard) ── */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 1000; }
        .sidebar-overlay.active { display: block; }

        /* ── Content Card ── */
        .content-card { background: white; border-radius: 24px; padding: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }

        /* ── Status Pills ── */
        .status-pill { padding: 4px 12px; border-radius: 99px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-Pending { background: #fff7ed; color: #ea580c; }
        .status-Approved { background: #f0fdf4; color: #16a34a; }
        .status-Rejected { background: #fef2f2; color: #dc2626; }
        .status-ViewbyAdmin { background: #e0f2fe; color: #2563eb; }

        /* ── Responsive: Mobile (from sk_dashboard) ── */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; height: 100vh; }
            .sidebar.active { left: 0; }
            .mobile-menu-btn { display: block; }
            .sticky-header { padding: 10px 16px; }
            .main-content { padding: 16px; }
            .header-title-wrapper { display: flex; flex-direction: column; }
            .header-title-wrapper h1 { font-size: 16px !important; }
            #notificationDropdown { width: 280px; right: 12px; top: 60px; }
            table th:nth-child(4), table td:nth-child(4) { display: none; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .content-card { padding: 12px; }
            table { font-size: 10px !important; }
            table td, table th { padding: 4px !important; }
            table th:nth-child(2), table td:nth-child(2) { display: none; }
            .status-pill { padding: 2px 6px; font-size: 8px; }
            .filter-btn { padding: 6px 10px; font-size: 9px; }
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ── Sidebar (sk_dashboard style) ── -->
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
                <a href="financial_aid_tre.php" class="nav-link active"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list_tre.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php else: ?>
                <a href="sk_list.php" class="nav-link"><i class="fas fa-users"></i> SK Members</a>
                <a href="document_submissions.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="submit_proposal.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
                <a href="financial_aid.php" class="nav-link active"><i class="fas fa-wallet"></i> Financial Aid</a>
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

    <!-- ── Main Container ── -->
    <div class="main-container">

        <!-- ── Sticky Header (sk_dashboard style) ── -->
        <header class="sticky-header">
            <div class="flex items-center gap-4">
                <button id="sidebarToggle" class="mobile-menu-btn text-[#1B1B4B]">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="header-title-wrapper">
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">Financial Aid Management</h1>
                    <p id="current-time" class="text-[11px] text-slate-500 font-semibold"></p>
                </div>
            </div>

            <div class="flex items-center gap-3 relative">
                <a href="financial_aid_submission.php" class="bg-[#1B1B4B] text-white text-[11px] font-bold py-2 px-4 rounded-xl hover:bg-slate-700 transition items-center gap-2 hidden sm:flex">
                    <i class="fas fa-plus"></i> New Request
                </a>
                <!-- Mobile New Request (icon only) -->
                <a href="financial_aid_submission.php" class="sm:hidden notif-btn text-[#1B1B4B]">
                    <i class="fas fa-plus text-sm"></i>
                </a>

                <button id="notificationBtn" class="notif-btn">
                    <i class="fa-regular fa-bell text-slate-600"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>

            </div>

            <!-- Notification Dropdown -->
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

        <!-- ── Page Content ── -->
        <main class="main-content">

            <!-- Filter Buttons -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button class="filter-btn px-4 py-2 rounded-xl bg-[#1B1B4B] text-white text-[11px] font-bold transition" data-status="All">All Requests</button>
                <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Pending">Pending</button>
                <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Approved">Approved</button>
                <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Rejected">Rejected</button>
            </div>

            <!-- Table Card -->
            <div class="content-card">
                <?php if (!empty($error_message) && count($requests) === 0): ?>
                    <div class="p-10 text-center">
                        <i class="fas fa-folder-open text-3xl text-slate-200 mb-3"></i>
                        <p class="text-slate-400 font-medium"><?= htmlspecialchars($error_message) ?></p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table id="requests-table" class="w-full">
                            <thead>
                                <tr class="text-left border-b border-slate-100">
                                    <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Student</th>
                                    <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Type</th>
                                    <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Time</th>
                                    <th class="pb-4 px-2 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($requests as $request): ?>
                                    <tr class="hover:bg-slate-50/50 transition" data-status="<?= htmlspecialchars($request['status']) ?>">
                                        <td class="py-4 px-2">
                                            <p class="font-bold text-[#1B1B4B]"><?= htmlspecialchars($request['student_name']) ?></p>
                                            <p class="text-[10px] text-slate-400">ID: #<?= $request['id'] ?></p>
                                        </td>
                                        <td class="py-4 px-2 text-slate-600 font-medium"><?= htmlspecialchars($request['aid_type']) ?></td>
                                        <td class="py-4 px-2">
                                            <?php
                                                $status = htmlspecialchars($request['status']);
                                                $statusClass = 'status-' . str_replace(' ', '', $status);
                                                $statusLabel = $status;
                                                if (strcasecmp($status, 'View by Admin') === 0) {
                                                    $statusClass = 'status-ViewbyAdmin';
                                                    $statusLabel = 'View by Admin';
                                                }
                                            ?>
                                            <span class="status-pill <?= $statusClass ?>" title="This request is currently being reviewed by an admin.">
                                                <?= $statusLabel ?>
                                            </span>
                                            <?php if (!empty($request['created_at'])): ?>
                                                <span class="block text-[10px] text-slate-400 mt-1">(<?= date('M d, Y', strtotime($request['created_at'])) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-2">
                                            <?php if (!empty($request['created_at'])): ?>
                                                <?php
                                                    $time = strtotime($request['created_at']);
                                                    echo '<span class="block text-[10px] text-slate-500">' . date('g:i a', $time) . '</span>';
                                                ?>
                                            <?php else: ?>
                                                <span class="block text-[10px] text-slate-500">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <?php if (strcasecmp($request['status'], 'Rejected') === 0): ?>
                                                    <button class="view-rejection-btn w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition"
                                                        data-rejection-reason="<?= htmlspecialchars($request['rejection_reason']) ?>"
                                                        data-student-name="<?= htmlspecialchars($request['student_name']) ?>">
                                                        <i class="fas fa-info-circle text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="view-details-btn w-8 h-8 rounded-lg bg-slate-100 text-[#1B1B4B] flex items-center justify-center hover:bg-slate-200 transition"
                                                    data-student-name="<?= htmlspecialchars($request['student_name']) ?>"
                                                    data-aid-type="<?= htmlspecialchars($request['aid_type']) ?>"
                                                    data-reason="<?= htmlspecialchars($request['reason']) ?>"
                                                    data-submitted-by="<?= htmlspecialchars($request['submitted_by']) ?>"
                                                    data-total-amount="<?= htmlspecialchars(number_format((float)$request['total_amount'], 2)) ?>"
                                                    data-type-or-value="<?= htmlspecialchars($request['type_or_value']) ?>"
                                                    data-admin-doc="<?= htmlspecialchars($request['admin_doc']) ?>"
                                                    data-request-id="<?= htmlspecialchars($request['id']) ?>">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

</div>

<!-- ── Request Details Modal ── -->
<div id="request-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-[2000] p-6">
    <div class="bg-white rounded-[30px] p-8 w-full max-w-md shadow-2xl">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 id="modal-student-name" class="text-lg font-black text-[#1B1B4B] mb-1"></h2>
                <span id="modal-aid-type" class="text-[10px] text-[#ea580c] font-bold uppercase tracking-widest"></span>
            </div>
            <div class="text-right">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Amount</p>
                <p id="modal-total-amount" class="text-lg font-black text-[#1B1B4B]"></p>
            </div>
        </div>
        <div class="space-y-4 mb-8">
            <div class="bg-slate-50 p-4 rounded-2xl">
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Details</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400">SUBMITTED BY</p>
                        <p id="modal-submitted-by" class="text-[11px] font-bold text-[#1B1B4B]"></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400">TYPE/VALUE</p>
                        <p id="modal-type-or-value" class="text-[11px] font-bold text-[#1B1B4B]"></p>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2 ml-1">Reason for Request</p>
                <div id="modal-reason" class="text-slate-600 text-[12px] leading-relaxed bg-slate-50 p-4 rounded-2xl max-h-32 overflow-y-auto"></div>
            </div>
        </div>
        <div class="mb-4 flex justify-center">
            <a id="modal-download-link" href="#" target="_blank" class="bg-[#1B1B4B] text-white text-xs font-bold py-2 px-4 rounded-xl hover:bg-slate-700 transition flex items-center gap-2" style="display:none">
                <i class="fas fa-file-lines"></i> View Approval Document
                <span style="display:none" id="modal-download-filename"></span>
            </a>
        </div>
        <button onclick="closeModal('request-modal')" class="w-full py-3 bg-slate-100 text-[#1B1B4B] rounded-xl text-xs font-bold hover:bg-slate-200 transition">Close Details</button>
    </div>
</div>

<!-- ── Rejection Modal ── -->
<div id="rejection-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-[2000] p-6">
    <div class="bg-white rounded-[30px] p-8 w-full max-w-sm shadow-2xl">
        <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 mb-6">
            <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <h2 id="rejection-modal-title" class="text-lg font-black text-[#1B1B4B] mb-2">Rejection Reason</h2>
        <div id="modal-rejection-reason" class="text-slate-600 text-[13px] leading-relaxed mb-8 italic"></div>
        <button onclick="closeModal('rejection-modal')" class="w-full py-3 bg-red-500 text-white rounded-xl text-xs font-bold hover:bg-red-600 transition shadow-lg shadow-red-200">Acknowledge</button>
    </div>
</div>

<script>
    // ── Clock ──
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    // ── Sidebar Toggle (sk_dashboard logic) ──
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

    // Close sidebar on nav link click (mobile)
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });

    // ── Notification Dropdown (sk_dashboard logic) ──
    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');

    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
    });

    document.addEventListener('click', () => { notifDropdown.style.display = 'none'; });

    // ── Filter Logic ──
    const filterBtns = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('#requests-table tbody tr');

    filterBtns.forEach(btn => {
        btn.onclick = function() {
            filterBtns.forEach(b => {
                b.classList.remove('bg-[#1B1B4B]', 'text-white');
                b.classList.add('bg-white', 'text-slate-600');
            });
            this.classList.add('bg-[#1B1B4B]', 'text-white');
            this.classList.remove('bg-white', 'text-slate-600');

            const status = this.dataset.status;
            tableRows.forEach(row => {
                row.style.display = (status === 'All' || row.dataset.status === status) ? '' : 'none';
            });
        };
    });

    // ── Modal Logic ──
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.onclick = function() {
            const d = this.dataset;
            document.getElementById('modal-student-name').textContent = d.studentName;
            document.getElementById('modal-aid-type').textContent = d.aidType;
            document.getElementById('modal-submitted-by').textContent = d.submittedBy;
            document.getElementById('modal-total-amount').textContent = `₱${d.totalAmount}`;
            document.getElementById('modal-type-or-value').textContent = d.typeOrValue;
            document.getElementById('modal-reason').textContent = d.reason;

            const downloadLink = document.getElementById('modal-download-link');
            if (d.adminDoc && d.adminDoc !== 'null' && d.adminDoc !== '') {
                let filePath = d.adminDoc.trim();
                // Strip any leading '../', './' or '/'
                filePath = filePath.replace(/^(\.\.\/|\.\/|\/)+/, '');
                // Build direct URL - files are stored under uploads/ inside public_html
                downloadLink.href = window.location.origin + '/' + filePath;
                downloadLink.setAttribute('target', '_blank');
                downloadLink.removeAttribute('download');
                downloadLink.style.display = '';
            } else {
                downloadLink.style.display = 'none';
            }

            openModal('request-modal');
        };
    });

    document.querySelectorAll('.view-rejection-btn').forEach(btn => {
        btn.onclick = function() {
            const d = this.dataset;
            document.getElementById('rejection-modal-title').textContent = `Reason for ${d.studentName}`;
            document.getElementById('modal-rejection-reason').textContent = d.rejectionReason;
            openModal('rejection-modal');
        };
    });
</script>

</body>
</html>