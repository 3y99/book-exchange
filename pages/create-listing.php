<?php
$pageTitle = isset($_GET['edit']) ? "Update Listing" : "Create Listing";  //Set title
require_once '../includes/config.php';     //Import the configuration file config.php
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {          //If the user is not logged in
    header('Location: login.php');           //redirect back to the login page
    exit();
}

$user_id = $_SESSION['user_id'];             //Obtain the user ID 

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);   

// Editing? Load existing listing
$listing_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$listing = null;

//Users can only edit their own listing
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
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {             //When the user clicks the button to submit
    $title = sanitize($_POST['title']);                  //Read the input
    $author = sanitize($_POST['author']);
    $isbn = sanitize($_POST['isbn']);
    $description = sanitize($_POST['description']);
    $category_id = $_POST['category_id'];
    $course_code = sanitize($_POST['course_code']);
    $condition = $_POST['condition'];
    $price = floatval($_POST['price']);
    $listing_description = sanitize($_POST['listing_description']);
    
    // Validate input
    $errors = [];              //Save the error message
    
    if (empty($title)) $errors[] = "Book title is required.";
    if (empty($author)) $errors[] = "Author is required.";
    if (empty($category_id)) $errors[] = "Category is required.";
    if (empty($condition)) $errors[] = "Condition is required.";
    if (empty($price) || $price <= 0) $errors[] = "Valid price is required.";
    
    // Handle image uploads
    $uploaded_images = [];  //Save uploaded images
    if (!empty($_FILES['images']['name'][0])) {        //Check whether the user has really selected the file
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {   //Traverse each uploaded file
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {    //Check whether the file has been uploaded successfully
                $file = [                             // Organize file information
                    'name' => $_FILES['images']['name'][$key],
                    'tmp_name' => $tmp_name,
                    'size' => $_FILES['images']['size'][$key]
                ];
                
                $result = uploadImage($file, '../' . BOOK_IMAGE_PATH);  //Call the function "uploadImage()" to save the file
                
                if (is_array($result) && isset($result['errors'])) {    //error
                    $errors = array_merge($errors, $result['errors']);
                } else {                                                //sccess
                    $uploaded_images[] = $result;
                }
            }
        }
    }
    
    if (empty($errors)) {         
        try {
            $pdo->beginTransaction();          //Use database transactions

            //  Check for duplicate ISBN (unique constraint)
            $params = [$isbn];

            //Construct ISBN check SQL
            $isbnCheckQuery = "SELECT id FROM books WHERE isbn = ?"; 
            if ($listing_id && $listing) {
                $isbnCheckQuery .= " AND id != ?";
                $params[] = $listing['book_id'];
            }

            //Execute SQL queries
            $stmt = $pdo->prepare($isbnCheckQuery);
            $stmt->execute($params);
            $existingIsbn = $stmt->fetch(PDO::FETCH_ASSOC);

            //Execute SQL queries
            if ($existingIsbn) {
                throw new Exception("A book with this ISBN already exists. ISBNs must be unique.");
            }
            
            // Check if book already exists
            $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($book) {
                $book_id = $book['id'];
                // Update book info
                $stmt = $pdo->prepare("
                    UPDATE books 
                    SET title = ?, author = ?, description = ?, category_id = ?, course_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $author, $description, $category_id, $course_code, $book_id]);
            } else {
                // Create new book
                $stmt = $pdo->prepare("
                    INSERT INTO books (title, author, isbn, description, category_id, course_code) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $author, $isbn, $description, $category_id, $course_code]);
                $book_id = $pdo->lastInsertId();
            }
            
            if ($listing_id) {
                // Update listing
                $stmt = $pdo->prepare("
                    UPDATE listings 
                    SET book_id = ?, book_condition = ?, price = ?, description = ?
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$book_id, $condition, $price, $listing_description, $listing_id, $user_id]);
            } else {
                // Create new listing
                $stmt = $pdo->prepare("
                    INSERT INTO listings (seller_id, book_id, book_condition, price, description) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $book_id, $condition, $price, $listing_description]);
                $listing_id = $pdo->lastInsertId();
            }
            
            // Add new images
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
            <div class="form-section">
                <h2>Book Information</h2>
                
                <div class="form-group">
                    <label for="title" class="form-label">Book Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ($listing['title'] ?? '')); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="author" class="form-label">Author *</label>
                        <input type="text" id="author" name="author" class="form-control" required 
                               value="<?php echo htmlspecialchars($_POST['author'] ?? ($listing['author'] ?? '')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn" class="form-label">ISBN</label>
                        <input type="text" id="isbn" name="isbn" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['isbn'] ?? ($listing['isbn'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category *</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php 
                                        $selected = $_POST['category_id'] ?? ($listing['category_id'] ?? '');
                                        echo $selected == $category['id'] ? 'selected' : ''; 
                                    ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_code" class="form-label">Course Code</label>
                        <input type="text" id="course_code" name="course_code" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['course_code'] ?? ($listing['course_code'] ?? '')); ?>" 
                               placeholder="e.g., COMP101">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Book Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php 
                        echo htmlspecialchars($_POST['description'] ?? ($listing['book_description'] ?? '')); 
                    ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Listing Details</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="condition" class="form-label">Condition *</label>
                        <select id="condition" name="condition" class="form-select" required>
                            <?php 
                            $selectedCondition = $_POST['condition'] ?? ($listing['book_condition'] ?? '');
                            $conditions = ['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];
                            foreach ($conditions as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $selectedCondition === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="form-label">Price ($) *</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required 
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ($listing['price'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="listing_description" class="form-label">Listing Description</label>
                    <textarea id="listing_description" name="listing_description" class="form-control" rows="4" 
                              placeholder="Describe any notes about this specific copy of the book"><?php 
                        echo htmlspecialchars($_POST['listing_description'] ?? ($listing['listing_description'] ?? '')); 
                    ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Images</h2>
                
                <div class="form-group">
                    <label for="images" class="form-label">Upload Images</label>
                    <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*">
                    <small>You can upload multiple images. The first image will be used as the primary image.</small>
                </div>
                
                <div id="image-preview" class="main-image"></div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-large">
                <?php echo $listing_id ? "Update Listing" : "Create Listing"; ?>
            </button>
        </form>
    </div>
</div>

<script>
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
                        const img = document.createElement('div');
                        img.className = 'preview-image';
                        img.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" class="remove-image">&times;</button>`;
                        img.querySelector('.remove-image').addEventListener('click', function() {
                            img.remove();
                        });
                        imagePreview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
