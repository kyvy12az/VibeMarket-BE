<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_end_clean();

try {
    require_once '../../config/database.php';

    // Xử lý multipart/form-data hoặc JSON
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'multipart/form-data') !== false) {
        // Upload có file ảnh
        $user_id = $_POST['user_id'] ?? null;
        $product_id = $_POST['product_id'] ?? null;
        $order_id = $_POST['order_id'] ?? null;
        $rating = $_POST['rating'] ?? null;
        $comment = $_POST['comment'] ?? '';
        
        // Xử lý upload ảnh
        $image_urls = [];
        if (isset($_FILES['images'])) {
            $upload_dir = '../../uploads/reviews/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $files = $_FILES['images'];
            $file_count = is_array($files['name']) ? count($files['name']) : 1;
            
            for ($i = 0; $i < $file_count && $i < 5; $i++) {
                if (is_array($files['name'])) {
                    $file_name = $files['name'][$i];
                    $file_tmp = $files['tmp_name'][$i];
                    $file_error = $files['error'][$i];
                } else {
                    $file_name = $files['name'];
                    $file_tmp = $files['tmp_name'];
                    $file_error = $files['error'];
                }
                
                if ($file_error === UPLOAD_ERR_OK) {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_ext, $allowed)) {
                        $new_filename = uniqid('review_', true) . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $image_urls[] = '/uploads/reviews/' . $new_filename;
                        }
                    }
                }
            }
        }
        
        $images_json = !empty($image_urls) ? json_encode($image_urls) : null;
        
    } else {
        // JSON request
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            throw new Exception('Invalid JSON data');
        }
        
        $user_id = $data['user_id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $order_id = $data['order_id'] ?? null;
        $rating = $data['rating'] ?? null;
        $comment = $data['comment'] ?? '';
        $images_json = isset($data['images']) ? json_encode($data['images']) : null;
    }
    
    if (!$user_id || !$product_id || !$order_id || !$rating) {
        throw new Exception('Thiếu thông tin bắt buộc');
    }
    
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Đánh giá phải từ 1 đến 5 sao');
    }
    
    // Kiểm tra connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Không thể kết nối database');
    }
    
    // Kiểm tra đơn hàng đã giao và thuộc về user
    $stmt = $conn->prepare("
        SELECT o.id, oi.seller_id 
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? 
          AND o.customer_id = ? 
          AND oi.product_id = ?
          AND o.status = 'delivered'
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("iii", $order_id, $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Đơn hàng không hợp lệ hoặc chưa được giao');
    }
    
    $order_data = $result->fetch_assoc();
    $seller_id = $order_data['seller_id'];
    $stmt->close();
    
    // Kiểm tra đã đánh giá chưa
    $stmt = $conn->prepare("
        SELECT id FROM product_reviews 
        WHERE user_id = ? AND product_id = ? AND seller_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("iii", $user_id, $product_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        throw new Exception('Bạn đã đánh giá sản phẩm này rồi');
    }
    $stmt->close();
    
    // Thêm đánh giá với ảnh
    $stmt = $conn->prepare("
        INSERT INTO product_reviews (product_id, seller_id, user_id, rating, comment, images)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("iiiiss", $product_id, $seller_id, $user_id, $rating, $comment, $images_json);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }
    $stmt->close();
    
    // Cập nhật rating trung bình cho sản phẩm
    $stmt = $conn->prepare("
        UPDATE products 
        SET rating = (
            SELECT AVG(rating) FROM product_reviews WHERE product_id = ?
        )
        WHERE id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("ii", $product_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cảm ơn bạn đã đánh giá sản phẩm!',
        'images' => $image_urls ?? []
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