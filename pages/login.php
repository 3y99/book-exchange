<?php
$pageTitle = "Login";
require_once '../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: role-selection.php');
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    if (empty($errors)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if user is banned
            if ($user['is_banned']) {
                $errors[] = "Your account has been banned. Please contact support.";
            } else {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Always redirect to role selection after login
                header('Location: role-selection.php');
                exit();
            }
        } else {
            $errors[] = "Invalid username or password.";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-form">
        <h1>Login to Your Account</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </form>
        
        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>