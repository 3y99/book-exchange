<?php
// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>">
                    <i class="fas fa-book"></i>
                    <span><?php echo SITE_NAME; ?></span>
                </a>
            </div>
            
            <nav class="nav">
                <ul>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <!-- Guest -->
                        <li><a href="<?php echo SITE_URL; ?>/pages/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                    <?php elseif (isAdmin()): ?>
                        <!-- Admin -->
                        <li><a href="<?php echo SITE_URL; ?>/admin/"><i class="fas fa-cog"></i> Admin Dashboard</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <!-- Normal logged in user -->
                        <li><a href="<?php echo SITE_URL; ?>/pages/dashboard.php"><i class="fas fa-user"></i> Dashboard</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                        <!-- Existing navigation items -->
                        <li><a href="<?php echo SITE_URL; ?>/pages/create-listing.php"><i class="fas fa-plus-circle"></i> Sell</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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
            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Guest -->
                <li><a href="<?php echo SITE_URL; ?>/pages/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="<?php echo SITE_URL; ?>/pages/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php elseif (isAdmin()): ?>
                <!-- Admin -->
                <li><a href="<?php echo SITE_URL; ?>/admin/"><i class="fas fa-cog"></i> Admin</a></li>
                <li><a href="<?php echo SITE_URL; ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <!-- Normal logged in user -->
                <li><a href="<?php echo SITE_URL; ?>/pages/dashboard.php"><i class="fas fa-user"></i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/pages/messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="<?php echo SITE_URL; ?>/pages/create-listing.php"><i class="fas fa-plus-circle"></i> Sell</a></li>
                <li><a href="<?php echo SITE_URL; ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <main class="main-content">
<?php
// End output buffering and flush
ob_end_flush();
?>
