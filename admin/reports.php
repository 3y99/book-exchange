<?php
$pageTitle = "Admin - Manage Reports";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
requireAdmin();

// Handle report actions (resolve, delete, update status)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $reportId = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'resolve') {
        $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success'] = "Report marked as resolved.";
    } elseif ($action == 'review') {
        $stmt = $pdo->prepare("UPDATE reports SET status = 'reviewed' WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success'] = "Report marked as reviewed.";
    } elseif ($action == 'reopen') {
        $stmt = $pdo->prepare("UPDATE reports SET status = 'pending' WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success'] = "Report reopened.";
    } elseif ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success'] = "Report deleted successfully.";
    } elseif ($action == 'update_notes') {
        if (isset($_POST['admin_notes'])) {
            $admin_notes = sanitize($_POST['admin_notes']);
            $stmt = $pdo->prepare("UPDATE reports SET admin_notes = ? WHERE id = ?");
            $stmt->execute([$admin_notes, $reportId]);
            $_SESSION['success'] = "Admin notes updated successfully.";
        }
    }
    
    header('Location: reports.php');
    exit();
}

// Get filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with optional status filter

$sql = "
    SELECT r.*, 
           reporter.username AS reporter_username,
           reported_user.username AS reported_user_username,
           b.title AS reported_listing_title,
           u2.username AS reported_listing_seller
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported_user ON r.reported_user_id = reported_user.id
    LEFT JOIN listings reported_listing ON r.reported_listing_id = reported_listing.id
    LEFT JOIN books b ON reported_listing.book_id = b.id
    LEFT JOIN users u2 ON reported_listing.seller_id = u2.id
";


$params = [];

if ($status_filter != 'all') {
    $sql .= " WHERE r.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get report counts for filters
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_count = 0;
$pending_count = 0;
$reviewed_count = 0;
$resolved_count = 0;

foreach ($status_counts as $status) {
    $all_count += $status['count'];
    if ($status['status'] == 'pending') $pending_count = $status['count'];
    if ($status['status'] == 'reviewed') $reviewed_count = $status['count'];
    if ($status['status'] == 'resolved') $resolved_count = $status['count'];
}
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Reports</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- Report Filters -->
        <div class="filters">
            <h3>Filter by Status</h3>
            <div class="filter-buttons">
                <a href="?status=all" class="btn btn-small <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                    All (<?php echo $all_count; ?>)
                </a>
                <a href="?status=pending" class="btn btn-small <?php echo $status_filter == 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="?status=reviewed" class="btn btn-small <?php echo $status_filter == 'reviewed' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Reviewed (<?php echo $reviewed_count; ?>)
                </a>
                <a href="?status=resolved" class="btn btn-small <?php echo $status_filter == 'resolved' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Resolved (<?php echo $resolved_count; ?>)
                </a>
            </div>
        </div>
        
        <div class="reports-list">
            <?php foreach ($reports as $report): ?>
            <div class="report-card">
                <div class="report-header">
                    <div class="report-meta">
                        <span class="report-id">Report #<?php echo $report['id']; ?></span>
                        <span class="report-status status-<?php echo $report['status']; ?>">
                            <?php echo ucfirst($report['status']); ?>
                        </span>
                        <span class="report-date"><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                    </div>
                    <div class="report-actions">
                        <?php if ($report['status'] == 'pending'): ?>
                            <a href="?action=review&id=<?php echo $report['id']; ?>" class="btn btn-small btn-warning">
                                <i class="fas fa-eye"></i> Mark Reviewed
                            </a>
                        <?php elseif ($report['status'] == 'reviewed'): ?>
                            <a href="?action=resolve&id=<?php echo $report['id']; ?>" class="btn btn-small btn-success">
                                <i class="fas fa-check"></i> Resolve
                            </a>
                        <?php else: ?>
                            <a href="?action=reopen&id=<?php echo $report['id']; ?>" class="btn btn-small btn-secondary">
                                <i class="fas fa-redo"></i> Reopen
                            </a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?php echo $report['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this report?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                
                <div class="report-content">
                    <div class="report-details">
                        <p><strong>Reporter:</strong> <?php echo $report['reporter_username']; ?></p>
                        
                        <?php if ($report['reported_user_username']): ?>
                            <p><strong>Reported User:</strong> <?php echo $report['reported_user_username']; ?></p>
                        <?php endif; ?>
                        
                        <?php if ($report['reported_listing_title']): ?>
                            <p><strong>Reported Listing:</strong> <?php echo $report['reported_listing_title']; ?></p>
                            <?php if ($report['reported_listing_seller']): ?>
                                <p><strong>Listing Owner:</strong> <?php echo $report['reported_listing_seller']; ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <p><strong>Reason:</strong> <?php echo nl2br($report['reason']); ?></p>
                        
                        <?php if ($report['admin_notes']): ?>
                            <div class="admin-notes">
                                <h4>Admin Notes</h4>
                                <p><?php echo nl2br($report['admin_notes']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="admin-notes-form">
                        <form method="POST" action="?action=update_notes&id=<?php echo $report['id']; ?>">
                            <div class="form-group">
                                <label for="admin_notes_<?php echo $report['id']; ?>" class="form-label">Admin Notes</label>
                                <textarea id="admin_notes_<?php echo $report['id']; ?>" name="admin_notes" class="form-control" rows="3" placeholder="Add notes about this report..."><?php echo $report['admin_notes'] ?? ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-small btn-primary">Update Notes</button>
                        </form>
                    </div>
                    
                    <div class="report-links">
                        <?php if ($report['reported_user_id']): ?>
                            <a href="../pages/profile.php?id=<?php echo $report['reported_user_id']; ?>" class="btn btn-small btn-primary" target="_blank">
                                <i class="fas fa-user"></i> View User
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($report['reported_listing_id']): ?>
                            <a href="../pages/view-listing.php?id=<?php echo $report['reported_listing_id']; ?>" class="btn btn-small btn-primary" target="_blank">
                                <i class="fas fa-book"></i> View Listing
                            </a>
                        <?php endif; ?>
                        
                        <a href="../pages/profile.php?id=<?php echo $report['reporter_id']; ?>" class="btn btn-small btn-secondary" target="_blank">
                            <i class="fas fa-user"></i> View Reporter
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($reports) == 0): ?>
            <div class="alert alert-info">
                <p>No reports found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>