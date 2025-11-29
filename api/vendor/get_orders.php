<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu user_id']);
    exit;
}

// Lấy seller_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Query đơn hàng của seller
$sql = "
    SELECT DISTINCT
        o.id,
        o.code,
        o.customer_name,
        o.phone,
        o.email,
        o.address,
        o.note,
        o.total,
        o.shipping_fee,
        o.status,
        o.payment_method,
        o.payment_status,
        o.created_at,
        o.shipping_tracking_code,
        (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.seller_id = ?) as total_quantity,
        (SELECT SUM(oi2.quantity * oi2.price) FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.seller_id = ?) as seller_total
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = ?
";

// Filter theo status nếu không phải 'all'
if ($status !== 'all') {
    $sql .= " AND o.status = ?";
}

$sql .= " ORDER BY o.created_at DESC";

if ($status !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $seller_id, $seller_id, $seller_id, $status);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $seller_id, $seller_id, $seller_id);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    // Lấy chi tiết items của seller trong đơn hàng này
    $items_stmt = $conn->prepare("
        SELECT 
            oi.id,
            oi.product_id,
            oi.quantity,
            oi.price,
            oi.size,
            oi.color,
            p.name as product_name,
            p.image as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ? AND oi.seller_id = ?
    ");
    $items_stmt->bind_param("ii", $row['id'], $seller_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        // Parse product image
        $images = json_decode($item['product_image'], true);
        $firstImage = is_array($images) && !empty($images) ? $images[0] : null;
        
        $items[] = [
            'id' => (int)$item['id'],
            'product_id' => (int)$item['product_id'],
            'product_name' => $item['product_name'],
            'product_image' => $firstImage,
            'quantity' => (int)$item['quantity'],
            'price' => (int)$item['price'],
            'size' => $item['size'],
            'color' => $item['color'],
            'subtotal' => (int)$item['quantity'] * (int)$item['price']
        ];
    }
    $items_stmt->close();
    
    $orders[] = [
        'id' => (int)$row['id'],
        'code' => $row['code'],
        'customer_name' => $row['customer_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'address' => $row['address'],
        'note' => $row['note'],
        'total' => (int)$row['total'],
        'shipping_fee' => (int)$row['shipping_fee'],
        'status' => $row['status'],
        'payment_method' => $row['payment_method'],
        'payment_status' => $row['payment_status'],
        'created_at' => $row['created_at'],
        'shipping_tracking_code' => $row['shipping_tracking_code'],
        'total_quantity' => (int)$row['total_quantity'],
        'seller_total' => (int)$row['seller_total'],
        'items' => $items
    ];
}

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'total' => count($orders)
]);

$stmt->close();
$conn->close();