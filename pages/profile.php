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
               COALESCE((SELECT COUNT(*) FROM purchases WHERE seller_id = u.id AND status = 'completed'), 0) as completed_sales,
               COALESCE((SELECT COUNT(*) FROM purchases WHERE buyer_id = u.id AND status = 'completed'), 0) as completed_purchases,
               COALESCE((SELECT COUNT(*) FROM reports WHERE reported_user_id = u.id), 0) as times_reported,
               COALESCE((SELECT COUNT(*) FROM reports r 
                         JOIN listings l ON r.reported_listing_id = l.id 
                         WHERE l.seller_id = u.id), 0) as listings_reported_count
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
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

// Check if current user is admin
$is_admin = isAdmin();
$is_own_profile = ($user_id == $profile_id);

// Handle profile update
$update_success = false;
$update_errors  = [];

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? sanitize($_POST['first_name']) : $profile['first_name'];
    $last_name  = isset($_POST['last_name']) ? sanitize($_POST['last_name']) : $profile['last_name'];
    $university = isset($_POST['university']) ? sanitize($_POST['university']) : $profile['university'];
    $phone      = isset($_POST['phone']) ? sanitize($_POST['phone']) : $profile['phone'];
    $bio        = isset($_POST['bio']) ? sanitize($_POST['bio']) : $profile['bio'];

    if (isset($_POST['first_name']) && empty($first_name)) {
        $update_errors[] = "First name is required.";
    }
    if (isset($_POST['last_name']) && empty($last_name)) {
        $update_errors[] = "Last name is required.";
    }

    $profile_picture = $profile['profile_picture'];
    if (!empty($_FILES['profile_picture']['name'])) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $result = uploadImage($file, __DIR__ . '/../' . PROFILE_IMAGE_PATH);
            if (is_array($result) && isset($result['errors'])) {
                $update_errors = array_merge($update_errors, $result['errors']);
            } else {
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
            $profile = getUserProfile($pdo, $user_id);
        } else {
            $update_errors[] = "An error occurred. Please try again.";
        }
    }
}

// Handle admin actions (ban/unban user)
if ($is_admin && !$is_own_profile && isset($_POST['admin_action'])) {
    if ($_POST['admin_action'] == 'toggle_ban') {
        // Check if users table has is_banned column, if not, add it
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'is_banned'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            // Add is_banned column if it doesn't exist
            $pdo->exec("ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE");
        }
        
        $new_ban_status = $profile['is_banned'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ?");
        if ($stmt->execute([$new_ban_status, $profile_id])) {
            $_SESSION['success'] = "User " . ($new_ban_status ? "banned" : "unbanned") . " successfully.";
            $profile = getUserProfile($pdo, $profile_id); // Refresh profile data
        } else {
            $_SESSION['error'] = "Failed to update user ban status.";
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
                        
                        <!-- User Rating -->
                        <div class="user-rating">
                            <div class="rating-stars">
                                <?php
                                $avg_rating = $profile['avg_rating'];
                                $full_stars = floor($avg_rating);
                                $half_star = ($avg_rating - $full_stars) >= 0.5;
                                $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                                
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '<i class="fas fa-star"></i>';
                                }
                                if ($half_star) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                }
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <span class="rating-text">(<?php echo number_format($avg_rating, 1); ?> · <?php echo $profile['review_count']; ?> reviews)</span>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <?php if ($is_own_profile): ?>
                            <!-- Own profile actions -->
                        <?php else: ?>
                            <?php if ($is_admin): ?>
                                <!-- Admin actions for other users -->
                                <form method="POST" action="" class="admin-action-form" onsubmit="return confirm('Are you sure you want to <?php echo $profile['is_banned'] ? 'unban' : 'ban'; ?> this user?')">
                                    <input type="hidden" name="admin_action" value="toggle_ban">
                                    <button type="submit" class="btn <?php echo $profile['is_banned'] ? 'btn-success' : 'btn-warning'; ?>">
                                        <i class="fas fa-<?php echo $profile['is_banned'] ? 'check' : 'ban'; ?>"></i>
                                        <?php echo $profile['is_banned'] ? 'Unban User' : 'Ban User'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($profile['bio']): ?>
                    <div class="bio-card">
                        <h3>About</h3>
                        <p><?php echo $profile['bio']; ?></p>
                    </div>
                <?php endif; ?>

                <!-- User Stats -->
                <div class="stats-card">
                    <h3>User Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $profile['listing_count']; ?></span>
                            <span class="stat-label">Listings</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $profile['completed_sales']; ?></span>
                            <span class="stat-label">Sales</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $profile['completed_purchases']; ?></span>
                            <span class="stat-label">Purchases</span>
                        </div>
                    </div>
                </div>

                <?php if ($is_admin && !$is_own_profile): ?>
                    <!-- Admin Only Stats -->
                    <div class="admin-stats-card">
                        <h3><i class="fas fa-shield-alt"></i> Admin Information</h3>
                        <div class="admin-stats-grid">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $profile['times_reported']; ?></span>
                                <span class="stat-label">User Reports</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $profile['listings_reported_count']; ?></span>
                                <span class="stat-label">Listings Reported</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-status <?php echo $profile['is_banned'] ? 'status-banned' : 'status-active'; ?>">
                                    <?php echo $profile['is_banned'] ? 'Banned' : 'Active'; ?>
                                </span>
                                <span class="stat-label">Account Status</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_own_profile && !$is_admin): ?>
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
                <?php else: ?>
                    <!-- View other user's profile -->
                    <div class="profile-section">
                        <div class="section-header">
                            <h2>Profile Information</h2>
                        </div>
                        <div class="profile-details">
                            <div class="detail-row">
                                <strong>Name:</strong>
                                <span><?php echo $profile['first_name'] . ' ' . $profile['last_name']; ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Username:</strong>
                                <span>@<?php echo $profile['username']; ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Email:</strong>
                                <span><?php echo $profile['email']; ?></span>
                            </div>
                            <?php if ($profile['university']): ?>
                            <div class="detail-row">
                                <strong>University:</strong>
                                <span><?php echo $profile['university']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($profile['phone']): ?>
                            <div class="detail-row">
                                <strong>Phone:</strong>
                                <span><?php echo $profile['phone']; ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <strong>Member Since:</strong>
                                <span><?php echo date('M j, Y', strtotime($profile['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>