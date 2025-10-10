<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['error' => 'Thiếu user_id']);
    exit;
}

// Lấy seller_id, store_name, avatar từ user_id
$stmt = $conn->prepare("SELECT seller_id, store_name, avatar FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id, $store_name, $avatar);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Thống kê tổng sản phẩm
$res = $conn->query("SELECT COUNT(*) as total FROM products WHERE seller_id = $seller_id");
$totalProducts = $res && $res->num_rows > 0 ? $res->fetch_assoc()['total'] ?? 0 : 0;

// Thống kê tổng đơn hàng
$res = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND MONTH(o.created_at) = MONTH(CURRENT_DATE())
      AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
");
$totalOrders = $res && $res->num_rows > 0 ? (int)$res->fetch_assoc()['total'] : 0;

// Thống kê tổng doanh thu (tính theo order_items đã giao của seller này)
$res = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
");
$totalRevenue = $res && $res->num_rows > 0 ? $res->fetch_assoc()['total'] ?? 0 : 0;

// Thống kê đánh giá trung bình
$res = $conn->query("SELECT AVG(rating) as avg FROM product_reviews WHERE seller_id = $seller_id");
$avgRating = $res && $res->num_rows > 0 ? round($res->fetch_assoc()['avg'] ?? 0, 1) : 0;

// Thống kê tổng khách hàng
$res = $conn->query("
    SELECT COUNT(DISTINCT o.customer_id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
");

echo json_encode([
    'seller_id' => $seller_id,
    'store_name' => $store_name,
    'avatar' => $avatar, 
    'totalProducts' => (int)$totalProducts,
    'totalOrders' => (int)$totalOrders,
    'totalRevenue' => (int)$totalRevenue,
    'avgRating' => (float)$avgRating,
    'totalCustomers' => (int)$totalCustomers
]);
$conn->close();