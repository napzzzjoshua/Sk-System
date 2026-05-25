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

// --- Helper Function for Time Formatting (Matches Dashboard) ---
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

// --- Notifications Logic (Matches Dashboard) ---
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

// --- Form Processing Logic ---
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
            
            // Notification logic
            $notification_message = "New Financial Aid Request for '{$student_name}' ({$aid_type}) submitted by {$submitted_by_string}.";
            $related_link = "financial_aid_tre.php";
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        
        /* Sidebar Styles - Unified */
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        
        /* Layout */
        .main-content { flex: 1; display: flex; flex-direction: column; background: #f1f5f9; overflow: hidden; }
        .content-body { flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; overflow-y: auto; }
        
        .form-card { width: 100%; max-width: 650px; background: white; border-radius: 24px; padding: 40px; box-shadow: 0 10px 40px -15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.04); }
        
        /* Inputs */
        .form-input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; font-size: 13px; transition: all 0.2s; }
        .form-input:focus { border-color: #FFD700; outline: none; background: white; box-shadow: 0 0 0 4px rgba(255,215,0,0.1); }
        .input-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; padding-left: 4px; }

        /* Right Panel */
        .right-panel { width: 290px; background: #FFFFFF; padding: 25px 20px; border-left: 1px solid #E5E9F0; overflow-y: auto; flex-shrink: 0; display: flex; flex-direction: column; }
        .info-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
        
        /* Notification Dropdown */
        #notificationDropdown { position: absolute; top: 60px; right: 0; width: 320px; background: white; border-radius: 18px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #E5E9F0; z-index: 1000; overflow: hidden; display: none; }
        
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="flex items-center gap-2 mb-8 px-2">
            <img src="<?= htmlspecialchars($logoPath) ?>" class="w-8 h-8 rounded-lg object-cover">
            <div>
                <h2 class="font-extrabold text-white text-sm leading-tight">SK Panel</h2>
                <span class="text-[9px] text-[#FFD700] font-bold uppercase tracking-wider"><?= htmlspecialchars($barangay) ?></span>
            </div>
        </div>

        <nav class="flex-grow">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3 px-3">Main Navigation</p>
            <a href="sk_dashboard.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="financial_aid_submission_tre.php" class="nav-link active"><i class="fas fa-wallet"></i> Financial Aid</a>
            <a href="scholarship_list.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
        </nav>

        <div class="user-profile-box flex-col items-start gap-1">
            <div class="flex items-center gap-2 w-full">
                <img src="<?= htmlspecialchars($profilePath) ?>" class="w-9 h-9 rounded-lg object-cover border border-white/20">
                <div class="overflow-hidden">
                    <p class="text-[11px] font-bold text-white truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[9px] text-slate-400 truncate"><?= htmlspecialchars($role) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2 w-full pt-2 border-t border-white/10">
                <a href="settings.php" class="text-[10px] text-[#FFD700] font-bold uppercase hover:opacity-80 transition flex items-center gap-1"><i class="fas fa-cog text-[9px]"></i> Settings</a>
                <span class="text-white/20">|</span>
                <a href="login.php" class="text-[10px] text-[#ea580c] font-bold uppercase hover:opacity-80 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-center py-4 px-8 border-b border-slate-200 bg-white/50 backdrop-blur-sm">
            <div>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-lg">Financial Aid Request</h1>
                <p id="current-time" class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"></p>
            </div>
            <div class="flex items-center gap-3 relative">
                <a href="financial_aid_tre.php" class="bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200 text-[#1B1B4B] font-bold text-[11px] hover:bg-slate-50 transition">
                    <i class="fas fa-history mr-1"></i> VIEW HISTORY
                </a>
                
                <button id="notificationBtn" class="relative bg-white p-2.5 rounded-xl shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                    <i class="fas fa-bell text-[#1B1B4B] text-xs"></i>
                    <?php if($unread_count > 0): ?>
                        <span id="notif-badge" class="absolute top-2 right-2 w-2 h-2 bg-[#ea580c] rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <span class="font-bold text-[#1B1B4B] text-xs">Notifications</span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase"><?= $unread_count ?> Unread</span>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <p class="p-4 text-center text-slate-400 text-[11px]">No notifications yet.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="p-3 border-b hover:bg-slate-50 transition cursor-pointer <?= $n['is_read'] ? '' : 'bg-orange-50/40' ?>">
                                    <p class="text-[11px] text-slate-600 leading-tight mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                    <span class="text-[9px] text-slate-400"><?= time_ago($n['created_at']) ?> ago</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="form-card">
                <div class="mb-8 border-b border-slate-100 pb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-[#1B1B4B]">Aid Application Form</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Provide details for project or event support</p>
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
                        Submit Application
                    </button>
                </form>
            </div>
        </div>
    </main>

    <aside class="right-panel">
        <h3 class="text-sm font-extrabold text-[#1B1B4B] mb-6 uppercase tracking-wider">Officer Details</h3>
        
        <div class="info-panel mb-6">
            <div class="mb-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Active Official</p>
                <p class="text-[12px] text-[#1B1B4B] font-bold"><?= htmlspecialchars($fullname) ?></p>
            </div>
            <div class="mb-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Administrative Unit</p>
                <p class="text-[12px] text-[#1B1B4B] font-bold">Barangay <?= htmlspecialchars($barangay) ?></p>
            </div>
            <div class="pt-3 border-t border-slate-200">
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Internal Reference</p>
                <p class="text-[11px] text-slate-500 font-mono uppercase">FA-<?= date('Ymd') ?>-LOG</p>
            </div>
        </div>

        <h4 class="text-[10px] font-bold text-[#1B1B4B] uppercase tracking-widest mb-4">Integrity Protocol</h4>
        <div class="space-y-4 px-1">
            <div class="flex gap-3">
                <div class="w-2 h-2 rounded-full bg-green-500 mt-1"></div>
                <div>
                    <p class="text-[11px] font-bold text-[#1B1B4B]">Instant Audit</p>
                    <p class="text-[10px] text-slate-500 leading-relaxed">Submissions are timestamped and logged in the treasury database.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-500 mt-1"></div>
                <div>
                    <p class="text-[11px] font-bold text-[#1B1B4B]">Chairperson Alert</p>
                    <p class="text-[10px] text-slate-500 leading-relaxed">The local chairperson will receive a notification for review.</p>
                </div>
            </div>
        </div>

        <div class="mt-auto bg-[#1B1B4B] rounded-3xl p-6 text-center shadow-lg">
            <p class="text-[10px] text-[#FFD700] font-black uppercase mb-2">Legal Disclaimer</p>
            <p class="text-[9px] text-slate-300 leading-relaxed">By proceeding, you verify that this request adheres to the Local Youth Investment Plan.</p>
        </div>
    </aside>
</div>

<script>
    // Real-time Clock
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' }) + " | " + now.toLocaleTimeString();
    }
    setInterval(updateTime, 1000); updateTime();

    // Unified Notification Logic
    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
    });
    document.addEventListener('click', () => { notifDropdown.style.display = 'none'; });

    // Input Filter
    function filterAlphabetOnly(event) {
        event.target.value = event.target.value.replace(/[^a-zA-Z\s]/g, '');
    }

    // Dynamic Form Swapping
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