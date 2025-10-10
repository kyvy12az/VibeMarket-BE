<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['user_id'], $data['store_name'], $data['avatar'], $data['business_type'], $data['tax_id'], $data['establish_year'], $data['description'], $data['phone'], $data['email'], $data['business_address'], $data['license_image'], $data['idcard_image'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

// Kiểm tra user_id đã tồn tại seller chưa
$stmtCheck = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmtCheck->bind_param("i", $data['user_id']);
$stmtCheck->execute();
$stmtCheck->store_result();
if ($stmtCheck->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Tài khoản đã đăng ký seller']);
    $stmtCheck->close();
    $conn->close();
    exit;
}
$stmtCheck->close();

$stmt = $conn->prepare(
    "INSERT INTO seller (user_id, store_name, avatar, business_type, tax_id, establish_year, description, phone, email, business_address, license_image, idcard_image, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "issssisssssss",
    $data['user_id'],
    $data['store_name'],
    $data['avatar'],
    $data['business_type'],
    $data['tax_id'],
    $data['establish_year'],
    $data['description'],
    $data['phone'],
    $data['email'],
    $data['business_address'],
    $data['license_image'],
    $data['idcard_image'],
    $status
);

$status = 'pending';

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đăng ký seller thành công']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi đăng ký seller']);
}

$stmt->close();
$conn->close();