<?php
session_start();

$message = "";
$email = $_SESSION['otp_email'] ?? '';
$message_type = ""; // To determine success or error modal

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = $_POST['otp'] ?? '';
    
    // Check if the OTP and email are still valid in the session
    if (isset($_SESSION['otp']) && isset($_SESSION['otp_expiry']) && time() < $_SESSION['otp_expiry']) {
        // Validate the entered OTP
        if ($user_otp == $_SESSION['otp']) {
            // OTP is correct, clear the OTP session data and redirect to the password reset page
            unset($_SESSION['otp']);
            unset($_SESSION['otp_expiry']);
            header("Location: reset_password.php");
            exit();
            
        } else {
            $message = "Invalid OTP. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = "Your OTP has expired. Please request a new one.";
        $message_type = "error";
        // Clear expired session data
        unset($_SESSION['otp']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_expiry']);
    }
} else if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $email_display = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : 'your email';
    $message = "An OTP has been sent to " . $email_display;
    $message_type = "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SK System - Verify OTP</title>
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
    <h2 class="text-3xl font-bold text-gray-800">Verify OTP</h2>
  </div>

  <form method="post" class="space-y-6">
    <div class="relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
          <i class="fas fa-key"></i>
      </span>
      <input type="text" name="otp" placeholder="Enter your 6-digit OTP" required
             class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
             pattern="\d{6}" title="Please enter a 6-digit number"
      >
    </div>
    <button type="submit"
            class="w-full bg-blue-500 text-white font-semibold py-3 rounded-xl shadow-lg hover:bg-blue-600 transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    >
      <i class="fas fa-check-circle mr-2"></i>Verify OTP
    </button>
  </form>
  
  <div class="text-center mt-6 text-sm">
      <p class="text-gray-500" id="countdown-text">
          Resend OTP in <span id="countdown">60</span>s
      </p>
      <a href="forgot_password.php" id="resend-link"
         class="text-blue-600 hover:text-blue-700 font-medium transition duration-200 mt-2 block disabled-link"
         style="pointer-events: none; opacity: 0.5;"
         >Request again
      </a>
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
    
    autoHideTimeout = setTimeout(() => {
        modal.classList.remove('active');
        setTimeout(() => { modalContainer.classList.add('hidden'); }, 500);
    }, 4000);
}

// Countdown timer functionality
const countdownElement = document.getElementById('countdown');
const resendLink = document.getElementById('resend-link');
const countdownText = document.getElementById('countdown-text');
let countdown = 60;

function startCountdown() {
    countdownElement.textContent = countdown;
    const timer = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        if (countdown <= 0) {
            clearInterval(timer);
            countdownText.textContent = "You can request a new OTP now.";
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
        }
    }, 1000);
}

// Show the modal and start the countdown on page load
window.onload = function() {
    if (message) {
        showModal(message, messageType);
    }
    startCountdown();
};
</script>

</body>
</html>