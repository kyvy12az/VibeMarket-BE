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

// Doanh thu 6 tháng gần nhất
$revenueData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = 'T' . date('n', strtotime("-$i months"));
    
    $res = $conn->query("
        SELECT 
            COALESCE(SUM(oi.quantity * oi.price), 0) / 1000000 as revenue,
            COUNT(DISTINCT o.id) as orders
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE oi.seller_id = $seller_id
          AND o.status = 'delivered'
          AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month'
    ");
    
    $data = $res->fetch_assoc();
    $revenueData[] = [
        'month' => $monthLabel,
        'revenue' => round($data['revenue'], 1),
        'orders' => (int)$data['orders']
    ];
}

echo json_encode($revenueData);
$conn->close();