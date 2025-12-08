<?php
require_once '../../../config/database.php';
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

// Lấy danh sách mã giảm giá của seller
$sql = "
    SELECT 
        c.*,
        p.name as product_name,
        CASE 
            WHEN c.end_date < NOW() THEN 'expired'
            WHEN c.start_date > NOW() THEN 'inactive'
            ELSE 'active'
        END as status
    FROM coupons c
    LEFT JOIN products p ON c.product_id = p.id
    WHERE c.seller_id = ?
    ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$coupons = [];
while ($row = $result->fetch_assoc()) {
    $coupons[] = [
        'id' => (int)$row['id'],
        'code' => $row['code'],
        'discount_type' => $row['discount_type'],
        'discount_value' => (float)$row['discount_value'],
        'min_purchase' => $row['min_purchase'] ? (float)$row['min_purchase'] : null,
        'max_discount' => $row['max_discount'] ? (float)$row['max_discount'] : null,
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'usage_limit' => $row['usage_limit'] ? (int)$row['usage_limit'] : null,
        'used_count' => (int)$row['used_count'],
        'product_id' => $row['product_id'] ? (int)$row['product_id'] : null,
        'product_name' => $row['product_name'],
        'status' => $row['status'],
        'description' => $row['description'],
        'created_at' => $row['created_at']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'coupons' => $coupons
]);
