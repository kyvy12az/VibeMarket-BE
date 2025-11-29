<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

if (!$product_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

// Lấy seller_id từ user_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Kiểm tra sản phẩm có thuộc seller không
$stmt = $conn->prepare("SELECT id, image FROM products WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm hoặc không có quyền xóa']);
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

// Xóa ảnh trên server (nếu cần)
$images = json_decode($product['image'], true);
if (is_array($images)) {
    foreach ($images as $img) {
        if ($img && file_exists('../../' . $img)) {
            unlink('../../' . $img);
        }
    }
}

// Xóa sản phẩm khỏi database
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa sản phẩm thành công'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi xóa sản phẩm'
    ]);
}

$stmt->close();
$conn->close();