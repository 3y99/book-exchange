<?php
$pageTitle = "Browse Listings";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Get search parameters
$search_query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
$condition = isset($_GET['condition']) ? $_GET['condition'] : '';
$course_code = isset($_GET['course']) ? sanitize($_GET['course']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build the SQL query
$sql = "
    SELECT l.*, b.title, b.author, b.isbn, b.course_code, 
           u.username, u.university,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.seller_id = u.id
    WHERE l.status = 'available'
";

$params = [];

// Add search conditions
if (!empty($search_query)) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.course_code LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category_id) && $category_id != 'all') {
    $sql .= " AND b.category_id = ?";
    $params[] = $category_id;
}

if (!empty($condition) && $condition != 'all') {
    $sql .= " AND l.book_condition = ?";
    $params[] = $condition;
}



if (!empty($course_code)) {
    $sql .= " AND b.course_code LIKE ?";
    $params[] = "%$course_code%";
}

$sql .= " AND l.price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as results";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $limit);

// Add sorting and pagination
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

// Execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="listings-page">
    <div class="container">
        <h1>Browse Textbooks</h1>
        
        <div class="listings-container">
            <div class="listings-sidebar">
                <div class="filter-card">
                    <h3>Filters</h3>
                    <form method="GET" action="">
                        <div class="form-group">
                            <label for="q" class="form-label">Search</label>
                            <input type="text" id="q" name="q" class="form-control" value="<?php echo $search_query; ?>" placeholder="Title, author, ISBN, or course">
                        </div>
                        
                        <div class="form-group">
                            <label for="category" class="form-label">Category</label>
                            <select id="category" name="category" class="form-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="condition" class="form-label">Condition</label>
                            <select id="condition" name="condition" class="form-select">
                                <option value="all">All Conditions</option>
                                <option value="new" <?php echo $condition == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="like_new" <?php echo $condition == 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                <option value="good" <?php echo $condition == 'good' ? 'selected' : ''; ?>>Good</option>
                                <option value="fair" <?php echo $condition == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                <option value="poor" <?php echo $condition == 'poor' ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="course" class="form-label">Course Code</label>
                            <input type="text" id="course" name="course" class="form-control" value="<?php echo $course_code; ?>" placeholder="e.g., COMP101">
                        </div>
                        
                        <div class="form-group">
                            <label for="min_price" class="form-label">Price Range</label>
                            <div class="price-range">
                                <input type="number" id="min_price" name="min_price" class="form-control" min="0" step="0.01" value="<?php echo $min_price; ?>" placeholder="Min">
                                <span>to</span>
                                <input type="number" id="max_price" name="max_price" class="form-control" min="0" step="0.01" value="<?php echo $max_price; ?>" placeholder="Max">
                            </div>
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
                        
                        <button type="submit" class="btn btn-primary btn-full">Apply Filters</button>
                        <a href="listings.php" class="btn btn-secondary btn-full">Reset Filters</a>
                    </form>
                </div>
            </div>
            
            <div class="listings-content">
                <div class="listings-header">
                    <p><?php echo $total_results; ?> results found</p>
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
                                    <div class="listing-meta">
                                        <span class="seller">Seller: <?php echo $listing['username']; ?></span>
                                        <span class="university"><?php echo $listing['university']; ?></span>
                                    </div>
                                    <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-search"></i>
                            <h3>No listings found</h3>
                            <p>Try adjusting your search filters or browse all listings.</p>
                            <a href="listings.php" class="btn btn-primary">Browse All</a>
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