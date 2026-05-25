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

// --- DATA LOGIC: DASHBOARD STATS ---
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$app_sub = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'Approved'")->fetch_assoc()['count'] ?? 0;
$app_aid = $conn->query("SELECT COUNT(*) as count FROM financial_aid_requests WHERE status = 'Approved'")->fetch_assoc()['count'] ?? 0;
$documents_count = $app_sub + $app_aid;
$sk_officials_count = $conn->query("SELECT COUNT(*) as count FROM sk_list")->fetch_assoc()['count'] ?? 0;

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

// --- DATA LOGIC: MAIN ANALYTICS ---
$all_requests_data = [];
$statuses = ['Approved', 'Pending', 'Rejected'];
foreach ($statuses as $status) {
    $query = "SELECT status, created_at FROM submissions WHERE status = '$status' 
            UNION ALL 
            SELECT status, created_at FROM financial_aid_requests WHERE status = '$status'";
    $res = $conn->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            $all_requests_data[] = ['status' => $row['status'], 'timestamp' => strtotime($row['created_at'])];
        }
    }
}
$all_requests_json = json_encode($all_requests_data);

// --- DATA LOGIC: SPECIFIC BAR CHARTS ---
$approved_projects_data = [];
$p_res = $conn->query("SELECT created_at FROM submissions WHERE status = 'Approved'");
while($row = $p_res->fetch_assoc()) { $approved_projects_data[] = ['timestamp' => strtotime($row['created_at'])]; }
$approved_projects_json = json_encode($approved_projects_data);

$approved_aid_data = [];
$a_res = $conn->query("SELECT created_at FROM financial_aid_requests WHERE status = 'Approved'");
while($row = $a_res->fetch_assoc()) { $approved_aid_data[] = ['timestamp' => strtotime($row['created_at'])]; }
$approved_aid_json = json_encode($approved_aid_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Profile Dropdown Specific Styles */
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
            <a href="admin_dashboard.php" class="nav-item active"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
            
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
            <h2 class="text-xl font-bold text-[#1B1B4B]">Analytics Dashboard</h2>
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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fa-solid fa-users text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Users</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($total_users) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-amber-50 text-[#FFD700] rounded-xl flex items-center justify-center mr-3">
                    <i class="fa-solid fa-user-tie text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">SK Officials</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($sk_officials_count) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fa-solid fa-hand-holding-dollar text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Approved Aid</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($app_aid) ?></h3>
                </div>
            </div>

            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-indigo-50 text-[#1B1B4B] rounded-xl flex items-center justify-center mr-3">
                    <i class="fa-solid fa-file-invoice text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Docs</p>
                    <h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($documents_count) ?></h3>
                </div>
            </div>
        </div>

        <div class="card-white mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-sm font-bold text-[#1B1B4B]">Overall Request Trends</h3>
                <div class="flex bg-gray-100 p-1 rounded-lg gap-1">
                    <button onclick="updateMainChart('day')" class="px-3 py-1 text-[10px] font-bold rounded-md hover:bg-white transition">Day</button>
                    <button onclick="updateMainChart('month')" class="px-3 py-1 text-[10px] font-bold rounded-md hover:bg-white transition">Month</button>
                    <button onclick="updateMainChart('year')" class="px-3 py-1 text-[10px] font-bold rounded-md hover:bg-white transition">Year</button>
                </div>
            </div>
            <div style="height: 300px;"><canvas id="mainTrendChart"></canvas></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card-white">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-[11px] font-bold text-slate-700 uppercase">Approved Projects</h4>
                    <div class="flex bg-gray-100 p-1 rounded-lg gap-1">
                        <button onclick="updateBarChart('approvedProjects', 'month')" class="px-2 py-0.5 text-[9px] font-bold rounded hover:bg-white transition">Month</button>
                        <button onclick="updateBarChart('approvedProjects', 'year')" class="px-2 py-0.5 text-[9px] font-bold rounded hover:bg-white transition">Year</button>
                    </div>
                </div>
                <div style="height: 200px;"><canvas id="approvedProjectsChart"></canvas></div>
            </div>
            <div class="card-white">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-[11px] font-bold text-slate-700 uppercase">Approved Financial Aid</h4>
                    <div class="flex bg-gray-100 p-1 rounded-lg gap-1">
                        <button onclick="updateBarChart('approvedAid', 'month')" class="px-2 py-0.5 text-[9px] font-bold rounded hover:bg-white transition">Month</button>
                        <button onclick="updateBarChart('approvedAid', 'year')" class="px-2 py-0.5 text-[9px] font-bold rounded hover:bg-white transition">Year</button>
                    </div>
                </div>
                <div style="height: 200px;"><canvas id="approvedAidChart"></canvas></div>
            </div>
        </div>
    </main>

    <script>
        // Dropdown Handlers
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

        window.onclick = () => {
            notifDropdown.classList.remove('show');
            profileDropdown.classList.remove('show');
        }

        const NAVY = '#1B1B4B';
        const GOLD = '#FFD700';
        const TEAL = '#2DD4BF';
        const RED  = '#EF4444';

        const rawMainData = <?= $all_requests_json ?>;
        const rawProjectsData = <?= $approved_projects_json ?>;
        const rawAidData = <?= $approved_aid_json ?>;
        let mainChart, approvedProjectsChart, approvedAidChart;

        function updateMainChart(view) {
            const dataMap = {};
            rawMainData.forEach(item => {
                const date = new Date(item.timestamp * 1000);
                let key;
                if(view === 'day') key = date.toLocaleDateString('en-US', {month:'short', day:'numeric'});
                else if(view === 'month') key = date.toLocaleDateString('en-US', {month:'short', year:'numeric'});
                else key = date.getFullYear().toString();

                if(!dataMap[key]) dataMap[key] = {Approved:0, Pending:0, Rejected:0};
                dataMap[key][item.status]++;
            });

            const labels = Object.keys(dataMap);
            if(mainChart) mainChart.destroy();
            mainChart = new Chart(document.getElementById('mainTrendChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Approved', data: labels.map(l => dataMap[l].Approved), borderColor: NAVY, backgroundColor: 'rgba(27, 27, 75, 0.1)', fill: true, tension: 0.4 },
                        { label: 'Pending', data: labels.map(l => dataMap[l].Pending), borderColor: TEAL, backgroundColor: 'rgba(45,212,191,0.05)', fill: true, tension: 0.4 },
                        { label: 'Rejected', data: labels.map(l => dataMap[l].Rejected), borderColor: RED, backgroundColor: 'rgba(239,68,68,0.05)', fill: true, tension: 0.4 }
                    ]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { font: { family: 'Inter', size: 10, weight: '600' } } } }
                }
            });
        }

        function updateBarChart(type, view) {
            let rawData, canvasId, label, color, chartRef;
            if(type === 'approvedProjects') {
                rawData = rawProjectsData; canvasId = 'approvedProjectsChart'; label = 'Projects'; color = GOLD; chartRef = approvedProjectsChart;
            } else {
                rawData = rawAidData; canvasId = 'approvedAidChart'; label = 'Aid'; color = NAVY; chartRef = approvedAidChart;
            }

            const map = {};
            rawData.forEach(item => {
                const date = new Date(item.timestamp * 1000);
                let key = (view === 'month') ? date.toLocaleDateString('en-US', {month:'short', year:'numeric'}) : date.getFullYear().toString();
                map[key] = (map[key] || 0) + 1;
            });

            const labels = Object.keys(map);
            if(chartRef && typeof chartRef.destroy === 'function') chartRef.destroy();
            
            const newChart = new Chart(document.getElementById(canvasId).getContext('2d'), {
                type: 'bar',
                data: { labels, datasets: [{ label, data: labels.map(l => map[l]), backgroundColor: color, borderRadius: 6 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            if(type === 'approvedProjects') approvedProjectsChart = newChart;
            else approvedAidChart = newChart;
        }

        window.onload = () => {
            updateMainChart('day');
            updateBarChart('approvedProjects', 'month');
            updateBarChart('approvedAid', 'month');
        };
    </script>
</body>
</html>