<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// Ghi log dữ liệu nhận được để debug
file_put_contents('update_status_debug.log', json_encode($data) . PHP_EOL, FILE_APPEND);

$order_id = (int)($data['order_id'] ?? 0);
$status = $data['status'] ?? '';

$allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!$order_id || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $order_id);
$success = $stmt->execute();

if ($success && $status === "processing") {
    // Lấy danh sách sản phẩm và số lượng trong đơn hàng này
    $sqlItems = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param("i", $order_id);
    $stmtItems->execute();
    $resultItems = $stmtItems->get_result();
    while ($item = $resultItems->fetch_assoc()) {
        // Cập nhật trường sold cho từng sản phẩm
        $conn->query("UPDATE products SET sold = sold + {$item['quantity']} WHERE id = {$item['product_id']}");
    }
}

// Cập nhật tổng doanh thu cho seller khi đơn hàng đã giao
if ($success && $status === "delivered") {
    // Lấy seller_id và tổng tiền của đơn hàng
    $sqlOrder = "SELECT seller_id, SUM(quantity * price) as total FROM order_items WHERE order_id = ? GROUP BY seller_id";
    $stmtOrder = $conn->prepare($sqlOrder);
    $stmtOrder->bind_param("i", $order_id);
    $stmtOrder->execute();
    $resultOrder = $stmtOrder->get_result();
    while ($row = $resultOrder->fetch_assoc()) {
        $seller_id = $row['seller_id'];
        $total = $row['total'];
        $conn->query("UPDATE seller SET total_revenue = total_revenue + $total WHERE seller_id = $seller_id");
    }
    $stmtOrder->close();

    // Cập nhật trạng thái thanh toán nếu là COD
    $sqlPayment = "UPDATE orders SET payment_status = 'paid', payment_paid_at = NOW() WHERE id = ? AND payment_method = 'cod'";
    $stmtPayment = $conn->prepare($sqlPayment);
    $stmtPayment->bind_param("i", $order_id);
    $stmtPayment->execute();
    $stmtPayment->close();
}

if (!$success) {
    // Ghi log lỗi SQL nếu có
    file_put_contents('update_status_debug.log', 'SQL error: ' . $stmt->error . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit;
}

echo json_encode([
    'success' => true,
    'order_id' => $order_id,
    'status' => $status
]);