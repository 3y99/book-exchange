<?php
$pageTitle = "My Reports";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get reports submitted by the logged-in user
$sql = "
    SELECT r.*, 
           u1.username AS reported_user_username,
           b.title AS reported_listing_title
    FROM reports r
    LEFT JOIN users u1 ON r.reported_user_id = u1.id
    LEFT JOIN listings l ON r.reported_listing_id = l.id
    LEFT JOIN books b ON l.book_id = b.id
    WHERE r.reporter_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="reports-container">
    <div class="container">
        <h1>My Reports</h1>

        <p>You have submitted <?php echo count($reports); ?> reports</p>

        <div class="reports-list">
            <?php if (count($reports) > 0): ?>
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="report-meta">
                                <span class="report-id">Report #<?php echo $report['id']; ?></span>
                                <span class="report-status status-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                                <span class="report-date">Submitted: <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="report-content">
                            <div class="report-details">
                                <?php if ($report['reported_user_username']): ?>
                                    <p><strong>Reported User:</strong> <?php echo $report['reported_user_username']; ?></p>
                                <?php endif; ?>

                                <?php if ($report['reported_listing_title']): ?>
                                    <p><strong>Reported Listing:</strong> <?php echo $report['reported_listing_title']; ?></p>
                                <?php endif; ?>

                                <p><strong>Reason:</strong> <?php echo nl2br($report['reason']); ?></p>

                                <?php if ($report['admin_notes']): ?>
                                    <div class="admin-response">
                                        <h4>Admin Response</h4>
                                        <div class="response-content">
                                            <?php echo nl2br($report['admin_notes']); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="no-response">
                                        <p><i class="fas fa-clock"></i> Your report is under review. You will see the admin’s response here once available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-reports">
                    <i class="fas fa-flag"></i>
                    <h3>No Reports Submitted</h3>
                    <p>You haven't submitted any reports yet. If you encounter any issues with users or listings, you can report them using the "Report" button on profiles or listing pages.</p>
                    <a href="listings.php" class="btn btn-primary">Browse Listings</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
