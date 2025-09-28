<?php
$pageTitle = "Admin Login";
require_once '../includes/config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isAdmin()) {
    header('Location: index.php');
    exit();
}

// Process admin login form
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
        // Check if user exists and is an admin
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_admin = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Redirect to admin dashboard
            header('Location: index.php');
            exit();
        } else {
            $errors[] = "Invalid admin credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            padding: 20px;
        }
        
        .admin-auth-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .admin-auth-header {
            background: var(--secondary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .admin-auth-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .admin-auth-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        
        .admin-auth-form {
            padding: 2rem;
        }
        
        .admin-auth-logo {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .admin-auth-footer {
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid var(--light-gray);
        }
    </style>
</head>
<body>
    <div class="admin-auth-container">
        <div class="admin-auth-box">
            <div class="admin-auth-header">
                <div class="admin-auth-logo">
                    <i class="fas fa-lock"></i>
                </div>
                <h1>Admin Portal</h1>
                <p><?php echo SITE_NAME; ?> Administration</p>
            </div>
            
            <div class="admin-auth-form">
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
                    
                    <button type="submit" class="btn btn-primary btn-full">Login to Admin Portal</button>
                </form>
            </div>
            
            <div class="admin-auth-footer">
                <p>Are you a regular user? <a href="../pages/login.php">Go to user login</a></p>
                <p>Need admin access? <a href="register.php">Register an admin account</a></p>
            </div>
        </div>
    </div>
</body>
</html>
