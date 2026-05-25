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

// --- Helper Function for Time Formatting (from dashboard) ---
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

// --- Logic to Fetch Submission Data ---
try {
    $query = "SELECT title, description, budget, objectives, expected_outcome, submitted_by, document_path, status, barangay, rejection_reason 
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .main-content { flex: 1; padding: 25px 30px; background: #f1f5f9; overflow-y: auto; }
        
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        .content-card { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); }
        
        .status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 10px; text-transform: uppercase; }
        .status-Pending { background: #fff7ed; color: #c2410c; }
        .status-Approved { background: #f0fdf4; color: #15803d; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

        /* Receipt Style */
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
            <a href="sk_dashboard_sec.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="document_submissions_sec.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="submit_proposal_sec.php" class="nav-link active"><i class="fas fa-paper-plane"></i> Proposals</a>
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
                <a href="settings.php" class="text-[10px] text-[#FFD700] font-bold uppercase hover:opacity-80 transition flex items-center gap-1"><i class="fas fa-cog text-[9px]"></i> Settings</a>
                <span class="text-white/20">|</span>
                <a href="login.php" class="text-[10px] text-[#ea580c] font-bold uppercase hover:opacity-80 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-xl">Project Proposals</h1>
                <p id="current-time" class="text-xs text-slate-400 font-medium"></p>
            </div>
            
            <div class="flex items-center gap-3 relative">
                <a href="submit_project_proposal_sec.php" class="bg-[#1B1B4B] text-white px-4 py-2 rounded-xl text-[11px] font-bold hover:bg-opacity-90 transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> New Proposal
                </a>
            </div>
        </header>

        <div class="content-card">
            <div class="flex flex-wrap gap-2 mb-6">
                <button class="filter-btn px-4 py-1.5 rounded-lg text-[11px] font-bold border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition" data-status="All">All Projects</button>
                <button class="filter-btn px-4 py-1.5 rounded-lg text-[11px] font-bold border border-orange-100 bg-orange-50 text-orange-600 hover:bg-orange-100 transition" data-status="Pending">Pending</button>
                <button class="filter-btn px-4 py-1.5 rounded-lg text-[11px] font-bold border border-green-100 bg-green-50 text-green-600 hover:bg-green-100 transition" data-status="Approved">Approved</button>
                <button class="filter-btn px-4 py-1.5 rounded-lg text-[11px] font-bold border border-red-100 bg-red-50 text-red-600 hover:bg-red-100 transition" data-status="Rejected">Rejected</button>
            </div>

            <div class="overflow-x-auto">
                <table id="submissions-table" class="w-full text-left">
                    <thead>
                        <tr class="text-slate-400 text-[10px] font-bold uppercase tracking-widest border-b border-slate-100">
                            <th class="pb-4 px-2">Project Title</th>
                            <th class="pb-4 px-2">Proponent</th>
                            <th class="pb-4 px-2">Status</th>
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
                                        <span class="status-badge status-<?= str_replace(' ', '', htmlspecialchars($submission['status'])) ?>"><?= htmlspecialchars($submission['status']) ?></span>
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
</div>

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

<div id="details-modal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm hidden items-center justify-center z-50 p-6">
    <div class="bg-white rounded-3xl p-0 w-full max-w-2xl shadow-2xl transition-all transform scale-95 opacity-0" id="modal-content" style="overflow:hidden;">
        <div class="bg-gradient-to-r from-[#1B1B4B] to-[#FFD700] p-7 flex items-center justify-between">
            <div>
                <h3 id="modal-title" class="text-2xl font-extrabold text-white tracking-tight mb-1"></h3>
                <p class="text-xs text-[#FFD700] font-bold uppercase tracking-widest">Project Proposal Details</p>
            </div>
            <span id="modal-status-badge" class="status-badge text-xs px-4 py-2 font-bold"></span>
        </div>
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Submitted By</p>
                    <p id="modal-submitted-by" class="text-base font-bold text-[#ea580c]"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Total Budget</p>
                    <p id="modal-budget" class="text-base font-bold text-emerald-600"></p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Objectives</p>
                    <p id="modal-objectives" class="text-xs text-slate-700"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Expected Outcome</p>
                    <p id="modal-outcome" class="text-xs text-slate-700"></p>
                </div>
            </div>
            <div class="mb-6">
                <p class="text-[10px] text-gray-400 uppercase font-bold mb-2 ml-1">Description / Narrative</p>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <p id="modal-description" class="text-xs text-slate-600 leading-relaxed"></p>
                </div>
            </div>
            <div id="modal-rejection" class="mb-6" style="display:none;">
                <p class="text-[10px] text-red-500 uppercase font-bold mb-2 ml-1">Rejection Reason</p>
                <div class="p-4 bg-red-50 rounded-xl border border-red-100">
                    <p id="modal-rejection-reason" class="text-xs text-red-600 leading-relaxed font-semibold"></p>
                </div>
            </div>
            <div class="flex justify-end mt-8">
                <button onclick="hideDetails()" class="px-8 py-3 bg-[#1B1B4B] text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition shadow-lg">Close Details</button>
            </div>
        </div>
    </div>
</div>

<script>
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    // Details Modal
    const modal = document.getElementById('details-modal');
    const modalContent = document.getElementById('modal-content');
    function showDetails(json) {
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
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { modalContent.classList.remove('scale-95', 'opacity-0'); modalContent.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function hideDetails() {
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }

    // Receipt Logic
    const rModal = document.getElementById('receipt-modal');
    const rBox = document.getElementById('receipt-to-pdf');
    function openReceipt(json) {
        const data = JSON.parse(json);
        const now = new Date();
        document.getElementById('r-title').innerText = data.title;
        document.getElementById('r-proponent').innerText = data.submitted_by;
        document.getElementById('r-budget').innerText = '₱' + parseFloat(data.budget).toLocaleString();
        document.getElementById('r-status').innerText = data.status.toUpperCase();
        document.getElementById('r-date').innerText = now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        document.getElementById('r-id').innerText = 'SK-' + Math.floor(100000 + Math.random() * 900000);
        
        rModal.classList.remove('hidden'); rModal.classList.add('flex');
        setTimeout(() => { rBox.classList.remove('scale-90', 'opacity-0'); rBox.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function closeReceipt() {
        rBox.classList.add('scale-90', 'opacity-0');
        setTimeout(() => { rModal.classList.add('hidden'); rModal.classList.remove('flex'); }, 200);
    }

    // PDF Export Function
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

    // Filter Logic
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