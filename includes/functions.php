<?php
// Redirect to login if not authenticated
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Check if user is banned (if they somehow got past login)
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['is_banned']) {
        // Destroy session and redirect to login
        session_destroy();
        header('Location: login.php?error=banned');
        exit();
    }
}

// Redirect to dashboard if already authenticated
function redirectIfAuthenticated() {
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }
}

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Upload image safely
function uploadImage($file, $targetDir, $maxSize = 5000000) {
    $errors = [];

    // Ensure target directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Validate image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        $errors[] = "File is not a valid image.";
    }

    if ($file['size'] > $maxSize) {
        $errors[] = "File is too large. Maximum size is " . ($maxSize / 1000000) . "MB.";
    }

    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }

    if (!empty($errors)) {
        return ['errors' => $errors];
    }

    // Generate unique safe filename
    $fileName = uniqid('profile_', true) . '.' . $fileType;
    $targetFilePath = rtrim($targetDir, '/') . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        return $fileName; // return just filename, store in DB
    } else {
        return ['errors' => ["Error uploading file."]];
    }
}

// Format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Get book condition text
function getConditionText($condition) {
    $conditions = [
        'new' => 'New',
        'like_new' => 'Like New',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor'
    ];
    
    return isset($conditions[$condition]) ? $conditions[$condition] : 'Unknown';
}

// Simplified isAdmin check (admin_approved removed)
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Redirect to admin login if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /book-exchange/admin/login.php');
        exit();
    }
}

// NEW FUNCTIONS FOR ROLE SYSTEM
function hasSelectedRole() {
    return isset($_SESSION['user_role']) && !empty($_SESSION['user_role']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function isSeller() {
    return getUserRole() === 'seller';
}

function isBuyer() {
    return getUserRole() === 'buyer';
}

function requireSeller() {
    if (!isSeller()) {
        header('Location: ../pages/dashboard.php');
        exit();
    }
}

function requireBuyer() {
    if (!isBuyer()) {
        header('Location: ../pages/dashboard.php');
        exit();
    }
}

// Get user's reports with response status
function getUserReports($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*,
               reported_user.username AS reported_user_username,
               reported_user.first_name AS reported_user_first_name,
               reported_user.last_name AS reported_user_last_name,
               reported_listing.title AS reported_listing_title,
               reported_listing_seller.username AS reported_listing_seller_username
        FROM reports r
        LEFT JOIN users reported_user ON r.reported_user_id = reported_user.id
        LEFT JOIN listings reported_listing ON r.reported_listing_id = reported_listing.id
        LEFT JOIN users reported_listing_seller ON reported_listing.seller_id = reported_listing_seller.id
        WHERE r.reporter_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mark report response as read
function markReportResponseAsRead($report_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE reports 
        SET response_read = TRUE 
        WHERE id = ? AND reporter_id = ?
    ");
    return $stmt->execute([$report_id, $user_id]);
}

// Get unread response count for user
function getUnreadReportResponseCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE reporter_id = ? 
        AND admin_response IS NOT NULL 
        AND response_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

// Real ChatGPT AI Response Function
function getAIResponse($user_message, $conversation_history = []) {
    $api_key = CHATGPT_API_KEY;
    $api_url = CHATGPT_API_URL;
    
    // Preparing conversation history for ChatGPT
    $messages = [
        [
            'role' => 'system',
            'content' => "You are a helpful customer support assistant for BookExchange, a platform for students to buy and sell textbooks and other books. 
            
            Important guidelines:
            - Be friendly, helpful, and professional
            - Focus on book buying/selling, pricing, shipping, and platform usage
            - If asked about topics outside book exchange, politely redirect
            - Keep responses concise but informative (2-4 paragraphs max)
            - For pricing, suggest checking similar listings on the platform
            - Emphasize safety for meetups and transactions
            - If unsure, suggest contacting site administrators
            
            Platform features:
            - Users can be buyers or sellers (choose role at login)
            - Listings with book details, condition, price
            - Messaging system between users
            - Local meetups or shipping
            - User ratings and reviews
            - Categories for different book types
            
            Always maintain a helpful and supportive tone."
        ]
    ];
    
    // Add conversation history (last 6 messages to stay within token limits)
    $recent_history = array_slice($conversation_history, -6);
    foreach ($recent_history as $msg) {
        $messages[] = [
            'role' => $msg['sender'] === 'user' ? 'user' : 'assistant',
            'content' => $msg['message']
        ];
    }
    
    // Add current user message
    $messages[] = [
        'role' => 'user',
        'content' => $user_message
    ];
    
    // Prepare API request
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages,
        'max_tokens' => 500,
        'temperature' => 0.7,
        'top_p' => 0.9
    ];
    
    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Handle API response
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        if (isset($response_data['choices'][0]['message']['content'])) {
            return trim($response_data['choices'][0]['message']['content']);
        }
    }
    
    // Log error for debugging
    error_log("ChatGPT API Error: HTTP $http_code - $error");
    
    // Fallback responses if API fails
    $fallback_responses = [
        "I apologize, but I'm having trouble connecting to my knowledge base right now. Please try again in a moment, or contact our support team for immediate assistance.",
        "I'm experiencing some technical difficulties at the moment. Could you please rephrase your question or try again shortly?",
        "I'm unable to process your request right now. For urgent matters, please use our contact form or email support directly.",
        "It seems there's a temporary issue with my response system. Please try again in a few minutes while I work to resolve this."
    ];
    
    return $fallback_responses[array_rand($fallback_responses)];
}


// new functions
// Cart functions
function addToCart($user_id, $listing_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, listing_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $listing_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function removeFromCart($user_id, $listing_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND listing_id = ?");
    return $stmt->execute([$user_id, $listing_id]);
}

function getCartItems($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, 
               l.*, 
               b.title, 
               b.author, 
               b.course_code, 
               u.username as seller_name, 
               u.id as seller_id,
               (SELECT image_path FROM listing_images 
                WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM cart c
        JOIN listings l ON c.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.seller_id = u.id
        WHERE c.user_id = ? AND l.status = 'available'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getCartCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function isInCart($user_id, $listing_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND listing_id = ?");
    $stmt->execute([$user_id, $listing_id]);
    return $stmt->fetch() !== false;
}

// Purchase functions
function createPurchase($buyer_id, $seller_id, $listing_id, $total_price) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Create purchase record with status completed
        $stmt = $pdo->prepare("
            INSERT INTO purchases (buyer_id, seller_id, listing_id, total_price, status) 
            VALUES (?, ?, ?, ?, 'completed')
        ");
        $stmt->execute([$buyer_id, $seller_id, $listing_id, $total_price]);
        
        // Update listing status to sold
        $stmt = $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
        $stmt->execute([$listing_id]);
        
        // Remove from cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND listing_id = ?");
        $stmt->execute([$buyer_id, $listing_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Purchase failed: " . $e->getMessage());
        return false;
    }
}


function createBulkPurchase($buyer_id, $cart_items) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        foreach ($cart_items as $item) {
            // Create purchase record
            $stmt = $pdo->prepare("
                INSERT INTO purchases (buyer_id, seller_id, listing_id, total_price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$buyer_id, $item['seller_id'], $item['id'], $item['price']]);
            
            // Update listing status to sold
            $stmt = $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
            $stmt->execute([$item['id']]);
        }
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$buyer_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function getUserPurchases($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            p.id AS purchase_id,
            p.buyer_id,
            p.seller_id,
            p.listing_id,
            p.total_price,
            p.status AS purchase_status,
            p.purchase_date,
            b.title,
            b.author,
            seller.username AS seller_name,
            buyer.username AS buyer_name,
            (SELECT image_path 
             FROM listing_images 
             WHERE listing_id = l.id AND is_primary = 1 
             LIMIT 1) AS primary_image
        FROM purchases p
        JOIN listings l ON p.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users seller ON p.seller_id = seller.id
        JOIN users buyer ON p.buyer_id = buyer.id
        WHERE p.buyer_id = ? OR p.seller_id = ?
        ORDER BY p.purchase_date DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Watchlist functions
function addToWatchlist($user_id, $listing_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, listing_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $listing_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function removeFromWatchlist($user_id, $listing_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND listing_id = ?");
    return $stmt->execute([$user_id, $listing_id]);
}

function isInWatchlist($user_id, $listing_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND listing_id = ?");
    $stmt->execute([$user_id, $listing_id]);
    return $stmt->fetch() !== false;
}

function getWatchlist($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT w.*, l.*, 
               b.title, 
               b.author, 
               b.course_code, 
               u.username as seller_name,
               (SELECT image_path FROM listing_images 
                WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM watchlist w
        JOIN listings l ON w.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.seller_id = u.id
        WHERE w.user_id = ? AND l.status = 'available'
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Rating functions - aligned to your schema (reviews references purchases)
function getUserRating($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT AVG(r.rating) as avg_rating, COUNT(*) as review_count 
        FROM reviews r
        JOIN purchases p ON r.purchase_id = p.id
        WHERE p.seller_id = ? OR p.buyer_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function canReviewTransaction($user_id, $purchase_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id FROM purchases 
        WHERE id = ? AND (buyer_id = ? OR seller_id = ?)
        AND status = 'completed'
        AND NOT EXISTS (
            SELECT 1 FROM reviews WHERE purchase_id = ? AND reviewer_id = ?
        )
    ");
    $stmt->execute([$purchase_id, $user_id, $user_id, $purchase_id, $user_id]);
    return $stmt->fetch() !== false;
}

function getAverageRating($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT AVG(r.rating) as avg_rating
        FROM reviews r
        JOIN purchases p ON r.purchase_id = p.id
        WHERE p.seller_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
}

function getReviewCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM reviews r
        JOIN purchases p ON r.purchase_id = p.id
        WHERE p.seller_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

// Listing functions with rating filter
function getListingsWithFilters($filters = []) {
    global $pdo;
    
    $whereConditions = ["1=1"];
    $params = [];
    
    if (isset($filters['category_id']) && $filters['category_id']) {
        $whereConditions[] = "b.category_id = ?";
        $params[] = $filters['category_id'];
    }
    
    if (isset($filters['min_price']) && $filters['min_price'] !== '') {
        $whereConditions[] = "l.price >= ?";
        $params[] = $filters['min_price'];
    }
    
    if (isset($filters['max_price']) && $filters['max_price'] !== '') {
        $whereConditions[] = "l.price <= ?";
        $params[] = $filters['max_price'];
    }
    
    if (isset($filters['condition']) && $filters['condition']) {
        $whereConditions[] = "l.book_condition = ?";
        $params[] = $filters['condition'];
    }
    
    if (isset($filters['search']) && $filters['search']) {
        $whereConditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.course_code LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (isset($filters['min_rating']) && $filters['min_rating']) {
        $whereConditions[] = "u.rating >= ?";
        $params[] = $filters['min_rating'];
    }
    
    // If caller explicitly wants only available, they can add filter, otherwise show all
    if (!(isset($filters['include_all_statuses']) && $filters['include_all_statuses'] === true)) {
        $whereConditions[] = "l.status = 'available'";
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT l.*, b.*, u.username, u.rating as seller_rating,
               (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM listings l
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.seller_id = u.id
        WHERE $whereClause
        ORDER BY l.created_at DESC
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// NEW helper: get listing + seller details (reliable source for report page)
function getListingSeller($listing_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT l.id AS listing_id, b.title AS listing_title,
               u.id AS seller_id, u.username AS seller_username,
               u.first_name AS seller_first_name, u.last_name AS seller_last_name
        FROM listings l
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.seller_id = u.id
        WHERE l.id = ?
        LIMIT 1
    ");
    $stmt->execute([$listing_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Admin messaging functions
function sendAdminMessage($admin_id, $user_id, $subject, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_messages (admin_id, user_id, subject, message) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$admin_id, $user_id, $subject, $message]);
    } catch (PDOException $e) {
        error_log("Admin message failed: " . $e->getMessage());
        return false;
    }
}

function getUserAdminMessages($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT am.*, u.username as admin_username, u.first_name as admin_first_name, u.last_name as admin_last_name
        FROM admin_messages am
        JOIN users u ON am.admin_id = u.id
        WHERE am.user_id = ?
        ORDER BY am.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadAdminMessageCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_messages WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function markAdminMessageAsRead($message_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE admin_messages SET is_read = TRUE WHERE id = ? AND user_id = ?");
    return $stmt->execute([$message_id, $user_id]);
}

function getAllAdminMessages() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT am.*, 
               admin.username as admin_username,
               user.username as user_username,
               user.first_name as user_first_name,
               user.last_name as user_last_name,
               user.email as user_email
        FROM admin_messages am
        JOIN users admin ON am.admin_id = admin.id
        JOIN users user ON am.user_id = user.id
        ORDER BY am.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Admin messaging response functions
function addUserResponse($message_id, $user_id, $response) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE admin_messages SET user_response = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        return $stmt->execute([$response, $message_id, $user_id]);
    } catch (PDOException $e) {
        error_log("User response failed: " . $e->getMessage());
        return false;
    }
}

function addAdminFollowup($message_id, $followup) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE admin_messages SET admin_followup = ?, admin_replied_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$followup, $message_id]);
    } catch (PDOException $e) {
        error_log("Admin followup failed: " . $e->getMessage());
        return false;
    }
}

function getAdminMessageWithResponses($message_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT am.*, 
               admin.username as admin_username, 
               admin.first_name as admin_first_name, 
               admin.last_name as admin_last_name,
               user.username as user_username,
               user.first_name as user_first_name,
               user.last_name as user_last_name
        FROM admin_messages am
        JOIN users admin ON am.admin_id = admin.id
        JOIN users user ON am.user_id = user.id
        WHERE am.id = ? AND am.user_id = ?
    ");
    $stmt->execute([$message_id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAdminMessagesWithResponses() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT am.*, 
               admin.username as admin_username,
               user.username as user_username,
               user.first_name as user_first_name,
               user.last_name as user_last_name,
               user.email as user_email
        FROM admin_messages am
        JOIN users admin ON am.admin_id = admin.id
        JOIN users user ON am.user_id = user.id
        ORDER BY am.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>