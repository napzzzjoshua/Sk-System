<?php
session_start();
require_once 'db_conn.php';

// Only allow SK Officials access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SK Official', 'SK Chairperson', 'SK Members', 'SK Treasurer', 'SK Secretary'])) {
    header("Location: login.php");
    exit;
}

$fullname = $_SESSION['fullname'];
$success_message = '';
$error_message = '';

// --- Fetch user details: fullname, role, barangay, and EMAIL ---
$sql = "SELECT email, surname, role, barangay, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($user_email, $surname_from_db, $role, $barangay, $profile_photo);
$stmt->fetch();
$stmt->close();

// Extract surname from fullname for submitted_by string
$nameParts = explode(" ", $fullname);
$surname = end($nameParts);

$profilePath = $profile_photo ? "uploads/profiles/" . basename($profile_photo) : "uploads/profiles/default-avatar.png";
$logoPath = "../sk_logo.png";
if (strcasecmp($barangay, 'Suba') === 0) { $logoPath = "../sk_suba.jpg"; }
elseif (strcasecmp($barangay, 'San Isidro') === 0) { $logoPath = "../sk_sanisidro.jpg"; }

$flexible_pattern = '/^[a-zA-Z0-9\s.,\-\/()!?]+$/';
$error_char_message = "Content contains invalid characters. Please use only letters, numbers, spaces, and common punctuation (., - / () ! ?).";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $objectives = $_POST['objectives'] ?? '';
    $expected_outcome = $_POST['expected_outcome'] ?? '';
    $file_path = '';
    
    if (!preg_match($flexible_pattern, $title)) {
        $error_message = "Project Title: " . $error_char_message;
    } elseif (!preg_match($flexible_pattern, $objectives)) {
        $error_message = "Objectives: " . $error_char_message;
    } elseif (!preg_match($flexible_pattern, $expected_outcome)) {
        $error_message = "Expected Outcome: " . $error_char_message;
    } elseif (!preg_match($flexible_pattern, $description)) {
        $error_message = "Detailed Description: " . $error_char_message;
    } elseif (!is_numeric($budget) || $budget < 0) {
        $error_message = "Estimated Budget must be a positive number.";
    }

    if (isset($_FILES['project_document']) && $_FILES['project_document']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['project_document']['tmp_name'];
        $file_name = $_FILES['project_document']['name'];
        $file_size = $_FILES['project_document']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Invalid file type. Only PDF, DOC, DOCX, JPG, JPEG, and PNG are allowed.";
        } elseif ($file_size > 5000000) {
            $error_message = "File is too large. Maximum size is 5MB.";
        } else {
            $new_file_name = uniqid('proposal_', true) . '.' . $file_ext;
            $upload_dir = 'uploads/';
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $destination)) { $file_path = $destination; }
        }
    }

    $submitted_by_string = $surname . " from " . $barangay . ", " . $role;

    if (empty($title) || empty($description) || empty($objectives) || empty($expected_outcome)) {
        $error_message = "All fields are required.";
    } elseif ($error_message == '') {
        $stmt_submission = $conn->prepare("INSERT INTO submissions (title, description, budget, objectives, expected_outcome, status, submitted_by, document_path, barangay) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
        $stmt_submission->bind_param("ssdsssss", $title, $description, $budget, $objectives, $expected_outcome, $submitted_by_string, $file_path, $barangay);
        
        if ($stmt_submission->execute()) {
            $notification_message = "New Project Proposal titled: '{$title}' has been submitted by {$fullname} ({$role}) from {$barangay}.";
            $related_link = "document_submissions.php";
            $stmt_notification = $conn->prepare("INSERT INTO sk_notifications (email, barangay, position, message, related_link) VALUES (?, ?, ?, ?, ?)");
            $stmt_notification->bind_param("sssss", $user_email, $barangay, $role, $notification_message, $related_link);
            $stmt_notification->execute();
            $stmt_notification->close();
            $success_message = "Your project proposal has been submitted successfully.";
        } else {
            $error_message = "Error submitting proposal: " . $stmt_submission->error;
        }
        $stmt_submission->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Proposal | SK Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; margin: 0; overflow: hidden; font-size: 13px; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 230px; background: #1B1B4B; padding: 20px 15px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .main-content { flex: 1; padding: 25px 30px; background: #f8fafc; overflow-y: auto; }
        .nav-link { display: flex; align-items: center; padding: 10px 14px; border-radius: 12px; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; }
        .nav-link i { font-size: 14px; width: 24px; }
        .nav-link.active { background: #FFD700; color: #1B1B4B; box-shadow: 0 4px 12px rgba(255,215,0,0.2); }
        .nav-link:hover:not(.active) { background: rgba(255,255,255,0.1); color: #FFFFFF; }
        .user-profile-box { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 12px; margin-top: auto; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); }
        .form-card { background: white; border-radius: 24px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; }
        input, textarea { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; font-size: 13px; width: 100%; transition: all 0.2s; }
        input:focus, textarea:focus { outline: none; border-color: #1B1B4B; background: white; box-shadow: 0 0 0 4px rgba(27, 27, 75, 0.05); }
        label { font-weight: 700; color: #1B1B4B; font-size: 11px; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-primary { background: #1B1B4B; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 700; transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:hover { background: #2a2a6b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27, 27, 75, 0.15); }
        .char-hint { font-size: 10px; color: #94a3b8; margin-top: 4px; display: block; text-align: right; }
        
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
            <a href="document_submissions_sec.php" class="nav-link"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="submit_proposal_sec.php" class="nav-link active"><i class="fas fa-paper-plane"></i> Proposals</a>
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
                <a href="settings.php" class="text-[10px] text-[#FFD700] font-bold uppercase hover:opacity-80 transition flex items-center gap-1">
                    <i class="fas fa-cog text-[9px]"></i> Settings
                </a>
                <span class="text-white/20">|</span>
                <a href="login.php" class="text-[10px] text-[#ea580c] font-bold uppercase hover:opacity-80 transition">Sign Out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-end mb-8">
            <div>
                <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest bg-blue-50 px-2 py-1 rounded-md">Official Submission</span>
                <h1 class="font-extrabold text-[#1B1B4B] tracking-tight text-2xl mt-1">Submit Project Proposal</h1>
                <p id="current-time" class="text-xs text-slate-400 font-medium"></p>
            </div>
            <a href="submit_proposal_sec.php" class="bg-white px-5 py-2.5 rounded-xl text-[11px] font-bold text-[#1B1B4B] shadow-sm border border-slate-200 hover:border-slate-300 transition flex items-center gap-2">
                <i class="fas fa-arrow-left text-[10px]"></i> Back to Proposals
            </a>
        </header>

        <?php if ($success_message): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-white text-xs"></i>
                    </div>
                    <div>
                        <p class="text-emerald-900 font-bold text-sm">Success!</p>
                        <p class="text-emerald-700 text-xs font-medium"><?= $success_message ?></p>
                    </div>
                </div>
                <a href="document_submissions.php" class="text-xs font-bold text-emerald-700 underline px-4">View Records</a>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3">
                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-times text-white text-xs"></i>
                </div>
                <div>
                    <p class="text-red-900 font-bold text-sm">Submission Error</p>
                    <p class="text-red-700 text-xs font-medium"><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-card max-w-4xl mx-auto">
            <form method="post" enctype="multipart/form-data" class="space-y-8" id="proposalForm">
                <div class="section-group">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[2px] mb-6 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title">Project Title</label>
                            <input type="text" name="title" id="title" required placeholder="e.g. Linggo ng Kabataan 2024" pattern="[a-zA-Z0-9\s.,\-\/()!?]+" oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s.,\-\/()!?]/g, '')">
                            <span class="char-hint">Clear and concise title</span>
                        </div>
                        <div>
                            <label for="budget">Estimated Budget (PHP)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#1B1B4B] font-bold">₱</span>
                                <input type="number" name="budget" id="budget" required class="pl-8" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
                            </div>
                            <span class="char-hint">Total project allocation</span>
                        </div>
                    </div>
                </div>

                <div class="section-group">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[2px] mb-6 flex items-center gap-2">
                        <i class="fas fa-align-left"></i> Project Scope
                    </h3>
                    <div class="space-y-6">
                        <div>
                            <label for="objectives">Objectives</label>
                            <textarea name="objectives" id="objectives" rows="3" required placeholder="Outline the primary goals..." oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s.,\-\/()!?\n]/g, '')"></textarea>
                            <span class="char-hint">Define what this project aims to solve</span>
                        </div>

                        <div>
                            <label for="expected_outcome">Expected Outcome</label>
                            <textarea name="expected_outcome" id="expected_outcome" rows="3" required placeholder="Specify the metrics of success..." oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s.,\-\/()!?\n]/g, '')"></textarea>
                            <span class="char-hint">Measurable results after completion</span>
                        </div>

                        <div>
                            <label for="description">Detailed Description</label>
                            <textarea name="description" id="description" rows="5" required placeholder="Provide a full breakdown of the project activities..." oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s.,\-\/()!?\n]/g, '')"></textarea>
                        </div>
                    </div>
                </div>

                <div class="section-group">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[2px] mb-6 flex items-center gap-2">
                        <i class="fas fa-file-pdf"></i> Attachments
                    </h3>
                    <div>
                        <label for="project_document">Supporting Documents</label>
                        <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 bg-slate-50 hover:bg-white hover:border-[#1B1B4B]/30 transition-all relative group cursor-pointer text-center">
                            <input type="file" name="project_document" id="project_document" class="opacity-0 absolute inset-0 cursor-pointer">
                            <div class="space-y-2">
                                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto shadow-sm group-hover:scale-110 transition-transform">
                                    <i class="fas fa-upload text-[#1B1B4B]"></i>
                                </div>
                                <p class="text-[12px] text-[#1B1B4B] font-bold" id="file-label-text">Click to upload or drag and drop</p>
                                <p class="text-[10px] text-slate-400">PDF, DOC, JPG, or PNG (Max. 5MB)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-[10px] text-slate-400 max-w-[250px]">
                        By submitting, you confirm that these details are accurate and adhere to the SK budget guidelines.
                    </p>
                    <button type="submit" id="submitBtn" class="btn-primary w-full md:w-auto px-12">
                        Submit Proposal <i class="fas fa-paper-plane ml-2"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <footer class="mt-12 pb-8 text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; 2024 Sangguniang Kabataan - Barangay <?= htmlspecialchars($barangay) ?></p>
        </footer>
    </main>
</div>

<script>
    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    setInterval(updateTime, 1000); updateTime();

    // File name display helper and styling
    document.getElementById('project_document').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : 'Click to upload or drag and drop';
        const label = document.getElementById('file-label-text');
        label.textContent = fileName;
        if(e.target.files[0]) {
            label.parentElement.parentElement.classList.add('border-emerald-400', 'bg-emerald-50');
            label.classList.replace('text-[#1B1B4B]', 'text-emerald-600');
        }
    });

    // Prevent Double Submit and show loading
    document.getElementById('proposalForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Submitting...';
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';
    });
</script>
</body>
</html>