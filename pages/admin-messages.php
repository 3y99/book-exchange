<?php
$pageTitle = "Admin Messages";
require_once '../includes/config.php';

// Check if user is logged in
requireAuth();

$user_id = $_SESSION['user_id'];

// Handle user response - must be before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_response'])) {
    $message_id = intval($_POST['message_id']);
    $response = sanitize($_POST['response']);
    
    if (empty($response)) {
        $_SESSION['error'] = "Response cannot be empty.";
    } else {
        if (addUserResponse($message_id, $user_id, $response)) {
            $_SESSION['success'] = "Response sent successfully.";
        } else {
            $_SESSION['error'] = "Failed to send response.";
        }
    }
    
    header('Location: admin-messages.php?view=' . $message_id);
    exit();
}

// Now include header after handling redirects
require_once '../includes/header.php';

// Get specific message if viewing
$current_message = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $message_id = intval($_GET['view']);
    $current_message = getAdminMessageWithResponses($message_id, $user_id);
    
    if ($current_message) {
        markAdminMessageAsRead($message_id, $user_id);
    }
}

// Get user's admin messages
$admin_messages = getUserAdminMessages($user_id);

// Get unread count for badge
$unread_count = getUnreadAdminMessageCount($user_id);
?>

<div class="container">
    <div class="page-header">
        <h1>Admin Messages</h1>
        <?php if ($unread_count > 0): ?>
            <span class="badge badge-warning"><?php echo $unread_count; ?> unread</span>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (empty($admin_messages)): ?>
        <div class="alert alert-info">
            <p>You have no messages from administrators.</p>
        </div>
    <?php else: ?>
        
        <?php if ($current_message): ?>
            <!-- Single Message View with Response Form -->
            <div class="message-detail-view">
                <a href="admin-messages.php" class="btn btn-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Back to Messages
                </a>
                
                <!-- Original Admin Message -->
                <div class="message-card admin-message">
                    <div class="message-header">
                        <div class="message-sender">
                            <i class="fas fa-shield-alt admin-badge" title="Administrator"></i>
                            <strong><?php echo $current_message['admin_first_name'] . ' ' . $current_message['admin_last_name']; ?></strong>
                            <small>(@<?php echo $current_message['admin_username']; ?>)</small>
                        </div>
                        <div class="message-date">
                            <?php echo date('M j, Y g:i A', strtotime($current_message['created_at'])); ?>
                        </div>
                    </div>
                    <div class="message-subject">
                        <strong><?php echo $current_message['subject']; ?></strong>
                    </div>
                    <div class="message-content">
                        <?php echo nl2br($current_message['message']); ?>
                    </div>
                </div>

                <!-- User Response (if exists) -->
                <?php if ($current_message['user_response']): ?>
                    <div class="message-card user-response">
                        <div class="message-header">
                            <div class="message-sender">
                                <i class="fas fa-user user-badge" title="Your Response"></i>
                                <strong>Your Response</strong>
                            </div>
                            <div class="message-date">
                                <?php echo date('M j, Y g:i A', strtotime($current_message['responded_at'])); ?>
                            </div>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br($current_message['user_response']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Admin Follow-up (if exists) -->
                <?php if ($current_message['admin_followup']): ?>
                    <div class="message-card admin-followup">
                        <div class="message-header">
                            <div class="message-sender">
                                <i class="fas fa-shield-alt admin-badge" title="Administrator Follow-up"></i>
                                <strong>Admin Follow-up</strong>
                            </div>
                            <div class="message-date">
                                <?php echo date('M j, Y g:i A', strtotime($current_message['admin_replied_at'])); ?>
                            </div>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br($current_message['admin_followup']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Response Form (only if no response yet) -->
                <?php if (!$current_message['user_response']): ?>
                    <div class="response-form-container">
                        <h3>Reply to Admin</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="message_id" value="<?php echo $current_message['id']; ?>">
                            <div class="form-group">
                                <label for="response" class="form-label">Your Response</label>
                                <textarea id="response" name="response" class="form-control" rows="6" required placeholder="Type your response to the administrator..."></textarea>
                            </div>
                            <button type="submit" name="send_response" class="btn btn-primary">Send Response</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>You have already responded to this message. Wait for the administrator to reply if needed.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Messages List View -->
            <div class="messages-list">
                <?php foreach ($admin_messages as $message): ?>
                    <div class="message-card <?php echo $message['is_read'] ? 'read' : 'unread'; ?> clickable-message" onclick="window.location.href='?view=<?php echo $message['id']; ?>'">
                        <div class="message-header">
                            <div class="message-sender">
                                <i class="fas fa-shield-alt admin-badge" title="Administrator"></i>
                                <strong><?php echo $message['admin_first_name'] . ' ' . $message['admin_last_name']; ?></strong>
                                <small>(@<?php echo $message['admin_username']; ?>)</small>
                            </div>
                            <div class="message-date">
                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                <?php if (!$message['is_read']): ?>
                                    <span class="badge badge-new">New</span>
                                <?php endif; ?>
                                <?php if ($message['user_response']): ?>
                                    <span class="badge badge-replied">Replied</span>
                                <?php endif; ?>
                                <?php if ($message['admin_followup']): ?>
                                    <span class="badge badge-followup">Follow-up</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="message-subject">
                            <strong><?php echo $message['subject']; ?></strong>
                        </div>
                        <div class="message-preview">
                            <?php 
                            $preview = strip_tags($message['message']);
                            echo strlen($preview) > 150 ? substr($preview, 0, 150) . '...' : $preview;
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.admin-badge {
    color: #dc3545;
    margin-right: 5px;
}
.user-badge {
    color: #007bff;
    margin-right: 5px;
}
.message-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fff;
    transition: all 0.3s ease;
}
.message-card.unread {
    border-left: 4px solid #007bff;
    background: #f8f9fa;
}
.message-card.clickable-message {
    cursor: pointer;
}
.message-card.clickable-message:hover {
    background: #f0f0f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.admin-message {
    border-left: 4px solid #dc3545;
}
.user-response {
    border-left: 4px solid #007bff;
    background: #f8f9ff;
}
.admin-followup {
    border-left: 4px solid #28a745;
    background: #f8fff8;
}
.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.message-sender {
    display: flex;
    align-items: center;
}
.badge-new {
    background: #dc3545;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-left: 5px;
}
.badge-replied {
    background: #007bff;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-left: 5px;
}
.badge-followup {
    background: #28a745;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-left: 5px;
}
.message-subject {
    margin-bottom: 10px;
    font-size: 1.1em;
}
.message-content {
    line-height: 1.6;
}
.message-preview {
    color: #666;
    line-height: 1.4;
}
.response-form-container {
    margin-top: 30px;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f8f9fa;
}
.response-form-container h3 {
    margin-bottom: 15px;
    color: #333;
}
.mb-3 {
    margin-bottom: 1rem;
}
</style>

<?php
require_once '../includes/footer.php';
?>