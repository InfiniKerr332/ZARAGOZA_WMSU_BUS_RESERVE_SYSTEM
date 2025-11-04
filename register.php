<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// If already logged in, redirect
if (is_logged_in()) {
    redirect(SITE_URL . 'student/dashboard.php');
}

$errors = [];
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data['first_name'] = clean_input($_POST['first_name']);
    $form_data['middle_name'] = clean_input($_POST['middle_name']);
    $form_data['last_name'] = clean_input($_POST['last_name']);
    $form_data['email'] = clean_input($_POST['email']);
    $form_data['contact_no'] = clean_input($_POST['contact_no']);
    $form_data['department'] = clean_input($_POST['department']);
    $form_data['role'] = clean_input($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Combine name
    $full_name = trim($form_data['first_name'] . ' ' . $form_data['middle_name'] . ' ' . $form_data['last_name']);
    
    // Validation
    if (empty($form_data['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($form_data['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($form_data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    } elseif (!str_ends_with($form_data['email'], '@wmsu.edu.ph')) {
        $errors['email'] = 'You must use your WMSU email account (@wmsu.edu.ph)';
    }
    
    if (empty($form_data['contact_no'])) {
        $errors['contact_no'] = 'Contact number is required';
    }
    
    if (empty($form_data['role'])) {
        $errors['role'] = 'Please select your role';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } else {
        $password_errors = validate_password($password);
        if (!empty($password_errors)) {
            $errors['password'] = implode('<br>', $password_errors);
        }
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Validate employee ID uploads - FRONT
    $employee_id_front_path = null;
    if (isset($_FILES['employee_id_front']) && $_FILES['employee_id_front']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        
        $file_type = $_FILES['employee_id_front']['type'];
        $file_size = $_FILES['employee_id_front']['size'];
        $file_size_mb = round($file_size / (1024 * 1024), 2); // Convert to MB
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['employee_id_front'] = 'Only JPG, JPEG, and PNG files are allowed';
        } elseif ($file_size > $max_size) {
            $errors['employee_id_front'] = "File size is {$file_size_mb}MB. Maximum allowed is 5MB";
        } else {
            // Generate unique filename
            $extension = pathinfo($_FILES['employee_id_front']['name'], PATHINFO_EXTENSION);
            $filename = 'emp_front_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_dir = 'uploads/employee_ids/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $employee_id_front_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['employee_id_front']['tmp_name'], $employee_id_front_path)) {
                $errors['employee_id_front'] = 'Failed to upload front ID';
                $employee_id_front_path = null;
            }
        }
    } elseif ($_FILES['employee_id_front']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['employee_id_front'] = 'Error uploading front ID';
    } else {
        $errors['employee_id_front'] = 'Front ID image is required';
    }
    
    // Validate employee ID uploads - BACK
    $employee_id_back_path = null;
    if (isset($_FILES['employee_id_back']) && $_FILES['employee_id_back']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        
        $file_type = $_FILES['employee_id_back']['type'];
        $file_size = $_FILES['employee_id_back']['size'];
        $file_size_mb = round($file_size / (1024 * 1024), 2); // Convert to MB
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['employee_id_back'] = 'Only JPG, JPEG, and PNG files are allowed';
        } elseif ($file_size > $max_size) {
            $errors['employee_id_back'] = "File size is {$file_size_mb}MB. Maximum allowed is 5MB";
        } else {
            // Generate unique filename
            $extension = pathinfo($_FILES['employee_id_back']['name'], PATHINFO_EXTENSION);
            $filename = 'emp_back_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_dir = 'uploads/employee_ids/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $employee_id_back_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['employee_id_back']['tmp_name'], $employee_id_back_path)) {
                $errors['employee_id_back'] = 'Failed to upload back ID';
                $employee_id_back_path = null;
            }
        }
    } elseif ($_FILES['employee_id_back']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['employee_id_back'] = 'Error uploading back ID';
    } else {
        $errors['employee_id_back'] = 'Back ID image is required';
    }
    
    // Check if email already exists
    if (empty($errors['email'])) {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already registered';
        }
    }
    
    // If no errors, register user
    if (empty($errors)) {
        $db = new Database();
        $conn = $db->connect();
        
        $hashed_password = hash_password($password);
        
        // Set position based on role
        $position_map = [
            'employee' => 'Employee',
            'teacher' => 'Teacher'
        ];
        $position = $position_map[$form_data['role']] ?? 'Employee';
        
        $sql = "INSERT INTO users (name, email, contact_no, department, position, password, role, employee_id_image, employee_id_back_image, account_status) 
                VALUES (:name, :email, :contact_no, :department, :position, :password, :role, :employee_id_front, :employee_id_back, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $full_name);
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->bindParam(':contact_no', $form_data['contact_no']);
        $stmt->bindParam(':department', $form_data['department']);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $form_data['role']);
        $stmt->bindParam(':employee_id_front', $employee_id_front_path);
        $stmt->bindParam(':employee_id_back', $employee_id_back_path);
        
        if ($stmt->execute()) {
            $success = 'Registration successful! Your account is pending admin approval. You will receive an email once approved.';
            
            // Send email to admin
            $admin_email_message = "
                <h3>New User Registration - Approval Required</h3>
                <p><strong>Name:</strong> " . htmlspecialchars($full_name) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($form_data['email']) . "</p>
                <p><strong>Role:</strong> " . ucfirst($form_data['role']) . "</p>
                <p><strong>Department:</strong> " . htmlspecialchars($form_data['department']) . "</p>
                <p><a href='" . SITE_URL . "admin/users.php'>Review Registration</a></p>
            ";
            send_email(ADMIN_EMAIL, 'New User Registration - Approval Required', $admin_email_message);
            
            $form_data = []; // Clear form
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
            // Delete uploaded files if registration failed
            if ($employee_id_front_path && file_exists($employee_id_front_path)) {
                unlink($employee_id_front_path);
            }
            if ($employee_id_back_path && file_exists($employee_id_back_path)) {
                unlink($employee_id_back_path);
            }
        }
    } else {
        // Delete uploaded files if there are validation errors
        if ($employee_id_front_path && file_exists($employee_id_front_path)) {
            unlink($employee_id_front_path);
        }
        if ($employee_id_back_path && file_exists($employee_id_back_path)) {
            unlink($employee_id_back_path);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .top-nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .top-nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .nav-logo img {
            height: 50px;
            width: 50px;
            object-fit: contain;
        }
        
        .nav-logo-text {
            color: var(--wmsu-maroon);
            font-size: 18px;
            font-weight: 700;
        }
        
        .nav-back {
            color: var(--wmsu-maroon);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-back:hover {
            opacity: 0.8;
        }
        
        .register-container {
            max-width: 650px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .logo-center {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-center h2 {
            color: var(--wmsu-maroon);
            margin-bottom: 5px;
            font-size: 28px;
        }
        
        .logo-center p {
            color: #666;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #666;
        }
        
        .name-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .name-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .file-upload-container {
            border: 2px dashed var(--wmsu-maroon);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .file-upload-container:hover {
            background: #f0f0f0;
            border-color: var(--wmsu-maroon-dark);
        }
        
        .file-upload-container input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .upload-icon {
            font-size: 48px;
            color: var(--wmsu-maroon);
            margin-bottom: 10px;
        }
        
        .upload-text {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .upload-hint {
            color: #999;
            font-size: 12px;
        }
        
        .preview-container {
            margin-top: 15px;
            display: none;
        }
        
        .preview-container.show {
            display: block;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 10px;
        }
        
        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .file-info-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 13px;
        }
        
        .file-info-label {
            color: #666;
            font-weight: 600;
        }
        
        .file-info-value {
            color: #333;
        }
        
        .file-size-ok {
            color: var(--success-green);
        }
        
        .file-size-error {
            color: var(--danger-red);
        }
        
        .remove-file {
            margin-top: 10px;
            padding: 8px 15px;
            background: var(--danger-red);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-file:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="top-nav-content">
            <a href="index.php" class="nav-logo">
                <img src="images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
                <span class="nav-logo-text">WMSU Bus Reserve</span>
            </a>
            
            <a href="index.php" class="nav-back">
                ← Back to Home
            </a>
        </div>
    </nav>

    <div class="register-container">
        <div class="card">
            <div class="logo-center">
                <h2>Create Your Account</h2>
                <p>Register to reserve WMSU buses (Teachers & Employees Only)</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?php echo $success; ?>
                </div>
                <a href="login.php" class="btn btn-primary btn-block" style="margin-top: 15px;">Go to Login</a>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <?php echo $errors['general']; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
                <div class="name-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" 
                               placeholder="Juan"
                               required>
                        <?php if (isset($errors['first_name'])): ?>
                            <span class="error-text"><?php echo $errors['first_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name <small>(Optional)</small></label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>" 
                               placeholder="Santos">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" 
                               placeholder="Dela Cruz"
                               required>
                        <?php if (isset($errors['last_name'])): ?>
                            <span class="error-text"><?php echo $errors['last_name']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">WMSU Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                           placeholder="your.email@wmsu.edu.ph"
                           required>
                    <small style="color: #666;">Must be your official WMSU email (@wmsu.edu.ph)</small>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="contact_no">Contact Number <span class="required">*</span></label>
                    <input type="text" id="contact_no" name="contact_no" class="form-control" 
                           placeholder="09XXXXXXXXX" 
                           value="<?php echo htmlspecialchars($form_data['contact_no'] ?? ''); ?>" 
                           required>
                    <?php if (isset($errors['contact_no'])): ?>
                        <span class="error-text"><?php echo $errors['contact_no']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="department">Department <span class="required">*</span></label>
                    <input type="text" id="department" name="department" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['department'] ?? ''); ?>"
                           placeholder="e.g., CCS, CLA, COE"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="role">I am a <span class="required">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">-- Select Your Role --</option>
                        <option value="employee" <?php echo (isset($form_data['role']) && $form_data['role'] == 'employee') ? 'selected' : ''; ?>>Employee</option>
                        <option value="teacher" <?php echo (isset($form_data['role']) && $form_data['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                    <small style="color: #666;">⚠️ Only teachers and employees can register</small>
                    <?php if (isset($errors['role'])): ?>
                        <span class="error-text"><?php echo $errors['role']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- FRONT ID -->
                <div class="form-group">
                    <label>Employee/Teacher ID - FRONT SIDE <span class="required">*</span></label>
                    <div class="file-upload-container" id="frontUploadArea">
                        <input type="file" id="employee_id_front" name="employee_id_front" accept="image/jpeg,image/jpg,image/png" required>
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">Click to upload FRONT of your ID</div>
                        <div class="upload-hint">JPG, PNG - Max 5MB</div>
                    </div>
                    <div class="preview-container" id="frontPreview">
                        <img class="preview-image" id="frontImage" src="" alt="Front ID Preview">
                        <div class="file-info" id="frontFileInfo"></div>
                        <button type="button" class="remove-file" onclick="removeFrontFile()">🗑️ Remove</button>
                    </div>
                    <?php if (isset($errors['employee_id_front'])): ?>
                        <span class="error-text"><?php echo $errors['employee_id_front']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- BACK ID -->
                <div class="form-group">
                    <label>Employee/Teacher ID - BACK SIDE <span class="required">*</span></label>
                    <div class="file-upload-container" id="backUploadArea">
                        <input type="file" id="employee_id_back" name="employee_id_back" accept="image/jpeg,image/jpg,image/png" required>
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">Click to upload BACK of your ID</div>
                        <div class="upload-hint">JPG, PNG - Max 5MB</div>
                    </div>
                    <div class="preview-container" id="backPreview">
                        <img class="preview-image" id="backImage" src="" alt="Back ID Preview">
                        <div class="file-info" id="backFileInfo"></div>
                        <button type="button" class="remove-file" onclick="removeBackFile()">🗑️ Remove</button>
                    </div>
                    <?php if (isset($errors['employee_id_back'])): ?>
                        <span class="error-text"><?php echo $errors['employee_id_back']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter password"
                           required>
                    <small style="color: #666;">Min 8 characters, 1 uppercase, 1 special character</small>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error-text"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Re-enter password"
                           required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error-text"><?php echo $errors['confirm_password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div style="text-align: center;">
                <p>Already have an account? <a href="login.php" style="color: var(--wmsu-maroon); font-weight: 600;">Login here</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer style="margin-top: 50px;">
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
    <script src="js/validation.js"></script>
    <script>
        // Handle FRONT ID upload
        document.getElementById('employee_id_front').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileUpload(file, 'front');
            }
        });
        
        // Handle BACK ID upload
        document.getElementById('employee_id_back').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileUpload(file, 'back');
            }
        });
        
        function handleFileUpload(file, side) {
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, JPEG, and PNG files are allowed');
                resetFileInput(side);
                return;
            }
            
            // Check file size
            if (file.size > maxSize) {
                alert(`File size is ${fileSizeMB}MB. Maximum allowed is 5MB. Please choose a smaller file.`);
                resetFileInput(side);
                return;
            }
            
            // Hide upload area, show preview
            document.getElementById(side + 'UploadArea').style.display = 'none';
            document.getElementById(side + 'Preview').classList.add('show');
            
            // Show image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(side + 'Image').src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Show file info
            const sizeClass = file.size > maxSize ? 'file-size-error' : 'file-size-ok';
            const fileInfo = `
                <div class="file-info-item">
                    <span class="file-info-label">File name:</span>
                    <span class="file-info-value">${file.name}</span>
                </div>
                <div class="file-info-item">
                    <span class="file-info-label">File size:</span>
                    <span class="file-info-value ${sizeClass}">${fileSizeMB} MB</span>
                </div>
                <div class="file-info-item">
                    <span class="file-info-label">File type:</span>
                    <span class="file-info-value">${file.type}</span>
                </div>
            `;
            document.getElementById(side + 'FileInfo').innerHTML = fileInfo;
        }
        
        function removeFrontFile() {
            resetFileInput('front');
        }
        
        function removeBackFile() {
            resetFileInput('back');
        }
        
        function resetFileInput(side) {
            document.getElementById('employee_id_' + side).value = '';
            document.getElementById(side + 'UploadArea').style.display = 'block';
            document.getElementById(side + 'Preview').classList.remove('show');
            document.getElementById(side + 'Image').src = '';
            document.getElementById(side + 'FileInfo').innerHTML = '';
        }
    </script>
</body>
</html>