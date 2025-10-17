<?php
// Redirect to login if not authenticated
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
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
               reported_user.username as reported_user_username,
               reported_listing.title as reported_listing_title
        FROM reports r
        LEFT JOIN users reported_user ON r.reported_user_id = reported_user.id
        LEFT JOIN listings reported_listing ON r.reported_listing_id = reported_listing.id
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
    return $result['count'];
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
        'model' => 'gpt-5',
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
?>