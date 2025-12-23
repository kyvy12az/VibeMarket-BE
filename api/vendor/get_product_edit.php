<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$product_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
    exit;
}

// Lấy seller_id từ user_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Helper function để xử lý product image URLs
function getProductImageUrls($imageField) {
    // Build base URL dynamically (include project base when API under /<project>/api/...)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (false !== ($pos = strpos($script, '/api/'))) {
        $projectBase = substr($script, 0, $pos);
    } else {
        $projectBase = dirname(dirname(dirname($script)));
    }
    $projectBase = rtrim($projectBase, '/');
    $baseUrl = $protocol . '://' . $host . ($projectBase ? $projectBase : '');

    $field = trim((string)($imageField ?? ''));
    if ($field === '') return [];

    $images = [];

    // Try decode JSON first
    $decoded = json_decode($field, true);
    if (is_array($decoded)) {
        $images = $decoded;
    } else {
        // Not JSON: if contains commas, treat as CSV list
        if (strpos($field, ',') !== false) {
            $parts = array_map('trim', explode(',', $field));
            $images = array_filter($parts, fn($v) => $v !== '');
        } else {
            // Single path string
            $images = [$field];
        }
    }

    // Normalize each image to absolute URL
    $normalized = [];
    foreach ($images as $img) {
        $img = trim((string)$img, " \t\n\r\"'");
        if ($img === '') continue;
        // If already absolute
        if (stripos($img, 'http://') === 0 || stripos($img, 'https://') === 0) {
            $normalized[] = $img;
            continue;
        }

        // If starts with uploads/ or contains /uploads/, keep path under project base
        if (strpos($img, 'uploads/') === 0 || strpos($img, '/uploads/') !== false) {
            $path = ltrim($img, '/');
            $normalized[] = rtrim($baseUrl, '/') . '/' . $path;
            continue;
        }

        // Otherwise assume it's a filename stored under uploads/products/
        $normalized[] = rtrim($baseUrl, '/') . '/uploads/products/' . ltrim($img, '/');
    }

    return $normalized;
}

// Lấy thông tin sản phẩm
$sql = "SELECT 
    p.*,
    p.quantity as initial_stock,
    (p.quantity - p.sold) as current_stock
FROM products p
WHERE p.id = ? AND p.seller_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $imageUrls = getProductImageUrls($row['image']);
    
    // Parse các trường JSON/text
    $sizes = !empty($row['sizes']) ? explode(',', $row['sizes']) : [];
    $colors = !empty($row['colors']) ? explode(',', $row['colors']) : [];
    $tags = !empty($row['tags']) ? explode(',', $row['tags']) : [];
    
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'category' => $row['category'] ?? '',
            'brand' => $row['brand'] ?? '',
            'price' => (int)$row['price'],
            'original_price' => isset($row['original_price']) ? (int)$row['original_price'] : (int)$row['price'],
            'discount' => (int)$row['discount'],
            'quantity' => (int)$row['initial_stock'],
            'current_stock' => max(0, (int)$row['current_stock']),
            'sold' => (int)$row['sold'],
            'status' => $row['status'] ?? 'active',
            'flash_sale' => (bool)$row['flash_sale'],
            'sale_price' => isset($row['sale_price']) ? (int)$row['sale_price'] : null,
            'sale_quantity' => isset($row['sale_quantity']) ? (int)$row['sale_quantity'] : 0,
            'is_live' => (bool)($row['is_live'] ?? 0),
            'release_date' => $row['release_date'] ?? null,
            'shipping_fee' => isset($row['shipping_fee']) ? (int)$row['shipping_fee'] : 0,
            'images' => $imageUrls,
            'sizes' => $sizes,
            'colors' => $colors,
            'tags' => $tags,
            'created_at' => $row['created_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Không tìm thấy sản phẩm hoặc không có quyền chỉnh sửa'
    ]);
}

$stmt->close();
$conn->close();