<?php
$pageTitle = "Admin - Manage Messages";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
requireAdmin();

// Handle sending admin message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $user_id = intval($_POST['user_id']);
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);
        
        if (empty($subject) || empty($message)) {
            $_SESSION['error'] = "Subject and message are required.";
        } else {
            if (sendAdminMessage($_SESSION['user_id'], $user_id, $subject, $message)) {
                $_SESSION['success'] = "Message sent successfully.";
            } else {
                $_SESSION['error'] = "Failed to send message.";
            }
        }
    }
    
    // Handle admin follow-up
    if (isset($_POST['send_followup'])) {
        $message_id = intval($_POST['message_id']);
        $followup = sanitize($_POST['followup']);
        
        if (empty($followup)) {
            $_SESSION['error'] = "Follow-up message cannot be empty.";
        } else {
            if (addAdminFollowup($message_id, $followup)) {
                $_SESSION['success'] = "Follow-up sent successfully.";
            } else {
                $_SESSION['error'] = "Failed to send follow-up.";
            }
        }
    }
    
    header('Location: messages.php');
    exit();
}

// Get all users for dropdown
$stmt = $pdo->query("SELECT id, username, first_name, last_name, email FROM users WHERE is_admin = 0 ORDER BY username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all admin messages with responses
$admin_messages = getAdminMessagesWithResponses();
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Admin Messages</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Send Message Section -->
        <div class="admin-section">
            <h2>Send Message to User</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id" class="form-label">Select User</label>
                    <select id="user_id" name="user_id" class="form-control" required>
                        <option value="">Select a user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['username']; ?> (<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="message" class="form-label">Message</label>
                    <textarea id="message" name="message" class="form-control" rows="6" required></textarea>
                </div>
                
                <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
            </form>
        </div>

        <!-- Sent Messages History -->
        <div class="admin-section">
            <h2>Message History</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>To User</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>User Response</th>
                            <th>Admin Follow-up</th>
                            <th>Sent By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admin_messages as $msg): ?>
                        <tr>
                            <td>
                                <strong><?php echo $msg['user_first_name'] . ' ' . $msg['user_last_name']; ?></strong><br>
                                <small>@<?php echo $msg['user_username']; ?></small><br>
                                <small><?php echo $msg['user_email']; ?></small>
                            </td>
                            <td><?php echo $msg['subject']; ?></td>
                            <td>
                                <?php if ($msg['is_read']): ?>
                                    <span class="status-badge status-read">Read</span>
                                <?php else: ?>
                                    <span class="status-badge status-unread">Unread</span>
                                <?php endif; ?>
                                <?php if ($msg['user_response']): ?>
                                    <span class="status-badge status-replied">Replied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($msg['user_response']): ?>
                                    <small><?php echo date('M j, Y', strtotime($msg['responded_at'])); ?></small><br>
                                    <button type="button" class="btn btn-sm btn-info" onclick="alert('<?php echo addslashes(nl2br($msg['user_response'])); ?>')">View Response</button>
                                <?php else: ?>
                                    <span class="text-muted">No response</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($msg['admin_followup']): ?>
                                    <small><?php echo date('M j, Y', strtotime($msg['admin_replied_at'])); ?></small><br>
                                    <button type="button" class="btn btn-sm btn-success" onclick="alert('<?php echo addslashes(nl2br($msg['admin_followup'])); ?>')">View Follow-up</button>
                                <?php else: ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="button" class="btn btn-sm btn-warning" onclick="showFollowupForm(<?php echo $msg['id']; ?>)">Add Follow-up</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $msg['admin_username']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($msg['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!$msg['admin_followup'] && $msg['user_response']): ?>
                                        <form method="POST" action="" class="followup-form" id="followup-form-<?php echo $msg['id']; ?>" style="display: none;">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <div class="form-group">
                                                <textarea name="followup" class="form-control" rows="3" placeholder="Enter follow-up message..." required></textarea>
                                            </div>
                                            <button type="submit" name="send_followup" class="btn btn-sm btn-success">Send</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="hideFollowupForm(<?php echo $msg['id']; ?>)">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showFollowupForm(messageId) {
    document.getElementById('followup-form-' + messageId).style.display = 'block';
}

function hideFollowupForm(messageId) {
    document.getElementById('followup-form-' + messageId).style.display = 'none';
}
</script>

<style>
.followup-form {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-top: 5px;
}
.status-badge.status-replied {
    background: #007bff;
    color: white;
}
</style>

<?php
require_once 'footer.php';
?>