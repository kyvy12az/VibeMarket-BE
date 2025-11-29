<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Stats Overview
$stats = [];

// Tổng sản phẩm
$res = $conn->query("SELECT COUNT(*) as total FROM products WHERE seller_id = $seller_id");
$stats['totalProducts'] = $res ? (int)$res->fetch_assoc()['total'] : 0;

// Đơn hàng đã đặt thành công (pending, processing, shipped, delivered)
$res = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status IN ('pending', 'processing', 'shipped', 'delivered')
");
$stats['totalOrders'] = $res ? (int)$res->fetch_assoc()['total'] : 0;

// Đơn hàng đã giao thành công
$res = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
");
$stats['totalDeliveredOrders'] = $res ? (int)$res->fetch_assoc()['total'] : 0;

// Tổng doanh thu (tất cả đơn delivered)
$res = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
");
$stats['totalRevenue'] = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;

// Doanh thu tháng này (dùng created_at thay vì updated_at)
$res = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
      AND MONTH(o.created_at) = MONTH(CURRENT_DATE())
      AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
");
$currentMonthRevenue = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
$stats['currentMonthRevenue'] = $currentMonthRevenue;

// Doanh thu tháng trước
$res = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
      AND MONTH(o.created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
      AND YEAR(o.created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$lastMonthRevenue = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;

// Tính % tăng trưởng
$revenueGrowth = 0;
if ($lastMonthRevenue > 0) {
    $revenueGrowth = round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
} elseif ($currentMonthRevenue > 0) {
    $revenueGrowth = 100; // Tăng 100% nếu tháng trước = 0
}
$stats['revenueGrowth'] = $revenueGrowth;

// Đánh giá trung bình
$res = $conn->query("SELECT AVG(rating) as avg FROM product_reviews WHERE seller_id = $seller_id");
$stats['avgRating'] = $res ? round($res->fetch_assoc()['avg'] ?? 0, 1) : 0;

// Tổng số đánh giá
$res = $conn->query("SELECT COUNT(*) as total FROM product_reviews WHERE seller_id = $seller_id");
$stats['totalReviews'] = $res ? (int)$res->fetch_assoc()['total'] : 0;

// Tổng khách hàng (từ tất cả đơn đã đặt thành công)
$res = $conn->query("
    SELECT COUNT(DISTINCT o.customer_id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status IN ('pending', 'processing', 'shipped', 'delivered')
");
$stats['totalCustomers'] = $res ? (int)$res->fetch_assoc()['total'] : 0;

// Tính tỷ lệ chuyển đổi
// (Số đơn delivered / Tổng số đơn đã đặt) * 100
$conversionRate = 0;
if ($stats['totalOrders'] > 0) {
    $conversionRate = round(($stats['totalDeliveredOrders'] / $stats['totalOrders']) * 100, 1);
}
$stats['conversionRate'] = $conversionRate . '%';

// Tính conversion rate tháng trước (dùng created_at)
$res = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status = 'delivered'
      AND MONTH(o.created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
      AND YEAR(o.created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$lastMonthDelivered = $res ? (int)$res->fetch_assoc()['total'] : 0;

$res = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.seller_id = $seller_id
      AND o.status IN ('pending', 'processing', 'shipped', 'delivered')
      AND MONTH(o.created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
      AND YEAR(o.created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$lastMonthTotal = $res ? (int)$res->fetch_assoc()['total'] : 0;

$lastMonthConversion = 0;
if ($lastMonthTotal > 0) {
    $lastMonthConversion = round(($lastMonthDelivered / $lastMonthTotal) * 100, 1);
}

$conversionGrowth = round($conversionRate - $lastMonthConversion, 1);
$stats['conversionGrowth'] = ($conversionGrowth >= 0 ? '+' : '') . $conversionGrowth;

echo json_encode($stats);
$conn->close();