<?php

require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Kiểm tra phương thức HTTP (OPTIONS already handled by int_headers)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Xác thực JWT
$headers = getallheaders();
$user_id = null;
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Missing token"]);
    exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit;
}
$jwt = $matches[1];
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: Invalid JWT"]);
    exit;
}

try {
    // Lấy dữ liệu từ FE
    $rawInput = file_get_contents("php://input");
    error_log("=== ZALOPAY PAY.PHP REQUEST ===");
    error_log("Raw input: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    error_log("Decoded input: " . json_encode($input));

    if (!isset($input['orderCode'], $input['orderInfo'])) {
        error_log("ERROR: Missing required fields - orderCode: " . (isset($input['orderCode']) ? 'YES' : 'NO') . ", orderInfo: " . (isset($input['orderInfo']) ? 'YES' : 'NO'));
        throw new Exception('Invalid input: Missing orderCode or orderInfo');
    }

    $orderCode = $input['orderCode'];
    $orderInfo = $input['orderInfo'];
    
    error_log("Processing payment - orderCode: $orderCode, orderInfo: $orderInfo");

    // Validate order in database
    // Debug: Check orders table structure
    $tableCheck = $conn->query("DESCRIBE orders");
    error_log("=== ORDERS TABLE STRUCTURE ===");
    while ($row = $tableCheck->fetch_assoc()) {
        error_log("Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}");
    }
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE code = ? AND customer_id = ?");
    $stmt->bind_param("si", $orderCode, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    error_log("Order lookup - Code: $orderCode, User: $user_id");
    error_log("Order found: " . ($order ? "YES" : "NO"));
    if ($order) {
        error_log("Order data: " . json_encode($order));
    }

    if (!$order) {
        error_log("ERROR: Order not found - Code: $orderCode, User: $user_id");
        throw new Exception('Order does not exist or does not belong to you');
    }
    
    error_log("Order found successfully - ID: {$order['id']}, Total: {$order['total']}");

    $amount = $order['total'];

    // Lấy thông tin sản phẩm từ đơn hàng
    $items = [];
    $productNames = []; // Lưu tên sản phẩm để tạo description
    $orderItemsStmt = $conn->prepare("SELECT oi.*, p.name as product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    
    if ($orderItemsStmt) {
        $orderItemsStmt->bind_param("i", $order['id']);
        $orderItemsStmt->execute();
        $orderItems = $orderItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Format items cho ZaloPay
        foreach ($orderItems as $item) {
            $productName = $item['product_name'] ?? 'Sản phẩm';
            $items[] = [
                'itemid' => (string)$item['product_id'],
                'itemname' => $productName,
                'itemprice' => (int)$item['price'],
                'itemquantity' => (int)$item['quantity']
            ];
            $productNames[] = $productName;
        }
        
        error_log("Order items for ZaloPay: " . json_encode($items));
    } else {
        error_log("WARNING: Failed to prepare order_items query: " . $conn->error);
        // Fallback: Tạo item mặc định từ thông tin đơn hàng
        $items[] = [
            'itemid' => '1',
            'itemname' => 'Đơn hàng #' . $orderCode,
            'itemprice' => (int)$amount,
            'itemquantity' => 1
        ];
        $productNames[] = 'Đơn hàng #' . $orderCode;
    }
    
    // Tạo mô tả đơn hàng với tên sản phẩm
    if (count($productNames) > 0) {
        if (count($productNames) == 1) {
            $orderDescription = "Thanh toán đơn hàng: " . $productNames[0];
        } else {
            // Nếu nhiều sản phẩm, hiển thị tên sản phẩm đầu + số lượng còn lại
            $firstProduct = $productNames[0];
            $remainingCount = count($productNames) - 1;
            $orderDescription = "Thanh toán đơn hàng: " . $firstProduct . " và " . $remainingCount . " sản phẩm khác";
        }
    } else {
        $orderDescription = "Thanh toán đơn hàng #" . $orderCode;
    }

    // URL callback và redirect - hỗ trợ localhost và production
    $frontend_url = $isDev ? "http://localhost:8080" : "https://vibemarket.kyvydev.id.vn";
    $backend_url = $isDev ? "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE" : "https://komer.id.vn";
    
    $redirect_url = $frontend_url . "/callback/zalopay";
    $callback_url = $backend_url . "/api/payment/zalopay/callback.php";

    // Tạo dữ liệu gửi đến ZaloPay
    $data = [
        'app_id' => (int)$ZaloPay_AppID,
        'app_trans_id' => date("ymd") . "_" . $orderCode, // Mã giao dịch
        'app_user' => "user_$user_id", // Người dùng (có thể thay đổi theo hệ thống của bạn)
        'amount' => (int)$amount,
        'app_time' => round(microtime(true) * 1000),
        'item' => json_encode($items), // Danh sách sản phẩm
        'embed_data' => json_encode([
            "redirecturl" => $redirect_url
        ]),
        'description' => $orderDescription, // Sử dụng mô tả động với tên sản phẩm
        'bank_code' => ""
    ];

    // Tạo chữ ký (signature) theo đúng tài liệu ZaloPay
    // mac = HMAC(HmacSHA256, key1, app_id|app_trans_id|app_user|amount|app_time|embed_data|item)
    $hmacInput = $data['app_id'] . "|" . $data['app_trans_id'] . "|" . $data['app_user'] . "|" . 
                 $data['amount'] . "|" . $data['app_time'] . "|" . $data['embed_data'] . "|" . $data['item'];
    $data['mac'] = hash_hmac('sha256', $hmacInput, $ZaloPay_Key1);
    
    error_log("ZaloPay MAC Input: " . $hmacInput);
    error_log("ZaloPay MAC: " . $data['mac']);

    // Gửi yêu cầu đến ZaloPay
    $postData = array_merge($data, ['callback_url' => $callback_url]);
    
    $ch = curl_init($ZaloPay_Endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

    error_log("Sending request to ZaloPay: " . $ZaloPay_Endpoint);
    error_log("Request data: " . json_encode($postData));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("ZaloPay response - HTTP Code: $httpCode");
    if ($curlError) {
        error_log("cURL Error: " . $curlError);
    }
    error_log("ZaloPay response body: " . substr($response, 0, 500));

    if ($httpCode !== 200) {
        throw new Exception('Failed to connect to ZaloPay - HTTP ' . $httpCode);
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['return_code']) && $responseData['return_code'] === 1) {
        // Lưu giao dịch vào database
        $app_trans_id = $data['app_trans_id'];
        $zp_trans_token = $responseData['zp_trans_token'] ?? null;
        $order_url = $responseData['order_url'];
        
        // Debug: Check zalopay_transactions table structure
        $tableCheck = $conn->query("DESCRIBE zalopay_transactions");
        if ($tableCheck) {
            error_log("=== ZALOPAY_TRANSACTIONS TABLE STRUCTURE ===");
            while ($row = $tableCheck->fetch_assoc()) {
                error_log("Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}");
            }
        } else {
            error_log("WARNING: zalopay_transactions table does not exist!");
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO zalopay_transactions (order_code, app_trans_id, amount, zp_trans_token, order_url, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            if ($stmt) {
                $stmt->bind_param("ssiss", $orderCode, $app_trans_id, $amount, $zp_trans_token, $order_url);
                $result = $stmt->execute();
                error_log("ZaloPay transaction insert result: " . ($result ? "SUCCESS" : "FAILED"));
                if (!$result) {
                    error_log("Insert error: " . $stmt->error);
                }
            }
        } catch (Exception $dbError) {
            // Log error but don't fail the payment
            error_log("ZaloPay transaction save failed: " . $dbError->getMessage());
        }
        
        echo json_encode(["success" => true, "payUrl" => $order_url]);
    } else {
        throw new Exception($responseData['return_message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>