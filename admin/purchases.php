<?php
$pageTitle = "Admin - Manage Purchases";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../pages/login.php');
    exit();
}

// Handle purchase actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $purchaseId = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
        $stmt->execute([$purchaseId]);
        $_SESSION['success'] = "Purchase record deleted successfully.";
    } elseif ($action == 'update_status') {
        $new_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'completed';
        $stmt = $pdo->prepare("UPDATE purchases SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $purchaseId]);
        $_SESSION['success'] = "Purchase status updated successfully.";
    }
    
    header('Location: purchases.php');
    exit();
}

// Handle search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$buyer_filter = isset($_GET['buyer']) ? intval($_GET['buyer']) : '';
$seller_filter = isset($_GET['seller']) ? intval($_GET['seller']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'purchase_date_desc';

// Build the query with filters
$query = "
    SELECT 
        p.*,
        buyer.username as buyer_username,
        buyer.first_name as buyer_first_name,
        buyer.last_name as buyer_last_name,
        buyer.email as buyer_email,
        seller.username as seller_username,
        seller.first_name as seller_first_name,
        seller.last_name as seller_last_name,
        seller.email as seller_email,
        b.title as book_title,
        b.author as book_author,
        l.price as listing_price
    FROM purchases p
    JOIN users buyer ON p.buyer_id = buyer.id
    JOIN users seller ON p.seller_id = seller.id
    JOIN listings l ON p.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR buyer.username LIKE ? OR seller.username LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($buyer_filter)) {
    $query .= " AND p.buyer_id = ?";
    $params[] = $buyer_filter;
}

if (!empty($seller_filter)) {
    $query .= " AND p.seller_id = ?";
    $params[] = $seller_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.purchase_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.purchase_date) <= ?";
    $params[] = $date_to;
}

// Add sorting
switch ($sort) {
    case 'purchase_date_asc':
        $query .= " ORDER BY p.purchase_date ASC";
        break;
    case 'price_asc':
        $query .= " ORDER BY p.total_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.total_price DESC";
        break;
    case 'buyer_asc':
        $query .= " ORDER BY buyer.username ASC";
        break;
    case 'buyer_desc':
        $query .= " ORDER BY buyer.username DESC";
        break;
    case 'seller_asc':
        $query .= " ORDER BY seller.username ASC";
        break;
    case 'seller_desc':
        $query .= " ORDER BY seller.username DESC";
        break;
    case 'purchase_date_desc':
    default:
        $query .= " ORDER BY p.purchase_date DESC";
        break;
}

// Get filtered purchases
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for dropdowns
$users_stmt = $pdo->query("SELECT id, username, first_name, last_name FROM users ORDER BY username");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total counts for filter badges
$total_purchases = $pdo->query("SELECT COUNT(*) FROM purchases")->fetchColumn();
$completed_purchases = $pdo->query("SELECT COUNT(*) FROM purchases WHERE status = 'completed'")->fetchColumn();
$pending_purchases = $pdo->query("SELECT COUNT(*) FROM purchases WHERE status = 'pending'")->fetchColumn();
$cancelled_purchases = $pdo->query("SELECT COUNT(*) FROM purchases WHERE status = 'cancelled'")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) FROM purchases WHERE status = 'completed'")->fetchColumn();
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Purchases</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_purchases; ?></h3>
                    <p>Total Purchases</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $completed_purchases; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pending_purchases; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($total_revenue ?: 0); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="admin-section">
            <div class="filters-container">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search" class="form-label">Search Purchases</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Search by book title, author, buyer, seller..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="buyer" class="form-label">Filter by Buyer</label>
                            <select id="buyer" name="buyer" class="form-control">
                                <option value="">All Buyers</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $buyer_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['username']; ?> (<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="seller" class="form-label">Filter by Seller</label>
                            <select id="seller" name="seller" class="form-control">
                                <option value="">All Sellers</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $seller_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['username']; ?> (<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort" class="form-label">Sort By</label>
                            <select id="sort" name="sort" class="form-control">
                                <option value="purchase_date_desc" <?php echo $sort === 'purchase_date_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="purchase_date_asc" <?php echo $sort === 'purchase_date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="buyer_asc" <?php echo $sort === 'buyer_asc' ? 'selected' : ''; ?>>Buyer A-Z</option>
                                <option value="buyer_desc" <?php echo $sort === 'buyer_desc' ? 'selected' : ''; ?>>Buyer Z-A</option>
                                <option value="seller_asc" <?php echo $sort === 'seller_asc' ? 'selected' : ''; ?>>Seller A-Z</option>
                                <option value="seller_desc" <?php echo $sort === 'seller_desc' ? 'selected' : ''; ?>>Seller Z-A</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="purchases.php" class="btn btn-outline">Clear All</a>
                        </div>
                    </div>
                </form>
                
                <!-- Filter Badges -->
                <div class="filter-badges">
                    <span class="filter-badge">Total: <?php echo $total_purchases; ?></span>
                    <span class="filter-badge">Completed: <?php echo $completed_purchases; ?></span>
                    <span class="filter-badge">Pending: <?php echo $pending_purchases; ?></span>
                    <span class="filter-badge">Cancelled: <?php echo $cancelled_purchases; ?></span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-badge filter-badge-search">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="purchases.php?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status_filter)): ?>
                        <span class="filter-badge filter-badge-status">
                            Status: <?php echo ucfirst($status_filter); ?>
                            <a href="purchases.php?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($buyer_filter)): ?>
                        <?php 
                        $buyer_name = '';
                        foreach ($users as $user) {
                            if ($user['id'] == $buyer_filter) {
                                $buyer_name = $user['username'];
                                break;
                            }
                        }
                        ?>
                        <span class="filter-badge filter-badge-buyer">
                            Buyer: <?php echo $buyer_name; ?>
                            <a href="purchases.php?<?php echo http_build_query(array_merge($_GET, ['buyer' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($seller_filter)): ?>
                        <?php 
                        $seller_name = '';
                        foreach ($users as $user) {
                            if ($user['id'] == $seller_filter) {
                                $seller_name = $user['username'];
                                break;
                            }
                        }
                        ?>
                        <span class="filter-badge filter-badge-seller">
                            Seller: <?php echo $seller_name; ?>
                            <a href="purchases.php?<?php echo http_build_query(array_merge($_GET, ['seller' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Purchases Table -->
        <div class="admin-section">
            <h2>
                Purchase History 
                <?php if (!empty($search) || !empty($status_filter) || !empty($buyer_filter) || !empty($seller_filter)): ?>
                    <small>(Filtered: <?php echo count($purchases); ?> results)</small>
                <?php endif; ?>
            </h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book</th>
                            <th>Buyer</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Purchase Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($purchases) > 0): ?>
                            <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?php echo $purchase['id']; ?></td>
                                <td>
                                    <strong><?php echo $purchase['book_title']; ?></strong><br>
                                    <small>by <?php echo $purchase['book_author']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo $purchase['buyer_username']; ?></strong><br>
                                    <small><?php echo $purchase['buyer_first_name'] . ' ' . $purchase['buyer_last_name']; ?></small><br>
                                    <small><?php echo $purchase['buyer_email']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo $purchase['seller_username']; ?></strong><br>
                                    <small><?php echo $purchase['seller_first_name'] . ' ' . $purchase['seller_last_name']; ?></small><br>
                                    <small><?php echo $purchase['seller_email']; ?></small>
                                </td>
                                <td><?php echo formatPrice($purchase['total_price']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $purchase['status']; ?>">
                                        <?php echo ucfirst($purchase['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($purchase['purchase_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../pages/view-listing.php?id=<?php echo $purchase['listing_id']; ?>" class="btn btn-small btn-primary" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-small btn-<?php echo $purchase['status'] == 'completed' ? 'success' : ($purchase['status'] == 'pending' ? 'warning' : 'secondary'); ?> dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($purchase['status'] != 'completed'): ?>
                                                    <li><a class="dropdown-item" href="?action=update_status&id=<?php echo $purchase['id']; ?>&status=completed">Mark as Completed</a></li>
                                                <?php endif; ?>
                                                <?php if ($purchase['status'] != 'pending'): ?>
                                                    <li><a class="dropdown-item" href="?action=update_status&id=<?php echo $purchase['id']; ?>&status=pending">Mark as Pending</a></li>
                                                <?php endif; ?>
                                                <?php if ($purchase['status'] != 'cancelled'): ?>
                                                    <li><a class="dropdown-item" href="?action=update_status&id=<?php echo $purchase['id']; ?>&status=cancelled">Mark as Cancelled</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <a href="?action=delete&id=<?php echo $purchase['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this purchase record? This action cannot be undone.')">
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
                                        <p>No purchases found matching your criteria.</p>
                                        <a href="purchases.php" class="btn btn-primary">Clear Filters</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
    margin-bottom: 15px;
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

.filter-badge-buyer {
    background: #e2e3e5;
    color: #383d41;
}

.filter-badge-seller {
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

.status-completed {
    background: #28a745;
    color: white;
}

.status-pending {
    background: #ffc107;
    color: black;
}

.status-cancelled {
    background: #dc3545;
    color: white;
}

.dropdown {
    display: inline-block;
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