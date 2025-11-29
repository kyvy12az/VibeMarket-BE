<?php
// Tắt tất cả output trước khi set headers
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Clear any buffered output
ob_end_clean();

try {
    require_once '../../config/database.php';

    $user_id = $_GET['user_id'] ?? null;
    $order_id = $_GET['order_id'] ?? null;
    
    if (!$user_id || !$order_id) {
        throw new Exception('Thiếu thông tin');
    }
    
    // Kiểm tra connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Không thể kết nối database');
    }
    
    // Lấy danh sách sản phẩm trong đơn hàng và kiểm tra đã review chưa
    $stmt = $conn->prepare("
        SELECT 
            oi.product_id,
            oi.seller_id,
            p.name as product_name,
            p.image as product_image,
            oi.price,
            (SELECT COUNT(*) FROM product_reviews 
             WHERE user_id = ? 
             AND product_id = oi.product_id 
             AND seller_id = oi.seller_id) as has_reviewed
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE o.id = ? 
          AND o.customer_id = ?
          AND o.status = 'delivered'
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare statement error: ' . $conn->error);
    }
    
    $stmt->bind_param("iii", $user_id, $order_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Parse JSON image array và lấy ảnh đầu tiên
        $image_url = '';
        if (!empty($row['product_image'])) {
            $images = json_decode($row['product_image'], true);
            if (is_array($images) && count($images) > 0) {
                $image_url = $images[0];
            }
        }
        
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'seller_id' => (int)$row['seller_id'],
            'product_name' => $row['product_name'],
            'image_url' => $image_url,
            'price' => (int)$row['price'],
            'has_reviewed' => (int)$row['has_reviewed'] > 0
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename(__FILE__),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}