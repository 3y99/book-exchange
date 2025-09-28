<?php
$pageTitle = "Profile";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id    = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $user_id;

/**
 * Fetch profile with stats
 */
function getUserProfile($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COALESCE((SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id), 0) as avg_rating,
               COALESCE((SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id), 0) as review_count,
               COALESCE((SELECT COUNT(*) FROM listings WHERE seller_id = u.id), 0) as listing_count,
               COALESCE((SELECT COUNT(*) FROM transactions WHERE seller_id = u.id AND status = 'completed'), 0) as completed_sales,
               COALESCE((SELECT COUNT(*) FROM transactions WHERE buyer_id = u.id AND status = 'completed'), 0) as completed_purchases
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        // Normalize nullable fields
        $profile['first_name']       = $profile['first_name'] ?? '';
        $profile['last_name']        = $profile['last_name'] ?? '';
        $profile['university']       = $profile['university'] ?? '';
        $profile['phone']            = $profile['phone'] ?? '';
        $profile['bio']              = $profile['bio'] ?? '';
        $profile['profile_picture']  = $profile['profile_picture'] ?? '';
        $profile['username']         = $profile['username'] ?? 'Unknown';
    }

    return $profile;
}

// Get user profile
$profile = getUserProfile($pdo, $profile_id);

if (!$profile) {
    header('Location: dashboard.php');
    exit();
}

// Get user's active listings
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    WHERE l.seller_id = ? AND l.status = 'available'
    ORDER BY l.created_at DESC
    LIMIT 6
");
$stmt->execute([$profile_id]);
$user_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user reviews
$stmt = $pdo->prepare("
    SELECT r.*, reviewer.username as reviewer_name, b.title as listing_title
    FROM reviews r
    JOIN users reviewer ON r.reviewer_id = reviewer.id
    JOIN transactions t ON r.transaction_id = t.id
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$profile_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update (if editing own profile)
$is_own_profile = ($user_id == $profile_id);
$update_success = false;
$update_errors  = [];

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? sanitize($_POST['first_name']) : $profile['first_name'];
    $last_name  = isset($_POST['last_name']) ? sanitize($_POST['last_name']) : $profile['last_name'];
    $university = isset($_POST['university']) ? sanitize($_POST['university']) : $profile['university'];
    $phone      = isset($_POST['phone']) ? sanitize($_POST['phone']) : $profile['phone'];
    $bio        = isset($_POST['bio']) ? sanitize($_POST['bio']) : $profile['bio'];

    // Validate input only if text fields are submitted
    if (isset($_POST['first_name']) && empty($first_name)) {
        $update_errors[] = "First name is required.";
    }
    if (isset($_POST['last_name']) && empty($last_name)) {
        $update_errors[] = "Last name is required.";
    }

    // Handle profile picture upload
    $profile_picture = $profile['profile_picture'];
    if (!empty($_FILES['profile_picture']['name'])) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $result = uploadImage($file, __DIR__ . '/../' . PROFILE_IMAGE_PATH);
            if (is_array($result) && isset($result['errors'])) {
                $update_errors = array_merge($update_errors, $result['errors']);
            } else {
                // Delete old picture if exists
                $oldFile = __DIR__ . '/../' . PROFILE_IMAGE_PATH . $profile['profile_picture'];
                if ($profile['profile_picture'] && file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $profile_picture = $result;
            }
        }
    }

    if (empty($update_errors)) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, university = ?, phone = ?, bio = ?, profile_picture = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$first_name, $last_name, $university, $phone, $bio, $profile_picture, $user_id])) {
            $update_success = true;
            $profile = getUserProfile($pdo, $user_id); // refresh full profile
        } else {
            $update_errors[] = "An error occurred. Please try again.";
        }
    }
}
?>

<div class="profile-container">
    <div class="container">
        <div class="profile-content">
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-image">
                        <?php if ($profile['profile_picture']): ?>
                            <img src="../assets/images/uploads/profiles/<?php echo $profile['profile_picture']; ?>" alt="<?php echo $profile['username']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <?php if ($is_own_profile): ?>
                            <form method="POST" action="" enctype="multipart/form-data" class="profile-picture-form">
                                <label for="profile_picture" class="edit-picture-btn">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                                </label>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="profile-info">
                        <h2><?php echo $profile['first_name'] . ' ' . $profile['last_name']; ?></h2>
                        <p class="username">@<?php echo $profile['username']; ?></p>
                        <?php if ($profile['university']): ?>
                            <p class="university"><i class="fas fa-university"></i> <?php echo $profile['university']; ?></p>
                        <?php endif; ?>

                        <div class="profile-stats">
                            <div class="stat"><span class="number"><?php echo $profile['listing_count']; ?></span><span class="label">Listings</span></div>
                            <div class="stat"><span class="number"><?php echo $profile['completed_sales']; ?></span><span class="label">Sales</span></div>
                            <div class="stat"><span class="number"><?php echo $profile['completed_purchases']; ?></span><span class="label">Purchases</span></div>
                        </div>

                        <div class="profile-rating">
                            <div class="stars">
                                <?php $rating = $profile['avg_rating'] ? round($profile['avg_rating'], 1) : 0;
                                for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($rating) ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span>(<?php echo $rating; ?> · <?php echo $profile['review_count']; ?> reviews)</span>
                        </div>
                    </div>

                    <?php if ($is_own_profile): ?>
                        <div class="profile-actions">
                            <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                            <a href="logout.php" class="btn btn-secondary">Logout</a>
                        </div>
                    <?php else: ?>
                        <div class="profile-actions">
                            <a href="../api/chat.php?action=start&user_id=<?php echo $profile_id; ?>" class="btn btn-primary">Message</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($profile['bio']): ?>
                    <div class="bio-card">
                        <h3>About</h3>
                        <p><?php echo $profile['bio']; ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_id): ?>
    <a href="report.php?user_id=<?php echo $profile_id; ?>" class="btn btn-small btn-warning">
        <i class="fas fa-flag"></i> Report User
    </a>
<?php endif; ?>

            <div class="profile-main">
                <?php if ($is_own_profile): ?>
                    <div class="profile-section">
                        <div class="section-header"><h2>Edit Profile</h2></div>
                        <?php if ($update_success): ?><div class="alert alert-success">Profile updated successfully!</div><?php endif; ?>
                        <?php if (!empty($update_errors)): ?>
                            <div class="alert alert-error"><ul><?php foreach ($update_errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo $profile['first_name']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo $profile['last_name']; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="university" class="form-label">University</label>
                                <input type="text" id="university" name="university" class="form-control" value="<?php echo $profile['university']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $profile['phone']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo $profile['bio']; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="profile-section">
                    <div class="section-header">
                        <h2>Listings</h2>
                        <?php if ($is_own_profile): ?><a href="create-listing.php" class="btn btn-small btn-primary">Create New Listing</a><?php endif; ?>
                    </div>
                    <?php if (count($user_listings) > 0): ?>
                        <div class="listings-grid mini">
                            <?php foreach ($user_listings as $listing): ?>
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
                                        <span class="status-badge status-<?php echo $listing['status']; ?>"><?php echo ucfirst($listing['status']); ?></span>
                                        <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small">View</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($profile['listing_count'] > 6): ?>
                            <div class="view-all"><a href="listings.php?user=<?php echo $profile_id; ?>" class="btn btn-secondary">View All Listings</a></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No listings found.</p>
                    <?php endif; ?>
                </div>

                <?php if (count($reviews) > 0): ?>
                    <div class="profile-section">
                        <div class="section-header"><h2>Reviews</h2></div>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer">
                                            <strong><?php echo $review['reviewer_name']; ?></strong>
                                            <div class="stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <span class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <?php if ($review['listing_title']): ?><p class="review-listing">For: <?php echo $review['listing_title']; ?></p><?php endif; ?>
                                    <?php if ($review['comment']): ?><p class="review-comment">"<?php echo $review['comment']; ?>"</p><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
