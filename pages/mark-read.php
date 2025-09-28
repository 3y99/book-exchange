<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    
    // Mark the response as read
    if (markReportResponseAsRead($report_id, $user_id)) {
        $_SESSION['success'] = "Response marked as read.";
    } else {
        $_SESSION['error'] = "Unable to mark response as read.";
    }
}

// Redirect back to the previous page
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'my-reports.php'));
exit();