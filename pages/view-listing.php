<?php
$pageTitle = "View Listing";
require_once '../includes/config.php';

// Check if listing ID is provided
if (!isset($_GET['id'])) {
    header('Location: listings.php');
    exit();
}

$listing_id = intval($_GET['id']);

// Handle watchlist actions - must be before any output
if (isset($_SESSION['user_id']) && isset($_POST['watchlist_action'])) {
    if ($_POST['watchlist_action'] === 'add') {
        addToWatchlist($_SESSION['user_id'], $listing_id);
        $_SESSION['success'] = "Added to watchlist!";
    } elseif ($_POST['watchlist_action'] === 'remove') {
        removeFromWatchlist($_SESSION['user_id'], $listing_id);
        $_SESSION['success'] = "Removed from watchlist!";
    }
    header('Location: view-listing.php?id=' . $listing_id);
    exit();
}

// Handle message form submission - must be before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_POST['message'])) {
    $message = sanitize($_POST['message']);

    if (empty($message)) {
        $errors[] = "Message cannot be empty.";
    } else {
        // Get seller_id first since we don't have $listing yet
        $stmt = $pdo->prepare("SELECT seller_id FROM listings WHERE id = ?");
        $stmt->execute([$listing_id]);
        $listing_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($listing_data) {
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, listing_id, message) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $listing_data['seller_id'], $listing_id, $message]);

            $_SESSION['success'] = "Message sent successfully!";
            header('Location: messages.php?conversation=' . $listing_id);
            exit();
        }
    }
}

// Now include header after handling redirects
require_once '../includes/header.php';

// Get listing details
$stmt = $pdo->prepare("
    SELECT 
        l.id AS listing_id,
        l.seller_id,
        l.price,
        l.status,
        l.book_id,
        l.created_at,
        l.description AS listing_description,
        b.title,
        b.author,
        b.isbn,
        b.course_code,
        b.description AS book_description,
        b.category_id,
        c.name AS category_name,
        u.id AS seller_user_id,
        u.username,
        u.first_name,
        u.last_name,
        u.university,
        (SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id) AS seller_rating,
        (SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id) AS seller_reviews
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.seller_id = u.id
    JOIN categories c ON b.category_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    header('Location: listings.php');
    exit();
}

// Get listing images
$stmt = $pdo->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC, id ASC");
$stmt->execute([$listing_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get similar listings
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, u.username,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.seller_id = u.id
    WHERE l.id != ? AND (b.category_id = ? OR b.course_code = ?) AND l.status = 'available'
    ORDER BY l.created_at DESC
    LIMIT 4
");
$stmt->execute([$listing_id, $listing['category_id'], $listing['course_code']]);
$similar_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if listing is in user's watchlist
$in_watchlist = false;
if (isset($_SESSION['user_id'])) {
    $in_watchlist = isInWatchlist($_SESSION['user_id'], $listing_id);
}

// Fetch reviews for this specific listing
$stmt = $pdo->prepare("
    SELECT r.rating, r.comment, r.created_at, u.username
    FROM reviews r
    JOIN purchases p ON r.purchase_id = p.id
    JOIN users u ON r.reviewer_id = u.id
    WHERE p.listing_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$listing_id]);
$listing_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$listing_review_count = count($listing_reviews);
?>

<div class="listing-detail-container">
    <div class="container">
        <nav class="breadcrumb">
            <a href="listings.php">Browse Listings</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo $listing['title']; ?></span>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="listing-detail">
            <div class="listing-gallery">
                <?php if (count($images) > 0): ?>
                    <div class="main-image">
                        <img src="../assets/images/uploads/books/<?php echo $images[0]['image_path']; ?>" alt="<?php echo $listing['title']; ?>">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id'] && !isAdmin()): ?>
                            <form method="POST" class="watchlist-form">
                                <input type="hidden" name="watchlist_action" value="<?php echo $in_watchlist ? 'remove' : 'add'; ?>">
                                <button type="submit" class="watchlist-btn <?php echo $in_watchlist ? 'in-watchlist' : ''; ?>" title="<?php echo $in_watchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($images as $image): ?>
                                <div class="thumbnail">
                                    <img src="../assets/images/uploads/books/<?php echo $image['image_path']; ?>" alt="<?php echo $listing['title']; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="main-image">
                        <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id'] && !isAdmin()): ?>
                            <form method="POST" class="watchlist-form">
                                <input type="hidden" name="watchlist_action" value="<?php echo $in_watchlist ? 'remove' : 'add'; ?>">
                                <button type="submit" class="watchlist-btn <?php echo $in_watchlist ? 'in-watchlist' : ''; ?>" title="<?php echo $in_watchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="listing-info">
                <div class="listing-header">
                    <h1><?php echo $listing['title']; ?></h1>
                    <p class="author">by <?php echo $listing['author']; ?></p>

                    <div class="listing-meta">
                        <span class="condition-badge"><?php echo getConditionText($listing['book_condition']); ?></span>
                        <span class="status-badge status-<?php echo $listing['status']; ?>">
                            <?php echo ucfirst($listing['status']); ?>
                        </span>
                    </div>

                    <p class="price"><?php echo formatPrice($listing['price']); ?></p>
                </div>

                <div class="listing-details">
                    <div class="detail-group">
                        <h3>Details</h3>
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="label">Category:</span>
                                <span class="value"><?php echo $listing['category_name']; ?></span>
                            </div>

                            <?php if ($listing['course_code']): ?>
                                <div class="detail-item">
                                    <span class="label">Course Code:</span>
                                    <span class="value"><?php echo $listing['course_code']; ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($listing['isbn']): ?>
                                <div class="detail-item">
                                    <span class="label">ISBN:</span>
                                    <span class="value"><?php echo $listing['isbn']; ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="detail-item">
                                <span class="label">Listed:</span>
                                <span class="value"><?php echo date('F j, Y', strtotime($listing['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($listing['book_description']): ?>
                        <div class="detail-group">
                            <h3>Book Description</h3>
                            <p><?php echo $listing['book_description']; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($listing['listing_description']): ?>
                        <div class="detail-group">
                            <h3>Seller Notes</h3>
                            <p><?php echo $listing['listing_description']; ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="seller-info">
                    <h3>Seller Information</h3>
                    <div class="seller-card">
                         <?php if ($listing['university']): ?>
                            <div class="seller-meta">
                                <i class="fas fa-university"></i>
                                <span><?php echo $listing['university']; ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="seller-header">
                            <div class="seller-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="seller-details">
                                <h4><?php echo $listing['username']; ?></h4>
                                <div class="seller-rating">
                                    <!-- Button showing review count -->
                                   <button class="btn btn-outline" id="toggle-reviews-btn">
                                        <?php echo $listing_review_count; ?> reviews
                                    </button>
                                </div>
                                 <div id="seller-reviews-container" style="display:none; margin-top:1rem;">
    <?php if ($listing_review_count > 0): ?>
        <?php foreach ($listing_reviews as $r): ?>
            <div class="review-item">
                <strong><?php echo htmlspecialchars($r['username']); ?></strong>
                <div class="stars">
                    <?php for ($i = 1; $i <= $r['rating']; $i++): ?>
                        <i class="fas fa-star active" style="color:#facc15;"></i>
                    <?php endfor; ?>
                </div>
                <p><?php echo htmlspecialchars($r['comment']); ?></p>
                <small><?php echo date('M j, Y', strtotime($r['created_at'])); ?></small>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews yet.</p>
    <?php endif; ?>
</div>
                            </div>
                        </div>

                       
                        <div class="seller-actions-row">
                            <?php if (!isAdmin()): ?>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <!-- Logged in user actions -->
                                    <?php if ($_SESSION['user_id'] != $listing['seller_id']): ?>
                                        <!-- Other users see contact and cart buttons -->
                                        <a href="#contact-seller" class="btn btn-primary">Contact Seller</a>

                                        <?php if ($listing['status'] === 'available'): ?>
                                            <?php if (isInCart($_SESSION['user_id'], $listing_id)): ?>
                                                <form method="POST" action="cart.php" class="cart-form-inline">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                                                    <button type="submit" class="btn btn-outline">
                                                        <i class="fas fa-shopping-cart"></i> Remove from Cart
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="cart.php" class="cart-form-inline">
                                                    <input type="hidden" name="action" value="add">
                                                    <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($in_watchlist): ?>
                                            <form method="POST" class="watchlist-form-inline">
                                                <input type="hidden" name="watchlist_action" value="remove">
                                                <button type="submit" class="btn btn-outline">
                                                    <i class="fas fa-heart"></i> Remove from Watchlist
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="watchlist-form-inline">
                                                <input type="hidden" name="watchlist_action" value="add">
                                                <button type="submit" class="btn btn-outline">
                                                    <i class="far fa-heart"></i> Add to Watchlist
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="report.php?listing_id=<?php echo $listing['listing_id']; ?>&user_id=<?php echo $listing['seller_user_id']; ?>" 
                                        class="btn btn-warning">
                                        <i class="fas fa-flag"></i> Report Listing
                                        </a>

                                    <?php else: ?>
                                        <!-- Seller sees edit button -->
                                        <a href="create-listing.php?edit=<?php echo $listing['listing_id']; ?>" class="btn btn-primary">
                                             Edit Listing
                                        </a>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <!-- Not logged in user actions -->
                                    <a href="login.php?redirect=view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-primary">
                                        Contact Seller
                                    </a>

                                    <?php if ($listing['status'] === 'available'): ?>
                                        <a href="login.php?redirect=view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-success">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </a>
                                    <?php endif; ?>

                                    <a href="login.php?redirect=view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-outline">
                                        <i class="far fa-heart"></i> Add to Watchlist
                                    </a>

                                    <a href="login.php?redirect=view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-warning">
                                        <i class="fas fa-flag"></i> Report Listing
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id'] && !isAdmin()): ?>
                    <div id="contact-seller" class="contact-seller">
                        <h2>Contact Seller</h2>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="message" class="form-label">Message</label>
                                <textarea id="message" name="message" class="form-control" rows="4" placeholder="Ask about this book, negotiate price, or arrange pickup..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (count($similar_listings) > 0): ?>
                    <div class="similar-listings">
                        <h2>Similar Listings</h2>
                        <div class="listings-grid">
                            <?php foreach ($similar_listings as $similar): ?>
                                <div class="listing-card">
                                    <div class="listing-image">
                                        <?php if ($similar['primary_image']): ?>
                                            <img src="../assets/images/uploads/books/<?php echo $similar['primary_image']; ?>" alt="<?php echo $similar['title']; ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                                        <?php endif; ?>
                                        <span class="condition-badge"><?php echo getConditionText($similar['book_condition']); ?></span>
                                    </div>
                                    <div class="listing-details">
                                        <h3><?php echo $similar['title']; ?></h3>
                                        <p class="author">by <?php echo $similar['author']; ?></p>
                                        <p class="price"><?php echo formatPrice($similar['price']); ?></p>
                                        <div class="listing-meta">
                                            <span class="seller">Seller: <?php echo $similar['username']; ?></span>
                                        </div>
                                        <a href="view-listing.php?id=<?php echo $similar['id']; ?>" class="btn btn-small">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Reviews Toggle Button */
#toggle-reviews-btn {
    margin-top: 0.5rem;
}

/* Seller Reviews */
#seller-reviews-container .review-item {
    border-bottom: 1px solid #eee;
    padding: 0.5rem 0;
}
#seller-reviews-container .stars i.active {
    color: #facc15;
}

/* Seller Actions Row */
.seller-actions-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}
.seller-actions-row .btn,
.seller-actions-row form {
    margin: 0;
}

/* Inline forms buttons */
.cart-form-inline button,
.watchlist-form-inline button {
    margin: 0;
}
</style>

<script>
document.getElementById('toggle-reviews-btn')?.addEventListener('click', function() {
    const container = document.getElementById('seller-reviews-container');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
});
</script>

<?php
require_once '../includes/footer.php';
?>