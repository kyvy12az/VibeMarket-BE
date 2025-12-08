<?php
require_once '../../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Lấy dữ liệu từ request
$data = json_decode(file_get_contents("php://input"), true);

$coupon_code = isset($data['coupon_code']) ? trim(strtoupper($data['coupon_code'])) : '';
$seller_id = isset($data['seller_id']) ? intval($data['seller_id']) : 0;
$product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
$order_total = isset($data['order_total']) ? floatval($data['order_total']) : 0;

if (!$coupon_code) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá']);
    exit;
}

if (!$order_total) {
    echo json_encode(['success' => false, 'message' => 'Tổng đơn hàng không hợp lệ']);
    exit;
}

// Check if this is a user voucher from lucky wheel (starts with "LW")
$is_lucky_wheel_voucher = strpos($coupon_code, 'LW') === 0;

if ($is_lucky_wheel_voucher) {
    // Get user voucher from user_vouchers table
    $sql = "SELECT 
                id, 
                voucher_code as code, 
                prize_name as description,
                discount_amount,
                min_order_value as min_purchase,
                is_used,
                expires_at as end_date
            FROM user_vouchers 
            WHERE voucher_code = ? AND is_used = 0 AND expires_at > NOW()";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $coupon_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn']);
        exit;
    }
    
    $coupon = $result->fetch_assoc();
    $stmt->close();
    
    // Set standard fields for user voucher
    $coupon['discount_type'] = 'fixed';
    $coupon['discount_value'] = $coupon['discount_amount'];
    $coupon['start_date'] = null;
    $coupon['usage_limit'] = 1;
    $coupon['used_count'] = 0;
    $coupon['max_discount'] = null;
    $coupon['product_id'] = null;
    $coupon['seller_id'] = null;
    $coupon['is_lucky_wheel_voucher'] = true;
    
} else {
    // Lấy thông tin mã giảm giá từ seller coupons
    $sql = "SELECT * FROM coupons WHERE code = ?";
    $params = [$coupon_code];
    $types = "s";

    if ($seller_id) {
        $sql .= " AND seller_id = ?";
        $params[] = $seller_id;
        $types .= "i";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại']);
        exit;
    }

    $coupon = $result->fetch_assoc();
    $stmt->close();
    $coupon['is_lucky_wheel_voucher'] = false;
}

// Kiểm tra thời gian hiệu lực
$now = date('Y-m-d H:i:s');
if (!$coupon['is_lucky_wheel_voucher'] && $coupon['start_date'] && $coupon['start_date'] > $now) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá chưa có hiệu lực']);
    exit;
}

if ($coupon['end_date'] && $coupon['end_date'] < $now) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết hạn']);
    exit;
}

// Kiểm tra giới hạn sử dụng
if (!$coupon['is_lucky_wheel_voucher'] && $coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng']);
    exit;
}

// Kiểm tra giá trị đơn hàng tối thiểu
if ($coupon['min_purchase'] && $order_total < $coupon['min_purchase']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Đơn hàng tối thiểu ' . number_format($coupon['min_purchase'], 0, ',', '.') . '₫'
    ]);
    exit;
}

// Kiểm tra sản phẩm áp dụng
if ($coupon['product_id'] && $product_id && $coupon['product_id'] != $product_id) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá không áp dụng cho sản phẩm này']);
    exit;
}

// Tính toán giảm giá
$discount_amount = 0;
if ($coupon['discount_type'] === 'percentage') {
    $discount_amount = ($order_total * $coupon['discount_value']) / 100;
    
    // Áp dụng giảm tối đa nếu có
    if ($coupon['max_discount'] && $discount_amount > $coupon['max_discount']) {
        $discount_amount = $coupon['max_discount'];
    }
} else {
    $discount_amount = $coupon['discount_value'];
}

// Đảm bảo giảm giá không vượt quá tổng đơn hàng
if ($discount_amount > $order_total) {
    $discount_amount = $order_total;
}

$final_total = $order_total - $discount_amount;

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Áp dụng mã giảm giá thành công',
    'coupon' => [
        'id' => (int)$coupon['id'],
        'code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'discount_amount' => round($discount_amount, 2),
        'final_total' => round($final_total, 2),
        'description' => $coupon['description'],
        'is_lucky_wheel_voucher' => $coupon['is_lucky_wheel_voucher']
    ]
]);
