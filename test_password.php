<?php
$hash = '$2y$10$7J2dK8mL9pQ0R1sT2uV3w.X4Y5Z6aB7cD8eF9gH0iJ1kL2mN3oP4qR';
$password = 'Password123!';

if (password_verify($password, $hash)) {
    echo "SUCCESS: Password matches!";
} else {
    echo "FAILED: Password doesn't match";
}
?>