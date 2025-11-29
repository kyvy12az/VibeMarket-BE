<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_end_clean();

try {
    require_once '../../config/database.php';

    $product_id = $_GET['product_id'] ?? null;
    
    if (!$product_id) {
        throw new Exception('Thiếu product_id');
    }
    
    // Kiểm tra connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Không thể kết nối database');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            pr.id,
            pr.rating,
            pr.comment,
            pr.images,
            pr.created_at,
            u.fullname as user_name,
            u.avatar_url
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ?
        ORDER BY pr.created_at DESC
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $product_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $images = [];
        if (!empty($row['images'])) {
            $images = json_decode($row['images'], true) ?? [];
        }
        
        $reviews[] = [
            'id' => (int)$row['id'],
            'rating' => (float)$row['rating'],
            'comment' => $row['comment'],
            'images' => $images,
            'created_at' => $row['created_at'],
            'user_name' => $row['user_name'],
            'avatar_url' => $row['avatar_url'] ?? ''
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'count' => count($reviews)
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