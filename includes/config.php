<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wmsu_bus_system');

// Site Configuration
define('SITE_NAME', 'WMSU Bus Reserve System');
define('SITE_URL', 'http://localhost/wmsu_bus_reserve_system/');

// Email Configuration (PHP mail function)
define('ADMIN_EMAIL', 'admin@wmsu.edu.ph');
define('FROM_EMAIL', 'noreply@wmsu.edu.ph');
define('FROM_NAME', 'WMSU Bus System');

// Password Requirements
define('MIN_PASSWORD_LENGTH', 8);

// Timezone
date_default_timezone_set('Asia/Manila');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Error Reporting (Turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>