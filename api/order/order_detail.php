<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$order_code = $_GET['code'] ?? '';
if (!$order_code) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn hàng']);
    exit;
}

$sql = "SELECT o.*, sm.shipping_method, sm.shipping_carrier, sm.shipping_estimated_days,
        c.code as coupon_code
        FROM orders o
        LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
        LEFT JOIN coupons c ON o.coupon_id = c.id
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

// Thông tin mã giảm giá - Dual voucher support
$order['discount_amount'] = isset($order['discount_amount']) ? (float)$order['discount_amount'] : 0;

// Get discount coupon code and determine if it's from lucky wheel or seller
$order['discount_coupon_code'] = null;
$order['is_discount_lucky_wheel'] = false;

// Priority 1: Check discount_coupon_id (new dual voucher system)
if (isset($order['discount_coupon_id']) && $order['discount_coupon_id'] > 0) {
    $discount_id = (int)$order['discount_coupon_id'];
    
    // Try user_vouchers first (lucky wheel vouchers have LW- prefix)
    $voucherCheck = $conn->query("SELECT voucher_code FROM user_vouchers WHERE id = {$discount_id} LIMIT 1");
    if ($voucherCheck && $voucherCheck->num_rows > 0) {
        $voucher = $voucherCheck->fetch_assoc();
        $order['discount_coupon_code'] = $voucher['voucher_code'];
        $order['is_discount_lucky_wheel'] = true;
    } else {
        // Try coupons table (seller coupon)
        $couponCheck = $conn->query("SELECT code FROM coupons WHERE id = {$discount_id} LIMIT 1");
        if ($couponCheck && $couponCheck->num_rows > 0) {
            $coupon = $couponCheck->fetch_assoc();
            $order['discount_coupon_code'] = $coupon['code'];
            $order['is_discount_lucky_wheel'] = false;
        }
    }
    $order['discount_coupon_id'] = $discount_id;
} 
// Priority 2: Fallback to legacy coupon_id (old system)
else if (isset($order['coupon_id']) && $order['coupon_id'] > 0) {
    $coupon_id = (int)$order['coupon_id'];
    $couponCheck = $conn->query("SELECT code FROM coupons WHERE id = {$coupon_id} LIMIT 1");
    if ($couponCheck && $couponCheck->num_rows > 0) {
        $coupon = $couponCheck->fetch_assoc();
        $order['discount_coupon_code'] = $coupon['code'];
        $order['is_discount_lucky_wheel'] = false;
    }
    $order['discount_coupon_id'] = null;
} else {
    $order['discount_coupon_id'] = null;
}

// Keep legacy coupon_code for backward compatibility
$order['coupon_code'] = $order['discount_coupon_code'];

// Get freeship coupon code and determine if it's from lucky wheel or seller
$order['freeship_coupon_code'] = null;
$order['freeship_discount'] = isset($order['freeship_discount']) ? (float)$order['freeship_discount'] : 0;
$order['is_freeship_lucky_wheel'] = false;

if (isset($order['freeship_coupon_id']) && $order['freeship_coupon_id'] > 0) {
    $freeship_id = (int)$order['freeship_coupon_id'];
    
    // Try user_vouchers first (lucky wheel)
    $voucherCheck = $conn->query("SELECT voucher_code FROM user_vouchers WHERE id = {$freeship_id} LIMIT 1");
    if ($voucherCheck && $voucherCheck->num_rows > 0) {
        $voucher = $voucherCheck->fetch_assoc();
        $order['freeship_coupon_code'] = $voucher['voucher_code'];
        $order['is_freeship_lucky_wheel'] = true;
    } else {
        // Try coupons table (seller coupon)
        $couponCheck = $conn->query("SELECT code FROM coupons WHERE id = {$freeship_id} LIMIT 1");
        if ($couponCheck && $couponCheck->num_rows > 0) {
            $coupon = $couponCheck->fetch_assoc();
            $order['freeship_coupon_code'] = $coupon['code'];
            $order['is_freeship_lucky_wheel'] = false;
        }
    }
    $order['freeship_coupon_id'] = $freeship_id;
} else {
    $order['freeship_coupon_id'] = null;
}

// Nếu FE vẫn cần trường cũ:
$order['payment_method'] = $order['payment_method'] ?? null;
$order['created_at'] = $order['created_at'] ?? null;

echo json_encode(['success' => true, 'order' => $order]);
$conn->close();