<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// If already logged in, redirect
if (is_logged_in()) {
    if (is_admin()) {
        redirect(SITE_URL . 'admin/dashboard.php');
    } else {
        redirect(SITE_URL . 'student/dashboard.php');
    }
}

$error = '';
$timeout_message = '';

// Check if session timed out
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $timeout_message = 'Your session has expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && verify_password($password, $user['password'])) {
            // Check account status
            if ($user['account_status'] == 'pending') {
                $error = 'Your account is pending admin approval. Please wait for approval before logging in.';
            } elseif ($user['account_status'] == 'rejected') {
                $rejection_reason = $user['rejection_reason'] ? ' Reason: ' . $user['rejection_reason'] : '';
                $error = 'Your account registration was rejected.' . $rejection_reason;
            } else {
                // Account is approved, proceed with login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect(SITE_URL . 'admin/dashboard.php');
                } else {
                    redirect(SITE_URL . 'student/dashboard.php');
                }
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
        
        .login-container {
            max-width: 450px;
            margin: 60px auto;
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
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="top-nav-content">
            <a href="index.php" class="nav-logo">
                <img src="images/wmsu.png" alt="WMSU Logo">
                <span class="nav-logo-text">WMSU Bus Reserve</span>
            </a>
            
            <a href="index.php" class="nav-back">
                ← Back to Home
            </a>
        </div>
    </nav>

    <div class="login-container">
        <div class="card">
            <div class="logo-center">
                <h2>Welcome Back!</h2>
                <p>Login to your account</p>
            </div>
            
            <?php if ($timeout_message): ?>
                <div class="alert alert-warning">
                    <?php echo $timeout_message; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           placeholder="your.email@wmsu.edu.ph"
                           required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password"
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div style="text-align: center;">
                <p>Don't have an account? <a href="register.php" style="color: var(--wmsu-maroon); font-weight: 600;">Register here</a></p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f0f8ff; border-radius: 8px;">
            <p style="color: #666; font-size: 14px; margin-bottom: 10px;"><strong>🔐 Test Admin Account</strong></p>
            <p style="color: #666; font-size: 13px; margin: 5px 0;"><strong>Email:</strong> admin@wmsu.edu.ph</p>
            <p style="color: #666; font-size: 13px; margin: 5px 0;"><strong>Password:</strong> Admin123!</p>
        </div>
    </div>
    
    <footer style="margin-top: 50px;">
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
</body>
</html>