<?php
$pageTitle = "Choose Your Role";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    
    if (in_array($role, ['buyer', 'seller'])) {
        // Update user role in database
        $stmt = $pdo->prepare("UPDATE users SET user_role = ?, role_selected_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $_SESSION['user_id']]);
        
        // Update session
        $_SESSION['user_role'] = $role;
        
        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Please select a valid role";
    }
}

// Get current role if any to pre-select it
$current_role = null;
$stmt = $pdo->prepare("SELECT user_role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if ($user && $user['user_role']) {
    $current_role = $user['user_role'];
}
?>

<style>
/* Hide dashboard and messages tabs in navbar for role selection page */
.nav li a[href*="dashboard.php"],
.nav li a[href*="messages.php"],
.mobile-menu li a[href*="dashboard.php"],
.mobile-menu li a[href*="messages.php"] {
    display: none !important;
}
</style>

<div class="role-selection-container">
    <div class="role-selection-card">
        <h2>Choose Your Role</h2>
        <p>Please select how you'd like to use BookExchange today:</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="role-form">
            <div class="role-options">
                <label class="role-option">
                    <input type="radio" name="role" value="buyer" required <?php echo ($current_role == 'buyer') ? 'checked' : ''; ?>>
                    <div class="role-card">
                        <div class="role-icon">📚</div>
                        <h3>Buyer</h3>
                        <p>Browse and purchase books from other students</p>
                        <ul>
                            <li>Search and browse listings</li>
                            <li>Contact sellers</li>
                            <li>Make purchases</li>
                            <li>Add to watchlist</li>
                        </ul>
                    </div>
                </label>
                
                <label class="role-option">
                    <input type="radio" name="role" value="seller" required <?php echo ($current_role == 'seller') ? 'checked' : ''; ?>>
                    <div class="role-card">
                        <div class="role-icon">💰</div>
                        <h3>Seller</h3>
                        <p>Sell your books to other students</p>
                        <ul>
                            <li>Create book listings</li>
                            <li>Manage your inventory</li>
                            <li>Respond to buyers</li>
                            <li>Track sales</li>
                        </ul>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Continue</button>
        </form>
        
        <p class="note">You can choose a different role every time you log in.</p>
    </div>
</div>

<style>
.role-selection-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.role-selection-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.role-selection-card h2 {
    color: #333;
    margin-bottom: 0.5rem;
}

.role-selection-card > p {
    color: #666;
    margin-bottom: 2rem;
}

.role-form {
    margin-top: 2rem;
}

.role-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.role-option input[type="radio"] {
    display: none;
}

.role-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
}

.role-option input[type="radio"]:checked + .role-card {
    border-color: #007bff;
    background-color: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
}

.role-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.role-card h3 {
    color: #333;
    margin-bottom: 1rem;
}

.role-card p {
    color: #666;
    margin-bottom: 1rem;
    font-weight: 500;
}

.role-card ul {
    text-align: left;
    color: #666;
    margin-top: 1rem;
}

.role-card ul li {
    margin-bottom: 0.5rem;
    padding-left: 1rem;
}

.note {
    color: #888;
    font-size: 0.9rem;
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .role-options {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>