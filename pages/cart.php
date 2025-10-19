<?php
$pageTitle = "Shopping Cart";
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cart actions - must be before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['listing_id'])) {
        $listing_id = intval($_POST['listing_id']);
        
        if ($_POST['action'] === 'remove') {
            removeFromCart($user_id, $listing_id);
            $_SESSION['success'] = "Item removed from cart!";
        } elseif ($_POST['action'] === 'add') {
            addToCart($user_id, $listing_id);
            $_SESSION['success'] = "Item added to cart!";
        }
        
        header('Location: cart.php');
        exit();
    }
    
    // Handle bulk purchase
    if (isset($_POST['purchase_all'])) {
        $cart_items = getCartItems($user_id);
        if (count($cart_items) > 0) {
            if (createBulkPurchase($user_id, $cart_items)) {
                $_SESSION['success'] = "Purchase completed successfully!";
                header('Location: purchase-history.php');
                exit();
            } else {
                $errors[] = "There was an error processing your purchase. Please try again.";
            }
        }
    }
    
    // Handle single item purchase
    if (isset($_POST['purchase_single'])) {
        $listing_id = intval($_POST['listing_id']);
        $cart_items = getCartItems($user_id);
        $item_to_purchase = null;
        
        foreach ($cart_items as $item) {
            if ($item['id'] == $listing_id) {
                $item_to_purchase = $item;
                break;
            }
        }
        
        if ($item_to_purchase && createPurchase($user_id, $item_to_purchase['seller_id'], $listing_id, $item_to_purchase['price'])) {
            $_SESSION['success'] = "Purchase completed successfully!";
            header('Location: purchase-history.php');
            exit();
        } else {
            $errors[] = "There was an error processing your purchase. Please try again.";
        }
    }
}

// Now include header after handling redirects
require_once '../includes/header.php';

// Get cart items
$cart_items = getCartItems($user_id);
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['price'];
}
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
        <p>Review your selected books</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="cart-page">
        <?php if (count($cart_items) > 0): ?>
            <div class="cart-content">
                <div class="cart-items">
                    <div class="cart-header">
                        <h2>Your Items (<?php echo count($cart_items); ?>)</h2>
                    </div>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['primary_image']): ?>
                                    <img src="../assets/images/uploads/books/<?php echo $item['primary_image']; ?>" alt="<?php echo $item['title']; ?>">
                                <?php else: ?>
                                    <img src="../assets/images/placeholder-book.jpg" alt="No image available">
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h3><?php echo $item['title']; ?></h3>
                                <p class="author">by <?php echo $item['author']; ?></p>
                                <?php if ($item['course_code']): ?>
                                    <p class="course">Course: <?php echo $item['course_code']; ?></p>
                                <?php endif; ?>
                                <div class="item-meta">
                                    <span class="condition">Condition: <?php echo getConditionText($item['book_condition']); ?></span>
                                    <span class="seller">Seller: <?php echo $item['seller_name']; ?></span>
                                </div>
                            </div>
                            
                            <div class="item-price">
                                <span class="price"><?php echo formatPrice($item['price']); ?></span>
                            </div>
                            
                            <div class="item-actions">
                                <form method="POST" class="purchase-form">
                                    <input type="hidden" name="listing_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="purchase_single" class="btn btn-success btn-small">
                                        <i class="fas fa-bolt"></i> Buy Now
                                    </button>
                                </form>
                                
                                <form method="POST" class="remove-form">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="listing_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-small">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                                
                                <a href="view-listing.php?id=<?php echo $item['id']; ?>" class="btn btn-small">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <div class="summary-card">
                        <h3>Order Summary</h3>
                        <div class="summary-details">
                            <div class="summary-row">
                                <span>Items (<?php echo count($cart_items); ?>):</span>
                                <span><?php echo formatPrice($cart_total); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span>Free</span>
                            </div>
                            <div class="summary-row total">
                                <span><strong>Total:</strong></span>
                                <span><strong><?php echo formatPrice($cart_total); ?></strong></span>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="purchase_all" class="btn btn-primary btn-large btn-full">
                                <i class="fas fa-shopping-bag"></i> Purchase All Items
                            </button>
                        </form>
                        
                        <div class="cart-notes">
                            <p><small>This is a simulated purchase. No actual payment is required.</small></p>
                        </div>
                    </div>
                    
                    <div class="continue-shopping">
                        <a href="listings.php" class="btn btn-outline btn-full">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <div class="empty-state">
                    <i class="fas fa-shopping-cart" style="color: #ccc; font-size: 4rem; margin-bottom: 1rem;"></i>
                    <h3>Your cart is empty</h3>
                    <p>Start browsing books and add them to your cart.</p>
                    <a href="listings.php" class="btn btn-primary">Browse Listings</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cart-page {
    max-width: 1200px;
    margin: 0 auto;
}

.cart-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    align-items: start;
}

.cart-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.cart-header h2 {
    color: #333;
    margin: 0;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr auto auto;
    gap: 1.5rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    align-items: center;
}

.item-image {
    width: 100px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details h3 {
    margin: 0 0 0.5rem 0;
    color: #333;
    font-size: 1.1rem;
}

.item-details .author {
    color: #666;
    margin: 0 0 0.5rem 0;
}

.item-details .course {
    color: #007bff;
    margin: 0 0 0.5rem 0;
    font-weight: 500;
}

.item-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.item-meta span {
    font-size: 0.9rem;
    color: #666;
}

.item-price {
    text-align: center;
}

.item-price .price {
    font-size: 1.2rem;
    font-weight: bold;
    color: #28a745;
}

.item-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 120px;
}

.item-actions .btn {
    white-space: nowrap;
}

.cart-summary {
    position: sticky;
    top: 2rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}

.summary-card h3 {
    margin: 0 0 1rem 0;
    color: #333;
    text-align: center;
}

.summary-details {
    margin-bottom: 1.5rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-row.total {
    border-top: 2px solid #e9ecef;
    border-bottom: none;
    margin-top: 0.5rem;
    padding-top: 1rem;
    font-size: 1.1rem;
}

.cart-notes {
    margin-top: 1rem;
    text-align: center;
}

.cart-notes p {
    color: #666;
    font-size: 0.8rem;
    margin: 0;
}

.continue-shopping {
    text-align: center;
}

.empty-cart {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 400px;
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

.cart-form-inline {
    display: inline-block;
    margin-left: 0.5rem;
}

/* Header cart styles */
.cart-link {
    position: relative;
}

.cart-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Responsive */
@media (max-width: 768px) {
    .cart-content {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        gap: 1rem;
    }
    
    .item-price, .item-actions {
        grid-column: 1 / -1;
        text-align: left;
    }
    
    .item-actions {
        flex-direction: row;
        justify-content: flex-start;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>