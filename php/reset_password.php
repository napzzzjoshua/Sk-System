<?php
session_start();

$message = "";
$message_type = "";

// Check if the user's email is set in the session.
// This ensures they have gone through the OTP verification process.
if (!isset($_SESSION['otp_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email_to_update = $_SESSION['otp_email'];

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    } elseif (!preg_match("#[0-9]+#", $new_password)) {
        $message = "Password must include at least one number.";
        $message_type = "error";
    } elseif (!preg_match("#[a-z]+#", $new_password)) {
        $message = "Password must include at least one lowercase letter.";
        $message_type = "error";
    } elseif (!preg_match("#[A-Z]+#", $new_password)) {
        $message = "Password must include at least one uppercase letter.";
        $message_type = "error";
    } elseif (!preg_match("#[\W]+#", $new_password)) {
        $message = "Password must include at least one symbol.";
        $message_type = "error";
    } else {
        // Here's the function to update the password in the database
        function updatePassword($email, $password) {
            // Database connection details
            $servername = "localhost";
            $username = "root"; // Your database username
            $db_password = ""; // Your database password
            $dbname = "sk_system"; // Your database name

            // Create connection
            $conn = new mysqli($servername, $username, $db_password, $dbname);

            // Check connection
            if ($conn->connect_error) {
                return "Connection failed: " . $conn->connect_error;
            }

            // Hash the new password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Prepare the SQL statement to prevent SQL injection
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            if (!$stmt) {
                $conn->close();
                return "Error preparing statement: " . $conn->error;
            }
            
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                return "success";
            } else {
                $error = $stmt->error;
                $stmt->close();
                $conn->close();
                return "Error updating password: " . $error;
            }
        }

        // Call the function
        $result = updatePassword($email_to_update, $new_password);
        
        if ($result === "success") {
            // After a successful password update, clear the email from the session and set success message
            unset($_SESSION['otp_email']);
            $message = "Password has been successfully reset! Redirecting to login...";
            $message_type = "success";
        } else {
            $message = $result; // Display the error message from the function
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SK System - Reset Password</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
    .modal-container { position: fixed; top: 0; left: 0; right: 0; z-index: 50; display: flex; justify-content: center; padding-top: 1rem; }
    .modal { transform: translateY(-100%); transition: transform 0.5s ease-in-out; }
    .modal.active { transform: translateY(0); }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="bg-white shadow-xl rounded-2xl p-8 max-w-sm w-full mx-auto text-gray-800">
  <div class="flex flex-col items-center justify-center mb-6">
    <img src="../majayjay_logo.jpg" alt="SK Logo" class="h-24 w-24 mb-4 rounded-full border-2 border-gray-300">
    <h2 class="text-3xl font-bold text-gray-800">Reset Password</h2>
  </div>

  <form method="post" class="space-y-6">
    <div class="relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
          <i class="fas fa-lock"></i>
      </span>
      <input type="password" name="new_password" id="new_password" placeholder="New Password" required
             class="w-full pl-10 pr-10 py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
      >
      <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400" onclick="togglePasswordVisibility('new_password')">
          <i class="fas fa-eye" id="toggle_new_password"></i>
      </span>
    </div>
    <div class="relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
          <i class="fas fa-lock-open"></i>
      </span>
      <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required
             class="w-full pl-10 pr-10 py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
      >
      <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400" onclick="togglePasswordVisibility('confirm_password')">
          <i class="fas fa-eye" id="toggle_confirm_password"></i>
      </span>
    </div>
    <button type="submit"
            class="w-full bg-blue-500 text-white font-semibold py-3 rounded-xl shadow-lg hover:bg-blue-600 transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    >
      <i class="fas fa-sync-alt mr-2"></i>Reset Password
    </button>
  </form>
  
  <div class="text-center mt-6 text-sm">
      <p>
          <a href="login.php" class="text-blue-600 hover:text-blue-700 font-medium transition duration-200">Back to Login</a>
      </p>
  </div>
</div>

<div id="notificationModal" class="modal-container hidden">
    <div class="modal bg-white rounded-lg shadow-xl max-w-xs w-full p-4 flex items-center space-x-4 text-gray-800">
        <div id="modalIcon" class="flex-shrink-0 text-2xl"></div>
        <div>
            <h3 id="modalTitle" class="text-md font-semibold"></h3>
            <p id="modalMessage" class="text-sm"></p>
        </div>
    </div>
</div>

<script>
const message = "<?php echo htmlspecialchars($message); ?>";
const messageType = "<?php echo $message_type; ?>";
const modalContainer = document.getElementById('notificationModal');
const modal = modalContainer.querySelector('.modal');
const modalIconContainer = document.getElementById('modalIcon');
const modalTitle = document.getElementById('modalTitle');
const modalMessage = document.getElementById('modalMessage');
let autoHideTimeout;

function showModal(msg, type) {
    clearTimeout(autoHideTimeout);
    modalMessage.innerHTML = msg;

    if (type === 'success') {
        modalIconContainer.innerHTML = `<i class="fas fa-check-circle text-green-500"></i>`;
        modalTitle.textContent = "Success";
    } else if (type === 'error') {
        modalIconContainer.innerHTML = `<i class="fas fa-exclamation-triangle text-red-600"></i>`;
        modalTitle.textContent = "Error";
    }

    modalContainer.classList.remove('hidden');
    setTimeout(() => { modal.classList.add('active'); }, 10);
    
    // Auto-hide the modal after 4 seconds
    autoHideTimeout = setTimeout(() => {
        modal.classList.remove('active');
        setTimeout(() => { modalContainer.classList.add('hidden'); }, 500);
    }, 4000);
}

// Redirect to login.php after a successful password reset
function redirectToLogin() {
    window.location.href = 'login.php';
}

// Show/hide password functionality
function togglePasswordVisibility(id) {
    const passwordInput = document.getElementById(id);
    const toggleIcon = document.getElementById('toggle_' + id);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Show the modal on page load if a message is set
window.onload = function() {
    if (message) {
        showModal(message, messageType);
        if (messageType === 'success') {
            // Wait for 2 seconds before redirecting to allow the user to see the success message
            setTimeout(redirectToLogin, 2000);
        }
    }
};
</script>

</body>
</html>