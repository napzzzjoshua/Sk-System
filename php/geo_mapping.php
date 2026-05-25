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

// --- Logo Mapping Logic ---
$barangay_logos = [
    'default' => '../sk_logo.png',
    'Suba' => '../sk_suba.jpg',
    'San Isidro' => '../sk_sanisidro.jpg',
    'Amonoy' => '../sk_amonoy.jpg',
    'Bakia' => '../sk_bakia.jpg',
    'Balanac' => '../sk_balanac.jpg',
    'Balayong' => '../sk_balayong.png',
    'Coralao' => '../sk_coralao.jpg',
    'Ibabang Banga' => '../sk_ibabang_banga.jpg',
    'Ibabang Bayucain' => '../sk_ibabang_bayucain.jpg',
    'Ilayang Banga' => '../sk_ilayang_banga.png',
    'May-It' => '../sk_may-it.png',
    'Munting Kawayan' => '../sk_munting_kawayan.jpg',
    'Olla' => '../sk_olla.jpg',
    'Panalaban' => '../sk_panalaban.jpg',
    'Talortor' => '../sk_talortor.jpg',
    'Tanawan' => '../sk_tanawan.jfif',
    'Origuel' => '../sk_origuel.jpg',
    'San Francisco' => '../sk_san_francisco.jpg',
    'San Miguel' => '../sk_san_miguel.jpg',
];
$barangay_logos_json = json_encode($barangay_logos);

// --- YEAR DROPDOWN LOGIC ---
$years = [];
$year_query = "SELECT DISTINCT YEAR(created_at) as year FROM (SELECT created_at FROM financial_aid_requests UNION ALL SELECT created_at FROM submissions) as all_years ORDER BY year DESC";
$year_result = $conn->query($year_query);
if ($year_result) { while ($row = $year_result->fetch_assoc()) { $years[] = (int)$row['year']; } }
$current_real_year = (int)date('Y');
if (count($years) === 0) { $years[] = $current_real_year; }
$current_year = isset($_GET['year']) ? $_GET['year'] : (count($years) ? $years[0] : $current_real_year);

// --- STATISTICS CALCULATIONS ---
$total_sk_officials = $conn->query("SELECT COUNT(*) as count FROM sk_list")->fetch_assoc()['count'] ?? 0;
$grand_total_aid = (float)($conn->query("SELECT SUM(CAST(total_amount AS DECIMAL(10,2))) as total FROM financial_aid_requests WHERE status = 'Approved' AND YEAR(created_at) = '$current_year'")->fetch_assoc()['total'] ?? 0);
$grand_total_budget = (float)($conn->query("SELECT SUM(CAST(budget AS DECIMAL(10,2))) as total FROM submissions WHERE status = 'Approved' AND YEAR(created_at) = '$current_year'")->fetch_assoc()['total'] ?? 0);
$grand_total_accumulated = $grand_total_aid + $grand_total_budget;

// --- MAP DATA FETCHING ---
$sk_officials_data = [];
$sql_sk = "SELECT first_name, middle_name, last_name, email, profile_photo, barangay, position FROM sk_list ORDER BY barangay, position";
$res_sk = $conn->query($sql_sk);
while ($row = $res_sk->fetch_assoc()) {
    $sk_officials_data[$row['barangay']][$row['position']] = [
        'name' => trim("{$row['first_name']} {$row['middle_name']} {$row['last_name']}"),
        'email' => $row['email'],
        'profile_photo' => $row['profile_photo']
    ];
}
$sk_officials_data_json = json_encode($sk_officials_data);

$barangay_financial_data = [];
$res_aid = $conn->query("SELECT barangay, SUM(CAST(total_amount AS DECIMAL(10,2))) as total FROM financial_aid_requests WHERE status = 'Approved' AND YEAR(created_at) = '$current_year' GROUP BY barangay");
while($row = $res_aid->fetch_assoc()) { $barangay_financial_data[$row['barangay']]['total_aid'] = (float)$row['total']; }
$res_bud = $conn->query("SELECT barangay, SUM(CAST(budget AS DECIMAL(10,2))) as total FROM submissions WHERE status = 'Approved' AND YEAR(created_at) = '$current_year' GROUP BY barangay");
while($row = $res_bud->fetch_assoc()) { $barangay_financial_data[$row['barangay']]['total_budget'] = (float)$row['total']; }
$barangay_financial_data_json = json_encode($barangay_financial_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Geo Mapping</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.css" />
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
        .user-menu-item { display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: #4A5568; transition: all 0.2s; cursor: pointer; }
        .user-menu-item:hover { background-color: #F8FAFC; color: var(--navy-primary); }
        .user-menu-item i { width: 18px; margin-right: 10px; text-align: center; font-size: 0.9rem; }

        #mapid { height: 550px; width: 100%; border-radius: 15px; z-index: 10; border: 1px solid #E2E8F0; }
        .sk-logo-marker img { width: 38px; height: 38px; border-radius: 50%; border: 2px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.1); object-fit: cover; }

        /* Map layer toggle control */
        .map-layer-control { position: absolute; top: 10px; right: 10px; z-index: 500; display: flex; flex-direction: column; gap: 6px; }
        .map-layer-btn { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px 12px; font-size: 11px; font-weight: 600; color: #1B1B4B; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.12); transition: all 0.2s; }
        .map-layer-btn:hover, .map-layer-btn.active { background: #1B1B4B; color: #FFD700; }

        /* Leaflet popup custom style */
        .leaflet-popup-content-wrapper { border-radius: 12px !important; box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important; border: none !important; }
        .leaflet-popup-tip { background: white !important; }
        .custom-popup b { color: #1B1B4B; font-size: 13px; display: block; margin-bottom: 2px; }
        .custom-popup span { color: #64748b; font-size: 11px; }

        /* Pulse animation for selected marker */
        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2.2); opacity: 0; } }
        .marker-pulse::before { content: ''; position: absolute; width: 38px; height: 38px; border-radius: 50%; background: rgba(27,27,75,0.25); animation: pulse-ring 1.5s ease-out infinite; top: 0; left: 0; z-index: -1; }
        .sk-logo-marker { position: relative; }
        
        /* Modal Design Match */
        #officialsModal { position: fixed; top: 0; right: 0; height: 100%; width: 380px; background: white; z-index: 100; box-shadow: -10px 0 30px rgba(0,0,0,0.05); transform: translateX(100%); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); border-left: 1px solid #F0F1F7; }
        #officialsModal.modal-active { transform: translateX(0); }
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
            <a href="geo_mapping.php" class="nav-item active"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
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
            <h2 class="text-xl font-bold text-[#1B1B4B]">Geospatial Mapping</h2>
            <div class="flex items-center space-x-6">
                <form id="yearForm" method="get" class="flex items-center">
                    <select onchange="this.form.submit()" name="year" class="bg-white border border-gray-200 text-[10px] font-bold px-3 py-1.5 rounded-lg outline-none">
                        <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y == $current_year ? 'selected' : '' ?>>FY <?= $y ?></option><?php endforeach; ?>
                    </select>
                </form>
                
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
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-users text-sm"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Officials</p><h3 class="text-lg font-bold text-[#1B1B4B]"><?= number_format($total_sk_officials) ?></h3></div>
            </div>
            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-hand-holding-dollar text-sm"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Financial Aid</p><h3 class="text-lg font-bold text-[#1B1B4B]">₱<?= number_format($grand_total_aid, 2) ?></h3></div>
            </div>
            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-indigo-50 text-[#1B1B4B] rounded-xl flex items-center justify-center mr-3"><i class="fas fa-file-invoice text-sm"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Project Budget</p><h3 class="text-lg font-bold text-[#1B1B4B]">₱<?= number_format($grand_total_budget, 2) ?></h3></div>
            </div>
            <div class="card-white flex items-center p-4">
                <div class="w-10 h-10 bg-amber-50 text-[#FFD700] rounded-xl flex items-center justify-center mr-3"><i class="fas fa-vault text-sm"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Funds</p><h3 class="text-lg font-bold text-[#1B1B4B]">₱<?= number_format($grand_total_accumulated, 2) ?></h3></div>
            </div>
        </div>

        <div class="card-white">
            <div class="flex flex-col md:flex-row gap-3 mb-4">
                <div class="relative flex-grow">
                    <input type="text" id="barangaySearchInput" placeholder="Quick find a barangay..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-xs focus:ring-1 ring-navy-primary outline-none transition">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                </div>
                <button onclick="searchBarangay()" class="px-6 py-2 bg-[#1B1B4B] text-white text-xs font-bold rounded-xl shadow-md shadow-navy-primary/10 hover:opacity-90 transition">Find Barangay</button>
            </div>
            <div id="mapid"></div>
        </div>
    </main>

    <div id="officialsModal" class="flex flex-col">
        <div class="p-5 border-b border-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-sm text-[#1B1B4B]">Barangay Details</h3>
            <button id="closeModal" class="text-gray-300 hover:text-red-500 transition"><i class="fas fa-times-circle text-xl"></i></button>
        </div>
        <div id="modalHeaderContent" class="p-5 bg-slate-50/50"></div>
        <div id="officialsList" class="flex-grow overflow-y-auto p-4 space-y-2.5"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.js"></script>
    <script>
        // Modal Data
        const SK_OFFICIALS_DATA = <?= $sk_officials_data_json ?>;
        const BARANGAY_LOGOS = <?= $barangay_logos_json ?>;
        const BARANGAY_FINANCIAL_DATA = <?= $barangay_financial_data_json ?>;
        const ROLES = ["SK Chairman", "SK Kagawad (1st)", "SK Kagawad (2nd)", "SK Kagawad (3rd)", "SK Kagawad (4th)", "SK Kagawad (5th)", "SK Kagawad (6th)", "SK Kagawad (7th)", "SK Secretary", "SK Treasurer"];

        const barangays = [
            { name: "Amonoy", lat: 14.1018, lng: 121.4958 }, { name: "Bakia", lat: 14.1557, lng: 121.4904 },
            { name: "Balanac", lat: 14.1776, lng: 121.4559 }, { name: "Balayong", lat: 14.1289, lng: 121.4860 },
            { name: "Banilad", lat: 14.1898, lng: 121.4653 }, { name: "Banti", lat: 14.1803, lng: 121.4645 },
            { name: "Bitaoy", lat: 14.1350, lng: 121.5097 }, { name: "Botocan", lat: 14.1549, lng: 121.4963 },
            { name: "Bukal", lat: 14.1215, lng: 121.4688 }, { name: "Burgos", lat: 14.12723, lng: 121.50116 },
            { name: "Burol", lat: 14.1713, lng: 121.4811 }, { name: "Coralao", lat: 14.1414, lng: 121.4621 },
            { name: "Gagalot", lat: 14.1146, lng: 121.5115 }, { name: "Ibabang Banga", lat: 14.1534, lng: 121.4786 },
            { name: "Ibabang Bayucain", lat: 14.1674, lng: 121.4425 }, { name: "Ilayang Banga", lat: 14.1458, lng: 121.4840 },
            { name: "Ilayang Bayucain", lat: 14.1570, lng: 121.4456 }, { name: "Isabang", lat: 14.1469, lng: 121.5081 },
            { name: "Malinao", lat: 14.1071, lng: 121.4833 }, { name: "May-It", lat: 14.1397, lng: 121.4862 },
            { name: "Munting Kawayan", lat: 14.1580, lng: 121.4658 }, { name: "Olla", lat: 14.1605, lng: 121.4550 },
            { name: "Oobi", lat: 14.1219, lng: 121.4857 }, { name: "Panalaban", lat: 14.1250, lng: 121.4936 },
            { name: "Pangil", lat: 14.1349, lng: 121.4654 }, { name: "Panglan", lat: 14.1413, lng: 121.4531 },
            { name: "Piit", lat: 14.1418, lng: 121.5007 }, { name: "Pook", lat: 14.1705, lng: 121.4646 },
            { name: "Rizal", lat: 14.1251, lng: 121.5150 }, { name: "San Isidro", lat: 14.1739, lng: 121.4412 },
            { name: "San Roque", lat: 14.1258, lng: 121.4610 }, { name: "Suba", lat: 14.1731, lng: 121.4494 },
            { name: "Talortor", lat: 14.1520, lng: 121.4596 }, { name: "Tanawan", lat: 14.1814, lng: 121.4539 },
            { name: "Taytay", lat: 14.1145, lng: 121.5045 }, { name: "Villa Nogales", lat: 14.1414, lng: 121.4697 },
            { name: "Origuel (Poblacion)", lat: 14.1448, lng: 121.4726 },
            { name: "San Francisco (Poblacion)", lat: 14.1453, lng: 121.4736 },
            { name: "San Miguel (Poblacion)", lat: 14.1429, lng: 121.4714 },
            { name: "Santa Catalina (Poblacion)", lat: 14.1474, lng: 121.4713 }
        ];

        let map, barangayMarkers = {};
        const formatCurrency = (amt) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amt || 0);

        function openOfficialsModal(bName) {
            const cleaned = bName.replace(/\s*\(Poblacion\)\s*/, '');
            const logo = BARANGAY_LOGOS[cleaned] || BARANGAY_LOGOS['default'];
            const fin = BARANGAY_FINANCIAL_DATA[cleaned] || { total_aid: 0, total_budget: 0 };
            const officials = SK_OFFICIALS_DATA[cleaned] || {};
            const localTotalBudget = (fin.total_aid || 0) + (fin.total_budget || 0);
            const localOfficialsCount = Object.keys(officials).length;

            document.getElementById('modalHeaderContent').innerHTML = `
                <div class="flex items-center gap-3 mb-4">
                    <img src="${logo}" class="w-12 h-12 rounded-full border-2 border-white shadow-sm object-cover">
                    <div>
                        <h4 class="font-bold text-base text-[#1B1B4B]">Brgy. ${bName}</h4>
                        <p class="text-[9px] text-gray-400 uppercase tracking-tighter">Barangay Data Summary</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white p-2.5 rounded-xl border border-gray-100">
                        <p class="text-[8px] font-bold text-blue-500 uppercase">Officials</p>
                        <p class="text-sm font-bold text-[#1B1B4B]">${localOfficialsCount}</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-amber-100">
                        <p class="text-[8px] font-bold text-amber-600 uppercase">Total Budget</p>
                        <p class="text-sm font-bold text-[#1B1B4B]">${formatCurrency(localTotalBudget)}</p>
                    </div>
                </div>`;

            document.getElementById('officialsList').innerHTML = ROLES.map(role => {
                const off = officials[role];
                const blankPhoto = `https://ui-avatars.com/api/?name=?&background=F1F5F9&color=94A3B8&length=1`;
                const photoUrl = off?.profile_photo ? off.profile_photo : blankPhoto;
                
                return `<div class="flex items-center gap-3 p-2.5 bg-white rounded-xl border border-gray-50 hover:border-gold-accent transition">
                    <img src="${photoUrl}" class="w-8 h-8 rounded-full object-cover border border-gray-100">
                    <div class="overflow-hidden">
                        <p class="text-[8px] font-bold text-gray-400 uppercase leading-none mb-0.5">${role}</p>
                        <p class="text-xs font-bold text-[#1B1B4B] truncate">${off?.name || 'Vacant'}</p>
                    </div>
                </div>`;
            }).join('');
            document.getElementById('officialsModal').classList.add('modal-active');
        }

        function searchBarangay() {
            const q = document.getElementById('barangaySearchInput').value.trim().toLowerCase();
            const found = barangays.find(b => b.name.toLowerCase().includes(q));
            if(found) { 
                map.flyTo([found.lat, found.lng], 15); 
                setTimeout(() => { 
                    barangayMarkers[found.name].openPopup(); 
                    openOfficialsModal(found.name); 
                }, 1000); 
            }
        }

        // --- UPDATED DROPDOWN HANDLERS ---
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

        document.addEventListener('DOMContentLoaded', () => {
            // --- ADVANCED MAP SETUP ---
            map = L.map('mapid', {
                center: [14.1455, 121.4725],
                zoom: 14,
                zoomControl: false,
                gestureHandling: true,
            });

            // --- TILE LAYERS ---
            const streetLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
                maxZoom: 20,
                subdomains: 'abcd'
            });

            const satelliteLayer = L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&hl=en&x={x}&y={y}&z={z}', {
                attribution: 'Map data &copy; Google',
                subdomains: ['0','1','2','3'],
                maxZoom: 20
            });

            const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, &copy; <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
                maxZoom: 17
            });

            // Default: street layer
            streetLayer.addTo(map);

            // --- ZOOM CONTROL (custom position) ---
            L.control.zoom({ position: 'bottomright' }).addTo(map);

            // --- SCALE BAR ---
            L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);

            // --- LAYER TOGGLE BUTTONS (proper Leaflet custom control) ---
            const LayerControl = L.Control.extend({
                options: { position: 'topright' },
                onAdd: function() {
                    const div = L.DomUtil.create('div', '');
                    div.innerHTML = `
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <button class="map-layer-btn active" id="btnStreet" onclick="switchLayer('street')"><i class="fas fa-map"></i> Street</button>
                            <button class="map-layer-btn" id="btnSatellite" onclick="switchLayer('satellite')"><i class="fas fa-satellite"></i> Satellite</button>
                            <button class="map-layer-btn" id="btnTopo" onclick="switchLayer('topo')"><i class="fas fa-mountain"></i> Terrain</button>
                        </div>`;
                    L.DomEvent.disableClickPropagation(div);
                    return div;
                }
            });
            new LayerControl().addTo(map);

            window._mapLayers = { street: streetLayer, satellite: satelliteLayer, topo: topoLayer };
            window._activeLayer = 'street';

            window.switchLayer = function(type) {
                Object.values(window._mapLayers).forEach(l => map.removeLayer(l));
                window._mapLayers[type].addTo(map);
                window._activeLayer = type;
                ['btnStreet','btnSatellite','btnTopo'].forEach(id => document.getElementById(id).classList.remove('active'));
                document.getElementById('btn' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active');
            };

            // --- MARKER CLUSTER GROUP ---
            const clusterGroup = L.markerClusterGroup({
                maxClusterRadius: 50,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: `<div style="background:#1B1B4B;color:#FFD700;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid #FFD700;box-shadow:0 4px 12px rgba(27,27,75,0.35);">${count}</div>`,
                        className: '', iconSize: [40, 40]
                    });
                },
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
            });

            // --- MARKERS ---
            barangays.forEach(b => {
                const cleaned = b.name.replace(/\s*\(Poblacion\)\s*/, '');
                const logoSrc = BARANGAY_LOGOS[cleaned] || BARANGAY_LOGOS['default'];

                const icon = L.divIcon({
                    className: 'sk-logo-marker',
                    html: `<img src="${logoSrc}" title="${b.name}">`,
                    iconSize: [38, 38],
                    iconAnchor: [19, 38]
                });

                const popupContent = `<div class="custom-popup" style="padding:4px 2px;"><b>${b.name}</b><span>Click to view officials</span></div>`;

                const marker = L.marker([b.lat, b.lng], { icon })
                    .bindPopup(popupContent, { maxWidth: 200, className: '' })
                    .on('click', () => {
                        // Pulse effect
                        document.querySelectorAll('.sk-logo-marker').forEach(el => el.classList.remove('marker-pulse'));
                        marker.getElement()?.classList.add('marker-pulse');
                        map.flyTo([b.lat, b.lng], 15, { animate: true, duration: 1 });
                        setTimeout(() => openOfficialsModal(b.name), 600);
                    });

                barangayMarkers[b.name] = marker;
                clusterGroup.addLayer(marker);
            });

            map.addLayer(clusterGroup);

            // --- SEARCH ---
            const searchInput = document.getElementById('barangaySearchInput');
            searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') searchBarangay(); });
            document.getElementById('closeModal').onclick = () => document.getElementById('officialsModal').classList.remove('modal-active');
        });
    </script>
</body>
</html>