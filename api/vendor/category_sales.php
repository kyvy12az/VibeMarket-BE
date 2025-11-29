<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['error' => 'Thiếu user_id']);
    exit;
}

// Lấy seller_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Doanh số theo danh mục
$res = $conn->query("
    SELECT 
        p.category as name,
        COUNT(oi.id) as sales,
        SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
    GROUP BY p.category
    ORDER BY revenue DESC
");

$total = 0;
$categories = [];
$colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];

while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
    $total += $row['revenue'];
}

// Tính phần trăm
$categoryData = [];
$colorIndex = 0;
foreach ($categories as $cat) {
    $value = $total > 0 ? round(($cat['revenue'] / $total) * 100, 1) : 0;
    $categoryData[] = [
        'name' => $cat['name'],
        'value' => $value,
        'color' => $colors[$colorIndex % count($colors)]
    ];
    $colorIndex++;
}

echo json_encode($categoryData);
$conn->close();