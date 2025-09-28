<?php
// Redirect to login if not authenticated
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Redirect to dashboard if already authenticated
function redirectIfAuthenticated() {
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }
}

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
/*
// Upload image
function uploadImage($file, $targetDir, $maxSize = 5000000) {
    $errors = [];
    $fileName = basename($file['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Check if image file is actual image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        $errors[] = "File is not an image.";
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File is too large. Maximum size is " . ($maxSize / 1000000) . "MB.";
    }
    
    // Allow certain file formats
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }
    
    // Generate unique filename if file already exists
    $counter = 1;
    while (file_exists($targetFilePath)) {
        $fileName = pathinfo($file['name'], PATHINFO_FILENAME) . '_' . $counter . '.' . $fileType;
        $targetFilePath = $targetDir . $fileName;
        $counter++;
    }
    
    if (empty($errors)) {
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            return $fileName;
        } else {
            $errors[] = "Sorry, there was an error uploading your file.";
        }
    }
    
    return ['errors' => $errors];
}
    */
// Upload image safely
function uploadImage($file, $targetDir, $maxSize = 5000000) {
    $errors = [];

    // Ensure target directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Validate image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        $errors[] = "File is not a valid image.";
    }

    if ($file['size'] > $maxSize) {
        $errors[] = "File is too large. Maximum size is " . ($maxSize / 1000000) . "MB.";
    }

    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }

    if (!empty($errors)) {
        return ['errors' => $errors];
    }

    // Generate unique safe filename
    $fileName = uniqid('profile_', true) . '.' . $fileType;
    $targetFilePath = rtrim($targetDir, '/') . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        return $fileName; // return just filename, store in DB
    } else {
        return ['errors' => ["Error uploading file."]];
    }
}


// Format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Get book condition text
function getConditionText($condition) {
    $conditions = [
        'new' => 'New',
        'like_new' => 'Like New',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor'
    ];
    
    return isset($conditions[$condition]) ? $conditions[$condition] : 'Unknown';
}

// Simplified isAdmin check (admin_approved removed)
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Redirect to admin login if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /book-exchange/admin/login.php');
        exit();
    }
}

// NEW FUNCTIONS
// Get user's reports with response status
function getUserReports($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, 
               reported_user.username as reported_user_username,
               reported_listing.title as reported_listing_title
        FROM reports r
        LEFT JOIN users reported_user ON r.reported_user_id = reported_user.id
        LEFT JOIN listings reported_listing ON r.reported_listing_id = reported_listing.id
        WHERE r.reporter_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mark report response as read
function markReportResponseAsRead($report_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE reports 
        SET response_read = TRUE 
        WHERE id = ? AND reporter_id = ?
    ");
    return $stmt->execute([$report_id, $user_id]);
}

// Get unread response count for user
function getUnreadReportResponseCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE reporter_id = ? 
        AND admin_response IS NOT NULL 
        AND response_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}
?>
