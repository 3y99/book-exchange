<?php
$pageTitle = "Admin Dashboard";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
requireAdmin();

// Get statistics for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM listings");
$totalListings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status = 'completed'");
$totalTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'");
$pendingReports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total admins (pending approval column removed)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
$pendingAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent activities
$stmt = $pdo->query("
    (SELECT 'listing' as type, l.id, b.title as name, u.username, l.created_at 
     FROM listings l 
     JOIN users u ON l.seller_id = u.id 
     JOIN books b ON l.book_id = b.id 
     ORDER BY l.created_at DESC LIMIT 5)
    UNION
    (SELECT 'user' as type, id, CONCAT(first_name, ' ', last_name) as name, username, created_at 
     FROM users 
     ORDER BY created_at DESC LIMIT 5)
    UNION
    (SELECT 'report' as type, r.id, r.reason as name, u.username, r.created_at 
     FROM reports r 
     JOIN users u ON r.reporter_id = u.id 
     ORDER BY r.created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<div class="admin-container">
    <div class="admin-content">
        <h1>Admin Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalListings; ?></h3>
                    <p>Total Listings</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalTransactions; ?></h3>
                    <p>Completed Transactions</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pendingReports; ?></h3>
                    <p>Pending Reports</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pendingAdmins; ?></h3>
                    <p>Pending Admin Approvals</p>
                </div>
            </div>
        </div>
        
        
        <div class="recent-activities">
            <div class="activities-header">
                <h2>Recent Activities</h2>
                <button class="toggle-activities-btn" onclick="toggleActivities()">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="activity-list" id="activityList">
                <?php
                if (count($activities) > 0) {
                    foreach ($activities as $activity) {
                        $icon = '';
                        $bgColor = '';
                        
                        switch ($activity['type']) {
                            case 'listing':
                                $icon = 'fa-book';
                                $bgColor = 'var(--primary-color)';
                                break;
                            case 'user':
                                $icon = 'fa-user';
                                $bgColor = 'var(--success-color)';
                                break;
                            case 'report':
                                $icon = 'fa-flag';
                                $bgColor = 'var(--warning-color)';
                                break;
                        }
                        
                        echo "
                        <div class='activity-item'>
                            <div class='activity-icon' style='background-color: {$bgColor}'>
                                <i class='fas {$icon}'></i>
                            </div>
                            <div class='activity-details'>
                                <p><strong>{$activity['name']}</strong> by {$activity['username']}</p>
                                <small>" . date('M j, Y g:i A', strtotime($activity['created_at'])) . "</small>
                            </div>
                        </div>
                        ";
                    }
                } else {
                    echo "<p>No recent activities found.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.activities-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.toggle-activities-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--text-color);
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.toggle-activities-btn:hover {
    background-color: var(--light-gray);
}

.activity-list {
    transition: all 0.3s ease;
}

.activity-list.collapsed {
    display: none;
}

.toggle-activities-btn .fa-chevron-down {
    transition: transform 0.3s ease;
}

.toggle-activities-btn.collapsed .fa-chevron-down {
    transform: rotate(-90deg);
}
</style>

<script>
function toggleActivities() {
    const activityList = document.getElementById('activityList');
    const toggleBtn = document.querySelector('.toggle-activities-btn');
    
    activityList.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
    
    // Update button icon
    const icon = toggleBtn.querySelector('i');
    if (activityList.classList.contains('collapsed')) {
        icon.className = 'fas fa-chevron-right';
    } else {
        icon.className = 'fas fa-chevron-down';
    }
}

// Optional: Start with activities collapsed
 document.addEventListener('DOMContentLoaded', function() {
    toggleActivities(); // Uncomment this line if you want activities to start collapsed
 });
</script>

<?php
require_once 'footer.php';
?>