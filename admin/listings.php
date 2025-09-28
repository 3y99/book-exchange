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

// Get all listings with user information
$stmt = $pdo->query("
    SELECT l.*, b.title, b.author, u.username, u.email 
    FROM listings l 
    JOIN books b ON l.book_id = b.id 
    JOIN users u ON l.seller_id = u.id 
    ORDER BY l.created_at DESC
");
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Listings</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
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
                </tbody>
            </table>
        </div>
        
        <?php if (count($listings) == 0): ?>
            <div class="alert alert-info">
                <p>No listings found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'footer.php';
?>