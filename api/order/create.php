<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['products']) || !is_array($data['products']) || count($data['products']) === 0 ||
    !isset($data['fullName'], $data['phone'], $data['address'], $data['payment_method'], $data['total'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin đơn hàng']);
    exit;
}

$email = isset($data['email']) ? $data['email'] : null;
$customer_id = isset($data['customer_id']) && $data['customer_id'] ? $data['customer_id'] : null;
$note = isset($data['note']) ? $data['note'] : '';
$status = 'pending';
$payment_status = $data['payment_method'] === 'cod' ? 'pending' : 'unpaid';
$code = 'OD' . time() . rand(100, 999);
$payment_transaction_id = 'VMGD' . time() . rand(1000, 9999);
$shipping_tracking_code = 'TRK' . time() . rand(1000, 9999);

$conn->begin_transaction();

$shipping_fee = isset($data['shipping_fee']) ? intval($data['shipping_fee']) : 0;

$shipping_method_id = isset($data['shipping_method_id']) ? intval($data['shipping_method_id']) : null;
$shipping_method = null;
$shipping_carrier = null;
$shipping_estimated_days = null;

if ($shipping_method_id) {
    $stmtShip = $conn->prepare("SELECT shipping_method, shipping_carrier, shipping_estimated_days FROM shipping_methods WHERE id = ?");
    $stmtShip->bind_param("i", $shipping_method_id);
    $stmtShip->execute();
    $stmtShip->bind_result($shipping_method, $shipping_carrier, $shipping_estimated_days);
    $stmtShip->fetch();
    $stmtShip->close();
}

try {
    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders 
        (code, customer_id, customer_name, phone, email, address, note, total, shipping_fee, status, payment_method, payment_status, payment_transaction_id, shipping_method_id, shipping_tracking_code, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param(
        "sisssssiissssis",
        $code, // s
        $customer_id, // i
        $data['fullName'], // s
        $data['phone'], // s
        $email, // s
        $data['address'], // s
        $note, // s
        $data['total'], // i
        $shipping_fee, // i
        $status, // s
        $data['payment_method'], // s
        $payment_status, // s
        $payment_transaction_id,  // s
        $shipping_method_id,  // i
        $shipping_tracking_code // s
    );
    if (!$stmt->execute()) {
        throw new Exception("Order insert error: " . $stmt->error);
    }
    $order_id = $stmt->insert_id;
    $stmt->close();

    // Insert order_items
    foreach ($data['products'] as $item) {
        $product_id = $item['id'];
        $seller_id = $item['seller_id']; // lấy seller_id từ từng sản phẩm
        $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
        $price = isset($item['price']) ? $item['price'] : 0;
        $size = isset($item['size']) ? $item['size'] : null;
        $color = isset($item['color']) ? $item['color'] : null;

        $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, seller_id, quantity, price, size, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtItem->bind_param(
            "iiiiiss",
            $order_id,
            $product_id,
            $seller_id,
            $quantity,
            $price,
            $size,
            $color
        );
        if (!$stmtItem->execute()) {
            throw new Exception("Order item insert error: " . $stmtItem->error);
        }
        $stmtItem->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'code' => $code, 'id' => $order_id]);
} catch (Exception $e) {
    $conn->rollback();
    file_put_contents('order_error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi tạo đơn hàng', 'error' => $e->getMessage()]);
}
$conn->close();