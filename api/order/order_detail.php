<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$order_code = $_GET['code'] ?? '';
if (!$order_code) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn hàng']);
    exit;
}

$sql = "SELECT o.*, sm.shipping_method, sm.shipping_carrier, sm.shipping_estimated_days
        FROM orders o
        LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
        WHERE o.code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $order_code);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
    exit;
}

// Lấy sản phẩm trong đơn
$items = [];
$itemRes = $conn->query("SELECT p.name, oi.price, oi.quantity, p.image, p.sku, oi.seller_id,
    s.user_id as seller_user_id, s.store_name, s.business_address, s.phone as store_phone, s.email as store_email
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    LEFT JOIN seller s ON oi.seller_id = s.seller_id
    WHERE oi.order_id = {$order['id']}");
while ($item = $itemRes->fetch_assoc()) {
    // Xử lý lấy ảnh đầu tiên nếu image là JSON array
    $img = $item['image'];
    if ($img && ($img[0] === '[' || $img[0] === '{')) {
        $arr = json_decode($img, true);
        if (is_array($arr) && count($arr) > 0) {
            $img = $arr[0];
        }
    }
    $item['image'] = (strpos($img, 'http') === 0) ? $img : 'http://localhost/' . ltrim($img, '/');
    
    // Convert seller_id and seller_user_id to integers
    $item['seller_id'] = $item['seller_id'] ? (int)$item['seller_id'] : null;
    $item['seller_user_id'] = $item['seller_user_id'] ? (int)$item['seller_user_id'] : null;
    
    $items[] = $item;
}

$order['items'] = $items;

// Lấy thông tin khách hàng
$customer = null;
if (isset($order['customer_id'])) {
    $cusRes = $conn->query("SELECT name, phone, email, address FROM users WHERE id = {$order['customer_id']}");
    $customer = $cusRes && $cusRes->num_rows > 0 ? $cusRes->fetch_assoc() : null;
}
$order['customer'] = [
    'name' => $order['customer_name'] ?? null,
    'phone' => $order['phone'] ?? null,
    'email' => $order['email'] ?? null,
    'address' => $order['address'] ?? null
];

$order['phone'] = $order['phone'] ?? null;
$order['customer_name'] = $order['customer_name'] ?? null;
$order['address'] = $order['address'] ?? null;

// Thông tin vận chuyển (giả sử các trường này nằm trong bảng orders)
$order['shipping'] = [
    'method' => $order['shipping_method'] ?? null,
    'carrier' => $order['shipping_carrier'] ?? null,
    'trackingCode' => $order['shipping_tracking_code'] ?? null, // LẤY TỪ BẢNG ORDERS
    'estimatedDays' => $order['shipping_estimated_days'] ?? null
];

$order['payment'] = [
    'method' => $order['payment_method'] ?? null,
    'status' => $order['payment_status'] ?? null,
    'transaction_id' => $order['payment_transaction_id'] ?? null,
    'paid_at' => $order['payment_paid_at'] ?? null
];

$order['shipping_fee'] = $order['shipping_fee'] ?? 0;

// Nếu FE vẫn cần trường cũ:
$order['payment_method'] = $order['payment_method'] ?? null;
$order['created_at'] = $order['created_at'] ?? null;

echo json_encode(['success' => true, 'order' => $order]);
$conn->close();