<?php
$pageTitle = isset($_GET['edit']) ? "Update Listing" : "Create Listing";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect to role selection if not chosen
if (!hasSelectedRole()) {
    header('Location: role-selection.php');
    exit();
}

// Only sellers can create listings
if (!isSeller()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Editing? Load existing listing
$listing_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$listing = null;

if ($listing_id) {
    $stmt = $pdo->prepare("
        SELECT l.*, b.title, b.author, b.isbn, b.description AS book_description, 
               b.category_id, b.course_code 
        FROM listings l
        JOIN books b ON l.book_id = b.id
        WHERE l.id = ? AND l.seller_id = ?
    ");
    $stmt->execute([$listing_id, $user_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        $_SESSION['error'] = "Listing not found or unauthorized.";
        header('Location: my-listings.php');
        exit();
    }

    // Load existing images for preview
    $stmt = $pdo->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC, id ASC");
    $stmt->execute([$listing_id]);
    $listing_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $title = sanitize($_POST['title']);
    $author = sanitize($_POST['author']);
    $isbn = sanitize($_POST['isbn']);
    $description = sanitize($_POST['description']);
    $category_id = $_POST['category_id'];
    $course_code = sanitize($_POST['course_code']);
    $condition = $_POST['condition'];
    $price = floatval($_POST['price']);
    $listing_description = sanitize($_POST['listing_description']);
    
    $errors = [];

    // Validation
    if (empty($title)) $errors[] = "Book title is required.";
    if (empty($author)) $errors[] = "Author is required.";
    if (empty($category_id)) $errors[] = "Category is required.";
    if (empty($condition)) $errors[] = "Condition is required.";
    if (empty($price) || $price <= 0) $errors[] = "Valid price is required.";

    // Handle image uploads
    $uploaded_images = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['images']['name'][$key],
                    'tmp_name' => $tmp_name,
                    'size' => $_FILES['images']['size'][$key]
                ];
                
                $result = uploadImage($file, '../' . BOOK_IMAGE_PATH);
                
                if (is_array($result) && isset($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                } else {
                    $uploaded_images[] = $result;
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if book exists
            $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($book) {
                $book_id = $book['id'];
                $stmt = $pdo->prepare("
                    UPDATE books 
                    SET title = ?, author = ?, description = ?, category_id = ?, course_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $author, $description, $category_id, $course_code, $book_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO books (title, author, isbn, description, category_id, course_code) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $author, $isbn, $description, $category_id, $course_code]);
                $book_id = $pdo->lastInsertId();
            }

            if ($listing_id) {
                $stmt = $pdo->prepare("
                    UPDATE listings 
                    SET book_id = ?, book_condition = ?, price = ?, description = ?
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$book_id, $condition, $price, $listing_description, $listing_id, $user_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO listings (seller_id, book_id, book_condition, price, description) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $book_id, $condition, $price, $listing_description]);
                $listing_id = $pdo->lastInsertId();
            }

            // Add images
            if (!empty($uploaded_images)) {
                $primary_set = false;
                foreach ($uploaded_images as $image) {
                    $is_primary = $primary_set ? 0 : 1;
                    $primary_set = true;

                    $stmt = $pdo->prepare("
                        INSERT INTO listing_images (listing_id, image_path, is_primary) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$listing_id, $image, $is_primary]);
                }
            }

            $pdo->commit();

            $_SESSION['success'] = $listing_id ? "Listing updated successfully!" : "Listing created successfully!";
            header('Location: view-listing.php?id=' . $listing_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!-- Form -->
<div class="form-container">
    <div class="form-content">
        <h1><?php echo $listing_id ? "Update Listing" : "Create New Listing"; ?></h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Book Info -->
            <div class="form-section">
                <h2>Book Information</h2>

                <div class="form-group">
                    <label for="title">Book Title *</label>
                    <input type="text" name="title" id="title" required class="form-control"
                        value="<?php echo htmlspecialchars($_POST['title'] ?? ($listing['title'] ?? '')); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="author">Author *</label>
                        <input type="text" name="author" id="author" required class="form-control"
                               value="<?php echo htmlspecialchars($_POST['author'] ?? ($listing['author'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" name="isbn" id="isbn" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['isbn'] ?? ($listing['isbn'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select name="category_id" id="category_id" required class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): 
                                $selected = $_POST['category_id'] ?? ($listing['category_id'] ?? '');
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $selected == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_code">Course Code</label>
                        <input type="text" name="course_code" id="course_code" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['course_code'] ?? ($listing['course_code'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Book Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?php 
                        echo htmlspecialchars($_POST['description'] ?? ($listing['book_description'] ?? '')); 
                    ?></textarea>
                </div>
            </div>

            <!-- Listing Details -->
            <div class="form-section">
                <h2>Listing Details</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="condition">Condition *</label>
                        <select name="condition" id="condition" required class="form-select">
                            <?php
                            $conditions = ['new'=>'New','like_new'=>'Like New','good'=>'Good','fair'=>'Fair','poor'=>'Poor'];
                            $selectedCond = $_POST['condition'] ?? ($listing['book_condition'] ?? '');
                            foreach ($conditions as $val=>$label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $selectedCond === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price ($) *</label>
                        <input type="number" name="price" id="price" class="form-control" min="0" step="0.01" required
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ($listing['price'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="listing_description">Listing Description</label>
                    <textarea name="listing_description" id="listing_description" class="form-control" rows="4"><?php 
                        echo htmlspecialchars($_POST['listing_description'] ?? ($listing['listing_description'] ?? '')); 
                    ?></textarea>
                </div>
            </div>

            <!-- Images -->
            <div class="form-section">
                <h2>Images</h2>

                <div class="form-group">
                    <label for="images">Upload Images</label>
                    <input type="file" name="images[]" id="images" multiple accept="image/*" class="form-control">
                    <small>Upload multiple images. First image will be primary.</small>
                </div>

                <div id="image-preview" class="main-image">
                    <?php if (!empty($listing_images)): ?>
                        <?php foreach ($listing_images as $img): ?>
                            <div class="preview-image">
                                <img src="../assets/images/uploads/books/<?php echo $img['image_path']; ?>" alt="Preview">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-large">
                <?php echo $listing_id ? "Update Listing" : "Create Listing"; ?>
            </button>
        </form>
    </div>
</div>

<script>
// Image preview for new uploads
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('images');
    const imagePreview = document.getElementById('image-preview');

    imageInput.addEventListener('change', function() {
        imagePreview.innerHTML = '';
        if (this.files) {
            Array.from(this.files).forEach(file => {
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-image';
                        div.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" class="remove-image">&times;</button>`;
                        div.querySelector('.remove-image').addEventListener('click', function() { div.remove(); });
                        imagePreview.appendChild(div);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
