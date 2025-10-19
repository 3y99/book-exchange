<?php
$pageTitle = "Dashboard";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect to role selection if not chosen
if (!hasSelectedRole()) {
    header('Location: role-selection.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ NEW CODE — get unread admin responses count
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM reports 
    WHERE reporter_id = ? 
      AND admin_notes IS NOT NULL 
      AND admin_notes <> '' 
      AND (is_user_notified = 0 OR is_user_notified IS NULL)
");
$stmt->execute([$user_id]);
$unread_reports = $stmt->fetchColumn();

// Get user stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM listings WHERE seller_id = ?");
$stmt->execute([$user_id]);
$total_listings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM listings WHERE seller_id = ? AND status = 'available'");
$stmt->execute([$user_id]);
$active_listings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Count total transactions from purchases table
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM purchases WHERE buyer_id = ? OR seller_id = ?");
$stmt->execute([$user_id, $user_id]);
$total_transactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];


$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent listings
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    WHERE l.seller_id = ?
    ORDER BY l.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions (seller’s sold books only, latest first)
$stmt = $pdo->prepare("
    SELECT 
        t.id AS transaction_id,
        b.title AS listing_title,
        buyer.username AS buyer_username,
        seller.username AS seller_username,
        t.price AS transaction_price,
        t.status,
        t.transaction_date,
        t.buyer_id,
        t.seller_id
    FROM transactions t
    INNER JOIN listings l ON t.listing_id = l.id
    INNER JOIN books b ON l.book_id = b.id
    INNER JOIN users buyer ON t.buyer_id = buyer.id
    INNER JOIN users seller ON t.seller_id = seller.id
    WHERE t.seller_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <div class="dashboard-grid">
        <div class="dashboard-sidebar">
            <div class="user-profile">
                <div class="profile-image">
                    <?php
                    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="../assets/images/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                            alt="<?php echo $_SESSION['username']; ?>" 
                            class="profile-picture">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo $_SESSION['username']; ?></h3>
                    <p><?php echo $_SESSION['email']; ?></p>
                    <span class="role-badge role-<?php echo getUserRole(); ?>">
                        <?php echo ucfirst(getUserRole()); ?>
                    </span>
                </div>
            </div>
            
            <ul class="dashboard-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                
                <?php if (isSeller()): ?>
                    <li><a href="my-listings.php"><i class="fas fa-book"></i> My Listings</a></li>
                    <li><a href="create-listing.php"><i class="fas fa-plus-circle"></i> Create Listing</a></li>
                    <li><a href="sales-history.php"><i class="fas fa-dollar-sign"></i> Sales History</a></li>
                <?php elseif (isBuyer()): ?>
                    <li><a href="listings.php"><i class="fas fa-search"></i> Browse Books</a></li>
                    <li><a href="watchlist.php"><i class="fas fa-heart"></i> My Watchlist</a></li>
                    <li><a href="purchase-history.php"><i class="fas fa-heart"></i> Purchase History</a></li>
                      <li>
                    <a href="my-reports.php">
                        <i class="fas fa-flag"></i> My Reports 
                        <?php if (!empty($unread_reports) && $unread_reports > 0): ?>
                            <span class="badge" title="New admin response"><?php echo $unread_reports; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?> 
                
                <li>
                    <a href="messages.php"><i class="fas fa-envelope"></i> Messages 
                        <?php if ($unread_messages > 0): ?>
                            <span class="badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
              

                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                
                <?php if (isAdmin()): ?>
                    <li><a href="../admin/"><i class="fas fa-cog"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="dashboard-content">
            <h1>Dashboard - <?php echo ucfirst(getUserRole()); ?> View</h1>
            
            <div class="stats-grid">
                <?php if (isSeller()): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_listings; ?></h3>
                            <p>Total Listings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $active_listings; ?></h3>
                            <p>Active Listings</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_transactions; ?></h3>
                        <p>Transactions</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $unread_messages; ?></h3>
                        <p>Unread Messages</p>
                    </div>
                </div>
            </div>
            
            <?php if (isSeller() && count($recent_listings) > 0): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Listings</h2>
                        <a href="listings.php" class="btn btn-small">View All</a>
                    </div>
                    
                    <div class="listings-grid mini">
                        <?php foreach ($recent_listings as $listing): ?>
                            <div class="listing-card">
                                <div class="listing-image">
                                    <?php if ($listing['primary_image']): ?>
                                        <img src="../assets/images/uploads/books/<?php echo $listing['primary_image']; ?>" alt="<?php echo $listing['title']; ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                                    <?php endif; ?>
                                    <span class="condition-badge"><?php echo getConditionText($listing['book_condition']); ?></span>
                                </div>
                                <div class="listing-details">
                                    <h3><?php echo $listing['title']; ?></h3>
                                    <p class="price"><?php echo formatPrice($listing['price']); ?></p>
                                    <span class="status-badge status-<?php echo $listing['status']; ?>">
                                        <?php echo ucfirst($listing['status']); ?>
                                    </span>
                                    <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (isSeller()): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Get Started</h2>
                    </div>
                    <div class="welcome-card">
                        <h3>Welcome, Seller!</h3>
                        <p>Start selling your books by creating your first listing.</p>
                        <a href="create-listing.php" class="btn btn-primary">Create Your First Listing</a>
                    </div>
                </div>
            <?php elseif (isBuyer()): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Welcome, Buyer!</h2>
                    </div>
                    <div class="welcome-card">
                        <h3>Start Exploring Books</h3>
                        <p>Browse through available books and find what you need for your courses.</p>
                        <a href="listings.php" class="btn btn-primary">Browse Books</a>
                    </div>
                </div>
            <?php endif; ?>
            
          

        </div>
    </div>
</div>

<style>
/* ✅ added badge styling */
.badge {
    background-color: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 3px 7px;
    font-size: 0.75rem;
    margin-left: 6px;
    vertical-align: middle;
    font-weight: bold;
}

.role-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.role-badge.role-seller {
    background-color: #e7f3ff;
    color: #0066cc;
}

.role-badge.role-buyer {
    background-color: #f0fff4;
    color: #059669;
}

.welcome-card {
    background: white;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.welcome-card h3 {
    color: #333;
    margin-bottom: 1rem;
}

.welcome-card p {
    color: #666;
    margin-bottom: 1.5rem;
}
</style>

<?php
require_once '../includes/footer.php';
?>