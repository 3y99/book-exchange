<?php
$pageTitle = "Admin Registration";
require_once '../includes/config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isAdmin()) {
    header('Location: index.php');
    exit();
}

// Process admin registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists.";
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database as admin
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, is_admin) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name])) {
            $_SESSION['success'] = "Admin account created successfully. You can now log in.";
            header('Location: login.php');
            exit();
        } else {
            $errors[] = "An error occurred. Please try again.";
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
            max-width: 500px;
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
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="admin-auth-container">
        <div class="admin-auth-box">
            <div class="admin-auth-header">
                <div class="admin-auth-logo">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1>Admin Registration</h1>
                <p>Create an admin account for <?php echo SITE_NAME; ?></p>
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
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Register as Admin</button>
                </form>
            </div>
            
            <div class="admin-auth-footer">
                <p>Already have an admin account? <a href="login.php">Login here</a></p>
                <p>Are you a regular user? <a href="../pages/register.php">Go to user registration</a></p>
            </div>
        </div>
    </div>
</body>
</html>
