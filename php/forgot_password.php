<?php
session_start();
$message = "";
$message_type = "";

// Load PHPMailer manually
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer-6.10.0/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-6.10.0/src/SMTP.php';
require __DIR__ . '/PHPMailer-6.10.0/src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        $message = "Email is required.";
        $message_type = "error";
    } else {
        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP in session (valid for 5 mins)
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes

        // Send OTP via Gmail (PHPMailer)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'majayjaysk@gmail.com'; // your Gmail
            $mail->Password   = 'szka vaas xzyj rzpz';   // Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('majayjaysk@gmail.com', 'Majayjay System');
            $mail->addAddress($email);

            $emailBody = '
            <div style="font-family: \'Poppins\', sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; width: 100%; text-align: center;">
                <table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                    <tr>
                        <td style="padding: 0 40px 20px;">
                            <h1 style="color: #1a202c; font-size: 28px; font-weight: 600; margin: 0 0 10px;">Your One-Time Password</h1>
                            <p style="color: #4a5568; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                Hi there, <br>
                                You\'ve requested a password reset. Please use the following code to verify your identity.
                            </p>
                            <div style="background-color: #edf2f7; border-radius: 8px; padding: 25px; display: inline-block; margin-bottom: 25px;">
                                <span style="font-size: 36px; font-weight: 700; color: #2d3748; letter-spacing: 5px;">
                                    ' . $otp . '
                                </span>
                            </div>
                            <p style="color: #718096; font-size: 14px; margin: 0;">
                                This code is valid for 5 minutes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 40px 40px; text-align: center;">
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                            <p style="color: #a0aec0; font-size: 12px; margin: 0;">
                                If you did not request this, please ignore this email. Do not share this code with anyone.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>';

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body    = $emailBody;

            $mail->send();
            
            // Redirect to the OTP verification page with a success message
            header("Location: otp_verification.php?status=success&email=" . urlencode($email));
            exit();

        } catch (Exception $e) {
            $message = "Error sending OTP. Mailer Error: {$mail->ErrorInfo}";
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
<title>SK System - Forgot Password</title>
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
    <h2 class="text-3xl font-bold text-gray-800">Forgot Password</h2>
  </div>

  <form method="post" class="space-y-6">
    <div class="relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
          <i class="fas fa-envelope"></i>
      </span>
      <input type="email" name="email" placeholder="Enter your email" required
             class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 bg-gray-50 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
      >
    </div>
    <button type="submit"
            class="w-full bg-blue-500 text-white font-semibold py-3 rounded-xl shadow-lg hover:bg-blue-600 transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    >
      <i class="fas fa-paper-plane mr-2"></i>Send OTP
    </button>
  </form>
  
  <div class="text-center mt-6 text-sm">
      <p>
          <span class="text-gray-500">Remember your password?</span>
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
    
    autoHideTimeout = setTimeout(() => {
        modal.classList.remove('active');
        setTimeout(() => { modalContainer.classList.add('hidden'); }, 500);
    }, 4000);
}

if (message) {
    showModal(message, messageType);
}
</script>

</body>
</html>