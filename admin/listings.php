<?php
$pageTitle = "Admin - Manage Listings";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../pages/login.php');
    exit();
}

// Handle listing actions (approve, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $listingId = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $_SESSION['success'] = "Listing deleted successfully.";
    } elseif ($action == 'toggle_status') {
        $stmt = $pdo->prepare("SELECT status FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newStatus = $listing['status'] == 'available' ? 'sold' : 'available';
        $stmt = $pdo->prepare("UPDATE listings SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $listingId]);
        
        $_SESSION['success'] = "Listing status updated successfully.";
    }
    
    header('Location: listings.php');
    exit();
}

// Handle search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$condition_filter = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_at_desc';

// Build the query with filters
$query = "
    SELECT l.*, b.title, b.author, b.id as book_id, u.username, u.email 
    FROM listings l 
    JOIN books b ON l.book_id = b.id 
    JOIN users u ON l.seller_id = u.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status_filter)) {
    $query .= " AND l.status = ?";
    $params[] = $status_filter;
}

if (!empty($condition_filter)) {
    $query .= " AND l.book_condition = ?";
    $params[] = $condition_filter;
}

// Add sorting
switch ($sort) {
    case 'title_asc':
        $query .= " ORDER BY b.title ASC";
        break;
    case 'title_desc':
        $query .= " ORDER BY b.title DESC";
        break;
    case 'author_asc':
        $query .= " ORDER BY b.author ASC";
        break;
    case 'author_desc':
        $query .= " ORDER BY b.author DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY l.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY l.price DESC";
        break;
    case 'created_at_asc':
        $query .= " ORDER BY l.created_at ASC";
        break;
    case 'created_at_desc':
    default:
        $query .= " ORDER BY l.created_at DESC";
        break;
}

// Get filtered listings
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total counts for filter badges
$total_listings = $pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$total_available = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'available'")->fetchColumn();
$total_sold = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'sold'")->fetchColumn();
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Listings</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- Search and Filter Section -->
        <div class="admin-section">
            <div class="filters-container">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search" class="form-label">Search Listings</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Search by book title, author, seller..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="sold" <?php echo $status_filter === 'sold' ? 'selected' : ''; ?>>Sold</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="condition" class="form-label">Filter by Condition</label>
                            <select id="condition" name="condition" class="form-control">
                                <option value="">All Conditions</option>
                                <option value="new" <?php echo $condition_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="like_new" <?php echo $condition_filter === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                <option value="good" <?php echo $condition_filter === 'good' ? 'selected' : ''; ?>>Good</option>
                                <option value="fair" <?php echo $condition_filter === 'fair' ? 'selected' : ''; ?>>Fair</option>
                                <option value="poor" <?php echo $condition_filter === 'poor' ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort" class="form-label">Sort By</label>
                            <select id="sort" name="sort" class="form-control">
                                <option value="created_at_desc" <?php echo $sort === 'created_at_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="created_at_asc" <?php echo $sort === 'created_at_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                                <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                                <option value="author_asc" <?php echo $sort === 'author_asc' ? 'selected' : ''; ?>>Author A-Z</option>
                                <option value="author_desc" <?php echo $sort === 'author_desc' ? 'selected' : ''; ?>>Author Z-A</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="listings.php" class="btn btn-outline">Clear All</a>
                        </div>
                    </div>
                </form>
                
                <!-- Filter Badges -->
                <div class="filter-badges">
                    <span class="filter-badge">Total: <?php echo $total_listings; ?></span>
                    <span class="filter-badge">Available: <?php echo $total_available; ?></span>
                    <span class="filter-badge">Sold: <?php echo $total_sold; ?></span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-badge filter-badge-search">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="listings.php?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status_filter)): ?>
                        <span class="filter-badge filter-badge-status">
                            Status: <?php echo ucfirst($status_filter); ?>
                            <a href="listings.php?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($condition_filter)): ?>
                        <span class="filter-badge filter-badge-condition">
                            Condition: <?php echo getConditionText($condition_filter); ?>
                            <a href="listings.php?<?php echo http_build_query(array_merge($_GET, ['condition' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($listings) > 0): ?>
                        <?php foreach ($listings as $listing): ?>
                        <tr>
                            <td><?php echo $listing['id']; ?></td>
                            <td>
                                <strong><?php echo $listing['title']; ?></strong><br>
                                <small>by <?php echo $listing['author']; ?></small>
                            </td>
                            <td>
                                <?php echo $listing['username']; ?><br>
                                <small><?php echo $listing['email']; ?></small>
                            </td>
                            <td><?php echo formatPrice($listing['price']); ?></td>
                            <td><?php echo getConditionText($listing['book_condition']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $listing['status']; ?>">
                                    <?php echo ucfirst($listing['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../pages/view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-small btn-primary" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-book.php?id=<?php echo $listing['book_id']; ?>" class="btn btn-small btn-info">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=toggle_status&id=<?php echo $listing['id']; ?>" class="btn btn-small btn-<?php echo $listing['status'] == 'available' ? 'warning' : 'success'; ?>">
                                        <i class="fas fa-<?php echo $listing['status'] == 'available' ? 'times' : 'check'; ?>"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $listing['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this listing?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="no-results">
                                    <i class="fas fa-search"></i>
                                    <p>No listings found matching your criteria.</p>
                                    <a href="listings.php" class="btn btn-primary">Clear Filters</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($listings) == 0 && empty($search) && empty($status_filter) && empty($condition_filter)): ?>
            <div class="alert alert-info">
                <p>No listings found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filters-container {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: end;
}

.filter-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.filter-badge {
    background: #e9ecef;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-badge-search {
    background: #d1ecf1;
    color: #0c5460;
}

.filter-badge-status {
    background: #d4edda;
    color: #155724;
}

.filter-badge-condition {
    background: #fff3cd;
    color: #856404;
}

.remove-filter {
    color: inherit;
    text-decoration: none;
    font-weight: bold;
    margin-left: 5px;
    cursor: pointer;
}

.remove-filter:hover {
    color: #dc3545;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #dee2e6;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .filter-actions {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
require_once 'footer.php';
?>