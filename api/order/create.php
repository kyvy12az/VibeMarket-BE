<?php
require_once '../../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
$mailConfig = require_once __DIR__ . '/../../config/mail.php';


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

// Coupon data - Support dual vouchers (discount + freeship)
$discount_coupon_id = isset($data['discount_coupon_id']) ? intval($data['discount_coupon_id']) : null;
$freeship_coupon_id = isset($data['freeship_coupon_id']) ? intval($data['freeship_coupon_id']) : null;
$discount_amount = isset($data['discount_amount']) ? floatval($data['discount_amount']) : 0;
$freeship_discount = isset($data['freeship_discount']) ? floatval($data['freeship_discount']) : 0;

// Legacy support for old single coupon_id (for backward compatibility)
if (!$discount_coupon_id && isset($data['coupon_id'])) {
    $discount_coupon_id = intval($data['coupon_id']);
}

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
    // Insert order with dual voucher support
    $stmt = $conn->prepare("INSERT INTO orders 
        (code, customer_id, customer_name, phone, email, address, note, total, shipping_fee, payment_method, payment_status, payment_transaction_id, shipping_method_id, shipping_tracking_code, discount_coupon_id, discount_amount, freeship_coupon_id, freeship_discount, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param(
        "sissssssisssiididd",
        $code, // s - 1
        $customer_id, // i - 2
        $data['fullName'], // s - 3
        $data['phone'], // s - 4
        $email, // s - 5
        $data['address'], // s - 6
        $note, // s - 7
        $data['total'], // i - 8
        $shipping_fee, // i - 9
        $data['payment_method'], // s - 10
        $payment_status, // s - 11
        $payment_transaction_id,  // s - 12
        $shipping_method_id,  // i - 13
        $shipping_tracking_code, // s - 14
        $discount_coupon_id, // i - 15
        $discount_amount, // d - 16
        $freeship_coupon_id, // i - 17
        $freeship_discount // d - 18
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
    
    // Update discount coupon usage
    if ($discount_coupon_id && $discount_amount > 0) {
        // Check if this ID exists in user_vouchers (lucky wheel)
        $voucherCheck = $conn->query("SELECT id FROM user_vouchers WHERE id = {$discount_coupon_id} LIMIT 1");
        
        if ($voucherCheck && $voucherCheck->num_rows > 0) {
            // This is a lucky wheel voucher - mark as used
            $voucherStmt = $conn->prepare("UPDATE user_vouchers SET is_used = 1, used_at = NOW() WHERE id = ?");
            $voucherStmt->bind_param("i", $discount_coupon_id);
            if (!$voucherStmt->execute()) {
                throw new Exception("Failed to mark discount voucher as used: " . $voucherStmt->error);
            }
            $voucherStmt->close();
        } else {
            // This is a seller coupon - increment usage count
            $couponStmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
            $couponStmt->bind_param("i", $discount_coupon_id);
            if (!$couponStmt->execute()) {
                throw new Exception("Failed to update discount coupon usage: " . $couponStmt->error);
            }
            $couponStmt->close();
        }
    }

    // Update freeship coupon usage
    if ($freeship_coupon_id && $freeship_discount > 0) {
        // Check if this ID exists in user_vouchers (lucky wheel)
        $voucherCheck = $conn->query("SELECT id FROM user_vouchers WHERE id = {$freeship_coupon_id} LIMIT 1");
        
        if ($voucherCheck && $voucherCheck->num_rows > 0) {
            // This is a lucky wheel voucher - mark as used
            $voucherStmt = $conn->prepare("UPDATE user_vouchers SET is_used = 1, used_at = NOW() WHERE id = ?");
            $voucherStmt->bind_param("i", $freeship_coupon_id);
            if (!$voucherStmt->execute()) {
                throw new Exception("Failed to mark freeship voucher as used: " . $voucherStmt->error);
            }
            $voucherStmt->close();
        } else {
            // This is a seller coupon - increment usage count
            $couponStmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
            $couponStmt->bind_param("i", $freeship_coupon_id);
            if (!$couponStmt->execute()) {
                throw new Exception("Failed to update freeship coupon usage: " . $couponStmt->error);
            }
            $couponStmt->close();
        }
    }

    $conn->commit();

    // Send order confirmation email only for COD orders
    $toEmail = $email; // from earlier in file
    $paymentMethod = strtolower($data['payment_method'] ?? '');
    if ($toEmail && $paymentMethod === 'cod') {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['secure'];
            $mail->Port = $mailConfig['port'];

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
            $mail->addAddress($toEmail, $data['fullName'] ?? '');

            $mail->isHTML(true);
            $mail->Subject = "Xác nhận đơn hàng #{$code}";

            // build products HTML (fetch product names from DB if product_id provided)
            $productIds = [];
            foreach ($data['products'] as $it) {
                if (!empty($it['id'])) $productIds[] = intval($it['id']);
            }
            $productNames = [];
            if (count($productIds) > 0) {
                $ids = implode(',', array_unique($productIds));
                $res = $conn->query("SELECT id, name FROM products WHERE id IN ($ids)");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $productNames[intval($row['id'])] = $row['name'];
                    }
                    $res->free();
                }
            }

            $productsHtml = '';
            $itemsTotal = 0;
            foreach ($data['products'] as $item) {
                $pId = isset($item['id']) ? intval($item['id']) : 0;
                $rawName = $productNames[$pId] ?? ($item['name'] ?? ($item['title'] ?? 'Sản phẩm'));
                $pName = htmlspecialchars($rawName);
                $qty = intval($item['quantity'] ?? 1);
                $price = intval($item['price'] ?? 0);
                $subtotal = $qty * $price;
                $itemsTotal += $subtotal;

                $productsHtml .= '<tr>
                    <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;">' . $pName . '</td>
                    <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:center;">' . $qty . '</td>
                    <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:right;">' . number_format($price) . '₫</td>
                    <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:right;">' . number_format($subtotal) . '₫</td>
                </tr>';
            }

            // chuẩn hóa dữ liệu hiển thị
            $customerName = htmlspecialchars($data['fullName'] ?? '');
            $phoneEsc = htmlspecialchars($data['phone'] ?? '');
            $addressEsc = htmlspecialchars($data['address'] ?? '');
            $amountFormatted = number_format(intval($data['total'])) . '₫';
            $paymentMethodEsc = htmlspecialchars($data['payment_method'] ?? '');
            $orderLink = rtrim($mailConfig['frontend_base'], '/') . "/orders/{$code}";

            $body = <<<HTML
            <div style="font-family:'Inter',Roboto,Arial,sans-serif;color:#111;max-width:680px;margin:0 auto;background:#f9fafc;">
                <!-- Header -->
                <div style="background:linear-gradient(90deg,#8b5cf6,#d946ef,#06b6d4);padding:24px 20px;border-radius:12px 12px 0 0;color:#fff;text-align:center;box-shadow:0 3px 6px rgba(0,0,0,0.08);">
                    <h1 style="margin:0;font-size:22px;letter-spacing:-0.5px;">VibeMarket</h1>
                    <p style="margin:6px 0 0;font-size:15px;opacity:.95;">Xác nhận đơn hàng của bạn</p>
                </div>

                <!-- Body -->
                <div style="background:#fff;border:1px solid #eceef3;border-top:0;padding:24px 20px;border-radius:0 0 12px 12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <p style="margin:0 0 8px;">Xin chào <strong>{$customerName}</strong>,</p>
                    <p style="margin:0 0 12px;line-height:1.6;">Cảm ơn bạn đã đặt hàng tại <strong>VibeMarket</strong>! Mã đơn hàng của bạn là <strong style="color:#8b5cf6;">{$code}</strong>.</p>

                    <!-- Shipping info -->
                    <div style="margin-bottom:16px;">
                    <strong style="font-size:15px;">Thông tin giao hàng</strong>
                    <div style="color:#374151;font-size:14px;margin-top:6px;line-height:1.6;">
                        <div>Số điện thoại: {$phoneEsc}</div>
                        <div>Địa chỉ: {$addressEsc}</div>
                    </div>
                    </div>

                    <!-- Product list -->
                    <div style="margin:16px 0;">
                    <strong style="font-size:15px;">Chi tiết sản phẩm</strong>
                    <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:14px;">
                        <thead>
                        <tr style="text-align:left;color:#6b7280;font-size:13px;border-bottom:1px solid #eee;">
                            <th style="padding:8px 6px 8px 0;">Sản phẩm</th>
                            <th style="padding:8px 6px;text-align:center;">SL</th>
                            <th style="padding:8px 6px;text-align:right;">Giá</th>
                            <th style="padding:8px 6px;text-align:right;">Tổng</th>
                        </tr>
                        </thead>
                        <tbody>
                        {$productsHtml}
                        </tbody>
                    </table>
                    </div>

                    <!-- Total & payment -->
                    <div style="margin-top:16px; font-size:15px;">
                        <div style="color:#6b7280;">Phương thức thanh toán: <strong>Thanh toán tiền mặt</strong></div>
                        <div style="font-weight:700; font-size:17px; color:#d946ef; margin-top:6px;">Tổng thanh toán: {$amountFormatted}</div>
                    </div>


                    <!-- Button -->
                    <div style="text-align:center;margin-top:22px;">
                    <a href="{$orderLink}" style="display:inline-block;padding:12px 22px;background:linear-gradient(90deg,#8b5cf6,#d946ef,#06b6d4);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;box-shadow:0 3px 10px rgba(139,92,246,0.3);transition:all .2s ease;">
                        Xem chi tiết đơn hàng
                    </a>
                    </div>

                    <!-- Footer -->
                    <p style="margin:24px 0 0;color:#6b7280;font-size:13px;text-align:center;line-height:1.5;">
                    Cảm ơn bạn đã tin tưởng <strong>VibeMarket</strong>!<br>
                    Chúc bạn một ngày thật rực rỡ 💜
                    </p>
                </div>
                </div>
            HTML;

            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace("</p>", "\n", $body));
            $mail->send();
        } catch (Exception $e) {
            error_log("Order mail error for {$code}: " . $e->getMessage());
            // không rollback order vì lỗi gửi mail
        }
    }

    echo json_encode(['success' => true, 'order_id' => $order_id, 'code' => $code, 'id' => $order_id]);
} catch (Exception $e) {
    $conn->rollback();
    file_put_contents('order_error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi tạo đơn hàng', 'error' => $e->getMessage()]);
}
$conn->close();