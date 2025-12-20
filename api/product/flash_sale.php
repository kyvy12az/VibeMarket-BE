<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// Helper function để xử lý avatar URL
function getStoreAvatarUrl($avatar) {
    if (!$avatar) {
        return null;
    }
    
    if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
        return $avatar;
    }
    
    $backend_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    return $backend_url . '/uploads/store_avatars/' . $avatar;
}

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
        // Nếu đã có /uploads/products/ thì concat trực tiếp
        if (strpos($img, '/uploads/products/') === 0) {
            return $backend_url . $img;
        }
        // Còn lại thêm prefix
        return $backend_url . '/uploads/products/' . ltrim($img, '/');
    }, $images);
}

// Lấy các sản phẩm flash sale đang active và còn hàng
$sql = "SELECT 
    p.*, 
    (p.quantity - p.sold) as actual_stock,
    s.store_name AS seller_name, 
    s.avatar AS seller_avatar
FROM products p
LEFT JOIN seller s ON p.seller_id = s.seller_id
WHERE p.flash_sale = 1
  AND (p.release_date IS NULL OR p.release_date <= NOW())
  AND p.status = 'active'
  AND p.sale_quantity > 0
  AND (p.quantity - p.sold) > 0
ORDER BY p.id DESC";

$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $imageUrls = getProductImageUrls($row['image']);
    
    $products[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (int)$row['sale_price'], 
        'originalPrice' => (int)$row['price'], 
        'discount' => (int)$row['discount'],
        'image' => !empty($imageUrls) ? $imageUrls[0] : null,
        'images' => $imageUrls,
        'sold' => (int)$row['sold'],
        'stock' => (int)$row['actual_stock'], // Tồn kho thực tế
        'quantity' => (int)$row['sale_quantity'],
        'rating' => (float)$row['rating'],
        'reviews' => (int)($row['total_reviews'] ?? 0),
        'seller' => [
            'name' => $row['seller_name'],
            'avatar' => getStoreAvatarUrl($row['seller_avatar'])
        ]
    ];
}

echo json_encode(['success' => true, 'products' => $products]);
$conn->close();