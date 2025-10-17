<?php
// logout.php
require_once '../includes/config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with success message
$_SESSION['success'] = "You have been successfully logged out.";
header('Location: index.php');
exit();
?>