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

// --- Fetch user details (Matching Dashboard Logic) ---
$sql = "SELECT email, role, barangay, position, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email, $role, $barangay, $position, $profile_photo);
$stmt->fetch();
$stmt->close();

// Set session variables for consistency
$_SESSION['email'] = $user_email;
$_SESSION['barangay'] = $barangay;
$_SESSION['position'] = $position;

// Paths for assets
$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

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

// --- Notifications Logic ---
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
$sql = "SELECT id, student_name, aid_type, reason, total_amount, status, submitted_by, type_or_value, rejection_reason FROM financial_aid_requests WHERE barangay = ? ORDER BY id DESC";

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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .main-content { flex: 1; padding: 25px 30px; background: #f1f5f9; overflow-y: auto; }
        .right-panel { width: 290px; background: #FFFFFF; padding: 25px 20px; border-left: 1px solid #E5E9F0; overflow-y: auto; flex-shrink: 0; }
        
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        
        #notificationDropdown { position: absolute; top: 60px; right: 0; width: 300px; background: white; border-radius: 18px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #E5E9F0; z-index: 1000; overflow: hidden; display: none; }
        
        .content-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); }
        .status-pill { padding: 4px 12px; border-radius: 99px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-Pending { background: #fff7ed; color: #ea580c; }
        .status-Approved { background: #f0fdf4; color: #16a34a; }
        .status-Rejected { background: #fef2f2; color: #dc2626; }

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
            <a href="sk_dashboard.php" class="nav-link">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            
            <a href="financial_aid_submission.php" class="nav-link active"><i class="fas fa-wallet"></i> Financial Aid</a>
            <a href="scholarship_list_tre.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
        </nav>

        <div class="user-profile-box flex-col items-start gap-1">
            <div class="flex items-center gap-2 w-full">
                <img src="<?= htmlspecialchars($profilePath) ?>" class="w-9 h-9 rounded-lg object-cover border border-white/20">
                <div class="overflow-hidden">
                    <p class="text-[11px] font-bold text-white truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[9px] text-slate-400 truncate"><?= htmlspecialchars($position) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2 w-full pt-2 border-t border-white/10">
                <a href="settings.php" class="text-[10px] text-[#FFD700] font-bold uppercase hover:opacity-80 transition flex items-center gap-1">
                    <i class="fas fa-cog text-[9px]"></i> Settings
                </a>
                <span class="text-white/20">|</span>
                <a href="login.php" class="text-[10px] text-[#ea580c] font-bold uppercase hover:opacity-80 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-xl">Financial Aid Requests</h1>
                <p id="current-time" class="text-xs text-slate-400 font-medium"></p>
            </div>
            
            <div class="flex items-center gap-3 relative">
                <a href="financial_aid_submission_tre.php" class="bg-[#1B1B4B] text-white text-[11px] font-bold py-2 px-4 rounded-xl hover:bg-slate-700 transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> New Request
                </a>

                <button id="notificationBtn" class="relative bg-white p-2.5 rounded-xl shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                    <i class="fas fa-bell text-[#1B1B4B] text-xs"></i>
                    <?php if($unread_count > 0): ?>
                        <span id="notif-badge" class="absolute top-2 right-2 w-2 h-2 bg-[#ea580c] rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <span class="font-bold text-[#1B1B4B]">Notifications</span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase"><?= $unread_count ?> Unread</span>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <p class="p-4 text-center text-slate-400 text-xs">No notifications yet.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="p-3 border-b hover:bg-slate-50 transition cursor-pointer <?= $n['is_read'] ? '' : 'bg-orange-50/30' ?>">
                                    <p class="text-[11px] text-slate-600 leading-tight mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                    <span class="text-[9px] text-slate-400"><?= time_ago($n['created_at']) ?> ago</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="notifications.php" class="block p-3 text-center text-[11px] font-bold text-[#ea580c] hover:bg-orange-50">View All</a>
                </div>
            </div>
        </header>

        <div class="flex flex-wrap gap-2 mb-6">
            <button class="filter-btn active px-4 py-2 rounded-xl bg-[#1B1B4B] text-white text-[11px] font-bold transition" data-status="All">All</button>
            <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Pending">Pending</button>
            <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Approved">Approved</button>
            <button class="filter-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-[11px] font-bold hover:bg-slate-50 transition" data-status="Rejected">Rejected</button>
        </div>

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
                                <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Aid Type</th>
                                <th class="pb-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
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
                                        <span class="status-pill status-<?= htmlspecialchars($request['status']) ?>">
                                            <?= htmlspecialchars($request['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <?php if (strcasecmp($request['status'], 'Rejected') === 0): ?>
                                                <button class="view-rejection-btn w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition"
                                                    data-rejection-reason="<?= htmlspecialchars($request['rejection_reason']) ?>"
                                                    data-student-name="<?= htmlspecialchars($request['student_name']) ?>">
                                                    <i class="fas fa-exclamation-triangle text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="view-details-btn w-8 h-8 rounded-lg bg-slate-100 text-[#1B1B4B] flex items-center justify-center hover:bg-slate-200 transition"
                                                data-student-name="<?= htmlspecialchars($request['student_name']) ?>"
                                                data-aid-type="<?= htmlspecialchars($request['aid_type']) ?>"
                                                data-reason="<?= htmlspecialchars($request['reason']) ?>"
                                                data-submitted-by="<?= htmlspecialchars($request['submitted_by']) ?>"
                                                data-total-amount="<?= htmlspecialchars(number_format((float)$request['total_amount'], 2)) ?>"
                                                data-type-or-value="<?= htmlspecialchars($request['type_or_value']) ?>">
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

    <aside class="right-panel">
        <h3 class="text-lg font-extrabold text-[#1B1B4B] mb-6">Summary</h3>
        
        <div class="bg-[#1B1B4B] rounded-3xl p-5 mb-6 text-white relative overflow-hidden">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Requests</p>
            <h2 class="text-2xl font-black mb-4"><?= count($requests) ?></h2>
            <div class="flex items-center justify-between">
                <span class="text-[9px] bg-[#ea580c] text-white px-2 py-0.5 rounded-full font-bold">LIVE</span>
                <span class="text-[10px] text-slate-400"><?= date('M Y') ?></span>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="text-xs font-bold text-[#1B1B4B] mb-4">Recent Activity</h4>
            <div class="space-y-4">
                <?php foreach (array_slice($notifications, 0, 4) as $notif): ?>
                    <div class="flex gap-3">
                        <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 <?= $notif['is_read'] ? 'bg-slate-200' : 'bg-[#FFD700]' ?>"></div>
                        <div>
                            <p class="text-[11px] text-slate-600 font-medium leading-tight mb-0.5"><?= htmlspecialchars($notif['message']) ?></p>
                            <span class="text-[9px] text-slate-400 font-bold uppercase"><?= time_ago($notif['created_at']) ?> ago</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>

<div id="request-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-50 p-6">
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Submitted By</p>
                        <p id="modal-submitted-by" class="text-[11px] font-bold text-[#1B1B4B]"></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Type/Value</p>
                        <p id="modal-type-or-value" class="text-[11px] font-bold text-[#1B1B4B]"></p>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Reason</p>
                <div id="modal-reason" class="text-slate-600 text-[12px] leading-relaxed bg-slate-50 p-4 rounded-2xl max-h-32 overflow-y-auto"></div>
            </div>
        </div>
        <button onclick="closeModal('request-modal')" class="w-full py-3 bg-slate-100 text-[#1B1B4B] rounded-xl text-xs font-bold hover:bg-slate-200 transition">Close</button>
    </div>
</div>

<div id="rejection-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-50 p-6">
    <div class="bg-white rounded-[30px] p-8 w-full max-w-sm shadow-2xl">
        <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 mb-6">
            <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <h2 id="rejection-modal-title" class="text-lg font-black text-[#1B1B4B] mb-2">Rejection Reason</h2>
        <div id="modal-rejection-reason" class="text-slate-600 text-[13px] leading-relaxed mb-8 italic"></div>
        <button onclick="closeModal('rejection-modal')" class="w-full py-3 bg-red-500 text-white rounded-xl text-xs font-bold hover:bg-red-600 transition">Acknowledge</button>
    </div>
</div>

<script>
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    notifBtn.onclick = (e) => {
        e.stopPropagation();
        notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
    };
    document.onclick = () => { notifDropdown.style.display = 'none'; };

    // Filter Logic
    const filterBtns = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('#requests-table tbody tr');

    filterBtns.forEach(btn => {
        btn.onclick = function() {
            filterBtns.forEach(b => {
                b.classList.remove('active', 'bg-[#1B1B4B]', 'text-white');
                b.classList.add('bg-white', 'text-slate-600');
            });
            this.classList.add('active', 'bg-[#1B1B4B]', 'text-white');
            this.classList.remove('bg-white', 'text-slate-600');
            const status = this.dataset.status;
            tableRows.forEach(row => {
                row.style.display = (status === 'All' || row.dataset.status === status) ? '' : 'none';
            });
        };
    });

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