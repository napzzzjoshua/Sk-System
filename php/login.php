<?php
// login.php logic fully preserved
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$conn = new mysqli("localhost", "root", "", "sk_system");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$error = ""; $success = ""; $dashboard_file = "";
$remembered_email = $_COOKIE['saved_email'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = $_POST['remember_me'] ?? '';

    if (!empty($email) && $remember_me === 'on') {
        setcookie('saved_email', $email, time() + (86400 * 30), "/");
    } else {
        setcookie('saved_email', '', time() - 3600, "/");
    }

    $admin_email = 'superadmin@gmail.com';
    $admin_password = 'admin';
    
    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['user_id'] = 1; $_SESSION['fullname'] = 'Admin User'; $_SESSION['role'] = 'Admin';
        $success = "Admin login successful. Redirecting...";
        $dashboard_file = 'admin_dashboard.php';
    } else {
        $email = $conn->real_escape_string($email);
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['status'] == 'pending') { $error = "Your account is pending approval."; }
                else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['fullname'] = $user['firstname'] . ' ' . $user['surname'];
                    $_SESSION['role'] = $user['role'];
                    $success = "Login successful. Redirecting...";
                    switch ($user['role']) {
                        case 'Admin': $dashboard_file = 'admin_dashboard.php'; break;
                        case 'SK Official': $dashboard_file = 'sk_dashboard.php'; break;
                        case 'Barangay Official': $dashboard_file = 'barangay_dashboard.php'; break;
                        case 'Resident': $dashboard_file = 'beneficiary_dashboard.php'; break;
                        default: $dashboard_file = 'index.php'; $error = "Invalid role."; $success = ""; break;
                    }
                }
            } else { $error = "Invalid email or password."; }
        } else { $error = "Invalid email or password."; }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
                /* Responsive Design Additions */
                @media (max-width: 1024px) {
                    .container-box { flex-direction: column !important; min-height: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\], .w-full.md\:w-\[55\%\] { width: 100% !important; min-width: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\] { padding: 32px 12px !important; }
                    .w-full.md\:w-\[55\%\] { padding: 32px 12px !important; }
                }
                @media (max-width: 768px) {
                    .container-box { flex-direction: column !important; min-height: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\], .w-full.md\:w-\[55\%\] { width: 100% !important; min-width: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\] { padding: 24px 8px !important; }
                    .w-full.md\:w-\[55\%\] { padding: 24px 8px !important; }
                }
                @media (max-width: 600px) {
                    .container-box { flex-direction: column !important; min-height: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\], .w-full.md\:w-\[55\%\] { width: 100% !important; min-width: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\] { padding: 16px 4px !important; }
                    .w-full.md\:w-\[55\%\] { padding: 16px 4px !important; }
                }
                @media (max-width: 480px) {
                    .container-box { flex-direction: column !important; min-height: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\], .w-full.md\:w-\[55\%\] { width: 100% !important; min-width: 0 !important; }
                    .gradient-side, .w-full.md\:w-\[45\%\] { padding: 8px 2px !important; }
                    .w-full.md\:w-\[55\%\] { padding: 8px 2px !important; }
                }
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .gradient-side { background: linear-gradient(135deg, #1B1B4B 0%, #2D2D7A 100%); position: relative; overflow: hidden; }
        
        /* Updated design shapes to #FFD700 */
        .shape { position: absolute; background: #FFD700; border-radius: 50px; transform: rotate(-45deg); opacity: 0.1; }
        .shape-1 { width: 250px; height: 50px; bottom: -20px; left: -30px; }
        .shape-2 { width: 180px; height: 40px; top: 10%; right: -40px; }
        
        .form-input:focus { box-shadow: 0 0 0 3px rgba(27, 27, 75, 0.1); border-color: #1B1B4B; }
        .container-box { transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1); }

        .modal-container { position: fixed; top: 0; left: 0; right: 0; z-index: 50; display: flex; justify-content: center; padding-top: 1rem; }
        .modal { transform: translateY(-100%); transition: transform 0.4s ease-in-out; }
        .modal.active { transform: translateY(0); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div id="notificationModal" class="modal-container hidden">
        <div class="modal bg-white rounded-xl shadow-2xl max-w-xs w-full p-4 flex items-center space-x-3 border-l-4 border-orange-500">
            <div id="modalIcon" class="text-xl"></div>
            <div>
                <h3 id="modalTitle" class="text-sm font-bold text-gray-800"></h3>
                <p id="modalMessage" class="text-xs text-gray-600"></p>
            </div>
        </div>
    </div>

    <div id="mainContainer" class="container-box flex flex-col md:flex-row w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden min-h-[480px] border border-gray-100">
        
        <div class="gradient-side w-full md:w-[45%] p-8 flex flex-col items-center justify-center text-center text-white relative">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            
            <div class="relative z-10">
                <div class="bg-white p-2 w-fit rounded-full shadow-2xl mb-6 mx-auto">
                    <img src="../majayjay_logo.jpg" alt="Logo" class="h-20 w-20 rounded-full">
                </div>
                <h1 class="text-2xl font-bold tracking-tight">MAJAYJAY <span style="color: #FFD700;">SK</span></h1>
                <p class="text-blue-100/60 text-[10px] uppercase tracking-[0.3em] mt-2 font-semibold">Official Management System</p>
                
                <div class="w-10 h-1 mx-auto mt-6 rounded-full opacity-60" style="background-color: #FFD700;"></div>
            </div>

            <div class="absolute bottom-6 left-0 right-0 text-center">
                <p class="text-[9px] text-white/30 font-medium tracking-widest uppercase italic">© 2026 Majayjay SK</p>
            </div>
        </div>

        <div class="w-full md:w-[55%] p-8 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-800">Welcome Back</h3>
                <p class="text-gray-400 text-xs">Sign in to your account to continue</p>
            </div>

            <form action="login.php" method="post" class="space-y-4">
                <div class="relative group">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-[#FFD700] transition-colors"></i>
                    <input type="email" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($remembered_email) ?>"
                           class="form-input w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white outline-none transition-all text-sm">
                </div>

                <div class="relative group">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-[#FFD700] transition-colors"></i>
                    <input type="password" id="passwordInput" name="password" placeholder="Password" required
                           class="form-input w-full pl-11 pr-11 py-2.5 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white outline-none transition-all text-sm">
                    <button type="button" id="togglePassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye text-xs" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="flex items-center justify-between px-1">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="remember_me" class="w-3.5 h-3.5 rounded border-gray-300 text-orange-500 focus:ring-0" <?= !empty($remembered_email) ? 'checked' : '' ?>>
                        <span class="ml-2 text-[11px] text-gray-500">Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="text-[11px] text-orange-600 hover:underline font-semibold">Forgot Password?</a>
                </div>

                <button type="submit" class="w-full bg-[#1B1B4B] text-white font-bold py-3.5 rounded-xl shadow-lg hover:bg-[#FFD700] hover:text-[#1B1B4B] transition-all duration-300 text-xs uppercase tracking-widest mt-2">
                    Login Portal
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-gray-400 text-[11px]">No account? 
                    <a href="javascript:void(0)" onclick="switchPage('register.php')" class="text-[#1B1B4B] font-bold hover:underline ml-1">Register Now</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('toggleIcon');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });

        const modalContainer = document.getElementById('notificationModal');
        const modal = modalContainer.querySelector('.modal');
        const modalIconContainer = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');

        function showModal(message, type) {
            modalMessage.textContent = message;
            if (type === 'success') {
                modalIconContainer.innerHTML = `<i class="fas fa-check-circle text-green-500"></i>`;
                modalTitle.textContent = "Success";
            } else {
                modalIconContainer.innerHTML = `<i class="fas fa-exclamation-triangle text-red-500"></i>`;
                modalTitle.textContent = "Error";
            }
            modalContainer.classList.remove('hidden');
            setTimeout(() => modal.classList.add('active'), 10);
            setTimeout(() => {
                modal.classList.remove('active');
                setTimeout(() => modalContainer.classList.add('hidden'), 500);
            }, 3000);
        }

        const error = "<?= !empty($error) ? addslashes($error) : ''; ?>";
        const success = "<?= !empty($success) ? addslashes($success) : ''; ?>";
        const url = "<?= !empty($dashboard_file) ? $dashboard_file : ''; ?>";

        if (error) showModal(error, 'error');
        if (success) {
            showModal(success, 'success');
            setTimeout(() => { window.location.href = url; }, 2000);
        }
    });

    function switchPage(url) {
        document.getElementById('mainContainer').style.opacity = '0';
        document.getElementById('mainContainer').style.transform = 'scale(0.98)';
        setTimeout(() => { window.location.href = url; }, 400);
    }
    </script>
</body>
</html>