<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

int_headers();

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

$sql = "SELECT 
    p.*, 
    s.store_name AS seller_name, 
    s.avatar AS seller_avatar
FROM products p
LEFT JOIN seller s ON p.seller_id = s.seller_id
WHERE (p.release_date IS NULL OR p.release_date <= NOW())
  AND p.status = 'active'
  AND (p.quantity - p.sold) > 0
ORDER BY p.id DESC";

$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    // Parse images JSON
    $imageUrls = getProductImageUrls($row['image']);
    
    $products[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'price' => (int) $row['price'],
        'originalPrice' => isset($row['original_price']) ? (int) $row['original_price'] : null,
        'image' => !empty($imageUrls) ? $imageUrls[0] : null,
        'images' => $imageUrls,
        'rating' => (float) $row['rating'],
        'sold' => (int) $row['sold'],
        'discount' => (int) $row['discount'],
        'isLive' => (bool) $row['is_live'],
        'seller_id' => (int) $row['seller_id'],
        'seller' => [
            'name' => $row['seller_name'],
            'avatar' => getStoreAvatarUrl($row['seller_avatar'])
        ],
        'createdAt' => $row['created_at'],
        'category' => $row['category'],
    ];
}

echo json_encode([
    'success' => true,
    'products' => $products
]);

$conn->close();