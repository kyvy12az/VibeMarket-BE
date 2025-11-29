<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$new_status = isset($input['status']) ? $input['status'] : '';

if (!$product_id || !$user_id || !$new_status) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
    exit;
}

// Validate status
$valid_statuses = ['active', 'inactive'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Trạng thái không hợp lệ']);
    exit;
}

// Lấy seller_id từ user_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Kiểm tra sản phẩm có thuộc seller không
$stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy sản phẩm hoặc không có quyền cập nhật']);
    exit;
}
$stmt->close();

// Cập nhật trạng thái
$stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $product_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $new_status === 'active' ? 'Đã kích hoạt sản phẩm' : 'Đã ngừng bán sản phẩm',
        'product_id' => $product_id,
        'new_status' => $new_status
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Không thể cập nhật trạng thái: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();