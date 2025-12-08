<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if (!$vendor_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu vendor_id']);
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

// Lấy danh sách sản phẩm (chỉ id và name)
$sql = "SELECT id, name FROM products WHERE seller_id = ? AND status = 'active' ORDER BY name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => (int)$row['id'],
        'name' => $row['name']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'products' => $products
]);
