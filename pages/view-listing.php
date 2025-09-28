<?php
$pageTitle = "View Listing";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if listing ID is provided
if (!isset($_GET['id'])) {
    header('Location: listings.php');
    exit();
}

$listing_id = intval($_GET['id']);

// Get listing details
$stmt = $pdo->prepare("
    SELECT l.*, b.*, u.*, c.name as category_name,
           (SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id) as seller_rating,
           (SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id) as seller_reviews
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

// Handle message form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $message = sanitize($_POST['message']);
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty.";
    } else {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, listing_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $listing['seller_id'], $listing_id, $message]);
        
        $_SESSION['success'] = "Message sent successfully!";
        header('Location: messages.php?conversation=' . $listing_id);
        exit();
    }
}
?>

<div class="listing-detail-container">
    <div class="container">
        <nav class="breadcrumb">
            <a href="listings.php">Browse Listings</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo $listing['title']; ?></span>
        </nav>
        
        <div class="listing-detail">
            <div class="listing-gallery">
                <?php if (count($images) > 0): ?>
                    <div class="main-image">
                        <img src="../assets/images/uploads/books/<?php echo $images[0]['image_path']; ?>" alt="<?php echo $listing['title']; ?>">
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
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="listing-info">
                <div class="listing-header">
                    <h1><?php echo $listing['title']; ?></h1>
                    <p class="author">by <?php echo $listing['author']; ?></p>
                    
                    <div class="listing-meta">
                        <span class="condition-badge"><?php echo getConditionText($listing['condition']); ?></span>
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
                    
                    <?php if ($listing['description']): ?>
                        <div class="detail-group">
                            <h3>Book Description</h3>
                            <p><?php echo $listing['description']; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($listing['description']): ?>
                        <div class="detail-group">
                            <h3>Seller Notes</h3>
                            <p><?php echo $listing['description']; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="seller-info">
                    <h3>Seller Information</h3>
                    <div class="seller-card">
                        <div class="seller-header">
                            <div class="seller-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="seller-details">
                                <h4><?php echo $listing['username']; ?></h4>
                                <div class="seller-rating">
                                    <?php
                                    $rating = $listing['seller_rating'] ? round($listing['seller_rating'], 1) : 0;
                                    $reviews = $listing['seller_reviews'] ?: 0;
                                    ?>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= round($rating) ? 'active' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span>(<?php echo $rating; ?> · <?php echo $reviews; ?> reviews)</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($listing['university']): ?>
                            <div class="seller-meta">
                                <i class="fas fa-university"></i>
                                <span><?php echo $listing['university']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="seller-actions">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if ($_SESSION['user_id'] != $listing['seller_id']): ?>
                                    <a href="#contact-seller" class="btn btn-primary">Contact Seller</a>
                                    <a href="../api/chat.php?action=start&listing_id=<?php echo $listing_id; ?>&seller_id=<?php echo $listing['seller_id']; ?>" class="btn btn-secondary">Message</a>
                                <?php else: ?>
                                    <a href="create-listing.php?edit=<?php echo $listing_id; ?>" class="btn btn-primary">Edit Listing</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary">Login to Contact Seller</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id']): ?>
    <a href="report.php?listing_id=<?php echo $listing['id']; ?>&user_id=<?php echo $listing['seller_id']; ?>" class="btn btn-small btn-warning">
        <i class="fas fa-flag"></i> Report Listing
    </a>
<?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id']): ?>
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

<?php
require_once '../includes/footer.php';
?>