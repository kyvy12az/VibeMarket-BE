<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// Helper functions
function getStoreAvatarUrl($avatar) {
    if (!$avatar) return null;
    if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
        return $avatar;
    }
    $backend_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    return $backend_url . '/uploads/store_avatars/' . $avatar;
}

function getProductImageUrls($imageJson) {
    $images = json_decode($imageJson, true);
    if (!is_array($images)) return [];
    
    $backend_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    
    return array_map(function($img) use ($backend_url) {
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) return $img;
        if (strpos($img, 'uploads/') === 0) return $backend_url . '/' . $img;
        return $backend_url . '/uploads/products/' . $img;
    }, $images);
}

$category = isset($_GET['category']) ? $_GET['category'] : '';

$sql = "SELECT 
    p.*, 
    s.store_name AS seller_name, 
    s.avatar AS seller_avatar
FROM products p
LEFT JOIN seller s ON p.seller_id = s.seller_id
WHERE (p.release_date IS NULL OR p.release_date <= NOW())
  AND p.status = 'active'
  AND p.quantity > 0";

if ($category) {
    $sql .= " AND p.category = ?";
}

$sql .= " ORDER BY p.id DESC";

if ($category) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$products = [];
while ($row = $result->fetch_assoc()) {
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
    'products' => $products,
    'category' => $category
]);

if (isset($stmt)) $stmt->close();
$conn->close();