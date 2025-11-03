<?php
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('Asia/Ho_Chi_Minh');

// CORS (echo Origin nếu có, cần cho credentials)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';

// --- CONFIG - thay bằng giá trị thật của bạn ---
$vnp_TmnCode    = "RNDYZYEE";
$vnp_HashSecret = "BFTAPNWWCD2A9KY7TCX4YYEMWZI5H7HJ";
$vnp_Url        = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl  = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/payment/vnpay/callback.php";
// -------------------------------------------------

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Validate & defaults
$orderCode = trim($input['orderCode'] ?? '');
if ($orderCode === '') $orderCode = 'ORD' . time() . rand(1000,9999);
$orderInfo = trim($input['orderInfo'] ?? "Thanh toán đơn hàng $orderCode");
$amount = isset($input['amount']) ? intval($input['amount']) : 0; // amount in VND
if ($amount <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Số tiền không hợp lệ']);
    exit;
}
$bankCode = trim($input['bankCode'] ?? '');
$locale = trim($input['locale'] ?? 'vn');

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$vnp_TxnRef = $orderCode;
$vnp_CreateDate = date('YmdHis');
$vnp_ExpireDate = date('YmdHis', time() + 15*60); // 15 minutes

// Build params
$vnp_Params = [
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $amount * 100,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => $vnp_CreateDate,
    "vnp_ExpireDate" => $vnp_ExpireDate,
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $client_ip,
    "vnp_Locale" => $locale,
    "vnp_OrderInfo" => $orderInfo,
    "vnp_OrderType" => "other",
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
];
if ($bankCode !== '') $vnp_Params['vnp_BankCode'] = $bankCode;

ksort($vnp_Params);

// build hashdata and query using urlencode for query; use same pattern for hash (consistent with IPN)
$hashdata = [];
$query = [];
foreach ($vnp_Params as $key => $value) {
    $hashdata[] = urlencode($key) . "=" . urlencode($value);
    $query[] = urlencode($key) . "=" . urlencode($value);
}
$hashData = implode('&', $hashdata);
$vnp_SecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

$queryString = implode('&', $query);
$payUrl = $vnp_Url . '?' . $queryString . '&vnp_SecureHash=' . $vnp_SecureHash;

// Optionally log pending transaction
try {
    if (isset($conn)) {
        $order_id = null;
        $stmt = $conn->prepare("SELECT id FROM orders WHERE code = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $orderCode);
            $stmt->execute();
            $stmt->bind_result($found_id);
            if ($stmt->fetch()) $order_id = $found_id;
            $stmt->close();
        }
        $raw_request = json_encode($vnp_Params);
        $ins = $conn->prepare("INSERT IGNORE INTO vnpay_transactions (txn_ref, order_code, order_id, amount, secure_hash, ip_addr, raw_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($ins) {
            // types: s s i i s s s  -> txn_ref(s), order_code(s), order_id(i), amount(i), secure_hash(s), ip_addr(s), raw_data(s)
            $ins->bind_param("ssiiiss", $vnp_TxnRef, $orderCode, $order_id, $amount, $vnp_SecureHash, $client_ip, $raw_request);
            $ins->execute();
            $ins->close();
        }
    }
} catch (Exception $e) {
    error_log("VNPay pay log error: " . $e->getMessage());
}

ob_end_clean();
echo json_encode(['success' => true, 'payUrl' => $payUrl, 'txnRef' => $vnp_TxnRef, 'expire' => $vnp_ExpireDate]);

