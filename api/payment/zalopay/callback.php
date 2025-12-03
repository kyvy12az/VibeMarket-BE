<?php

require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Kiểm tra phương thức HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["return_code" => 0, "return_message" => "Method not allowed"]);
    exit;
}

// Lấy dữ liệu callback từ ZaloPay (KHÔNG cần JWT vì đây là callback từ ZaloPay server)
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['data'], $input['mac'])) {
    http_response_code(400);
    echo json_encode(["return_code" => 0, "return_message" => "Invalid callback data"]);
    exit;
}

// Xác thực chữ ký (signature) theo tài liệu ZaloPay
// reqmac = HMAC(HmacSHA256, key2, callback_data.data)
$calculatedMac = hash_hmac('sha256', $input['data'], $ZaloPay_Key2);
if ($calculatedMac !== $input['mac']) {
    error_log("ZaloPay callback MAC mismatch! Expected: " . $calculatedMac . ", Got: " . $input['mac']);
    http_response_code(400);
    echo json_encode(["return_code" => -1, "return_message" => "mac not equal"]);
    exit;
}

// Giải mã dữ liệu callback
$callbackData = json_decode($input['data'], true);

// Xử lý logic callback (cập nhật trạng thái đơn hàng, lưu log, v.v.)
$app_trans_id = $callbackData['app_trans_id'] ?? null;
$zp_trans_id = $callbackData['zp_trans_id'] ?? null;
$amount = $callbackData['amount'] ?? 0;

error_log("=== ZALOPAY CALLBACK RECEIVED ===");
error_log("Callback data: " . json_encode($callbackData));
error_log("app_trans_id: $app_trans_id");
error_log("zp_trans_id: $zp_trans_id");
error_log("amount: $amount");

// Debug: Check tables structure
$ordersCheck = $conn->query("DESCRIBE orders");
if ($ordersCheck) {
    error_log("=== ORDERS TABLE STRUCTURE (callback.php) ===");
    while ($row = $ordersCheck->fetch_assoc()) {
        error_log("Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}");
    }
}

$transCheck = $conn->query("DESCRIBE zalopay_transactions");
if ($transCheck) {
    error_log("=== ZALOPAY_TRANSACTIONS TABLE STRUCTURE (callback.php) ===");
    while ($row = $transCheck->fetch_assoc()) {
        error_log("Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}");
    }
}

if (!$app_trans_id) {
    http_response_code(400);
    echo json_encode(["return_code" => 0, "return_message" => "Missing required fields"]);
    exit;
}

try {
    // Tách orderCode từ app_trans_id (format: yymmdd_orderCode)
    $parts = explode('_', $app_trans_id);
    $orderCode = $parts[1] ?? null;
    
    if (!$orderCode) {
        throw new Exception("Invalid app_trans_id format");
    }
    
    error_log("Extracted orderCode: $orderCode");
    
    // Cập nhật trạng thái đơn hàng (KHÔNG cần customer_id vì callback từ ZaloPay)
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE code = ?");
    $stmt->bind_param("s", $orderCode);
    $updateResult = $stmt->execute();
    
    error_log("Order update result: " . ($updateResult ? "SUCCESS" : "FAILED"));
    error_log("Rows affected: " . $stmt->affected_rows);

    // Cập nhật trạng thái giao dịch ZaloPay
    $stmt = $conn->prepare("UPDATE zalopay_transactions SET status = 'success', zp_trans_id = ?, updated_at = NOW() WHERE app_trans_id = ?");
    $stmt->bind_param("is", $zp_trans_id, $app_trans_id);
    $transResult = $stmt->execute();
    
    error_log("Transaction update result: " . ($transResult ? "SUCCESS" : "FAILED"));
    error_log("Rows affected: " . $stmt->affected_rows);

    // Trả về response theo đúng format ZaloPay yêu cầu
    echo json_encode(["return_code" => 1, "return_message" => "success"]);
} catch (Exception $e) {
    error_log("Callback processing error: " . $e->getMessage());
    http_response_code(200); // Vẫn trả 200 cho ZaloPay
    // return_code = 0 để ZaloPay callback lại (tối đa 3 lần)
    echo json_encode(["return_code" => 0, "return_message" => $e->getMessage()]);
}

?>