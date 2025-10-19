<?php
$pageTitle = "Sales History";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch sales for this seller
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        b.title, 
        b.author,
        buyer.username AS buyer_name,
        seller.username AS seller_name,
        (SELECT image_path FROM listing_images WHERE listing_id = p.listing_id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM purchases p
    JOIN listings l ON p.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users buyer ON p.buyer_id = buyer.id
    JOIN users seller ON p.seller_id = seller.id
    WHERE p.seller_id = ?
    ORDER BY p.purchase_date DESC
");
$stmt->execute([$user_id]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-dollar-sign"></i> Sales History</h1>
        <p>Your completed sales transactions</p>
    </div>

    <?php if (count($sales) > 0): ?>
        <div class="purchases-list">
            <?php foreach ($sales as $sale): ?>
                <div class="purchase-item">
                    <div class="purchase-image">
                        <?php if ($sale['primary_image']): ?>
                            <img src="../assets/images/uploads/books/<?php echo htmlspecialchars($sale['primary_image']); ?>" alt="<?php echo htmlspecialchars($sale['title']); ?>">
                        <?php else: ?>
                            <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                        <?php endif; ?>
                    </div>

                    <div class="purchase-details">
                        <h3><?php echo htmlspecialchars($sale['title']); ?></h3>
                        <p class="author">by <?php echo htmlspecialchars($sale['author']); ?></p>

                        <div class="purchase-meta">
                            <div class="meta-item">
                                <strong>Price:</strong>
                                <span><?php echo formatPrice($sale['total_price']); ?></span>
                            </div>
                            <div class="meta-item">
                                <strong>Date:</strong>
                                <span><?php echo date('F j, Y g:i A', strtotime($sale['purchase_date'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <strong>Status:</strong>
                                <span class="status-badge status-<?php echo htmlspecialchars($sale['status']); ?>">
                                    <?php echo ucfirst($sale['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="transaction-parties">
                            <p><strong>Sold to:</strong> <?php echo htmlspecialchars($sale['buyer_name']); ?></p>
                        </div>
                    </div>

                    <div class="purchase-actions">
                        <a href="view-listing.php?id=<?php echo $sale['listing_id']; ?>" class="btn btn-small">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-dollar-sign" style="color: #ccc; font-size: 4rem; margin-bottom: 1rem;"></i>
            <h3>No Sales Yet</h3>
            <p>Your completed sales will appear here once you start selling books.</p>
            <a href="listings.php" class="btn btn-primary">View My Listings</a>
        </div>
    <?php endif; ?>
</div>

<style>
.purchase-history, .purchases-list {
    max-width: 800px;
    margin: 0 auto;
}

.purchase-item {
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.5rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    align-items: start;
}

.purchase-image {
    width: 80px;
    height: 100px;
    border-radius: 8px;
    overflow: hidden;
}

.purchase-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.purchase-details h3 {
    margin: 0 0 0.5rem 0;
    color: #333;
}

.purchase-details .author {
    color: #666;
    margin: 0 0 1rem 0;
}

.purchase-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.meta-item strong {
    color: #333;
    font-size: 0.9rem;
}

.transaction-parties p {
    margin: 0;
    color: #666;
}

.purchase-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.empty-state h3 {
    color: #333;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 2rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.btn, .btn-small, .btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    transition: background 0.2s ease;
}

.btn {
    background-color: #2563eb;
    color: white;
}

.btn:hover {
    background-color: #1e40af;
}

.btn-primary {
    background-color: #2563eb;
    color: white;
}

.btn-primary:hover {
    background-color: #1e40af;
}

@media (max-width: 768px) {
    .purchase-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .purchase-image {
        justify-self: center;
    }
    
    .purchase-actions {
        flex-direction: row;
        justify-content: center;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>
