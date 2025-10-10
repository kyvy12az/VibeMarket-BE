<?php
// Cho phép CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=utf-8");

// Lấy dữ liệu từ request
$data = json_decode(file_get_contents("php://input"), true);

$orderCode = $data['orderCode'] ?? '';
$orderInfo = $data['orderInfo'] ?? 'Thanh toán đơn hàng VibeMarket';

// Lấy thông tin đơn hàng từ DB
require_once '../../../config/database.php';
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $orderCode);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại']);
    exit;
}

// Cấu hình PayOS (Production)
$payosEndpoint = "https://api-merchant.payos.vn/v2/payment-requests";
$checksumKey = "58bdccfb9704b2ec1b19d71cb577878332eea9adf4d0b764ac73bdbc26953e76";
$payosClientId = "d955cb17-9aeb-4383-abd0-9c791bf5cd7a";
$payosApiKey = "27a0f6fa-b527-4076-822e-0c12a381983b";

// Thông tin đơn hàng
$orderCode = (int) $order['id'];
$amount = (int) $order['total'];
$description = $orderInfo;
$returnUrl = "http://localhost:8080/payos-callback";
$cancelUrl = "http://localhost:8080/checkout";

// Thông tin khách hàng (ví dụ, bạn có thể lấy từ bảng orders hoặc users)
$buyerName = $order['customer_name'] ?? '';
$buyerEmail = $order['customer_email'] ?? '';
$buyerPhone = $order['phone'] ?? '';
$buyerAddress = $order['address'] ?? '';

// Danh sách sản phẩm (ví dụ chỉ 1 sản phẩm, bạn có thể lấy từ order_items nếu cần)
$items = [
    [
        "name" => "Thanh toán đơn hàng #" . $orderCode,
        "quantity" => 1,
        "price" => $amount,
        "unit" => "đồng",
        "taxPercentage" => -2
    ]
];

// Invoice (hóa đơn)
$invoice = [
    "buyerNotGetInvoice" => true,
    "taxPercentage" => -2
];

// expiredAt (tùy chọn, có thể bỏ qua hoặc set thời gian hết hạn)
$expiredAt = time() + 3600; // 1 tiếng

// Tạo signature đúng định dạng yêu cầu
$rawData = "amount=$amount&cancelUrl=$cancelUrl&description=$description&orderCode=$orderCode&returnUrl=$returnUrl";
$signature = hash_hmac('sha256', $rawData, $checksumKey);

// Payload gửi PayOS
$payload = [
    "orderCode" => $orderCode,
    "amount" => $amount,
    "description" => $description,
    "buyerName" => $buyerName,
    "buyerCompanyName" => "",
    "buyerTaxCode" => "",
    "buyerAddress" => $buyerAddress,
    "buyerEmail" => $buyerEmail,
    "buyerPhone" => $buyerPhone,
    "items" => $items,
    "cancelUrl" => $cancelUrl,
    "returnUrl" => $returnUrl,
    "invoice" => $invoice,
    "expiredAt" => $expiredAt,
    "signature" => $signature
];

// Log request để debug
file_put_contents('payos_request.log', json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// Gửi request đến PayOS
$ch = curl_init($payosEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-client-id: $payosClientId",
    "x-api-key: $payosApiKey"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
$response = curl_exec($ch);

if ($response === false) {
    file_put_contents('payos_error.log', 'Curl error: ' . curl_error($ch) . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối PayOS']);
    exit;
}
curl_close($ch);

// Log response để debug
file_put_contents('payos_response.log', $response . PHP_EOL, FILE_APPEND);

$result = json_decode($response, true);

// Trả về kết quả
if (isset($result['checkoutUrl'])) {
    echo json_encode([
        'success' => true,
        'payUrl' => $result['checkoutUrl'],
        'orderId' => $orderCode
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Không thể tạo thanh toán PayOS'
    ]);
}