<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

int_headers();

$sql = "SELECT 
    p.*, 
    s.store_name AS seller_name, 
    s.avatar AS seller_avatar
FROM products p
LEFT JOIN seller s ON p.seller_id = s.seller_id
WHERE p.release_date IS NULL OR p.release_date <= NOW()
ORDER BY p.id DESC";

$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $images = json_decode($row['image'], true) ?: [];
    $products[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'price' => (int) $row['price'],
        'originalPrice' => isset($row['original_price']) ? (int) $row['original_price'] : null,
        'image' => isset($images[0]) ? $images[0] : null,
        'rating' => (float) $row['rating'],
        'sold' => (int) $row['sold'],
        'discount' => (int) $row['discount'],
        'isLive' => (bool) $row['is_live'],
        'seller_id' => (int) $row['seller_id'],
        'seller' => [
            'name' => $row['seller_name'],
            'avatar' => $row['seller_avatar']
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