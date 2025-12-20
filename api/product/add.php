<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

int_headers();

// Lấy dữ liệu từ body JSON
$data = json_decode(file_get_contents("php://input"), true);

// Helper function để normalize đường dẫn ảnh
function normalizeImagePath($imagePath) {
    if (empty($imagePath)) {
        return $imagePath;
    }
    
    // Nếu là URL đầy đủ, extract path
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        $parsed = parse_url($imagePath);
        $path = $parsed['path'] ?? '';
        
        // Tìm phần sau /VIBE_MARKET_BACKEND/VibeMarket-BE/
        if (strpos($path, '/VIBE_MARKET_BACKEND/VibeMarket-BE/') !== false) {
            $path = substr($path, strpos($path, '/VIBE_MARKET_BACKEND/VibeMarket-BE/') + strlen('/VIBE_MARKET_BACKEND/VibeMarket-BE'));
        }
        
        // Nếu đã có /uploads/products/ thì giữ nguyên
        if (strpos($path, '/uploads/products/') === 0) {
            return $path;
        }
        
        // Nếu có uploads/products/ (không có / đầu) thì thêm /
        if (strpos($path, 'uploads/products/') === 0) {
            return '/' . $path;
        }
        
        // Còn lại extract tên file
        $imagePath = basename($path);
    }
    
    // Nếu đã có /uploads/products/ hoặc uploads/products/ thì chuẩn hóa
    if (strpos($imagePath, '/uploads/products/') === 0) {
        return $imagePath;
    }
    if (strpos($imagePath, 'uploads/products/') === 0) {
        return '/' . $imagePath;
    }
    
    // Chỉ còn tên file -> thêm prefix
    return '/uploads/products/' . ltrim($imagePath, '/');
}

// Nếu image là mảng => normalize từng ảnh và convert sang JSON string
if (isset($data['image']) && is_array($data['image'])) {
    $normalizedImages = array_map('normalizeImagePath', $data['image']);
    $data['image'] = json_encode($normalizedImages, JSON_UNESCAPED_UNICODE);
} elseif (isset($data['image']) && is_string($data['image'])) {
    // Nếu là string, check xem có phải JSON không
    $decoded = json_decode($data['image'], true);
    if (is_array($decoded)) {
        // Là JSON array -> normalize từng ảnh
        $normalizedImages = array_map('normalizeImagePath', $decoded);
        $data['image'] = json_encode($normalizedImages, JSON_UNESCAPED_UNICODE);
    } else {
        // Là string đơn -> normalize và wrap trong array
        $normalized = normalizeImagePath($data['image']);
        $data['image'] = json_encode([$normalized], JSON_UNESCAPED_UNICODE);
    }
}

// Kiểm tra dữ liệu bắt buộc
if (!isset($data['name'], $data['price'], $data['originalPrice'], $data['image'], $data['rating'], $data['sold'], $data['discount'], $data['isLive'], $data['seller_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu sản phẩm']);
    exit;
}

// Nếu không có dữ liệu flash_sale thì gán giá trị mặc định là 0
if (!isset($data['flash_sale'])) $data['flash_sale'] = 0;

// Chuẩn bị câu lệnh insert
$sql = "INSERT INTO products (
    name, price, original_price, image, rating, sold, discount, is_live, 
    seller_name, seller_avatar, sku, description, category, brand, tags, 
    weight, length, width, height, material, cost, sale_price, sale_quantity, 
    meta_title, meta_description, slug, keywords, quantity, low_stock, 
    shipping_fee, status, visibility, release_date, seller_id, colors, sizes, origin, flash_sale
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi chuẩn bị câu lệnh']);
    exit;
}

// Bind params
$stmt->bind_param(
    "siisdiiisssssssidddsiiissssiiisssisssi",
    $data['name'],                       // s
    $data['price'],                      // i
    $data['originalPrice'],              // i
    $data['image'],                      // s
    $data['rating'],                     // d (float)
    $data['sold'],                       // i
    $data['discount'],                   // i
    $data['isLive'],                     // i (tinyint)
    $data['seller_name'],                // s
    $data['seller_avatar'],              // s
    $data['sku'],                        // s
    $data['description'],                // s
    $data['category'],                   // s
    $data['brand'],                      // s
    $data['tags'],                       // s
    $data['weight'],                     // i
    $data['length'],                     // d
    $data['width'],                      // d
    $data['height'],                     // d
    $data['material'],                   // s
    $data['cost'],                       // i
    $data['sale_price'],                 // i
    $data['sale_quantity'],              // i
    $data['meta_title'],                 // s
    $data['meta_description'],           // s
    $data['slug'],                       // s
    $data['keywords'],                   // s
    $data['quantity'],                   // i
    $data['low_stock'],                  // i
    $data['shipping_fee'],               // i
    $data['status'],                     // s
    $data['visibility'],                 // s
    $data['release_date'],               // s (datetime dạng string: "2025-10-02 14:30:00")
    $data['seller_id'],                   // i
    $data['colors'],                     // s
    $data['sizes'],                      // s   
    $data['origin'],                      // s
    $data['flash_sale']                  // i (tinyint)
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Thêm sản phẩm thành công',
        'id' => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm sản phẩm', 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
