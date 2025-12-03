<?php
require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Xác thực JWT
$headers = getallheaders();
$user_id = null;
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token']);
    exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
    exit;
}
$jwt = $matches[1];
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập lại']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Phương thức không được phép']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    $partnerCode = $input['partnerCode'] ?? '';
    $orderId = $input['orderId'] ?? '';
    $requestId = $input['requestId'] ?? '';
    $amount = $input['amount'] ?? 0;
    $orderInfo = urldecode($input['orderInfo'] ?? '');
    $orderType = $input['orderType'] ?? '';
    $transId = $input['transId'] ?? '';
    $resultCode = $input['resultCode'] ?? '';
    $message = urldecode($input['message'] ?? '');
    $payType = $input['payType'] ?? '';
    $responseTime = $input['responseTime'] ?? '';
    $extraData = $input['extraData'] ?? '';
    $signature = $input['signature'] ?? '';

    if (empty($orderId) || empty($requestId) || empty($signature)) {
        throw new Exception('Thiếu thông tin bắt buộc');
    }

    // Sử dụng config từ database.php
    global $Momo_SecretKey, $Momo_AccessKey;

    $rawHash = "accessKey=" . $Momo_AccessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&message=" . $message . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&orderType=" . $orderType . "&partnerCode=" . $partnerCode . "&payType=" . $payType . "&requestId=" . $requestId . "&responseTime=" . $responseTime . "&resultCode=" . $resultCode . "&transId=" . $transId;

    $expectedSignature = hash_hmac("sha256", $rawHash, $Momo_SecretKey);

    error_log("Raw hash: " . $rawHash);
    error_log("Expected signature: " . $expectedSignature);
    error_log("Received signature: " . $signature);

    if ($signature !== $expectedSignature) {
        throw new Exception('Chữ ký không hợp lệ');
    }

    $stmt = $conn->prepare("UPDATE momo_transactions SET result_code = ?, trans_id = ?, message = ?, pay_type = ?, response_time = ?, extra_data = ?, signature = ?, status = ?, updated_at = NOW() WHERE order_id = ?");

    $status = ($resultCode == 0) ? 'success' : 'failed';

    $stmt->bind_param("issssssss", $resultCode, $transId, $message, $payType, $responseTime, $extraData, $signature, $status, $orderId);
    $stmt->execute();
    
    $logStmt = $conn->prepare("INSERT INTO transaction_logs (reference_type, reference_id, action, request_data, response_data, http_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $logStmt->bind_param("sssssi", $momoRef, $orderId, $callbackAction, $requestDataJson, $responseDataJson, $httpStatus);
    
    $momoRef = 'momo';
    $callbackAction = 'callback';
    $requestDataJson = json_encode($input);
    $responseDataJson = json_encode(['status' => $status, 'message' => $message]);
    $httpStatus = 200;
    
    $logStmt->execute();

    if ($resultCode == 0) {
        // Cập nhật trạng thái thanh toán của đơn hàng
        $updateOrderStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE code = ? AND customer_id = ?");
        $updateOrderStmt->bind_param("si", $orderId, $user_id);
        $updateOrderStmt->execute();
        // Thanh toán thành công
        echo json_encode([
            'success' => true,
            'message' => 'Thanh toán thành công',
            'orderId' => $orderId,
            'transId' => $transId,
            'amount' => $amount
        ]);
    } else {
        // Thanh toán thất bại
        echo json_encode([
            'success' => false,
            'message' => 'Thanh toán thất bại: ' . $message,
            'resultCode' => $resultCode,
            'orderId' => $orderId
        ]);
    }
} catch (Exception $e) {
    if (isset($conn)) {
        try {
            $errorLogStmt = $conn->prepare("INSERT INTO transaction_logs (reference_type, reference_id, action, request_data, error_message, http_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            $momoRef = 'momo';
            $orderIdVal = $_POST['orderId'] ?? 'unknown';
            $actionVal = 'callback_error';
            $requestVal = json_encode($_POST);
            $errorVal = $e->getMessage();
            $httpVal = 400;
            
            $errorLogStmt->bind_param("sssssi", $momoRef, $orderIdVal, $actionVal, $requestVal, $errorVal, $httpVal);
            $errorLogStmt->execute();
        } catch (Exception $logError) {
            error_log("Failed to log error: " . $logError->getMessage());
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
