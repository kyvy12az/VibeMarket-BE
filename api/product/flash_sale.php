<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// Lấy các sản phẩm có flash_sale = 1 và đã đến ngày phát hành (nếu có)
$sql = "SELECT 
    p.*, 
    s.store_name AS seller_name, 
    s.avatar AS seller_avatar
FROM products p
LEFT JOIN seller s ON p.seller_id = s.seller_id
WHERE p.flash_sale = 1
  AND (p.release_date IS NULL OR p.release_date <= NOW())
ORDER BY p.id DESC";

$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $images = json_decode($row['image'], true) ?: [];
    $products[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (int)$row['sale_price'], 
        'originalPrice' => (int)$row['price'], 
        'discount' => (int)$row['discount'],
        'image' => $images[0] ?? null,
        'images' => $images,
        'sold' => (int)$row['sold'],
        'quantity' => (int)$row['sale_quantity'],
        'rating' => (float)$row['rating'],
        'reviews' => (int)($row['total_reviews'] ?? 0),
        'seller' => [
            'name' => $row['seller_name'],
            'avatar' => $row['seller_avatar']
        ]
    ];
}
echo json_encode(['success' => true, 'products' => $products]);
$conn->close();