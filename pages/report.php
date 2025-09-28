<?php
$pageTitle = "Report Issue";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get parameters if coming from a specific listing or user
$reported_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$reported_listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : null;

// Get user and listing details if provided
$reported_user = null;
$reported_listing = null;

if ($reported_user_id) {
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$reported_user_id]);
    $reported_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($reported_listing_id) {
    $stmt = $pdo->prepare("
        SELECT l.id, b.title, u.username 
        FROM listings l 
        JOIN books b ON l.book_id = b.id 
        JOIN users u ON l.seller_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$reported_listing_id]);
    $reported_listing = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Process report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reported_user_id = isset($_POST['reported_user_id']) ? intval($_POST['reported_user_id']) : null;
    $reported_listing_id = isset($_POST['reported_listing_id']) ? intval($_POST['reported_listing_id']) : null;
    $reason = sanitize($_POST['reason']);
    
    // Validate input
    $errors = [];
    
    if (empty($reason)) {
        $errors[] = "Please provide a reason for your report.";
    }
    
    if (empty($reported_user_id) && empty($reported_listing_id)) {
        $errors[] = "You must report either a user or a listing.";
    }
    
    if (empty($errors)) {
        // Insert report into database
        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, reported_user_id, reported_listing_id, reason) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$user_id, $reported_user_id, $reported_listing_id, $reason])) {
            $_SESSION['success'] = "Your report has been submitted successfully. Our admin team will review it shortly.";
            header('Location: dashboard.php');
            exit();
        } else {
            $errors[] = "An error occurred while submitting your report. Please try again.";
        }
    }
}
?>

<div class="form-container">
    <div class="form-content">
        <h1>Report an Issue</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
<div class="report-info">
    <!-- Show the logged-in reporter -->
    <div class="info-card">
        <h3>Your Information (Reporter)</h3>
        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
        <p><strong>Username:</strong> <?php echo $_SESSION['username']; ?></p>
    </div>

    <!-- Show the reported user if applicable -->
    <?php if ($reported_user): ?>
        <div class="info-card">
            <h3>Reported User</h3>
            <p><strong>Name:</strong> <?php echo $reported_user['first_name'] . ' ' . $reported_user['last_name']; ?></p>
            <p><strong>Username:</strong> <?php echo $reported_user['username']; ?></p>
        </div>
    <?php endif; ?>

    <!-- Show the reported listing if applicable -->
    <?php if ($reported_listing): ?>
        <div class="info-card">
            <h3>Reported Listing</h3>
            <p><strong>Title:</strong> <?php echo $reported_listing['title']; ?></p>
            <p><strong>Seller:</strong> <?php echo $reported_listing['username']; ?></p>
        </div>
    <?php endif; ?>
</div>

        
        <form method="POST" action="">
            <?php if ($reported_user_id): ?>
                <input type="hidden" name="reported_user_id" value="<?php echo $reported_user_id; ?>">
            <?php endif; ?>
            
            <?php if ($reported_listing_id): ?>
                <input type="hidden" name="reported_listing_id" value="<?php echo $reported_listing_id; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="reason" class="form-label">Reason for Report *</label>
                <textarea id="reason" name="reason" class="form-control" rows="6" required placeholder="Please provide detailed information about the issue you're reporting..."><?php echo isset($_POST['reason']) ? $_POST['reason'] : ''; ?></textarea>
                <small>Be specific about what's wrong. This helps our admin team address the issue quickly.</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Common Issues</label>
                <div class common-issues>
                    <button type="button" class="issue-btn" data-reason="User not responding to messages">User not responding to messages</button>
                    <button type="button" class="issue-btn" data-reason="Suspicious or scam behavior">Suspicious or scam behavior</button>
                    <button type="button" class="issue-btn" data-reason="Inappropriate content">Inappropriate content</button>
                    <button type="button" class="issue-btn" data-reason="Item condition misrepresented">Item condition misrepresented</button>
                    <button type="button" class="issue-btn" data-reason="Price manipulation">Price manipulation</button>
                    <button type="button" class="issue-btn" data-reason="Harassment or abusive behavior">Harassment or abusive behavior</button>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Report</button>
                <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reasonTextarea = document.getElementById('reason');
    const issueButtons = document.querySelectorAll('.issue-btn');
    
    issueButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reason = this.dataset.reason;
            if (reasonTextarea.value) {
                reasonTextarea.value += '\n\n' + reason;
            } else {
                reasonTextarea.value = reason;
            }
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>