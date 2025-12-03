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
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['app_trans_id'])) {
        throw new Exception('Missing app_trans_id');
    }
    
    $app_trans_id = $input['app_trans_id'];
    $update_status = $input['update_status'] ?? false;
    
    error_log("=== ZALOPAY VERIFY REQUEST ===");
    error_log("app_trans_id: $app_trans_id");
    error_log("update_status: " . ($update_status ? 'YES' : 'NO'));
    
    // Debug: Check zalopay_transactions table structure
    $tableCheck = $conn->query("DESCRIBE zalopay_transactions");
    if ($tableCheck) {
        error_log("=== ZALOPAY_TRANSACTIONS TABLE STRUCTURE (verify.php) ===");
        while ($row = $tableCheck->fetch_assoc()) {
            error_log("Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}");
        }
    } else {
        error_log("WARNING: zalopay_transactions table does not exist!");
    }
    
    // Kiểm tra trạng thái giao dịch trong database
    $stmt = $conn->prepare("SELECT * FROM zalopay_transactions WHERE app_trans_id = ?");
    $stmt->bind_param("s", $app_trans_id);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    
    error_log("Transaction lookup - app_trans_id: $app_trans_id");
    error_log("Transaction found: " . ($transaction ? "YES" : "NO"));
    if ($transaction) {
        error_log("Transaction data: " . json_encode($transaction));
    }
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Nếu được yêu cầu update status và user vừa quay lại từ ZaloPay (status = pending)
    if ($update_status && $transaction['status'] === 'pending') {
        error_log("Updating transaction status to success for: $app_trans_id");
        
        // Tách orderCode từ app_trans_id
        $parts = explode('_', $app_trans_id);
        $orderCode = $parts[1] ?? null;
        
        if ($orderCode) {
            // Cập nhật trạng thái đơn hàng
            $updateOrderStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE code = ? AND customer_id = ?");
            $updateOrderStmt->bind_param("si", $orderCode, $user_id);
            $updateOrderStmt->execute();
            error_log("Order payment_status updated - Rows affected: " . $updateOrderStmt->affected_rows);
            
            // Cập nhật trạng thái giao dịch
            $updateTransStmt = $conn->prepare("UPDATE zalopay_transactions SET status = 'success', updated_at = NOW() WHERE app_trans_id = ?");
            $updateTransStmt->bind_param("s", $app_trans_id);
            $updateTransStmt->execute();
            error_log("Transaction status updated - Rows affected: " . $updateTransStmt->affected_rows);
            
            // Reload transaction data
            $stmt = $conn->prepare("SELECT * FROM zalopay_transactions WHERE app_trans_id = ?");
            $stmt->bind_param("s", $app_trans_id);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
        }
    }
    
    // Tách orderCode từ app_trans_id
    $parts = explode('_', $app_trans_id);
    $orderCode = $parts[1] ?? null;
    
    if ($orderCode) {
        // Lấy thông tin đơn hàng
        $stmt = $conn->prepare("SELECT * FROM orders WHERE code = ? AND customer_id = ?");
        $stmt->bind_param("si", $orderCode, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            "success" => true,
            "transaction" => [
                "app_trans_id" => $transaction['app_trans_id'],
                "status" => $transaction['status'],
                "amount" => $transaction['amount'],
                "created_at" => $transaction['created_at'],
                "updated_at" => $transaction['updated_at']
            ],
            "order" => $order ? [
                "code" => $order['code'],
                "payment_status" => $order['payment_status'],
                "status" => $order['status'],
                "total" => $order['total']
            ] : null
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "transaction" => [
                "app_trans_id" => $transaction['app_trans_id'],
                "status" => $transaction['status'],
                "amount" => $transaction['amount'],
                "created_at" => $transaction['created_at'],
                "updated_at" => $transaction['updated_at']
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
