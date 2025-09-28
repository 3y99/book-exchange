<?php
$pageTitle = "Admin - Manage Users";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
requireAdmin();

// Handle user actions (toggle admin, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'delete') {
        // Prevent admin from deleting themselves
        if ($userId != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['success'] = "User deleted successfully.";
        } else {
            $_SESSION['error'] = "You cannot delete your own account.";
        }
    } elseif ($action == 'toggle_admin') {
        // Prevent admin from removing their own admin status
        if ($userId != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $newStatus = $user['is_admin'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            
            $_SESSION['success'] = "User admin status updated successfully.";
        } else {
            $_SESSION['error'] = "You cannot change your own admin status.";
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Users</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- All Users Section -->
        <div class="admin-section">
            <h2>All Users</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>University</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <strong><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></strong><br>
                                <small>@<?php echo $user['username']; ?></small>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['university'] ?: 'Not specified'; ?></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <span class="status-badge status-admin">Admin</span>
                                <?php else: ?>
                                    <span class="status-badge status-user">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../pages/profile.php?id=<?php echo $user['id']; ?>" class="btn btn-small btn-primary" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['is_admin']): ?>
                                            <a href="?action=toggle_admin&id=<?php echo $user['id']; ?>" class="btn btn-small btn-warning" onclick="return confirm('Are you sure you want to remove admin privileges from this user?')">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=toggle_admin&id=<?php echo $user['id']; ?>" class="btn btn-small btn-success" onclick="return confirm('Are you sure you want to make this user an admin?')">
                                                <i class="fas fa-user-shield"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

<?php
require_once 'footer.php';
?>
