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

$coupon_id = isset($data['coupon_id']) ? intval($data['coupon_id']) : 0;

if (!$coupon_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu coupon_id']);
    exit;
}

// Kiểm tra mã giảm giá có tồn tại không
$stmt = $conn->prepare("SELECT id, used_count FROM coupons WHERE id = ?");
$stmt->bind_param("i", $coupon_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy mã giảm giá']);
    exit;
}

$coupon = $result->fetch_assoc();
$stmt->close();

// Tăng số lượt sử dụng
$new_used_count = $coupon['used_count'] + 1;
$stmt = $conn->prepare("UPDATE coupons SET used_count = ? WHERE id = ?");
$stmt->bind_param("ii", $new_used_count, $coupon_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Áp dụng mã giảm giá thành công'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi áp dụng mã giảm giá: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
