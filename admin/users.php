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

// Handle search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_at_desc';

// Build the query with filters
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role_filter)) {
    if ($role_filter === 'admin') {
        $query .= " AND is_admin = 1";
    } elseif ($role_filter === 'user') {
        $query .= " AND is_admin = 0";
    }
}

// Add sorting
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY first_name ASC, last_name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY first_name DESC, last_name DESC";
        break;
    case 'username_asc':
        $query .= " ORDER BY username ASC";
        break;
    case 'username_desc':
        $query .= " ORDER BY username DESC";
        break;
    case 'email_asc':
        $query .= " ORDER BY email ASC";
        break;
    case 'email_desc':
        $query .= " ORDER BY email DESC";
        break;
    case 'created_at_asc':
        $query .= " ORDER BY created_at ASC";
        break;
    case 'created_at_desc':
    default:
        $query .= " ORDER BY created_at DESC";
        break;
}

// Get filtered users
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total counts for filter badges
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
$total_regular = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
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
        
        <!-- Search and Filter Section -->
        <div class="admin-section">
            <div class="filters-container">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search" class="form-label">Search Users</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Search by name, username, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="role" class="form-label">Filter by Role</label>
                            <select id="role" name="role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Regular Users</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort" class="form-label">Sort By</label>
                            <select id="sort" name="sort" class="form-control">
                                <option value="created_at_desc" <?php echo $sort === 'created_at_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="created_at_asc" <?php echo $sort === 'created_at_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                                <option value="username_asc" <?php echo $sort === 'username_asc' ? 'selected' : ''; ?>>Username A-Z</option>
                                <option value="username_desc" <?php echo $sort === 'username_desc' ? 'selected' : ''; ?>>Username Z-A</option>
                                <option value="email_asc" <?php echo $sort === 'email_asc' ? 'selected' : ''; ?>>Email A-Z</option>
                                <option value="email_desc" <?php echo $sort === 'email_desc' ? 'selected' : ''; ?>>Email Z-A</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="users.php" class="btn btn-outline">Clear All</a>
                        </div>
                    </div>
                </form>
                
                <!-- Filter Badges -->
                <div class="filter-badges">
                    <span class="filter-badge">Total: <?php echo $total_users; ?></span>
                    <span class="filter-badge">Admins: <?php echo $total_admins; ?></span>
                    <span class="filter-badge">Users: <?php echo $total_regular; ?></span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-badge filter-badge-search">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="users.php?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($role_filter)): ?>
                        <span class="filter-badge filter-badge-role">
                            Role: <?php echo ucfirst($role_filter); ?>
                            <a href="users.php?<?php echo http_build_query(array_merge($_GET, ['role' => ''])); ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- All Users Section -->
        <div class="admin-section">
            <h2>
                All Users 
                <?php if (!empty($search) || !empty($role_filter)): ?>
                    <small>(Filtered: <?php echo count($users); ?> results)</small>
                <?php endif; ?>
            </h2>
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
                        <?php if (count($users) > 0): ?>
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
                                        <!-- Add this message button -->
                                        <a href="messages.php?user_id=<?php echo $user['id']; ?>" class="btn btn-small btn-info">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <?php if ($user['is_admin']): ?>
                                                <a href="?action=toggle_admin&id=<?php echo $user['id']; ?>" class="btn btn-small btn-warning" onclick="return confirm('Are you sure you want to remove admin privileges from this user?')">
                                                    <i class="fas fa-user"></i>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="no-results">
                                        <i class="fas fa-search"></i>
                                        <p>No users found matching your criteria.</p>
                                        <a href="users.php" class="btn btn-primary">Clear Filters</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.filters-container {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: end;
}

.filter-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.filter-badge {
    background: #e9ecef;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-badge-search {
    background: #d1ecf1;
    color: #0c5460;
}

.filter-badge-role {
    background: #d4edda;
    color: #155724;
}

.remove-filter {
    color: inherit;
    text-decoration: none;
    font-weight: bold;
    margin-left: 5px;
    cursor: pointer;
}

.remove-filter:hover {
    color: #dc3545;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #dee2e6;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .filter-actions {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
require_once 'footer.php';
?>