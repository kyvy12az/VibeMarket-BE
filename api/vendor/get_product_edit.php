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
function getProductImageUrls($imageJson) {
    $images = json_decode($imageJson, true);
    if (!is_array($images)) {
        return [];
    }
    
    $backend_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    
    return array_map(function($img) use ($backend_url) {
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
            return $img;
        }
        if (strpos($img, 'uploads/') === 0) {
            return $backend_url . '/' . $img;
        }
        return $backend_url . '/uploads/products/' . $img;
    }, $images);
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