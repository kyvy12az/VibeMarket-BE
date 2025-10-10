<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['seller_id'], $data['avatar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

$stmt = $conn->prepare("UPDATE seller SET avatar = ? WHERE seller_id = ?");
$stmt->bind_param("si", $data['avatar'], $data['seller_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật avatar thành công']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật avatar']);
}

$stmt->close();
$conn->close();