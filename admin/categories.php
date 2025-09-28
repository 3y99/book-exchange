<?php
$pageTitle = "Admin - Manage Categories";
require_once '../includes/config.php';
require_once 'header.php';

// Check if user is admin
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new category
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $parent_id]);
            $_SESSION['success'] = "Category added successfully.";
        } else {
            $_SESSION['error'] = "Category name is required.";
        }
        header("Location: categories.php");
        exit();
    }

    // Update category
    if (isset($_POST['update_category'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

        if ($name) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?");
            $stmt->execute([$name, $description, $parent_id, $id]);
            $_SESSION['success'] = "Category updated successfully.";
        } else {
            $_SESSION['error'] = "Category name cannot be empty.";
        }
        header("Location: categories.php");
        exit();
    }
}

// Delete category
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Category deleted successfully.";
    header("Location: categories.php");
    exit();
}

// Fetch categories
$stmt = $pdo->query("SELECT c.*, p.name AS parent_name 
                     FROM categories c 
                     LEFT JOIN categories p ON c.parent_id = p.id 
                     ORDER BY c.name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-content">
        <h1>Manage Categories</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Add New Category -->
        <div class="admin-section">
            <h2>Add New Category</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parent Category</label>
                        <select name="parent_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </form>
        </div>

        <!-- All Categories -->
        <div class="admin-section">
            <h2>All Categories</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td><?php echo htmlspecialchars($cat['description']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Edit Category Modal Trigger -->
                                    <button class="btn btn-small btn-warning" onclick="openEditModal(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['description'], ENT_QUOTES); ?>', '<?php echo $cat['parent_id']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?action=delete&id=<?php echo $cat['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="form-content" style="display:none;">
    <div class="modal-content">
        <h2>Edit Category</h2>
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Parent Category</label>
                <select name="parent_id" id="edit_parent_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, description, parentId) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_parent_id').value = parentId || '';
    document.getElementById('editCategoryModal').style.display = 'block';
}
function closeEditModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}
</script>

<?php
require_once 'footer.php';
?>
