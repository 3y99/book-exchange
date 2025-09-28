<?php
// Start output buffering
ob_start();

// Check if user is admin
$isAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/admin/">
                    <i class="fas fa-lock"></i>
                    <span><?php echo SITE_NAME; ?> Admin</span>
                </a>
            </div>
            
            <nav class="nav">
                <ul>
                    <?php if ($isAdmin): ?>
                        <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="users.php"><i class="fas fa-users-cog"></i> Users</a></li>
                        <li><a href="listings.php"><i class="fas fa-list"></i> Listings</a></li>
                        <li><a href="categories.php"><i class="fas fa-book"></i>Categories</a></li>
                        <li><a href="reports.php"><i class="fas fa-flag"></i> Reports</a></li>
                        <li><a href="../pages/"><i class="fas fa-external-link-alt"></i> View Site</a></li>
                        <li><a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Admin Login</a></li>
                        <li><a href="../pages/"><i class="fas fa-external-link-alt"></i> View Site</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </header>
    
    <div class="mobile-menu">
        <ul>
            <?php if ($isAdmin): ?>
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Users</a></li>
                <li><a href="listings.php"><i class="fas fa-list"></i> Listings</a></li>
                <li><a href="categories.php"><i class="fas fa-book"></i>Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-flag"></i> Reports</a></li>
                <li><a href="../pages/"><i class="fas fa-external-link-alt"></i> View Site</a></li>
                <li><a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Admin Login</a></li>
                <li><a href="../pages/"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <main class="main-content">
<?php
// End output buffering and flush
ob_end_flush();
?>
