<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
if (!$seller_id) {
    echo json_encode([]);
    exit;
}

// Lấy 5 đơn hàng mới nhất có sản phẩm thuộc seller này
$res = $conn->query("
    SELECT DISTINCT o.id, o.code, o.customer_name as customer, o.total as amount, o.status, o.created_at as date
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
    ORDER BY o.created_at DESC
    LIMIT 5
");

$orders = [];
while ($row = $res && $res->num_rows > 0 ? $res->fetch_assoc() : false) {
    $order_id = $row['id'];
    // Lấy tên và giá sản phẩm đầu tiên của seller này trong đơn hàng
    $productRes = $conn->query("
        SELECT p.name, oi.price 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = $order_id AND oi.seller_id = $seller_id 
        LIMIT 1
    ");
    $productRow = $productRes && $productRes->num_rows > 0 ? $productRes->fetch_assoc() : null;
    $productName = $productRow ? $productRow['name'] : '';
    $productPrice = $productRow ? (int) $productRow['price'] : 0;

    $orders[] = [
        'id' => $row['id'],
        'code' => $row['code'],
        'customer' => $row['customer'],
        'product' => $productName,
        'amount' => $productPrice,
        'status' => $row['status'],
        'date' => $row['date']
    ];
}
echo json_encode($orders);
$conn->close();