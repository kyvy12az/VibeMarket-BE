<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
    exit;
}

// Cập nhật trạng thái seller
$stmt = $conn->prepare("UPDATE seller SET status = 'approved' WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$seller_updated = $stmt->affected_rows;
$stmt->close();

// Cập nhật role user thành seller
$stmt2 = $conn->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$user_updated = $stmt2->affected_rows;
$stmt2->close();

if ($seller_updated && $user_updated) {
    echo json_encode(['success' => true, 'message' => 'Seller approved and user role updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy seller hoặc user']);
}

$conn->close();