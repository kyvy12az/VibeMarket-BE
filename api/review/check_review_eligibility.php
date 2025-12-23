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
        // Normalize product image and pick the first image (support JSON array, CSV, or single path)
        $image_url = '';
        if (!empty($row['product_image'])) {
            // Helper: build base url including project folder when served under /<project>/api/...
            if (!function_exists('build_base_url')) {
                function build_base_url() {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $script = $_SERVER['SCRIPT_NAME'] ?? '';

                    if (false !== ($pos = strpos($script, '/api/'))) {
                        $projectBase = substr($script, 0, $pos);
                    } else {
                        $projectBase = dirname(dirname(dirname($script)));
                    }

                    $projectBase = rtrim($projectBase, '/');
                    return $protocol . '://' . $host . ($projectBase ? $projectBase : '');
                }
            }

            if (!function_exists('normalize_image_url')) {
                function normalize_image_url($img) {
                    $img = trim((string)($img ?? ''));
                    if ($img === '') return '';
                    $img = trim($img, "\"' \t\n\r");

                    $first = null;
                    if (isset($img[0]) && ($img[0] === '[' || $img[0] === '{')) {
                        $arr = json_decode($img, true);
                        if (is_array($arr) && count($arr) > 0) {
                            $first = reset($arr);
                        }
                    }

                    if ($first === null && strpos($img, ',') !== false) {
                        $parts = array_map('trim', explode(',', $img));
                        if (count($parts) > 0) $first = $parts[0];
                    }

                    if ($first === null) $first = $img;
                    $first = trim((string)$first, "\"' \t\n\r");
                    if ($first === '') return '';

                    if (strpos($first, 'http') === 0) return $first;

                    $firstPath = ltrim($first, '/');
                    $base = build_base_url();
                    if ($base === '') return 'http://localhost/' . $firstPath;
                    return rtrim($base, '/') . '/' . $firstPath;
                }
            }

            $image_url = normalize_image_url($row['product_image']);
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