<?php
require_once 'config.php';
require_once 'database.php';

// ==========================================
// PASSWORD FUNCTIONS
// ==========================================

// Hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Validate password strength
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

// ==========================================
// INPUT VALIDATION & SANITIZATION
// ==========================================

// Sanitize input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ==========================================
// USER FUNCTIONS
// ==========================================

// Get logged in user data from database
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    return $stmt->fetch();
}

// ==========================================
// DATE & TIME FUNCTIONS
// ==========================================

// Format date for display
function format_date($date) {
    return date('F d, Y', strtotime($date));
}

// Format time for display
function format_time($time) {
    return date('g:i A', strtotime($time));
}

// Check if date is a Sunday
function is_sunday($date) {
    return date('w', strtotime($date)) == 0;
}

// Check if date is in the past
function is_past_date($date) {
    return strtotime($date) < strtotime(date('Y-m-d'));
}

// Check if date is a working day (Monday to Saturday)
function is_working_day($date) {
    $day_of_week = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
    return $day_of_week < 7; // Return true if not Sunday
}

// Calculate approval deadline (24 hours before, considering working days)
function get_approval_deadline($reservation_date, $reservation_time) {
    $reservation_datetime = strtotime($reservation_date . ' ' . $reservation_time);
    $deadline = $reservation_datetime - (24 * 60 * 60); // 24 hours before
    
    // Check if deadline falls on Sunday, move it to Saturday
    if (date('N', $deadline) == 7) { // Sunday
        $deadline = strtotime('last Saturday', $deadline);
        $deadline = strtotime('17:00:00', $deadline); // Set to 5 PM Saturday
    }
    
    return date('Y-m-d H:i:s', $deadline);
}

// Check if reservation can still be approved
function can_approve_reservation($reservation_date, $reservation_time) {
    $now = time();
    $reservation_datetime = strtotime($reservation_date . ' ' . $reservation_time);
    $time_difference = $reservation_datetime - $now;
    
    // Must be at least 24 hours before
    return $time_difference >= (24 * 60 * 60);
}

// Check if reservation date is within 24 hours (working hours)
function is_within_approval_deadline($reservation_date) {
    $now = new DateTime();
    $res_date = new DateTime($reservation_date);
    
    // Calculate hours difference
    $interval = $now->diff($res_date);
    $hours_diff = ($interval->days * 24) + $interval->h;
    
    return $hours_diff >= 24;
}

// ==========================================
// BUS & DRIVER AVAILABILITY
// ==========================================

// Check if date is available for reservation
function is_date_available($date, $exclude_reservation_id = null) {
    $db = new Database();
    $conn = $db->connect();
    
    $sql = "SELECT COUNT(*) as count FROM reservations 
            WHERE reservation_date = :date 
            AND status IN ('pending', 'approved')";
    
    if ($exclude_reservation_id) {
        $sql .= " AND id != :exclude_id";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':date', $date);
    
    if ($exclude_reservation_id) {
        $stmt->bindParam(':exclude_id', $exclude_reservation_id);
    }
    
    $stmt->execute();
    $result = $stmt->fetch();
    
    // If there are already 3 reservations (all buses taken), date is not available
    return $result['count'] < 3;
}

// Get available buses for a date
function get_available_buses($date, $exclude_reservation_id = null) {
    $db = new Database();
    $conn = $db->connect();
    
    $sql = "SELECT b.* FROM buses b 
            WHERE b.status = 'available' 
            AND b.id NOT IN (
                SELECT bus_id FROM reservations 
                WHERE reservation_date = :date 
                AND status IN ('pending', 'approved')
                AND bus_id IS NOT NULL";
    
    if ($exclude_reservation_id) {
        $sql .= " AND id != :exclude_id";
    }
    
    $sql .= ")";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':date', $date);
    
    if ($exclude_reservation_id) {
        $stmt->bindParam(':exclude_id', $exclude_reservation_id);
    }
    
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get available drivers for a date
function get_available_drivers($date, $exclude_reservation_id = null) {
    $db = new Database();
    $conn = $db->connect();
    
    $sql = "SELECT d.* FROM drivers d 
            WHERE d.status = 'available' 
            AND d.id NOT IN (
                SELECT driver_id FROM reservations 
                WHERE reservation_date = :date 
                AND status IN ('pending', 'approved')
                AND driver_id IS NOT NULL";
    
    if ($exclude_reservation_id) {
        $sql .= " AND id != :exclude_id";
    }
    
    $sql .= ")";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':date', $date);
    
    if ($exclude_reservation_id) {
        $stmt->bindParam(':exclude_id', $exclude_reservation_id);
    }
    
    $stmt->execute();
    return $stmt->fetchAll();
}

// ==========================================
// DISPLAY & UI FUNCTIONS
// ==========================================

// Get status badge HTML
function get_status_badge($status) {
    $badges = array(
        'pending' => '<span class="badge badge-pending">Pending</span>',
        'approved' => '<span class="badge badge-approved">Approved</span>',
        'rejected' => '<span class="badge badge-rejected">Rejected</span>',
        'cancelled' => '<span class="badge badge-cancelled">Cancelled</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : '<span class="badge">' . htmlspecialchars($status) . '</span>';
}

// Get user role display name
function get_role_name($role) {
    $roles = array(
        'student' => 'Student',
        'employee' => 'Employee',
        'teacher' => 'Teacher',
        'admin' => 'Administrator'
    );
    return isset($roles[$role]) ? $roles[$role] : 'Unknown';
}

// Generate unique reservation code
function generate_reservation_code($id) {
    return 'WMSU-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// ==========================================
// NAVIGATION & REDIRECT
// ==========================================

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Set flash message
function set_message($type, $message) {
    $_SESSION['alert_type'] = $type; // success, error, warning, info
    $_SESSION['alert_message'] = $message;
}

// Get flash message
function get_message() {
    if (isset($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);
        return array('type' => $type, 'message' => $message);
    }
    return null;
}

// ==========================================
// EMAIL & NOTIFICATIONS
// ==========================================

// Send email notification
function send_email($to, $subject, $message) {
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Email template
    $email_template = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #800000; color: white; padding: 20px; text-align: center; }
            .content { background: #f5f5f5; padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background: #800000; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>WMSU Bus Reserve System</h2>
            </div>
            <div class="content">
                ' . $message . '
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Western Mindanao State University</p>
                <p>Normal Road, Baliwasan, Zamboanga City, Philippines 7000</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return mail($to, $subject, $email_template, $headers);
}

// Send SMS notification (placeholder)
function send_sms($phone, $message) {
    // This is a placeholder function
    // In production, you would integrate with SMS gateway API like Twilio or Semaphore
    
    // For now, just log the SMS
    error_log("SMS to {$phone}: {$message}");
    
    return true; // Return true for demonstration
}
?>