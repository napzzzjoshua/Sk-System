<?php
// register.php - SK System Registration (Full Database Functionality Restored)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// 1. Database Connection
$conn = new mysqli("localhost", "root", "", "sk_system");
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

function sanitize($v) { return trim(htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8')); }

$error_message = ''; $success_message = '';

// 2. Data Submission Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate required fields (Middle Name excluded)
    $required_fields = [
        'surname' => 'Surname', 'firstname' => 'First Name', 'mobile' => 'Mobile Number', 
        'gender' => 'Gender', 'civil_status' => 'Civil Status', 'dob' => 'Date of Birth', 
        'email' => 'Email Address', 'password' => 'Password', 'position' => 'Position', 
        'district' => 'District', 'municipality' => 'Municipality', 'province' => 'Province',
        'barangay' => 'Barangay', 'term_start' => 'Term Start Date'
    ];

    foreach ($required_fields as $key => $label) {
        if (empty($_POST[$key])) { 
            $error_message = "Please provide your $label."; 
            break; 
        }
    }

    // File Upload Check
    if (!$error_message && (empty($_FILES['profile_photo']['name']) || empty($_FILES['id_document']['name']))) {
        $error_message = "Both Profile Photo and Valid ID are required.";
    }

    if (!$error_message) {
        // Sanitize inputs
        $surname      = sanitize($_POST['surname']);
        $firstname    = sanitize($_POST['firstname']);
        $middlename   = sanitize($_POST['middlename']); 
        $mobile       = sanitize($_POST['mobile']);
        $gender       = sanitize($_POST['gender']);
        $civil_status = sanitize($_POST['civil_status']);
        $dob          = sanitize($_POST['dob']);
        $email        = sanitize($_POST['email']);
        $password     = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role         = "SK Official";
        $position     = sanitize($_POST['position']);
        $term_start   = sanitize($_POST['term_start']);
        $district     = sanitize($_POST['district']);
        $province     = sanitize($_POST['province']);
        $municipality = sanitize($_POST['municipality']);
        $barangay     = sanitize($_POST['barangay']);

        // Generate complete address string
        $complete_address = "$barangay, $municipality, $province";

        // Handle File Uploads (use correct path relative to project root)
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $profile_photo = time() . "_profile_" . basename($_FILES['profile_photo']['name']);
            $profile_photo_path = $upload_dir . $profile_photo;
            $profile_upload_ok = move_uploaded_file($_FILES['profile_photo']['tmp_name'], $profile_photo_path);

            $id_doc = time() . "_id_" . basename($_FILES['id_document']['name']);
            $id_doc_path = $upload_dir . $id_doc;
            $id_upload_ok = move_uploaded_file($_FILES['id_document']['tmp_name'], $id_doc_path);

            // Store file path relative to project root for DB
            $profile_photo_db = 'uploads/' . $profile_photo;
            $id_doc_db = 'uploads/' . $id_doc;

        // Debugging output for file upload
        if (!$profile_upload_ok || !$id_upload_ok) {
            $error_message = "File upload failed. " .
                (!$profile_upload_ok ? 'Profile photo failed. ' : '') .
                (!$id_upload_ok ? 'ID document failed. ' : '') .
                "Check that the uploads folder exists and is writable.";
        } else {
            // 3. Database Insertion
            $sql = "INSERT INTO users (role, surname, firstname, middlename, email, mobile, password, profile_photo, id_document, barangay, gender, dob, civil_status, position, term_start, district, municipality, province, complete_address, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("sssssssssssssssssss", 
                    $role, $surname, $firstname, $middlename, $email, $mobile, $password, 
                        $profile_photo_db, $id_doc_db, $barangay, $gender, $dob, $civil_status, 
                    $position, $term_start, $district, $municipality, $province, $complete_address
                );

                if ($stmt->execute()) { 
                    $success_message = "Registration submitted successfully! Please wait for admin approval."; 
                } else { 
                    $error_message = "Database Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$barangays = ['Amonoy','Bakia','Balanac','Balayong','Banilad','Banti','Bitaoy','Botocan','Bukal','Burgos','Burol','Coralao','Gagalot','Ibabang Banga','Ibabang Bayucain','Ilayang Banga','Ilayang Bayucain','Isabang','Malinao','May-It','Munting Kawayan','Olla','Oobi','Origuel','Piit','Poblacion','Pook','Rizal','San Francisco','San Isidro','San Juan','San Roque','Santa Catalina','Suba','Talortor','Taytay','Tibalon','Tipan','Villa Amonoy','Villa Nogales'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .gradient-side { background: linear-gradient(135deg, #1B1B4B 0%, #2D2D7A 100%); position: relative; overflow: hidden; }
        .shape { position: absolute; background: #FFD700; border-radius: 50px; transform: rotate(-45deg); opacity: 0.1; }
        .shape-1 { width: 250px; height: 50px; bottom: -20px; left: -30px; }
        .shape-2 { width: 180px; height: 40px; top: 10%; right: -40px; }
        
        .phase-content { display: none; }
        .phase-content.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .form-input { border: 1px solid #e2e8f0; background: #f8fafc80; transition: all 0.2s; }
        .form-input:focus { border-color: #1B1B4B; outline: none; background: #fff; box-shadow: 0 0 0 3px rgba(27, 27, 75, 0.05); }
        .input-error { border: 2px solid #ef4444 !important; background-color: #fef2f2 !important; }

        #autoModal {
            position: fixed; top: 0; left: 50%; transform: translate(-50%, -150%);
            z-index: 100; transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        #autoModal.slide-down { transform: translate(-50%, 40px); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div id="autoModal" class="w-[90%] max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
        <div class="p-5 flex items-center gap-4">
            <div id="modalIcon" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"></div>
            <div class="flex-1">
                <h3 id="modalTitle" class="text-sm font-bold text-gray-800">Registration</h3>
                <p id="modalMessage" class="text-xs text-gray-500 leading-relaxed"></p>
            </div>
        </div>
        <div id="modalBar" class="h-1.5 w-full"></div>
    </div>

    <div class="flex flex-col md:flex-row w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden min-h-[550px] border border-gray-100">
        
        <div class="gradient-side w-full md:w-[45%] p-8 flex flex-col items-center justify-center text-center text-white">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="relative z-10">
                <div class="bg-white p-2 w-fit rounded-full shadow-2xl mb-6 mx-auto">
                    <img src="../majayjay_logo.jpg" alt="Logo" class="h-16 w-16 rounded-full">
                </div>
                <h1 class="text-2xl font-bold tracking-tight mb-1 ">MAJAYJAY <span style="color: #FFD700;">SK</span></h1>
                <p class="text-[9px] uppercase tracking-[0.3em] opacity-80">Portal Registration</p>
                <div class="w-10 h-1 mx-auto mt-6 rounded-full opacity-60" style="background-color: #FFD700;"></div>
            </div>
        </div>

        <div class="w-full md:w-[55%] p-8 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800">Create Account</h3>
                <p class="text-gray-400 text-xs mb-4">Fill in your details to register</p>
                
                <div class="flex justify-between items-end mb-1">
                    <h3 id="phaseLabel" class="text-[10px] font-bold text-blue-900 uppercase tracking-widest">Personal Info</h3>
                    <span id="stepCount" class="text-[9px] font-bold text-gray-400">Step 1/3</span>
                </div>
                <div class="w-full bg-gray-100 h-1 rounded-full overflow-hidden">
                    <div id="progressBar" class="bg-[#1B1B4B] h-full transition-all duration-500" style="width: 33%"></div>
                </div>
            </div>

            <form id="regForm" action="register.php" method="post" enctype="multipart/form-data" class="space-y-3">
                <div id="phase1" class="phase-content active space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="surname" placeholder="Surname" data-label="Surname" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                        <input type="text" name="firstname" placeholder="First Name" data-label="First Name" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                    </div>
                    <input type="text" name="middlename" placeholder="Middle Name (Optional)" data-label="Middle Name" class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                    <input type="tel" name="mobile" placeholder="Mobile Number" data-label="Mobile" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <select name="gender" data-label="Gender" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                            <option value="">Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <select name="civil_status" data-label="Status" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                            <option value="">Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                    <input type="date" name="dob" data-label="Birth Date" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                    <button type="button" onclick="validatePhase(1, 2)" class="w-full bg-[#1B1B4B] text-white font-bold py-3 rounded-xl shadow-lg hover:bg-[#FFD700] hover:text-[#1B1B4B] transition-all text-xs uppercase tracking-widest mt-2">Continue</button>
                </div>

                <div id="phase2" class="phase-content space-y-3">
                    <input type="email" name="email" placeholder="Email Address" data-label="Email" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                    <div class="relative group">
                        <input type="password" id="passwordInput" name="password" placeholder="Password" data-label="Password" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm">
                        <button type="button" id="togglePassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-eye text-xs" id="toggleIcon"></i></button>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-400 uppercase ml-1">Profile Photo</label>
                            <input type="file" name="profile_photo" data-label="Photo" required class="form-input w-full px-3 py-1.5 rounded-xl text-[10px]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-400 uppercase ml-1">Valid ID Document</label>
                            <input type="file" name="id_document" data-label="ID" required class="form-input w-full px-3 py-1.5 rounded-xl text-[10px]">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="setPhase(1)" class="w-1/3 border py-3 rounded-xl text-[10px] uppercase font-bold text-gray-400">Back</button>
                        <button type="button" onclick="validatePhase(2, 3)" class="w-2/3 bg-[#1B1B4B] text-white py-3 rounded-xl shadow-lg text-xs font-bold uppercase">Next Step</button>
                    </div>
                </div>

                <div id="phase3" class="phase-content space-y-3">
                    <select name="position" data-label="Position" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                        <option value="">Select Position</option>
                        <option value="SK Chairman">SK Chairman</option>
                        <option value="SK Secretary">SK Secretary</option>
                        <option value="SK Treasurer">SK Treasurer</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="district" data-label="District" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                            <option value="">District</option>
                            <option value="District 1">District 1</option>
                            <option value="District 2">District 2</option>
                            <option value="District 3">District 3</option>
                            <option value="District 4">District 4</option>
                        </select>
                        <select name="barangay" data-label="Barangay" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                            <option value="">Barangay</option>
                            <?php foreach($barangays as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="municipality" value="Majayjay" readonly class="form-input w-full px-4 py-2.5 rounded-xl text-sm bg-gray-100">
                        <input type="text" name="province" value="Laguna" readonly class="form-input w-full px-4 py-2.5 rounded-xl text-sm bg-gray-100">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-400 uppercase ml-1">Term Start Date</label>
                        <input type="date" name="term_start" data-label="Term Start" required class="form-input w-full px-4 py-2.5 rounded-xl text-sm text-gray-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="setPhase(2)" class="w-1/3 border py-3 rounded-xl text-[10px] uppercase font-bold text-gray-400">Back</button>
                        <button type="submit" class="w-2/3 bg-green-600 text-white py-3 rounded-xl shadow-lg text-xs font-bold uppercase">Submit Registration</button>
                    </div>
                </div>
            </form>

            <div class="mt-8 text-center">
                <p class="text-[11px] text-gray-400">Already registered? 
                    <a href="login.php" class="text-[#1B1B4B] font-bold hover:underline ml-1">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Password Visibility Toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });

        // Modal Function
        function triggerAutoModal(title, message, type = 'error', redirect = null) {
            const modal = document.getElementById('autoModal');
            const icon = document.getElementById('modalIcon');
            const bar = document.getElementById('modalBar');
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalMessage').innerText = message;
            
            if(type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle text-green-500 text-xl"></i>';
                bar.className = "h-1.5 w-full bg-green-500";
            } else {
                icon.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>';
                bar.className = "h-1.5 w-full bg-red-500";
            }
            modal.classList.add('slide-down');
            setTimeout(() => {
                modal.classList.remove('slide-down');
                if(redirect) setTimeout(() => window.location.href = redirect, 500);
            }, 3000);
        }

        function setPhase(p) {
            document.querySelectorAll('.phase-content').forEach(el => el.classList.remove('active'));
            document.getElementById('phase' + p).classList.add('active');
            const titles = ["Personal Info", "Account Details", "Role & Address"];
            document.getElementById('phaseLabel').innerText = titles[p-1];
            document.getElementById('stepCount').innerText = `Step ${p}/3`;
            document.getElementById('progressBar').style.width = (p * 33.3) + "%";
        }

        function validatePhase(current, next) {
            const phase = document.getElementById('phase' + current);
            const inputs = phase.querySelectorAll('input, select');
            for (let input of inputs) {
                if (input.name === 'middlename') continue;
                if (input.hasAttribute('required') && (!input.value || (input.type === 'file' && input.files.length === 0))) {
                    input.classList.add('input-error');
                    triggerAutoModal("Required", `Please provide your ${input.getAttribute('data-label')}.`);
                    input.focus();
                    return;
                }
            }
            setPhase(next);
        }

        <?php 
            if($error_message) echo "triggerAutoModal('Error', '$error_message', 'error');";
            if($success_message) echo "triggerAutoModal('Success!', '$success_message', 'success', 'login.php');";
        ?>
    </script>
</body>
</html>