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

$user_id  = $_SESSION['user_id'];

// --- Fetch user details ---
$sql  = "SELECT surname, firstname, middlename, barangay, position, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) { die('Error: ' . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($surname, $firstname, $middlename, $barangay, $position, $profile_photo);
$stmt->fetch();
$stmt->close();

$user_full_name = trim($firstname . ' ' . $surname . ' ' . $middlename);
$fullname = $user_full_name;

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";

// --- Logo Logic ---
$logoPath = "../sk_logo.png"; 
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; } 
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

// --- Message Rendering Function (Updated for Dashboard Style) ---
function renderMessage($sender_id, $logged_in_user_id, $sender_fullname, $message_content, $created_at, $profilePath) {
    $is_user = (int)$sender_id === (int)$logged_in_user_id;
    $datetime = new DateTime($created_at);
    $time_formatted = $datetime->format('h:i A');

    $justifyClass = $is_user ? 'justify-end' : 'justify-start';
    // User bubble uses the orange gradient from dashboard stats; Admin uses light slate
    $bubbleClass  = $is_user ? 'bg-gradient-to-br from-[#ea580c] to-[#c2410c] text-white rounded-2xl rounded-tr-none' : 'bg-white border border-slate-200 text-slate-700 rounded-2xl rounded-tl-none';
    $textColor    = $is_user ? 'text-white/70' : 'text-slate-400';
    
    $is_admin = (int)$sender_id === 1;
    $displayName = $is_admin ? 'SK President' : htmlspecialchars($sender_fullname);
    $nameText = !$is_user ? "<p class='font-bold text-[10px] uppercase tracking-wider text-[#1B1B4B] mb-1'>{$displayName}</p>" : "";
    
    $avatarSrc = $is_user ? htmlspecialchars($profilePath) : "https://ui-avatars.com/api/?name=Admin&background=1B1B4B&color=fff";
    $avatar = "<img src='{$avatarSrc}' class='w-8 h-8 rounded-lg object-cover shadow-sm border border-slate-200'>";
    
    $content = "<div class='flex items-end gap-3 max-w-[80%]'>";
    if (!$is_user) $content .= $avatar;
    $content .= "<div class='{$bubbleClass} p-4 shadow-sm relative'>
                    {$nameText}
                    <p class='text-[13px] leading-relaxed'>" . nl2br(htmlspecialchars($message_content)) . "</p>
                    <p class='text-[9px] font-bold mt-2 {$textColor} uppercase'>{$time_formatted}</p>
                </div>";
    if ($is_user) $content .= $avatar;
    $content .= "</div>";

    return "<div class='flex {$justifyClass} mb-6'>{$content}</div>";
}

// --- Fetch Messages ---
$all_messages = [];
$admin_id = 1; 
$sql_fetch = "SELECT sender_id, sender_fullname, messages_content, created_at FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
while($row = $result_fetch->fetch_assoc()) { $all_messages[] = $row; }
$stmt_fetch->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Chat | SK Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; margin: 0; font-size: 13px; color: #1e293b; }

        /* ── App wrapper: flex row, full viewport height ── */
        .app-wrapper { display: flex; height: 100vh; position: relative; overflow: hidden; }

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
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        /* ── Main container: holds header + scrollable chat area + input ── */
        .main-container { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }

        /* ── Chat area ── */
        .chat-area { flex: 1; overflow-y: auto; padding: 24px; }

        /* ── Navigation (sk_dashboard style) ── */
        .nav-link { display: flex; align-items: center; padding: 12px 16px; border-radius: 14px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-weight: 600; }
        .nav-link i { font-size: 16px; width: 28px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 10px 15px -3px rgba(255, 215, 0, 0.3); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.08); color: #FFFFFF; }

        /* ── Notification button (sk_dashboard style) ── */
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

        /* ── Mobile hamburger (sk_dashboard style) ── */
        .mobile-menu-btn { background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; cursor: pointer; }

        /* ── Sidebar overlay (sk_dashboard style) ── */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 1000; }
        .sidebar-overlay.active { display: block; }

        /* ── Chat bubble container ── */
        .chat-container { background: #f8fafc; border-radius: 24px; border: 1px solid rgba(0,0,0,0.05); padding: 25px; }

        /* ── Mobile Responsiveness (sk_dashboard breakpoints) ── */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                height: 100vh;
                top: 0;
            }
            .sidebar.active { left: 0; }
            .sticky-header { padding: 10px 16px; }
            .chat-area { padding: 16px; }
            .header-title-wrapper h1 { font-size: 16px !important; }
        }

        @media (max-width: 640px) {
            body { font-size: 12px; }
            .chat-area { padding: 12px 10px; }
            .chat-container { padding: 15px; border-radius: 16px; }
        }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- ── Sidebar Overlay ── -->
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

            <?php if ($position === 'SK Treasurer'): ?>
                <a href="financial_aid_tre.php" class="nav-link"><i class="fas fa-wallet"></i> Financial Aid</a>
                <a href="scholarship_list_tre.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholars</a>
            <?php elseif ($position && strcasecmp($position, 'SK Secretary') === 0): ?>
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

    <!-- ── Main Container ── -->
    <div class="main-container">

        <!-- ── Sticky Header (sk_dashboard style) ── -->
        <header class="sticky-header">
            <div class="flex items-center gap-4">
                <button id="sidebarToggle" class="mobile-menu-btn text-[#1B1B4B]">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="header-title-wrapper">
                    <h1 class="font-extrabold text-[#1B1B4B] text-xl tracking-tight">Technical Support</h1>
                    <p class="text-[11px] text-slate-500 font-semibold">Direct Chat with SK President</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-[11px] font-bold text-[#1B1B4B] uppercase hidden sm:inline">Server Live</span>
                </div>
            </div>
        </header>

        <!-- ── Chat Area ── -->
        <div class="chat-area" id="chatContainer">
            <div class="chat-container">
                <?php
                if (empty($all_messages)) {
                    echo "<div class='flex flex-col items-center justify-center py-16 text-slate-400'>
                            <i class='fas fa-comments text-4xl mb-3 opacity-20'></i>
                            <p class='text-sm font-medium'>Start your conversation with the SK President</p>
                          </div>";
                } else {
                    foreach ($all_messages as $message) {
                        echo renderMessage($message['sender_id'], $user_id, $message['sender_fullname'], $message['messages_content'], $message['created_at'], $profilePath);
                    }
                }
                ?>
            </div>
        </div>

        <!-- ── Message Input ── -->
        <div class="px-6 py-4 border-t border-slate-200 bg-white/80 backdrop-blur flex-shrink-0">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <form id="chatForm" class="flex items-center gap-3">
                    <input type="text" id="messageInput" placeholder="Write your message..." required
                           class="flex-1 bg-slate-50 border-none px-5 py-3 rounded-xl text-sm focus:ring-2 focus:ring-[#FFD700] outline-none">
                    <button type="submit" class="bg-[#1B1B4B] text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-slate-800 transition flex items-center gap-2">
                        <span class="hidden sm:inline">Send</span>
                        <i class="fas fa-paper-plane text-[10px]"></i>
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /.main-container -->
</div><!-- /.app-wrapper -->

<script>
    // ── Sidebar Toggle (sk_dashboard logic) ──
    const sidebar        = document.getElementById('sidebar');
    const sidebarToggle  = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });

    // ── Chat bubble builder ──
    const chatContainer = document.getElementById('chatContainer');
    const profilePath = "<?= htmlspecialchars($profilePath) ?>";

    function createMessageBubble(content, sentAt) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex justify-end mb-6'; 
        messageDiv.innerHTML = `
            <div class="flex items-end gap-3 max-w-[80%]">
                <div class="bg-gradient-to-br from-[#ea580c] to-[#c2410c] text-white rounded-2xl rounded-tr-none p-4 shadow-sm relative">
                    <p class="text-[13px] leading-relaxed">${content}</p>
                    <p class="text-[9px] font-bold mt-2 text-white/70 uppercase text-left">${sentAt}</p>
                </div>
                <img src="${profilePath}" class="w-8 h-8 rounded-lg object-cover shadow-sm border border-slate-200">
            </div>
        `;
        return messageDiv;
    }

    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const messageText = input.value.trim();
        if (!messageText) return;

        const button = this.querySelector('button');
        const originalBtn = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        const formData = new FormData();
        formData.append('message_content', messageText);

        fetch('insert_message.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            button.innerHTML = originalBtn;
            button.disabled = false;
            if (data.success) {
                // Append inside the chat-container div
                const chatInner = chatContainer.querySelector('.chat-container');
                const messageElement = createMessageBubble(data.content, data.sent_at);
                chatInner.appendChild(messageElement);
                input.value = '';
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        })
        .catch(err => {
            button.innerHTML = originalBtn;
            button.disabled = false;
            console.error(err);
        });
    });

    window.onload = () => { chatContainer.scrollTop = chatContainer.scrollHeight; };
</script>
</body>
</html>