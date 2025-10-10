<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
if (!$seller_id) {
    echo json_encode([]);
    exit;
}

$res = $conn->query("SELECT name, image, sold as sales, price * sold as revenue, rating FROM products WHERE seller_id = $seller_id ORDER BY sales DESC LIMIT 3");
$products = [];
while ($row = $res->fetch_assoc()) {
    $images = json_decode($row['image'], true) ?: [];
    $products[] = [
        'name' => $row['name'],
        'image' => isset($images[0]) ? $images[0] : null, 
        'sales' => (int)$row['sales'],
        'revenue' => (int)$row['revenue'],
        'rating' => (float)$row['rating']
    ];
}
echo json_encode($products);
$conn->close();