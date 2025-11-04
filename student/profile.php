<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(SITE_URL . 'login.php');
}

// Redirect admin to admin dashboard
if (is_admin()) {
    redirect(SITE_URL . 'admin/dashboard.php');
}

$user = get_logged_user();
$success = '';
$errors = [];

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = clean_input($_POST['name']);
    $contact_no = clean_input($_POST['contact_no']);
    $department = clean_input($_POST['department']);
    $position = clean_input($_POST['position']);
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($errors)) {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("UPDATE users SET name = :name, contact_no = :contact_no, department = :department, position = :position WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $success = 'Profile updated successfully!';
            // Refresh user data
            $user = get_logged_user();
        } else {
            $errors[] = 'Failed to update profile';
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get fresh user data for password check
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $user_pass = $stmt->fetch();
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = 'All password fields are required';
    } elseif (!verify_password($current_password, $user_pass['password'])) {
        $errors[] = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    } else {
        $password_errors = validate_password($new_password);
        if (!empty($password_errors)) {
            $errors = array_merge($errors, $password_errors);
        }
    }
    
    if (empty($errors)) {
        $hashed_password = hash_password($new_password);
        
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
        } else {
            $errors[] = 'Failed to change password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            <div class="user-info">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="reserve.php">New Reservation</a></li>
            <li><a href="my_reservations.php">My Reservations</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <span class="alert-close">&times;</span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
                <span class="alert-close">&times;</span>
            </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <h2>👤 Profile Information</h2>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color: #666;">Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_no">Contact Number</label>
                        <input type="text" id="contact_no" name="contact_no" class="form-control" 
                               value="<?php echo htmlspecialchars($user['contact_no']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" class="form-control" 
                               value="<?php echo htmlspecialchars($user['department']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control" 
                               value="<?php echo htmlspecialchars($user['position']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <input type="text" class="form-control" 
                               value="<?php echo ucfirst($user['role']); ?>" disabled>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary btn-block" 
                            onclick="return confirm('Are you sure you want to update your profile?');">
                        Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h2>🔒 Change Password</h2>
                </div>
                
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Current Password <span class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small style="color: #666;">Min 8 characters, 1 uppercase, 1 special character</small>
                        <span class="error-text"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <span class="error-text"></span>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary btn-block" 
                            onclick="return confirm('Are you sure you want to change your password?');">
                        Change Password
                    </button>
                </form>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="color: #666; font-size: 14px;"><strong>Account Information:</strong></p>
                    <p style="color: #666; font-size: 13px;">
                        Member since: <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
    <script src="../js/validation.js"></script>
</body>
</html>