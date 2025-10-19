<?php
$pageTitle = "Purchase History";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$purchases = array_filter(getUserPurchases($user_id), function($purchase) use ($user_id) {
    return $purchase['buyer_id'] == $user_id;
});
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Purchase History</h1>
        <p>Your completed book transactions</p>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="purchase-history">
        <?php if (count($purchases) > 0): ?>
            <div class="purchases-list">
                <?php foreach ($purchases as $purchase): ?>
                    <div class="purchase-item">
                        <div class="purchase-image">
                            <?php if ($purchase['primary_image']): ?>
                                <img src="../assets/images/uploads/books/<?php echo htmlspecialchars($purchase['primary_image']); ?>" alt="<?php echo htmlspecialchars($purchase['title']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                            <?php endif; ?>
                        </div>

                        <div class="purchase-details">
                            <h3><?php echo htmlspecialchars($purchase['title']); ?></h3>
                            <p class="author">by <?php echo htmlspecialchars($purchase['author']); ?></p>

                            <div class="purchase-meta">
                                <div class="meta-item">
                                    <strong>Price:</strong>
                                    <span><?php echo formatPrice($purchase['total_price']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong>Date:</strong>
                                    <span><?php echo date('F j, Y g:i A', strtotime($purchase['purchase_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong>Status:</strong>
                                    <span class="status-badge status-<?php echo htmlspecialchars($purchase['purchase_status']); ?>">
                                        <?php echo ucfirst($purchase['purchase_status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="transaction-parties">
                                <p><strong>Purchased from:</strong> <?php echo htmlspecialchars($purchase['seller_name']); ?></p>
                            </div>
                        </div>

                        <div class="purchase-actions">
                            <a href="view-listing.php?id=<?php echo $purchase['listing_id']; ?>" class="btn btn-small">
                                <i class="fas fa-eye"></i> View
                            </a>

                           <?php if ($purchase['purchase_status'] === 'completed'): ?>
    <button class="btn btn-small btn-outline-primary" type="button" onclick="toggleReviewForm(<?php echo $purchase['purchase_id']; ?>)">
        <i class="fas fa-star"></i> Review
    </button>

    <form method="POST" action="submit_review.php" class="review-form" id="review-form-<?php echo $purchase['purchase_id']; ?>" style="display:none; margin-top:0.5rem;">
        <input type="hidden" name="transaction_id" value="<?php echo $purchase['purchase_id']; ?>">
        <input type="hidden" name="reviewee_id" value="<?php echo $purchase['seller_id']; ?>">

        <div class="rating-input">
            <label for="rating-<?php echo $purchase['purchase_id']; ?>">Rating:</label>
            <select name="rating" id="rating-<?php echo $purchase['purchase_id']; ?>" required>
                <option value="">Select</option>
                <option value="5">★★★★★</option>
                <option value="4">★★★★</option>
                <option value="3">★★★</option>
                <option value="2">★★</option>
                <option value="1">★</option>
            </select>
        </div>

        <div class="comment-input">
            <textarea name="comment" rows="2" placeholder="Write your review..." required></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-small">
            <i class="fas fa-paper-plane"></i> Submit
        </button>
    </form>
<?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history" style="color: #ccc; font-size: 4rem; margin-bottom: 1rem;"></i>
                <h3>No purchase history</h3>
                <p>Your completed purchases will appear here.</p>
                <a href="listings.php" class="btn btn-primary">Browse Listings</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.purchase-history, .purchases-list {
    max-width: 800px;
    margin: 0 auto;
}

.purchase-item {
    display: grid;
    grid-template-columns: 60px 1fr auto;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 1rem;
    align-items: start;
}

.purchase-image {
    width: 60px;
    height: 90px;
    border-radius: 6px;
    overflow: hidden;
}

.purchase-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.purchase-details h3 {
    margin: 0 0 0.25rem 0;
    color: #333;
    font-size: 1rem;
}

.purchase-details .author {
    color: #666;
    margin: 0 0 0.75rem 0;
    font-size: 0.85rem;
}

.purchase-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.meta-item strong {
    color: #333;
    font-size: 0.85rem;
}

.transaction-parties p {
    margin: 0;
    color: #666;
    font-size: 0.85rem;
}

.purchase-actions {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.review-form {
    background: #f9f9f9;
    padding: 0.6rem;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

.review-form .rating-input,
.review-form .comment-input {
    margin-bottom: 0.5rem;
}

.review-form select,
.review-form textarea {
    width: 100%;
    padding: 0.35rem;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 0.85rem;
}

.empty-state {
    text-align: center;
    padding: 2.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.empty-state h3 {
    color: #333;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.status-badge {
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
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
    padding: 0.35rem 0.7rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.8rem;
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

.btn-outline-primary {
    background: white;
    border: 1px solid #2563eb;
    color: #2563eb;
}

.btn-outline-primary:hover {
    background: #2563eb;
    color: white;
}

@media (max-width: 768px) {
    .purchase-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .purchase-image {
        justify-self: center;
        margin-bottom: 0.5rem;
    }
    
    .purchase-actions {
        flex-direction: row;
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<script>
function toggleReviewForm(id) {
    const form = document.getElementById('review-form-' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

</script>

<?php
require_once '../includes/footer.php';
?>
