<?php
$pageTitle = "My Listings";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ensure user is a seller
if (!isSeller()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$search_query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build the SQL query
$sql = "
    SELECT l.*, b.title, b.author, b.isbn, b.course_code,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    WHERE l.seller_id = ?
";
$params = [$user_id];

// Add search conditions
if (!empty($search_query)) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.course_code LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM ($sql) as results";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $limit);

// Add sorting
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY l.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY l.price DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY l.created_at ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY l.created_at DESC";
        break;
}

$sql .= " LIMIT $limit OFFSET $offset";

// Execute the final query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="listings-page">
    <div class="container">
        <h1>My Listings</h1>
        
        <div class="listings-container">
            <div class="listings-sidebar">
                <div class="filter-card">
                    <h3>Search My Listings</h3>
                    <form method="GET" action="">
                        <div class="form-group">
                            <label for="q" class="form-label">Search</label>
                            <input type="text" id="q" name="q" class="form-control" value="<?php echo $search_query; ?>" placeholder="Title, author, ISBN, or course">
                        </div>
                        
                        <div class="form-group">
                            <label for="sort" class="form-label">Sort By</label>
                            <select id="sort" name="sort" class="form-select">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">Apply</button>
                        <a href="my-listings.php" class="btn btn-secondary btn-full">Reset</a>
                    </form>
                </div>
            </div>
            
            <div class="listings-content">
                <div class="listings-header">
                    <p><?php echo $total_results; ?> listing<?php echo $total_results != 1 ? 's' : ''; ?> found</p>
                    <div class="view-options">
                        <span>View:</span>
                        <button class="view-btn active" data-view="grid"><i class="fas fa-th"></i></button>
                        <button class="view-btn" data-view="list"><i class="fas fa-list"></i></button>
                    </div>
                </div>
                
                <div class="listings-grid" id="listings-view">
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
                                    <?php if ($listing['course_code']): ?>
                                        <p class="course">Course: <?php echo $listing['course_code']; ?></p>
                                    <?php endif; ?>
                                    <p class="price"><?php echo formatPrice($listing['price']); ?></p>
                                    <span class="status-badge status-<?php echo $listing['status']; ?>">
                                        <?php echo ucfirst($listing['status']); ?>
                                    </span>
                                    <div class="listing-actions">
                                        <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small">View</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-box-open"></i>
                            <h3>No listings yet</h3>
                            <p>You haven't created any listings yet. Start selling by adding a new one!</p>
                            <a href="create-listing.php" class="btn btn-primary">Create Listing</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-btn');
    const listingsView = document.getElementById('listings-view');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            viewButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            listingsView.className = 'listings-' + this.dataset.view;
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
