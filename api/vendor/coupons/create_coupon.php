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

$vendor_id = isset($data['vendor_id']) ? intval($data['vendor_id']) : 0;
$code = isset($data['code']) ? trim(strtoupper($data['code'])) : '';
$discount_type = isset($data['discount_type']) ? $data['discount_type'] : 'percentage';
$discount_value = isset($data['discount_value']) ? floatval($data['discount_value']) : 0;
$min_purchase = isset($data['min_purchase']) ? floatval($data['min_purchase']) : null;
$max_discount = isset($data['max_discount']) ? floatval($data['max_discount']) : null;
$start_date = isset($data['start_date']) ? $data['start_date'] : date('Y-m-d');
$end_date = isset($data['end_date']) ? $data['end_date'] : null;
$usage_limit = isset($data['usage_limit']) ? intval($data['usage_limit']) : null;
$product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
$description = isset($data['description']) ? trim($data['description']) : null;

// Validate
if (!$vendor_id || !$code || !$discount_value) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

if (!in_array($discount_type, ['percentage', 'fixed'])) {
    echo json_encode(['success' => false, 'message' => 'Loại giảm giá không hợp lệ']);
    exit;
}

if ($discount_type === 'percentage' && $discount_value > 100) {
    echo json_encode(['success' => false, 'message' => 'Giá trị phần trăm không thể vượt quá 100']);
    exit;
}

// Lấy seller_id từ user_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Kiểm tra mã giảm giá đã tồn tại chưa
$stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND seller_id = ?");
$stmt->bind_param("si", $code, $seller_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã tồn tại']);
    exit;
}
$stmt->close();

// Tạo mã giảm giá mới
$sql = "INSERT INTO coupons (
    seller_id, 
    code, 
    discount_type, 
    discount_value, 
    min_purchase, 
    max_discount, 
    start_date, 
    end_date, 
    usage_limit, 
    product_id, 
    description,
    created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "issdddssiss",
    $seller_id,
    $code,
    $discount_type,
    $discount_value,
    $min_purchase,
    $max_discount,
    $start_date,
    $end_date,
    $usage_limit,
    $product_id,
    $description
);

if ($stmt->execute()) {
    $coupon_id = $stmt->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'Tạo mã giảm giá thành công',
        'coupon_id' => $coupon_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi tạo mã giảm giá: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
