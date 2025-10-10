<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu id sản phẩm']);
    exit;
}

$sql = "SELECT p.*, s.store_name AS seller_name, s.avatar AS seller_avatar
        FROM products p
        LEFT JOIN seller s ON p.seller_id = s.seller_id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    // Xử lý mảng ảnh
    $images = json_decode($row['image'], true) ?: [];
    // Xử lý tags, sizes, colors, features, specifications nếu có
    $tags = isset($row['tags']) ? explode(',', $row['tags']) : [];
    $sizes = isset($row['sizes']) ? explode(',', $row['sizes']) : [];
    $colors = isset($row['colors']) ? explode(',', $row['colors']) : [];
    $features = isset($row['features']) ? explode('|', $row['features']) : [];
    $specifications = isset($row['specifications']) ? json_decode($row['specifications'], true) : [];

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (int)$row['price'],
            'originalPrice' => (int)$row['original_price'],
            'discount' => (int)$row['discount'],
            'rating' => (float)$row['rating'],
            'totalReviews' => (int)($row['total_reviews'] ?? 0),
            'sold' => (int)$row['sold'],
            'inStock' => (int)$row['quantity'],
            'brand' => $row['brand'],
            'description' => $row['description'],
            'features' => $features,
            'images' => $images,
            'sizes' => $sizes,
            'colors' => $colors,
            'specifications' => $specifications,
            'seller_id' => (int)$row['seller_id'],
            'seller' => [
                'name' => $row['seller_name'],
                'avatar' => $row['seller_avatar']
            ],
            'shipping_fee' => (int)$row['shipping_fee'],
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm']);
}
$stmt->close();
$conn->close();