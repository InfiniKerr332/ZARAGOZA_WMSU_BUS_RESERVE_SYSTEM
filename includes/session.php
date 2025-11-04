<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout (30 minutes of inactivity)
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Require login (redirect if not logged in)
function require_login() {
    if (!is_logged_in()) {
        redirect(SITE_URL . 'login.php?timeout=1');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            redirect(SITE_URL . 'login.php?timeout=1');
        }
    }
    
    $_SESSION['last_activity'] = time();
}

// Require admin (redirect if not admin)
function require_admin() {
    require_login();
    
    if (!is_admin()) {
        redirect(SITE_URL . 'student/dashboard.php');
    }
}

// Get current user info from session
function get_user_session() {
    if (is_logged_in()) {
        return array(
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        );
    }
    return null;
}

// Destroy session (logout)
function destroy_session() {
    session_unset();
    session_destroy();
}
?>