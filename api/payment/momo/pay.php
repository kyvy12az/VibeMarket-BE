<?php
require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

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

error_log("User ID: " . $user_id);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("MoMo Pay Input: " . json_encode($input));

    if (!$input) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    $orderInfo = $input['orderInfo'] ?? 'Thanh toán đơn hàng VibeMarket';
    $orderCode =  $input['orderCode'];
    // Sửa lại truy vấn cho đúng trường
    $stmt_get_order_info = $conn->prepare("SELECT * FROM orders WHERE code = ? AND customer_id = ?");
    $stmt_get_order_info->bind_param("si", $orderCode, $user_id);
    $stmt_get_order_info->execute();
    $checkOrder = $stmt_get_order_info->get_result()->fetch_assoc();

    if (!$checkOrder) {
        throw new Exception('Đơn hàng không tồn tại');
    }
    $amount = $checkOrder['total'];
    
    // Lấy thông tin sản phẩm để tạo mô tả đơn hàng
    $productNames = [];
    $orderItemsStmt = $conn->prepare("SELECT oi.*, p.name as product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    
    if ($orderItemsStmt) {
        $orderItemsStmt->bind_param("i", $checkOrder['id']);
        $orderItemsStmt->execute();
        $orderItems = $orderItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($orderItems as $item) {
            $productName = $item['product_name'] ?? 'Sản phẩm';
            $productNames[] = $productName;
        }
    }
    
    // Tạo mô tả đơn hàng với tên sản phẩm
    if (count($productNames) > 0) {
        if (count($productNames) == 1) {
            $orderInfo = "Thanh toán đơn hàng: " . $productNames[0];
        } else {
            $firstProduct = $productNames[0];
            $remainingCount = count($productNames) - 1;
            $orderInfo = "Thanh toán đơn hàng: " . $firstProduct . " và " . $remainingCount . " sản phẩm khác";
        }
    } else {
        $orderInfo = "Thanh toán đơn hàng #" . $orderCode;
    }
    
    $requestId = time() . "";
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        $redirectUrl = "http://localhost:8080/callback/momo";
        $ipnUrl = "http://localhost:8080/callback/momo";
    } else {
        $redirectUrl = "https://vibemarket.kyvydev.id.vn/callback/momo";
        $ipnUrl = "https://vibemarket.kyvydev.id.vn/callback/momo";
    }

    $extraData = "";
    $requestType = "payWithMethod";
    $autoCapture = true;
    $lang = 'vi';

    $rawHash = "accessKey=" . $Momo_AccessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderCode . "&orderInfo=" . $orderInfo . "&partnerCode=" . $Momo_PartnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;

    $signature = hash_hmac("sha256", $rawHash, $Momo_SecretKey);

    $data = array(
        'partnerCode' => $Momo_PartnerCode,
        'partnerName' => "Momo",
        'storeId' => "MomoTestStore",
        'requestId' => $requestId,
        'amount' => $amount,
        'orderId' => $orderCode,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $redirectUrl,
        'ipnUrl' => $ipnUrl,
        'lang' => $lang,
        'extraData' => $extraData,
        'requestType' => $requestType,
        'signature' => $signature,
        'autoCapture' => $autoCapture
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $Momo_Endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
    ));

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    error_log("MoMo API Response (HTTP $httpCode): " . $response);
    $result = json_decode($response, true);

    if ($result && $result['resultCode'] == 0) {
        $stmt = $conn->prepare("INSERT INTO momo_transactions (order_id, request_id, amount, order_info, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssis", $orderCode, $requestId, $amount, $orderInfo);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'payUrl' => $result['payUrl'],
            'orderId' => $orderCode,
            'requestId' => $requestId
        ]);
    } else {
        throw new Exception('Lỗi tạo đơn hàng MoMo: ' . ($result['message'] ?? 'Lỗi không xác định'));
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}