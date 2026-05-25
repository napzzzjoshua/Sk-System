<?php
session_start();
require_once 'db_conn.php';

// --- Access Control ---
if (!isset($_SESSION['user_id']) || strpos($_SESSION['role'], 'SK') === false) {
    header("Location: login.php");
    exit;
}

$fullname = $_SESSION['fullname'];
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

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

// --- Fetch user details ---
$sql = "SELECT email, role, barangay, profile_photo FROM users WHERE id = ?"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email, $role, $barangay, $profile_photo); 
$stmt->fetch();
$stmt->close();

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";
$nameParts = explode(" ", $fullname);
$surname = end($nameParts);

// --- Barangay-based SK logo logic ---
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Notifications Logic ---
$notif_sql = "SELECT id, message, related_link, created_at, is_read FROM sk_notifications WHERE (email = ?) OR (barangay = ? AND position = ?) OR (barangay = ? AND position IS NULL) OR (email IS NULL AND barangay IS NULL AND position = ?) ORDER BY created_at DESC LIMIT 10";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("sssss", $email, $barangay, $role, $barangay, $role);
$stmt_notif->execute();
$result_notif = $stmt_notif->get_result();
$notifications = [];
while ($row = $result_notif->fetch_assoc()) { $notifications[] = $row; }
$stmt_notif->close();

$unread_count_sql = "SELECT COUNT(*) AS unread_count FROM sk_notifications WHERE (is_read = 0 AND email = ?) OR (is_read = 0 AND barangay = ? AND position = ?) OR (is_read = 0 AND barangay = ? AND position IS NULL) OR (is_read = 0 AND email IS NULL AND barangay IS NULL AND position = ?)";
$stmt_count = $conn->prepare($unread_count_sql);
$stmt_count->bind_param("sssss", $email, $barangay, $role, $barangay, $role);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'] ?? 0;
$stmt_count->close();

// --- Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_name = $_POST['student_name'] ?? '';
    $aid_type = $_POST['aid_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $total_amount_cash = $_POST['total_amount_cash'] ?? '';
    $total_amount_kind = $_POST['total_amount_kind'] ?? '';
    
    $validation_value = ($aid_type === 'In Cash') ? $total_amount_cash : (($aid_type === 'In Kind') ? $total_amount_kind : '');

    if (empty($student_name) || empty($aid_type) || empty($reason) || empty($validation_value)) {
        $error_message = "All fields are required.";
    } else {
        $amount_for_db = ($aid_type === 'In Cash') ? $total_amount_cash : '0'; 
        $kind_for_db = ($aid_type === 'In Kind') ? $total_amount_kind : NULL;
        $submitted_by_string = $surname . " from " . $barangay . ", " . $role;

        $stmt = $conn->prepare("INSERT INTO financial_aid_requests (student_name, aid_type, reason, total_amount, type_or_value, status, submitted_by, barangay) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $stmt->bind_param("sssssss", $student_name, $aid_type, $reason, $amount_for_db, $kind_for_db, $submitted_by_string, $barangay);
        
        if ($stmt->execute()) {
            $success_message = "Your financial aid request has been submitted successfully.";
            
            $notification_message = "New Financial Aid Request for '{$student_name}' ({$aid_type}) submitted by {$submitted_by_string}.";
            $related_link = "financial_aid.php";
            $stmt_notify = $conn->prepare("INSERT INTO sk_notifications (email, barangay, position, message, related_link) VALUES (?, ?, ?, ?, ?)");
            $stmt_notify->bind_param("sssss", $email, $barangay, $role, $notification_message, $related_link);
            $stmt_notify->execute();
            $stmt_notify->close();
            
            unset($_POST);
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Aid Submission | SK Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; margin: 0; font-size: 13px; color: #1e293b; }

        .app-wrapper { display: flex; min-height: 100vh; position: relative; }

        /* ── Sidebar (sk_dashboard style) ── */
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

        /* ── Sticky Header (sk_dashboard style) ── */
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
        .main-content { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 32px 24px; overflow-y: auto; }

        /* ── Navigation (sk_dashboard style) ── */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

        /* ── Notification Button (sk_dashboard style) ── */
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

        /* ── Mobile Toggle Button (sk_dashboard style) ── */
        .mobile-menu-btn { display: none; background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; cursor: pointer; }

        /* ── Sidebar Overlay (sk_dashboard style) ── */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 1000; }
        .sidebar-overlay.active { display: block; }

        /* ── Form Card ── */
        .form-card { width: 100%; max-width: 650px; background: white; border-radius: 24px; padding: 40px; box-shadow: 0 10px 40px -15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.04); }

        /* ── Form Styling ── */
        .form-input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; font-size: 13px; transition: all 0.2s; box-sizing: border-box; }
        .form-input:focus { border-color: #FFD700; outline: none; background: white; box-shadow: 0 0 0 4px rgba(255,215,0,0.1); }
        .input-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; padding-left: 4px; }

        /* ── Right Panel ── */
        .right-panel {
            width: 290px;
            background: #FFFFFF;
            padding: 24px 20px;
            border-left: 1px solid #e2e8f0;
            overflow-y: auto;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .info-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }

        /* ── Responsive: Tablet ── */
        @media (max-width: 1024px) {
            .right-panel { display: none; }
            .right-panel.active {
                display: flex;
                position: fixed;
                right: 0;
                top: 0;
                z-index: 1005;
                box-shadow: -10px 0 30px rgba(0,0,0,0.1);
            }
        }

        /* ── Responsive: Mobile (sk_dashboard style) ── */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; height: 100vh; }
            .sidebar.active { left: 0; }
            .mobile-menu-btn { display: block; }
            .sticky-header { padding: 10px 16px; }
            .main-content { padding: 16px; align-items: flex-start; }
            .form-card { padding: 24px; }
            .header-title-wrapper h1 { font-size: 16px !important; }
            #notificationDropdown { width: 280px; right: 12px; top: 60px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .form-card { padding: 18px; border-radius: 20px; }
            .form-input { padding: 10px 14px; font-size: 12px; }
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
            <a href="sk_dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="sk_list.php" class="nav-link"><i class="fas fa-users"></i> SK Members</a>
            <a href="document_submissions.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="submit_proposal.php" class="nav-link"><i class="fas fa-paper-plane"></i> Proposals</a>
            <a href="financial_aid_submission.php" class="nav-link active"><i class="fas fa-wallet"></i> Financial Aid</a>
            <a href="scholarship_list.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
        </nav>

        <div class="mt-auto bg-white/5 rounded-2xl p-4 border border-white/10">
            <div class="flex items-center gap-3 mb-4">
                <img src="<?= htmlspecialchars($profilePath) ?>" class="w-10 h-10 rounded-xl object-cover border-2 border-[#FFD700]/30">
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($role) ?></p>
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
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">New Request Form</h1>
                    <p id="current-time" class="text-[11px] text-slate-500 font-semibold"></p>
                </div>
            </div>

            <div class="flex items-center gap-3 relative">
                <a href="financial_aid.php" class="hidden sm:flex items-center gap-2 bg-white border border-slate-200 text-[#1B1B4B] font-bold text-[11px] px-4 py-2 rounded-xl hover:bg-slate-50 transition">
                    <i class="fas fa-history"></i> View History
                </a>
                <!-- Mobile: icon only -->
                <a href="financial_aid.php" class="sm:hidden notif-btn text-[#1B1B4B]">
                    <i class="fas fa-history text-sm"></i>
                </a>

                <button id="notificationBtn" class="notif-btn">
                    <i class="fa-regular fa-bell text-slate-600"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>

                <button id="rightPanelToggle" class="lg:hidden notif-btn">
                    <i class="fas fa-info-circle text-slate-600"></i>
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

        <!-- ── Form Content ── -->
        <div class="main-content">
            <div class="form-card">
                <div class="mb-8 border-b border-slate-100 pb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-[#1B1B4B]">Financial Aid Application</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Please provide accurate financial details</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-[#1B1B4B]">
                        <i class="fas fa-file-invoice text-lg"></i>
                    </div>
                </div>

                <?php if($success_message): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl text-[11px] font-bold flex items-center gap-3">
                        <i class="fas fa-check-circle text-lg"></i> <?= $success_message ?>
                    </div>
                <?php endif; ?>

                <?php if($error_message): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-[11px] font-bold flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-lg"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="space-y-6">
                    <div>
                        <label class="input-label">Project / Event Name</label>
                        <input type="text" name="student_name" required oninput="filterAlphabetOnly(event)" placeholder="e.g. Linggo ng Kabataan Sports Fest" class="form-input" value="<?= htmlspecialchars($_POST['student_name'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="input-label">Aid Type</label>
                            <select name="aid_type" id="aid_type_select" required onchange="handleAidTypeChange()" class="form-input cursor-pointer">
                                <option value="">-- Choose Type --</option>
                                <option value="In Cash" <?= (isset($_POST['aid_type']) && $_POST['aid_type'] == 'In Cash') ? 'selected' : ''; ?>>In Cash (Financial)</option>
                                <option value="In Kind" <?= (isset($_POST['aid_type']) && $_POST['aid_type'] == 'In Kind') ? 'selected' : ''; ?>>In Kind (Goods)</option>
                            </select>
                        </div>

                        <div id="in_cash_field">
                            <label class="input-label">Requested Amount (₱)</label>
                            <input type="number" name="total_amount_cash" id="total_amount_cash" placeholder="0.00" class="form-input" value="<?= htmlspecialchars($_POST['total_amount_cash'] ?? ''); ?>">
                        </div>

                        <div id="in_kind_field" class="hidden">
                            <label class="input-label">Item Description</label>
                            <input type="text" name="total_amount_kind" id="total_amount_kind" placeholder="e.g. 10 Boxes of Milk" class="form-input" value="<?= htmlspecialchars($_POST['total_amount_kind'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="input-label">Reason for Request</label>
                        <textarea name="reason" rows="4" required oninput="filterAlphabetOnly(event)" placeholder="Justify the need for this assistance..." class="form-input resize-none"><?= htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="w-full bg-[#1B1B4B] text-white py-4 rounded-2xl font-black text-xs uppercase tracking-[0.1em] hover:bg-[#2a2a6b] transition-all transform active:scale-[0.98] shadow-xl shadow-indigo-900/20">
                        Finalize & Submit Application
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right Panel (Session Details) ── -->
    <aside class="right-panel" id="rightPanel">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-sm font-extrabold text-[#1B1B4B] uppercase tracking-wider">Session Details</h3>
            <button id="closePanelBtn" class="lg:hidden text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times"></i></button>
        </div>

        <div class="info-panel mb-6">
            <div class="mb-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Active Official</p>
                <p class="text-[12px] text-[#1B1B4B] font-bold"><?= htmlspecialchars($fullname) ?></p>
            </div>
            <div class="mb-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Administrative Unit</p>
                <p class="text-[12px] text-[#1B1B4B] font-bold"><?= htmlspecialchars($barangay) ?></p>
            </div>
            <div class="pt-3 border-t border-slate-200">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Internal Reference</p>
                <p class="text-[11px] text-slate-500 font-mono">FA-<?= date('Ymd') ?>-AUTO</p>
            </div>
        </div>

        <h4 class="text-[10px] font-bold text-[#1B1B4B] uppercase tracking-widest mb-4">Integrity Protocol</h4>
        <div class="space-y-4 px-1 mb-6">
            <div class="flex gap-3">
                <div class="w-2 h-2 rounded-full bg-green-500 mt-1 flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] font-bold text-[#1B1B4B]">Instant Audit</p>
                    <p class="text-[10px] text-slate-500">Every submission is timestamped and logged in the treasury database.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-500 mt-1 flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] font-bold text-[#1B1B4B]">Chairperson Alert</p>
                    <p class="text-[10px] text-slate-500">The local chairperson will receive a push notification for review.</p>
                </div>
            </div>
        </div>

        <div class="mt-auto bg-slate-900 rounded-3xl p-6 text-center shadow-lg">
            <p class="text-[10px] text-[#FFD700] font-black uppercase mb-2">Legal Disclaimer</p>
            <p class="text-[9px] text-slate-400 leading-relaxed">By proceeding, you verify that this request adheres to the Annual Barangay Youth Investment Program (ABYIP).</p>
        </div>
    </aside>
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
    const rightPanel = document.getElementById('rightPanel');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const rightPanelToggle = document.getElementById('rightPanelToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closePanelBtn = document.getElementById('closePanelBtn');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });

    rightPanelToggle.addEventListener('click', () => {
        rightPanel.classList.toggle('active');
        if (rightPanel.classList.contains('active')) sidebarOverlay.classList.add('active');
    });

    closePanelBtn.addEventListener('click', () => {
        rightPanel.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        rightPanel.classList.remove('active');
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

    // ── Form Logic (unchanged) ──
    function filterAlphabetOnly(event) {
        event.target.value = event.target.value.replace(/[^a-zA-Z\s]/g, '');
    }

    function handleAidTypeChange() {
        const select = document.getElementById('aid_type_select');
        const inCashField = document.getElementById('in_cash_field');
        const inKindField = document.getElementById('in_kind_field');
        const cashInput = document.getElementById('total_amount_cash');
        const kindInput = document.getElementById('total_amount_kind');
        
        if (select.value === 'In Kind') {
            inCashField.classList.add('hidden');
            inKindField.classList.remove('hidden');
            kindInput.required = true; cashInput.required = false;
        } else {
            inCashField.classList.remove('hidden');
            inKindField.classList.add('hidden');
            cashInput.required = true; kindInput.required = false;
        }
    }
    document.addEventListener('DOMContentLoaded', handleAidTypeChange);
</script>
</body>
</html>