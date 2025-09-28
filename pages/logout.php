<?php
// logout.php
require_once '../includes/config.php'; //Import the configuration file config.php

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();            //Delete the user's login status

// Redirect to login page with success message
$_SESSION['success'] = "You have been successfully logged out.";
header('Location: index.php');         //Users can log in again
exit();
?>