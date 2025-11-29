<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once '../../../config/database.php';

$mailConfig = require_once __DIR__ . '/../../../config/mail.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Ho_Chi_Minh');
$vnp_HashSecret = "80T1F6UGLDVDQY5A152975JEUE3B532C";

// Lấy các tham số từ VNPay redirect
$vnpData = [];
foreach ($_GET as $k => $v) {
    if (substr($k, 0, 4) === 'vnp_') $vnpData[$k] = $v;
}

$vnpSecureHash = isset($vnpData['vnp_SecureHash']) ? $vnpData['vnp_SecureHash'] : '';
if (isset($vnpData['vnp_SecureHash'])) unset($vnpData['vnp_SecureHash']);
if (isset($vnpData['vnp_SecureHashType'])) unset($vnpData['vnp_SecureHashType']);

ksort($vnpData);
$i = 0;
$hashData = "";
foreach ($vnpData as $key => $value) {
    if ($i == 1) {
        $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}
$secureHashComputed = hash_hmac('sha512', $hashData, $vnp_HashSecret);

// So sánh chữ ký
if (!hash_equals(strtolower($secureHashComputed), strtolower($vnpSecureHash))) {
    error_log("VNPay callback: invalid hash for txn " . ($vnpData['vnp_TxnRef'] ?? ''));
    // Redirect về trang thất bại FE
    header('Location: http://localhost:8080/callback/vnpay?status=fail&code=' . urlencode($vnpData['vnp_TxnRef'] ?? ''));
    exit;
}

// Lưu giao dịch vào DB nếu chưa có (idempotency)
$txn_ref = $vnpData['vnp_TxnRef'] ?? '';
$order_code = $txn_ref;
$amount_vnd = isset($vnpData['vnp_Amount']) ? intval($vnpData['vnp_Amount']) / 100 : 0;
$bank_code = $vnpData['vnp_BankCode'] ?? null;
$bank_tran_no = $vnpData['vnp_BankTranNo'] ?? null;
$card_type = $vnpData['vnp_CardType'] ?? null;
$response_code = $vnpData['vnp_ResponseCode'] ?? '';
$response_message = $vnpData['vnp_Message'] ?? $response_code;
$payment_time = null;
if (!empty($vnpData['vnp_PayDate'])) {
    $t = DateTime::createFromFormat('YmdHis', $vnpData['vnp_PayDate']);
    if ($t) $payment_time = $t->format('Y-m-d H:i:s');
}
$client_ip = $_SERVER['REMOTE_ADDR'] ?? null;
$raw_json = json_encode($_GET);

$exists_stmt = $conn->prepare("SELECT id FROM vnpay_transactions WHERE txn_ref = ? LIMIT 1");
$exists_stmt->bind_param("s", $txn_ref);
$exists_stmt->execute();
$exists_stmt->store_result();
if ($exists_stmt->num_rows === 0) {
    $exists_stmt->close();

    // Tìm order_id nếu có
    $order_id = null;
    $lookup = $conn->prepare("SELECT id FROM orders WHERE code = ? LIMIT 1");
    if ($lookup) {
        $lookup->bind_param("s", $order_code);
        $lookup->execute();
        $lookup->bind_result($found_id);
        if ($lookup->fetch()) $order_id = $found_id;
        $lookup->close();
    }

    $insert_sql = "INSERT INTO vnpay_transactions
        (txn_ref, order_code, order_id, amount, bank_code, bank_tran_no, card_type, response_code, response_message, secure_hash, ip_addr, payment_time, raw_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param(
        "ssiisssssssss",
        $txn_ref,
        $order_code,
        $order_id,
        $amount_vnd,
        $bank_code,
        $bank_tran_no,
        $card_type,
        $response_code,
        $response_message,
        $vnpSecureHash,
        $client_ip,
        $payment_time,
        $raw_json
    );
    $stmt->execute();
    $stmt->close();
} else {
    $exists_stmt->close();
}

// Cập nhật trạng thái thanh toán trong bảng orders
if ($order_code) {
    if ($response_code === '00') {
        // Thanh toán thành công
        $update = $conn->prepare("UPDATE orders SET payment_status = 'paid', payment_transaction_id = ?, payment_paid_at = NOW() WHERE code = ?");
        $update->bind_param("ss", $vnpData['vnp_TransactionNo'], $order_code);
        $update->execute();
        $update->close();
    } else {
        // Thanh toán thất bại
        $update = $conn->prepare("UPDATE orders SET payment_status = 'unpaid', payment_transaction_id = ?, payment_paid_at = NULL WHERE code = ?");
        $update->bind_param("ss", $vnpData['vnp_TransactionNo'], $order_code);
        $update->execute();
        $update->close();
    }
}

// Fetch email, customer name, phone, address, total and order_id in one query
$orderEmail = null;
$orderName = null;
$orderPhone = null;
$orderAddress = null;
$orderTotal = 0;
$db_order_id = null;
if ($order_code) {
    $q = $conn->prepare("SELECT id, email, customer_name, phone, address, total FROM orders WHERE code = ? LIMIT 1");
    if ($q) {
        $q->bind_param("s", $order_code);
        $q->execute();
        $q->bind_result($db_order_id, $orderEmail, $orderName, $orderPhone, $orderAddress, $orderTotal);
        $q->fetch();
        $q->close();
    }
}

// Build products HTML by querying order_items + products if we have order_id
$productsHtml = '';
if ($db_order_id) {
    $it = $conn->prepare("SELECT oi.quantity, oi.price, COALESCE(p.name, oi.product_id) as product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    if ($it) {
        $it->bind_param("i", $db_order_id);
        $it->execute();
        $it->bind_result($pqty, $pprice, $pname);
        while ($it->fetch()) {
            $pnameEsc = htmlspecialchars($pname);
            $subtotal = intval($pqty) * intval($pprice);
            $productsHtml .= '<tr>
                <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;">' . $pnameEsc . '</td>
                <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:center;">' . intval($pqty) . '</td>
                <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:right;">' . number_format($pprice) . '₫</td>
                <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:right;">' . number_format($subtotal) . '₫</td>
            </tr>';
        }
        $it->close();
    }
} else {
    // fallback: if no order_id, try to use raw_json to show something (optional)
}

// Send email notifying status change (send only on success)
if ($orderEmail && $response_code === '00') {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $mailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->SMTPSecure = $mailConfig['secure'];
        $mail->Port = $mailConfig['port'];

        // Encoding / charset to avoid broken Vietnamese characters
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($orderEmail, $orderName ?? '');

        $mail->isHTML(true);
        $mail->Subject = "Thanh toán thành công cho đơn {$order_code}";

        // Inline styles + font-family fallback (email clients thường không tải webfonts)
        $formattedAmount = number_format($amount_vnd) . "₫";
        $transactionNo = htmlspecialchars($vnpData['vnp_TransactionNo'] ?? '');
        $orderLink = rtrim($mailConfig['frontend_base'], '/') . "/orders/{$order_code}";
        $phoneEsc = htmlspecialchars($orderPhone ?? '');
        $addressEsc = htmlspecialchars($orderAddress ?? '');
        $totalEsc = number_format(intval($orderTotal ?: $amount_vnd)) . '₫';

        $mailBody = '
        <div style="font-family: Inter, Roboto, Arial, sans-serif; color:#111; line-height:1.6; max-width:700px; margin:0 auto; background:#f9fafb;">
        <!-- Header -->
        <div style="background:linear-gradient(90deg,#a855f7,#ec4899,#06b6d4); padding:22px 20px; color:#fff; border-radius:10px 10px 0 0; text-align:center;">
            <h2 style="margin:0;font-size:22px;font-weight:700;">🎉 Thanh toán thành công!</h2>
            <p style="margin:6px 0 0;font-size:14px;opacity:.95;">Cảm ơn bạn đã mua sắm tại <strong>VibeMarket</strong></p>
        </div>

        <!-- Content -->
        <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 10px 10px;box-shadow:0 2px 6px rgba(0,0,0,0.05);">
            
            <!-- Greeting -->
            <p>Chào <strong>' . htmlspecialchars($orderName ?? '') . '</strong>,</p>
            <p>Đơn hàng <strong>' . htmlspecialchars($order_code) . '</strong> đã được thanh toán thành công 🎉</p>

            <!-- Shipping Info -->
            <div style="margin:18px 0;">
            <strong style="font-size:15px;color:#374151;">📦 Thông tin giao hàng</strong>
            <div style="color:#4b5563;margin-top:6px;font-size:14px;">
                <div>📞 Số điện thoại: ' . $phoneEsc . '</div>
                <div>🏠 Địa chỉ: ' . $addressEsc . '</div>
            </div>
            </div>

            <!-- Product Details -->
            <div style="margin-top:16px;">
            <strong style="font-size:15px;color:#374151;">🛍️ Chi tiết sản phẩm</strong>
            <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:14px;">
                <thead>
                <tr style="text-align:left; color:#6b7280; font-size:13px; border-bottom:1px solid #e5e7eb;">
                    <th style="padding:8px 6px;">Sản phẩm</th>
                    <th style="padding:8px 6px;text-align:center;">SL</th>
                    <th style="padding:8px 6px;text-align:right;">Giá</th>
                    <th style="padding:8px 6px;text-align:right;">Tổng</th>
                </tr>
                </thead>
                <tbody>' . $productsHtml . '</tbody>
            </table>
            </div>

            <!-- Payment Info -->
            <div style="margin-top:18px; border-top:1px solid #f1f5f9; padding-top:12px; font-size:15px;">
            <div style="color:#6b7280;">Mã giao dịch: <strong>' . $transactionNo . '</strong></div>
            <div style="margin-top:8px; font-weight:700; font-size:17px; color:#d946ef;">Tổng thanh toán: ' . $totalEsc . '</div>
            </div>

            <!-- Order Link -->
            <div style="text-align:center; margin-top:22px;">
            <a href="' . $orderLink . '" style="display:inline-block; padding:12px 24px; background:linear-gradient(90deg,#a855f7,#ec4899,#06b6d4); color:#fff; border-radius:8px; text-decoration:none; font-weight:600;">
                🔗 Xem chi tiết đơn hàng
            </a>
            </div>

            <!-- Footer -->
            <p style="color:#6b7280; font-size:13px; margin-top:22px; text-align:center;">
            Cảm ơn bạn đã mua sắm tại <strong>VibeMarket</strong>.<br/>
            Nếu không phải bạn thực hiện giao dịch, vui lòng liên hệ hỗ trợ ngay.
            </p>
        </div>
        </div>

        ';

        $logoPath = __DIR__ . '/../../../assets/email/logo.png';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo_cid', 'logo.png');
        }
        // chèn <img src="cid:logo_cid" ...> ở đầu $mailBody
        $mail->Body = $mailBody;
        $mail->AltBody = strip_tags(str_replace('</p>', "\n", $mailBody));
        $mail->send();
    } catch (Exception $e) {
        error_log("VNPay status mail error for {$order_code}: " . $e->getMessage());
    }
}

// Redirect về FE (chuyển đúng route FE của bạn)
if ($response_code === '00') {
    header('Location: http://localhost:8080/callback/vnpay?vnp_TxnRef=' . urlencode($txn_ref) . '&status=success'
        . '&amount=' . urlencode($amount_vnd)
        . '&bank=' . urlencode($bank_code)
        . '&payDate=' . urlencode($payment_time)
        . '&transactionNo=' . urlencode($vnpData['vnp_TransactionNo'] ?? '')
    );
    exit;
} else {
    header('Location: http://localhost:8080/callback/vnpay?vnp_TxnRef=' . urlencode($txn_ref) . '&status=fail');
    exit;
}