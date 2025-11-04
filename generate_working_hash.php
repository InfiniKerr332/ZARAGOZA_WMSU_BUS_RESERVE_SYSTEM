<?php
// Generate a hash using YOUR PHP installation
// This will definitely work because it's created by your own system

$password = "Admin123!";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Copy the hash below:</h2>";
echo "<p><strong>Password:</strong> Admin123!</p>";
echo "<p><strong>Hash:</strong></p>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; word-break: break-all;'>" . $hash . "</code>";

echo "<h2>SQL Command to run in phpMyAdmin:</h2>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; word-break: break-all;'>";
echo "DELETE FROM users WHERE email = 'admin@wmsu.edu.ph';<br>";
echo "INSERT INTO users (name, email, contact_no, department, position, password, role, created_at) ";
echo "VALUES ('WMSU Administrator', 'admin@wmsu.edu.ph', '09123456789', 'Administration', 'System Administrator', '" . $hash . "', 'admin', NOW());";
echo "</code>";

// Verify it works
echo "<h2>Verification:</h2>";
if (password_verify($password, $hash)) {
    echo "<p style='color: green;'><strong>✓ SUCCESS:</strong> This hash will work with password: Admin123!</p>";
} else {
    echo "<p style='color: red;'><strong>✗ FAILED:</strong> Something is wrong</p>";
}
?>