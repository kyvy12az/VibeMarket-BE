<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu user_id']);
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

// Lấy danh sách sản phẩm của seller với tính toán tồn kho thực tế
$sql = "SELECT 
    p.id,
    p.name,
    p.category,
    p.price,
    p.quantity as initial_stock,
    p.sold as sales,
    (p.quantity - p.sold) as stock,
    p.rating,
    p.image,
    p.status,
    p.created_at
FROM products p
WHERE p.seller_id = ?
ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // Parse images từ JSON
    $imageUrls = getProductImageUrls($row['image']);
    $firstImage = !empty($imageUrls) ? $imageUrls[0] : null;
    
    // Tính tồn kho thực tế (không cho âm)
    $actual_stock = max(0, (int)$row['stock']);
    
    // Xác định trạng thái
    $status = 'active';
    if ($actual_stock == 0) {
        $status = 'out_of_stock';
    } elseif ($row['status'] != 'active') {
        $status = 'inactive';
    }
    
    $products[] = [
        'id' => 'SP' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'name' => $row['name'],
        'category' => $row['category'] ?? 'Chưa phân loại',
        'price' => (int)$row['price'],
        'initial_stock' => (int)$row['initial_stock'], // Tồn kho ban đầu
        'stock' => $actual_stock, // Tồn kho hiện tại (initial - sold)
        'sales' => (int)$row['sales'], // Số lượng đã bán
        'rating' => (float)$row['rating'],
        'image' => $firstImage,
        'status' => $status,
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'products' => $products,
    'total' => count($products)
]);

$stmt->close();
$conn->close();