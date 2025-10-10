<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

require_once '../../../config/database.php';

// Nhận dữ liệu callback từ PayOS (POST hoặc GET)
$data = $_POST ?: $_GET;

// Lấy các trường cần thiết từ callback
$orderCode = isset($data['orderCode']) ? (int)$data['orderCode'] : 0;
$amount = isset($data['amount']) ? (int)$data['amount'] : 0;
$code = $data['code'] ?? null; // "00" là thành công
$message = $data['message'] ?? '';
$transId = $data['transId'] ?? '';
$signature = $data['signature'] ?? '';

// Kiểm tra chữ ký (signature) nếu cần bảo mật hơn (khuyến nghị)
$checksumKey = "YOUR_CHECKSUM_KEY"; // Thay bằng Checksum Key thật của bạn

// Tạo rawData đúng thứ tự tài liệu PayOS
// amount=$amount&cancelUrl=$cancelUrl&code=$code&description=$description&orderCode=$orderCode&returnUrl=$returnUrl&transId=$transId
$cancelUrl = $data['cancelUrl'] ?? '';
$description = $data['description'] ?? '';
$returnUrl = $data['returnUrl'] ?? '';

$rawData = "amount=$amount&cancelUrl=$cancelUrl&code=$code&description=$description&orderCode=$orderCode&returnUrl=$returnUrl&transId=$transId";
$expectedSignature = hash_hmac('sha256', $rawData, $checksumKey);

if ($signature !== $expectedSignature) {
    echo json_encode([
        'success' => false,
        'message' => 'Chữ ký không hợp lệ',
        'orderId' => $orderCode,
        'code' => $code,
        'transId' => $transId
    ]);
    exit;
}

// Nếu thanh toán thành công
if ($code === "00" && $orderCode) {
    // Cập nhật trạng thái đơn hàng
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $orderCode);
    $stmt->execute();

    // Lưu giao dịch vào bảng payos_transactions
    $stmt2 = $conn->prepare("INSERT INTO payos_transactions (order_id, trans_id, amount, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt2->bind_param("isis", $orderCode, $transId, $amount, $message);
    $stmt2->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Thanh toán thành công',
        'orderId' => $orderCode,
        'transId' => $transId,
        'amount' => $amount
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Thanh toán thất bại hoặc không hợp lệ',
        'orderId' => $orderCode,
        'code' => $code,
        'transId' => $transId
    ]);
}