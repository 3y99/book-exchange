<?php
$pageTitle = "Admin - Edit Book";
require_once '../includes/config.php';
require_once 'header.php';

// Check admin privileges
if (!isAdmin()) {
    header('Location: ../pages/login.php');
    exit();
}

$book_id = $_GET['id'] ?? 0;
$book = null;

if ($book_id) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
}

if (!$book) {
    header('Location: listings.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $course_code = $_POST['course_code'] ?? '';
    
    // Track changes
    $changes = [];
    if ($title != $book['title']) $changes['title'] = $title;
    if ($author != $book['author']) $changes['author'] = $author;
    if ($isbn != $book['isbn']) $changes['isbn'] = $isbn;
    if ($description != $book['description']) $changes['description'] = $description;
    if ($category_id != $book['category_id']) $changes['category_id'] = $category_id;
    if ($course_code != $book['course_code']) $changes['course_code'] = $course_code;
    
    // Update book
    $stmt = $pdo->prepare("UPDATE books SET title = ?, author = ?, isbn = ?, description = ?, category_id = ?, course_code = ? WHERE id = ?");
    $stmt->execute([$title, $author, $isbn, $description, $category_id, $course_code, $book_id]);
    
    // Log the edit
    if (!empty($changes)) {
        $stmt = $pdo->prepare("INSERT INTO book_edits (book_id, admin_id, edited_fields) VALUES (?, ?, ?)");
        $stmt->execute([$book_id, $_SESSION['user_id'], json_encode($changes)]);
    }
    
    $_SESSION['success'] = "Book updated successfully!";
    header('Location: listings.php');
    exit();
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Edit Book: <?php echo htmlspecialchars($book['title']); ?></h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="book-edit-form">
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Author:</label>
                <input type="text" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>ISBN:</label>
                <input type="text" name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>">
            </div>
            
            <div class="form-group">
                <label>Description:</label>
                <textarea name="description" rows="4"><?php echo htmlspecialchars($book['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Category:</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $book['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Course Code:</label>
                <input type="text" name="course_code" value="<?php echo htmlspecialchars($book['course_code']); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Update Book</button>
            <a href="listings.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>