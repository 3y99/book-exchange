<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'book_exchange');
define('DB_USER', 'root');
define('DB_PORT', '3306');
define('DB_PASS', 'wenman328328@');

// Site configuration
define('SITE_NAME', 'CampusBookSwap');
define('SITE_URL', 'http://localhost/book-exchange');

// File upload paths
define('BOOK_IMAGE_PATH', 'assets/images/uploads/books/');
define('PROFILE_IMAGE_PATH', 'assets/images/uploads/profiles/');

// ChatGPT API Configuration
define('CHATGPT_API_KEY', 'getenv("OPENAI_API_KEY")';
define('CHATGPT_API_URL', 'https://api.openai.com/v1/chat/completions');

// Start session
session_start();

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, 
        DB_USER, 
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include functions
require_once 'functions.php';
?>
