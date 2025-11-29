<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
$new_status = isset($input['status']) ? $input['status'] : '';

if (!$user_id || !$order_id || !$new_status) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
    exit;
}

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Trạng thái không hợp lệ']);
    exit;
}

// Lấy seller_id
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

// Kiểm tra xem order có thuộc seller này không
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM order_items 
    WHERE order_id = ? AND seller_id = ?
");
$stmt->bind_param("ii", $order_id, $seller_id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count == 0) {
    echo json_encode(['success' => false, 'error' => 'Không có quyền cập nhật đơn hàng này']);
    exit;
}

// Lấy trạng thái hiện tại của đơn hàng
$stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($current_status);
$stmt->fetch();
$stmt->close();

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // Update status đơn hàng
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Không thể cập nhật trạng thái đơn hàng: ' . $stmt->error);
    }
    $stmt->close();

    // Nếu chuyển sang trạng thái "delivered" và trạng thái cũ không phải "delivered"
    // thì cập nhật số lượng đã bán
    if ($new_status === 'delivered' && $current_status !== 'delivered') {
        // Lấy danh sách sản phẩm trong đơn hàng của seller
        $stmt = $conn->prepare("
            SELECT product_id, quantity 
            FROM order_items 
            WHERE order_id = ? AND seller_id = ?
        ");
        $stmt->bind_param("ii", $order_id, $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updated_products = [];
        
        while ($row = $result->fetch_assoc()) {
            $product_id = $row['product_id'];
            $quantity = $row['quantity'];
            
            // Cập nhật số lượng đã bán
            $update_stmt = $conn->prepare("
                UPDATE products 
                SET sold = sold + ? 
                WHERE id = ?
            ");
            $update_stmt->bind_param("ii", $quantity, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Không thể cập nhật số lượng đã bán cho sản phẩm ID: ' . $product_id);
            }
            
            $updated_products[] = [
                'product_id' => $product_id,
                'quantity_sold' => $quantity
            ];
            
            $update_stmt->close();
        }
        $stmt->close();
    }
    
    // Nếu chuyển từ "delivered" về trạng thái khác (ví dụ: hoàn hàng)
    // thì trừ số lượng đã bán
    if ($current_status === 'delivered' && $new_status !== 'delivered') {
        $stmt = $conn->prepare("
            SELECT product_id, quantity 
            FROM order_items 
            WHERE order_id = ? AND seller_id = ?
        ");
        $stmt->bind_param("ii", $order_id, $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $product_id = $row['product_id'];
            $quantity = $row['quantity'];
            
            // Trừ số lượng đã bán (không cho âm)
            $update_stmt = $conn->prepare("
                UPDATE products 
                SET sold = GREATEST(0, sold - ?) 
                WHERE id = ?
            ");
            $update_stmt->bind_param("ii", $quantity, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Không thể cập nhật số lượng đã bán cho sản phẩm ID: ' . $product_id);
            }
            
            $update_stmt->close();
        }
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Cập nhật trạng thái đơn hàng thành công',
        'order_id' => $order_id,
        'old_status' => $current_status,
        'new_status' => $new_status
    ];
    
    // Thêm thông tin sản phẩm đã cập nhật nếu có
    if (isset($updated_products) && !empty($updated_products)) {
        $response['updated_products'] = $updated_products;
        $response['message'] .= '. Đã cập nhật số lượng đã bán cho ' . count($updated_products) . ' sản phẩm.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();