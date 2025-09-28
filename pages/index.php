<?php
$pageTitle = "Home";
require_once '../includes/config.php';
require_once '../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Buy, Sell & Exchange Textbooks with Fellow Students</h1>
            <p>Save money on textbooks and help reduce waste by trading with your campus community.</p>
            <div class="hero-buttons">
                <a href="listings.php" class="btn btn-primary">Browse Listings</a>
                <a href="create-listing.php" class="btn btn-secondary">Sell Your Books</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="../assets/images/hero-books.jpg" alt="Books">
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2>Why Use CampusBookSwap?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3>Save Money</h3>
                <p>Buy textbooks at a fraction of the bookstore price and earn cash from books you no longer need.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-recycle"></i>
                </div>
                <h3>Eco-Friendly</h3>
                <p>Reduce paper waste and extend the life of textbooks by buying and selling within your campus.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Community Driven</h3>
                <p>Connect directly with fellow students for safe, convenient transactions on campus.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Platform</h3>
                <p>Built-in messaging, user ratings, and admin moderation ensure a safe trading experience.</p>
            </div>
        </div>
    </div>
</section>

<section class="recent-listings">
    <div class="container">
        <h2>Recently Added Textbooks</h2>
        
        <?php
        // Fetch recent listings
        $stmt = $pdo->query("
            SELECT l.*, b.title, b.author, u.username, 
                   (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM listings l
            JOIN books b ON l.book_id = b.id
            JOIN users u ON l.seller_id = u.id
            WHERE l.status = 'available'
            ORDER BY l.created_at DESC
            LIMIT 8
        ");
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="listings-grid">
            <?php if (count($listings) > 0): ?>
                <?php foreach ($listings as $listing): ?>
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
                            <p class="author">by <?php echo $listing['author']; ?></p>
                            <p class="price"><?php echo formatPrice($listing['price']); ?></p>
                            <div class="listing-meta">
                                <span class="seller">Seller: <?php echo $listing['username']; ?></span>
                            </div>
                            <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No listings available at the moment.</p>
            <?php endif; ?>
        </div>
        
        <div class="view-all">
            <a href="listings.php" class="btn btn-primary">View All Listings</a>
        </div>
    </div>
</section>

<section class="testimonials">
    <div class="container">
        <h2>What Students Are Saying</h2>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"I saved over $200 on textbooks this semester using CampusBookSwap. The process was easy and I met some great people!"</p>
                </div>
                <div class="testimonial-author">
                    <h4>Sarah M.</h4>
                    <p>Biology Major</p>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"Sold all my old textbooks in just a week. The messaging system made it easy to coordinate meetups on campus."</p>
                </div>
                <div class="testimonial-author">
                    <h4>James K.</h4>
                    <p>Engineering Student</p>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"As an international student, this platform helped me find affordable textbooks that weren't available in my home country."</p>
                </div>
                <div class="testimonial-author">
                    <h4>Ling W.</h4>
                    <p>International Student</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2>Ready to Get Started?</h2>
        <p>Join thousands of students who are saving money and helping the environment.</p>
        <div class="cta-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="create-listing.php" class="btn btn-large btn-primary">List Your Books</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-large btn-primary">Create Account</a>
            <?php endif; ?>
            <a href="listings.php" class="btn btn-large btn-secondary">Browse Textbooks</a>
        </div>
    </div>
</section>

<?php
require_once '../includes/footer.php';
?>