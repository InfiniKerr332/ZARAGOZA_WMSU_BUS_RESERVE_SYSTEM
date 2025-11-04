<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// Destroy session and redirect
session_unset();
session_destroy();

redirect(SITE_URL . 'index.php');
?>