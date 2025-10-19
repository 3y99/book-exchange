<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Get search parameters
$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
$condition = isset($_GET['condition']) ? $_GET['condition'] : '';
$course = isset($_GET['course']) ? sanitize($_GET['course']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;
$offset = ($page - 1) * $limit;

// Build the SQL query
$sql = "
    SELECT l.*, b.title, b.author, b.isbn, b.course_code, 
           u.username, u.university,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.seller_id = u.id
    WHERE l.status = 'available'
";

$params = [];

// Add search conditions
if (!empty($query)) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.course_code LIKE ?)";
    $search_term = "%$query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category) && $category != 'all') {
    $sql .= " AND b.category_id = ?";
    $params[] = $category;
}

if (!empty($condition) && $condition != 'all') {
    $sql .= " AND l.condition = ?";
    $params[] = $condition;
}

if (!empty($course)) {
    $sql .= " AND b.course_code LIKE ?";
    $params[] = "%$course%";
}

$sql .= " AND l.price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as results";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $limit);

// Add sorting and pagination
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY l.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY l.price DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY l.created_at ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY l.created_at DESC";
        break;
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare response
$response = [
    'results' => $results,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_results' => $total_results,
        'limit' => $limit
    ],
    'filters' => [
        'categories' => $categories,
        'conditions' => [
            ['value' => 'new', 'label' => 'New'],
            ['value' => 'like_new', 'label' => 'Like New'],
            ['value' => 'good', 'label' => 'Good'],
            ['value' => 'fair', 'label' => 'Fair'],
            ['value' => 'poor', 'label' => 'Poor']
        ]
    ]
];

echo json_encode($response);
?>