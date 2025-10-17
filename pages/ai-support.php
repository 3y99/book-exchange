<?php
$pageTitle = "AI Customer Support";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation = [];
$ai_response = '';

// Load conversation history from session
if (isset($_SESSION['ai_conversation'])) {
    $conversation = $_SESSION['ai_conversation'];
}

// Process user message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $user_message = sanitize(trim($_POST['message']));
    
    // Add user message to conversation
    $conversation[] = [
        'sender' => 'user',
        'message' => $user_message,
        'timestamp' => date('H:i:s')
    ];
    
    // Get AI response
    $ai_response = getAIResponse($user_message, $conversation);
    
    // Add AI response to conversation
    $conversation[] = [
        'sender' => 'ai',
        'message' => $ai_response,
        'timestamp' => date('H:i:s')
    ];
    
    // Save conversation to session (limit to last 20 messages)
    if (count($conversation) > 20) {
        $conversation = array_slice($conversation, -20);
    }
    $_SESSION['ai_conversation'] = $conversation;
}

// Clear conversation
if (isset($_GET['clear'])) {
    $_SESSION['ai_conversation'] = [];
    $conversation = [];
    header('Location: ai-support.php');
    exit();
}
?>

<div class="support-container">
    <div class="support-header">
        <h1><i class="fas fa-robot"></i> AI Customer Support</h1>
        <p>Get instant help with your questions about buying, selling, or using BookExchange</p>
        <?php if (!empty($conversation)): ?>
            <a href="ai-support.php?clear=1" class="btn btn-small btn-outline">Clear Conversation</a>
        <?php endif; ?>
    </div>

    <div class="chat-container">
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($conversation)): ?>
                <div class="welcome-message">
                    <div class="ai-message">
                        <div class="message-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-content">
                            <h4>Hello! I'm your BookExchange AI assistant 🤖</h4>
                            <p>I can help you with:</p>
                            <ul>
                                <li>Buying and selling books</li>
                                <li>Pricing your books competitively</li>
                                <li>Shipping and delivery options</li>
                                <li>Finding specific textbooks</li>
                                <li>Account and technical issues</li>
                                <li>Creating effective listings</li>
                                <li>Safety tips for transactions</li>
                            </ul>
                            <p>What would you like to know about BookExchange?</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($conversation as $msg): ?>
                    <div class="<?php echo $msg['sender'] === 'user' ? 'user-message' : 'ai-message'; ?>">
                        <div class="message-avatar">
                            <?php if ($msg['sender'] === 'user'): ?>
                                <i class="fas fa-user"></i>
                            <?php else: ?>
                                <i class="fas fa-robot"></i>
                            <?php endif; ?>
                        </div>
                        <div class="message-content">
                            <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            <span class="message-time"><?php echo $msg['timestamp']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input">
            <form method="POST" id="chatForm">
                <div class="input-group">
                    <input type="text" name="message" placeholder="Ask me anything about BookExchange..." required 
                           class="form-control" id="messageInput" autocomplete="off">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
            <div class="quick-questions">
                <p>Quick questions:</p>
                <div class="quick-buttons">
                    <button type="button" class="btn btn-small btn-outline quick-question" data-question="How do I create a book listing?">
                        How to create listing?
                    </button>
                    <button type="button" class="btn btn-small btn-outline quick-question" data-question="What's the best price for my textbook?">
                        Book pricing help
                    </button>
                    <button type="button" class="btn btn-small btn-outline quick-question" data-question="How do I contact a seller safely?">
                        Contact seller
                    </button>
                    <button type="button" class="btn btn-small btn-outline quick-question" data-question="What are the shipping options?">
                        Shipping info
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const messageInput = document.getElementById('messageInput');
    const chatForm = document.getElementById('chatForm');
    const submitBtn = document.getElementById('submitBtn');
    const quickQuestions = document.querySelectorAll('.quick-question');
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    scrollToBottom();
    
    // Quick question buttons
    quickQuestions.forEach(button => {
        button.addEventListener('click', function() {
            messageInput.value = this.getAttribute('data-question');
            chatForm.submit();
        });
    });
    
    // Auto-focus input
    messageInput.focus();
    
    // Show loading indicator when form is submitted
    chatForm.addEventListener('submit', function(e) {
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';
        submitBtn.disabled = true;
        messageInput.disabled = true;
        
        // Add typing indicator
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'ai-message typing-indicator';
        typingIndicator.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <p>AI is thinking<span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></p>
            </div>
        `;
        chatMessages.appendChild(typingIndicator);
        scrollToBottom();
        
        // Re-enable after 15 seconds in case of timeout
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            messageInput.disabled = false;
            
            // Remove typing indicator if still there
            const indicator = document.querySelector('.typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }, 15000);
    });
    
    // Allow Enter key to submit (but Shift+Enter for new line)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
});
</script>

<style>
.support-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.support-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.support-header h1 {
    color: #333;
    margin-bottom: 0.5rem;
}

.support-header h1 i {
    color: #007bff;
}

.chat-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.chat-messages {
    height: 500px;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f8f9fa;
}

.welcome-message {
    text-align: center;
}

.user-message, .ai-message {
    display: flex;
    margin-bottom: 1.5rem;
    align-items: flex-start;
}

.user-message {
    flex-direction: row-reverse;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 0.75rem;
    flex-shrink: 0;
}

.user-message .message-avatar {
    background: #007bff;
    color: white;
}

.ai-message .message-avatar {
    background: #28a745;
    color: white;
}

.message-content {
    max-width: 70%;
    padding: 0.75rem 1rem;
    border-radius: 18px;
    position: relative;
}

.user-message .message-content {
    background: #007bff;
    color: white;
    border-bottom-right-radius: 4px;
}

.ai-message .message-content {
    background: white;
    border: 1px solid #e9ecef;
    border-bottom-left-radius: 4px;
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.7;
    display: block;
    margin-top: 0.5rem;
}

.chat-input {
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.input-group {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.input-group .form-control {
    flex: 1;
    padding: 0.75rem;
}

.quick-questions p {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.quick-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.quick-buttons .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

/* Loading animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Typing indicator */
.typing-indicator {
    opacity: 0.7;
}

.typing-dots {
    display: inline-flex;
    margin-left: 0.25rem;
}

.typing-dots span {
    animation: typing 1.4s infinite;
    margin: 0 1px;
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { opacity: 0.3; }
    30% { opacity: 1; }
}

/* Responsive */
@media (max-width: 768px) {
    .message-content {
        max-width: 85%;
    }
    
    .quick-buttons {
        flex-direction: column;
    }
    
    .quick-buttons .btn {
        text-align: left;
    }
    
    .chat-messages {
        height: 400px;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>