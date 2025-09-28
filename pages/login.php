<?php
$pageTitle = "Login";   //Set title（Login）
require_once '../includes/config.php';  //Import the configuration file config.php

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {  //If the user has already logged in 
    header('Location: dashboard.php');  //jump directly to dashboard.php
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {   //When the user clicks the "login" button to submit
    $username = sanitize($_POST['username']);  //Read the input (username and password)
    $password = $_POST['password'];            //sanitize() :Clean up the input, prevent XSS/SQL injection attacks
    
    // Validate input
    $errors = [];  //Save the error message
    
    if (empty($username)) {
        $errors[] = "Username is required.";  
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    if (empty($errors)) {     //Check the database when there are no input errors
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {   //If the data matches the database
            // Login successful
            $_SESSION['user_id'] = $user['id'];         //Store user information in SESSION
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Redirect to dashboard after successful login
            header('Location: dashboard.php');
            exit();
        } else {                  //Show error if user does not exsist or wrong password
            $errors[] = "Invalid username or password.";
        }
    }
}

//HTML front-end form section
require_once '../includes/header.php';  //Introduce a common head
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