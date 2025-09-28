<?php
$pageTitle = "Messages";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : null;

// Get conversations
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

// Get messages for selected conversation
$messages = [];
$current_conversation = null;

if ($conversation_id) {
    // Verify user is part of this conversation
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE listing_id = ? AND (sender_id = ? OR receiver_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
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
        
        // Get conversation details
        $stmt = $pdo->prepare("
            SELECT l.*, b.title, b.author,
                   CASE 
                       WHEN m.sender_id = ? THEN u2.username 
                       ELSE u1.username 
                   END as other_user,
                   CASE 
                       WHEN m.sender_id = ? THEN u2.id 
                       ELSE u1.id 
                   END as other_user_id
            FROM messages m
            JOIN listings l ON m.listing_id = l.id
            JOIN books b ON l.book_id = b.id
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.listing_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $user_id, $conversation_id]);
        $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conversation_id && $current_conversation) {
    $message = sanitize($_POST['message']);
    
    if (!empty($message)) {
        $receiver_id = $current_conversation['other_user_id'];
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, listing_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $receiver_id, $conversation_id, $message]);
        
        // Redirect to refresh the page and show the new message
        header('Location: messages.php?conversation=' . $conversation_id);
        exit();
    }
}
?>

<div class="messages-container">
    <div class="container">
        <h1>Messages</h1>
        
        <div class="messages-content">
            <div class="conversations-list">
                <h2>Conversations</h2>
                
                <?php if (count($conversations) > 0): ?>
                    <div class="conversations">
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="?conversation=<?php echo $conversation['listing_id']; ?>" class="conversation <?php echo $conversation_id == $conversation['listing_id'] ? 'active' : ''; ?>">
                                <div class="conversation-info">
                                    <h4><?php echo $conversation['listing_title']; ?></h4>
                                    <p>With <?php echo $conversation['other_user']; ?></p>
                                    <p class="last-message"><?php echo strlen($conversation['last_message']) > 50 ? substr($conversation['last_message'], 0, 50) . '...' : $conversation['last_message']; ?></p>
                                    <span class="conversation-time"><?php echo date('M j, g:i A', strtotime($conversation['last_message_time'])); ?></span>
                                </div>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No conversations yet. Start a conversation by contacting a seller.</p>
                <?php endif; ?>
            </div>
            
            <div class="conversation-view">
                <?php if ($current_conversation): ?>
                    <div class="conversation-header">
                        <div class="conversation-details">
                            <h3><?php echo $current_conversation['title']; ?></h3>
                            <p>Conversation with <?php echo $current_conversation['other_user']; ?></p>
                        </div>
                        <div class="conversation-actions">
                            <a href="view-listing.php?id=<?php echo $conversation_id; ?>" class="btn btn-small btn-primary">View Listing</a>
                        </div>
                    </div>
                    
                    <div class="messages-list" id="messages-list">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <div class="message-content">
                                    <p><?php echo $message['message']; ?></p>
                                    <span class="message-time"><?php echo date('g:i A', strtotime($message['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="message-input">
                        <form method="POST" action="">
                            <div class="input-group">
                                <input type="text" name="message" placeholder="Type your message..." required>
                                <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-conversation">
                        <i class="fas fa-comments"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the list to start messaging.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($current_conversation): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesList = document.getElementById('messages-list');
    
    // Scroll to bottom function
    function scrollToBottom() {
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
    }
    
    // Initial scroll to bottom
    setTimeout(scrollToBottom, 100);
    
    // Auto-scroll when new messages are added
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                setTimeout(scrollToBottom, 100);
            }
        });
    });
    
    // Start observing for new messages
    if (messagesList) {
        observer.observe(messagesList, { childList: true });
    }
});
</script>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
