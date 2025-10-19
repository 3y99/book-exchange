<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get messages for a conversation
    if (isset($_GET['conversation_id'])) {
        $conversation_id = $_GET['conversation_id'];
        
        // Verify user is part of this conversation
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE id = ? AND (sender_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$conversation_id, $user_id, $user_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            echo json_encode(['error' => 'Conversation not found']);
            exit();
        }
        
        // Get all messages in this conversation
        $stmt = $pdo->prepare("
            SELECT m.*, u.username as sender_username 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.listing_id = ? 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE listing_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$conversation_id, $user_id]);
        
        echo json_encode(['messages' => $messages]);
    } else {
        // Get all conversations for the user
        $stmt = $pdo->prepare("
            SELECT DISTINCT m.listing_id, b.title as listing_title, 
                CASE 
                    WHEN m.sender_id = ? THEN u2.username 
                    ELSE u1.username 
                END as other_user,
                CASE 
                    WHEN m.sender_id = ? THEN u2.id 
                    ELSE u1.id 
                END as other_user_id,
                (SELECT message FROM messages WHERE listing_id = m.listing_id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE listing_id = m.listing_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM messages WHERE listing_id = m.listing_id AND receiver_id = ? AND is_read = 0) as unread_count
            FROM messages m
            JOIN listings l ON m.listing_id = l.id
            JOIN books b ON l.book_id = b.id
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        echo json_encode(['conversations' => $conversations]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Send a new message
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['listing_id']) || !isset($data['receiver_id']) || !isset($data['message'])) {
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    $listing_id = $data['listing_id'];
    $receiver_id = $data['receiver_id'];
    $message = sanitize($data['message']);
    
    // Verify the listing exists and user has permission to message about it
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        echo json_encode(['error' => 'Listing not found']);
        exit();
    }
    
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, listing_id, message) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $receiver_id, $listing_id, $message]);
    
    $message_id = $pdo->lastInsertId();
    
    // Get the complete message data to return
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $new_message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['message' => $new_message, 'success' => true]);
}
?>