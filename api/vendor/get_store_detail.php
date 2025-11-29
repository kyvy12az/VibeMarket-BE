<?php
// filepath: c:\xampp\htdocs\VIBE_MARKET_BACKEND\VibeMarket-BE\api\vendor\get_store_detail.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Disable HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering
ob_start();

try {
    // Include database and helper
    $config_path = '../../config/database.php';
    $helper_path = '../../config/url_helper.php';
    
    if (!file_exists($config_path)) {
        throw new Exception("Database configuration file not found at: " . $config_path);
    }
    
    if (!file_exists($helper_path)) {
        throw new Exception("URL helper file not found at: " . $helper_path);
    }
    
    require_once $config_path;
    require_once $helper_path;
    
    if (!isset($conn)) {
        throw new Exception("Database connection failed - \$conn is not set");
    }

    $seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;

    if (!$seller_id) {
        throw new Exception("Thiếu seller_id parameter");
    }

    // Lấy thông tin cửa hàng
    $stmt = $conn->prepare("
        SELECT 
            s.seller_id, 
            s.store_name, 
            s.avatar, 
            s.cover_image,
            s.business_type, 
            s.phone, 
            s.email, 
            s.business_address, 
            s.establish_year,
            s.description,
            s.total_revenue,
            s.created_at,
            u.name as owner_name
        FROM seller s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.seller_id = ? AND s.status = 'approved'
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $seller_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database query execution failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Cửa hàng với ID {$seller_id} không tồn tại hoặc chưa được duyệt");
    }

    $store = $result->fetch_assoc();
    $stmt->close();

    // Lấy số lượng sản phẩm
    $stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products WHERE seller_id = ? AND status = 'active'");
    if (!$stmt) {
        throw new Exception("Failed to prepare product count query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_count = intval($result->fetch_assoc()['total_products']);
    $stmt->close();

    // Lấy rating trung bình từ sản phẩm
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM products WHERE seller_id = ? AND status = 'active' AND rating > 0");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rating_data = $result->fetch_assoc();
    $avg_rating = $rating_data['avg_rating'] ? round(floatval($rating_data['avg_rating']), 1) : 0;
    $total_reviews = intval($rating_data['total']);
    $stmt->close();

    // Lấy danh sách sản phẩm của cửa hàng (limit 12)
    // FIX: Không JOIN với bảng categories, lấy trực tiếp từ cột category
    $stmt = $conn->prepare("
        SELECT 
            id as product_id,
            name as product_name,
            price,
            sale_price as discount_price,
            image,
            quantity as stock_quantity,
            category as category_name,
            rating,
            sold
        FROM products
        WHERE seller_id = ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 12
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare products query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $image_url = null;
        if ($row['image']) {
            $images = json_decode($row['image'], true);
            if (is_array($images) && count($images) > 0) {
                $image_url = getFullImageUrl('products/' . $images[0]);
            }
        }
        
        $products[] = [
            'product_id' => intval($row['product_id']),
            'product_name' => $row['product_name'],
            'price' => floatval($row['price']),
            'discount_price' => $row['discount_price'] ? floatval($row['discount_price']) : null,
            'image' => $image_url,
            'stock_quantity' => intval($row['stock_quantity'] ?? 0),
            'category_name' => $row['category_name'] ?? 'Chưa phân loại',
            'rating' => floatval($row['rating'] ?? 0),
            'sold' => intval($row['sold'] ?? 0)
        ];
    }
    $stmt->close();

    // Xử lý URLs - sử dụng helper function
    $avatar_url = null;
    $cover_url = null;
    
    if ($store['avatar']) {
        $avatar_url = getFullImageUrl('store_avatars/' . $store['avatar']);
    }
    
    if ($store['cover_image']) {
        $cover_url = getFullImageUrl('store_covers/' . $store['cover_image']);
    }

    // Clear output buffer
    ob_clean();

    // Close connection
    $conn->close();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'store' => [
            'seller_id' => intval($store['seller_id']),
            'store_name' => $store['store_name'],
            'avatar' => $avatar_url,
            'cover_image' => $cover_url,
            'business_type' => $store['business_type'],
            'phone' => $store['phone'],
            'email' => $store['email'],
            'business_address' => $store['business_address'],
            'establish_year' => $store['establish_year'] ? intval($store['establish_year']) : null,
            'description' => $store['description'],
            'owner_name' => $store['owner_name'],
            'created_at' => $store['created_at'],
            'total_products' => $product_count,
            'avg_rating' => floatval($avg_rating),
            'total_reviews' => intval($total_reviews)
        ],
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Clear output buffer
    ob_clean();
    
    // Log error with more details
    error_log("Store detail error for seller_id {$seller_id}: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Close connection if exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'seller_id' => $seller_id ?? 'not set',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Error $e) {
    // Handle fatal errors
    ob_clean();
    error_log("Store detail fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// End output buffering
ob_end_flush();
?>