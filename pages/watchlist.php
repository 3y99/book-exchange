<?php
$pageTitle = "My Watchlist";
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle watchlist actions - must be before any output
if (isset($_POST['remove_from_watchlist'])) {
    $listing_id = intval($_POST['listing_id']);
    removeFromWatchlist($_SESSION['user_id'], $listing_id);
    $_SESSION['success'] = "Removed from watchlist!";
    header('Location: watchlist.php');
    exit();
}

// Now include header after handling redirects
require_once '../includes/header.php';

// Get user's watchlist
$watchlist_items = getWatchlist($_SESSION['user_id']);
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-heart"></i> My Watchlist</h1>
        <p>Your saved book listings</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="listings-page">
        <div class="listings-content">
            <?php if (count($watchlist_items) > 0): ?>
                <div class="listings-header">
                    <p><?php echo count($watchlist_items); ?> items in your watchlist</p>
                </div>
                
                <div class="listings-grid">
                    <?php foreach ($watchlist_items as $item): ?>
                        <div class="listing-card">
                            <div class="listing-image">
                                <?php if ($item['primary_image']): ?>
                                    <img src="../assets/images/uploads/books/<?php echo $item['primary_image']; ?>" alt="<?php echo $item['title']; ?>">
                                <?php else: ?>
                                    <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                                <?php endif; ?>
                                <span class="condition-badge"><?php echo getConditionText($item['book_condition']); ?></span>
                                <form method="POST" class="watchlist-form">
                                    <input type="hidden" name="listing_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="remove_from_watchlist" class="watchlist-btn in-watchlist" title="Remove from Watchlist">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="listing-details">
                                <h3><?php echo $item['title']; ?></h3>
                                <p class="author">by <?php echo $item['author']; ?></p>
                                <?php if ($item['course_code']): ?>
                                    <p class="course">Course: <?php echo $item['course_code']; ?></p>
                                <?php endif; ?>
                                <p class="price"><?php echo formatPrice($item['price']); ?></p>
                                <div class="listing-meta">
                                    <span class="seller">Seller: <?php echo $item['seller_name']; ?></span>
                                </div>
                                <div class="listing-actions">
                                    <a href="view-listing.php?id=<?php echo $item['id']; ?>" class="btn btn-small btn-primary">View Details</a>
                                    <form method="POST" class="watchlist-form-inline">
                                        <input type="hidden" name="listing_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="remove_from_watchlist" class="btn btn-small btn-outline">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-heart" style="color: #ccc; font-size: 4rem; margin-bottom: 1rem;"></i>
                    <h3>Your watchlist is empty</h3>
                    <p>Start browsing books and add them to your watchlist by clicking the heart icon.</p>
                    <a href="listings.php" class="btn btn-primary">Browse Listings</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.watchlist-form {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}

.watchlist-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    color: #ff4757;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.watchlist-btn:hover {
    background: white;
    transform: scale(1.1);
}

.watchlist-form-inline {
    display: inline-block;
    margin-left: 0.5rem;
}

.listing-image {
    position: relative;
}

.listing-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.listing-actions .btn {
    flex: 1;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.page-header h1 {
    color: #333;
    margin-bottom: 0.5rem;
}

.page-header h1 i {
    color: #ff4757;
}

.no-results {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.no-results h3 {
    color: #333;
    margin-bottom: 1rem;
}

.no-results p {
    color: #666;
    margin-bottom: 2rem;
}
</style>

<?php
require_once '../includes/footer.php';
?>