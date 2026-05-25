<?php
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
    
require_once 'db_conn.php';
$fullname = $_SESSION['fullname'];
$user_id = $_SESSION['user_id']; // Matches admin_id context

// --- DATA LOGIC: NOTIFICATIONS & CHAT (Matched from admin_dashboard.php) ---
$unread_messages_query = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$unread_messages_query->bind_param("i", $user_id);
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

// --- PHPMailer Integration Setup ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/PHPMailer-6.10.0/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-6.10.0/src/SMTP.php';
require __DIR__ . '/PHPMailer-6.10.0/src/Exception.php';

// --- PHPMailer-based FUNCTION ---
function send_announcement_email($conn, $title, $content, $sender, $status, $published_at) {
    $email_query = "SELECT email FROM users WHERE email IS NOT NULL AND email != ''";
    $email_result = $conn->query($email_query);

    if (!$email_result) return false;

    $recipients = [];
    while ($row = $email_result->fetch_assoc()) {
        $recipients[] = $row['email'];
    }

    if (empty($recipients)) return false;
    
    $mail = new PHPMailer(true);
    $subject_prefix = ($status === 'Scheduled') ? "📅 [Scheduled Notice] " : "📢 [New Announcement] ";
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'majayjaysk@gmail.com'; 
        $mail->Password   = 'szka vaas xzyj rzpz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('majayjaysk@gmail.com', 'SK Council Majayjay');
        foreach ($recipients as $recipient_email) { $mail->addBCC($recipient_email); }
        
        $mail->isHTML(true);
        $mail->Subject = $subject_prefix . $title;
        $mail->Body    = "<html><body style='font-family: Arial, sans-serif; color: #1B1B4B;'>
            <div style='background: #1B1B4B; color: #FFD700; padding: 20px; text-align: center;'><h1>SK Council Official Notice</h1></div>
            <div style='padding: 20px;'>
                <h2 style='color: #1B1B4B;'>{$title}</h2>
                <div style='background: #f8fafc; padding: 15px; border-radius: 8px;'>{$content}</div>
                <p>Best regards, <br>{$sender}</p>
            </div>
        </body></html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Template Logic
$selected_template = isset($_GET['template']) ? htmlspecialchars($_GET['template']) : 'Blank Template';
$title_placeholder = "Enter the Announcement Title";
$content_placeholder = "Write your announcement content here.";

if ($selected_template === 'General Announcement') {
    $title_placeholder = "CIRCULAR NO. [XX-20XX]: Official Notice";
    $content_placeholder = "To: All Registered Youth...\nSubject: Mandatory Attendance...";
} elseif ($selected_template === 'Community Event') {
    $title_placeholder = "YOU'RE INVITED! [Event Name]";
    $content_placeholder = "Hey Youth! We are hosting [Event Name]...";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
    $announcement_content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_SPECIAL_CHARS);
    $template_used = filter_input(INPUT_POST, 'template_used', FILTER_SANITIZE_SPECIAL_CHARS);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
    $schedule_date = filter_input(INPUT_POST, 'schedule_date', FILTER_SANITIZE_SPECIAL_CHARS);
    $schedule_time = filter_input(INPUT_POST, 'schedule_time', FILTER_SANITIZE_SPECIAL_CHARS);
    
    $current_datetime = date('Y-m-d H:i:s');
    $published_at = ($status === 'Published') ? $current_datetime : null;

    if ($status === 'Scheduled' && !empty($schedule_date) && !empty($schedule_time)) {
        $published_at = $schedule_date . ' ' . $schedule_time . ':00';
    }

    $stmt = $conn->prepare("INSERT INTO announcements (user_id, title, content, status, template_used, created_at, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssss", $user_id, $announcement_title, $announcement_content, $status, $template_used, $current_datetime, $published_at);
        if ($stmt->execute()) {
            if ($status === 'Published' || $status === 'Scheduled') {
                send_announcement_email($conn, $announcement_title, $announcement_content, $fullname, $status, $published_at);
            }
            $_SESSION['form_notification'] = ['type' => 'success', 'message' => "Saved as $status!"];
            header("Location: form_management.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Admin - Create Announcement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --navy-primary: #1B1B4B;
            --gold-accent: #FFD700;
            --bg-light: #f1f5f9;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--navy-primary); overflow-x: hidden; }
        
        /* SIDEBAR DESIGN - Matched to Dashboard */
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
        
        /* CARD DESIGN - Matched to Dashboard */
        .card-white { background: white; border-radius: 20px; border: 1px solid #F0F1F7; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        /* NOTIFICATION DESIGN - Matched to Dashboard */
        .badge-count { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--gold-accent); color: var(--navy-primary); font-size: 10px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .tool-badge { background: var(--gold-accent); color: var(--navy-primary); font-size: 9px; padding: 2px 6px; border-radius: 8px; margin-left: auto; font-weight: 700; border: 1px solid rgba(27, 27, 75, 0.1); }
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; width: 320px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; }
        .dropdown-menu.show { display: block; }

        /* Form Inputs Style */
        input, textarea, select { 
            border: 1px solid #E2E8F0 !important; 
            border-radius: 10px !important; 
            padding: 0.75rem 1rem !important; 
            font-size: 0.9rem;
            width: 100%;
        }
        input:focus, textarea:focus { outline: none; border-color: var(--navy-primary) !important; box-shadow: 0 0 0 3px rgba(27, 27, 75, 0.05); }
        
        .btn-primary { 
            background: var(--navy-primary); 
            color: white; 
            padding: 0.75rem 2rem; 
            border-radius: 10px; 
            font-weight: 600; 
            font-size: 0.9rem;
            transition: 0.3s; 
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
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
            <a href="geo_mapping.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Geo Mapping</span></a>
            <a href="manage_users.php" class="nav-item"><i class="fa-solid fa-users-gear"></i><span>Manage Users</span></a>
            <a href="requests.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i><span>Requests</span></a>
            <a href="documents.php" class="nav-item"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="form_management.php" class="nav-item active"><i class="fa-solid fa-file-pen"></i><span>Management</span></a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <a href="login.php" class="nav-item text-red-500 hover:bg-red-50 mb-0"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content flex-grow">
        <header class="flex justify-between items-center mb-6">
            <div>
                <a href="form_management.php" class="text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-[#1B1B4B] transition flex items-center gap-2">
                    <i class="fas fa-chevron-left"></i> Back to Management
                </a>
                <h2 class="text-xl font-bold text-[#1B1B4B] mt-1">Create Announcement</h2>
            </div>

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
                <div class="flex items-center gap-3 border-l pl-6 border-gray-200">
                    <div class="text-right">
                        <p class="text-xs font-bold leading-none"><?= htmlspecialchars($fullname) ?></p>
                        <p class="text-[10px] text-gray-400 mt-1">Administrator</p>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($fullname) ?>&background=1B1B4B&color=FFD700" class="w-8 h-8 rounded-full border border-gray-200">
                </div>
            </div>
        </header>

        <div class="max-w-4xl mx-auto">
            <div class="card-white">
                <form action="create_announcement.php" method="POST" class="space-y-6">
                    <input type="hidden" name="template_used" value="<?= htmlspecialchars($selected_template) ?>">
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Announcement Title</label>
                            <input type="text" name="title" required placeholder="<?= $title_placeholder ?>" class="w-full">
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Announcement Content</label>
                            <textarea name="content" rows="10" required placeholder="<?= $content_placeholder ?>" class="w-full"></textarea>
                        </div>
                    </div>

                    <div id="scheduling-container" class="hidden p-5 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                        <h3 class="text-xs font-bold mb-4 flex items-center text-slate-600">
                            <i class="fas fa-calendar-alt mr-2 text-[#FFD700]"></i> Schedule Publication
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-bold text-gray-400 uppercase mb-1 block">Publish Date</label>
                                <input type="date" id="schedule_date" name="schedule_date">
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-gray-400 uppercase mb-1 block">Publish Time</label>
                                <input type="time" id="schedule_time" name="schedule_time">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 border-t border-gray-100 gap-4">
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Select Status:</span>
                            <select id="status" name="status" class="font-bold text-xs bg-gray-50 cursor-pointer">
                                <option value="Draft">Draft (Internal Only)</option>
                                <option value="Published">Publish Now (Email Users)</option>
                                <option value="Scheduled">Schedule Later</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary w-full md:w-auto justify-center">
                            <i class="fas fa-save"></i> Save Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Notification Logic - Matched to Dashboard
        const notifButton = document.getElementById('notifButton');
        const notifBadge = document.getElementById('notifBadge');
        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('notifDropdown').classList.toggle('show');
            if(notifBadge) {
                notifBadge.remove();
                fetch('mark_notifications_read.php'); 
            }
        });

        window.onclick = () => {
            const dropdown = document.getElementById('notifDropdown');
            if(dropdown) dropdown.classList.remove('show');
        }

        // Form Scheduling Logic
        const statusSelect = document.getElementById('status');
        const schedulingContainer = document.getElementById('scheduling-container');
        statusSelect.addEventListener('change', () => {
            if (statusSelect.value === 'Scheduled') {
                schedulingContainer.classList.remove('hidden');
                document.getElementById('schedule_date').required = true;
                document.getElementById('schedule_time').required = true;
            } else {
                schedulingContainer.classList.add('hidden');
                document.getElementById('schedule_date').required = false;
                document.getElementById('schedule_time').required = false;
            }
        });
    </script>
</body>
</html>