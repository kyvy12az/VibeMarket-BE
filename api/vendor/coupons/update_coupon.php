<?php
require_once '../../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Lấy dữ liệu từ request
$data = json_decode(file_get_contents("php://input"), true);

$coupon_id = isset($data['coupon_id']) ? intval($data['coupon_id']) : 0;
$code = isset($data['code']) ? trim(strtoupper($data['code'])) : '';
$discount_type = isset($data['discount_type']) ? $data['discount_type'] : 'percentage';
$discount_value = isset($data['discount_value']) ? floatval($data['discount_value']) : 0;
$min_purchase = isset($data['min_purchase']) ? floatval($data['min_purchase']) : null;
$max_discount = isset($data['max_discount']) ? floatval($data['max_discount']) : null;
$start_date = isset($data['start_date']) ? $data['start_date'] : null;
$end_date = isset($data['end_date']) ? $data['end_date'] : null;
$usage_limit = isset($data['usage_limit']) ? intval($data['usage_limit']) : null;
$product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
$description = isset($data['description']) ? trim($data['description']) : null;

// Validate
if (!$coupon_id || !$code || !$discount_value) {
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

// Kiểm tra mã giảm giá có tồn tại không
$stmt = $conn->prepare("SELECT seller_id FROM coupons WHERE id = ?");
$stmt->bind_param("i", $coupon_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy mã giảm giá']);
    exit;
}
$stmt->close();

// Kiểm tra mã giảm giá trùng lặp (trừ mã hiện tại)
$stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND seller_id = ? AND id != ?");
$stmt->bind_param("sii", $code, $seller_id, $coupon_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã tồn tại']);
    exit;
}
$stmt->close();

// Cập nhật mã giảm giá
$sql = "UPDATE coupons SET 
    code = ?, 
    discount_type = ?, 
    discount_value = ?, 
    min_purchase = ?, 
    max_discount = ?, 
    start_date = ?, 
    end_date = ?, 
    usage_limit = ?, 
    product_id = ?, 
    description = ?
WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssdddssissi",
    $code,
    $discount_type,
    $discount_value,
    $min_purchase,
    $max_discount,
    $start_date,
    $end_date,
    $usage_limit,
    $product_id,
    $description,
    $coupon_id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật mã giảm giá thành công'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi cập nhật mã giảm giá: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
