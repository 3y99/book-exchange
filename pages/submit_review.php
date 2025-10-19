<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_id = intval($_POST['transaction_id'] ?? 0);
    $reviewee_id = intval($_POST['reviewee_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $errors = [];

    // Basic validation
    if (!$purchase_id || !$reviewee_id || $rating < 1 || $rating > 5 || empty($comment)) {
        $errors[] = "All fields are required and rating must be between 1 and 5.";
    }

    // Check if user actually bought this item and it's completed
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND buyer_id = ? AND status = 'completed'");
    $stmt->execute([$purchase_id, $user_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        $errors[] = "Invalid purchase or you are not authorized to review this item.";
    }

    // Check if review already exists
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE purchase_id = ? AND reviewer_id = ?");
    $stmt->execute([$purchase_id, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = "You have already reviewed this purchase.";
    }

    if (empty($errors)) {
        // Insert review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (reviewer_id, reviewee_id, purchase_id, rating, comment) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $reviewee_id, $purchase_id, $rating, $comment]);

        $_SESSION['success'] = "Thank you! Your review has been submitted.";
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }

    // Redirect back to purchase history
    header('Location: purchase-history.php');
    exit();
}
?>
