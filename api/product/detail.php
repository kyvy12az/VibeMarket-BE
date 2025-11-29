<?php
// filepath: c:\xampp\htdocs\VIBE_MARKET_BACKEND\VibeMarket-BE\api\product\detail.php
require_once '../../config/database.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu id sản phẩm'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper function để xử lý product image URLs
function getProductImageUrls($imageJson) {
    if (empty($imageJson)) {
        return [];
    }
    
    $images = json_decode($imageJson, true);
    if (!is_array($images)) {
        return [];
    }
    
    $backend_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    
    return array_map(function($img) use ($backend_url) {
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
            return $img;
        }
        if (strpos($img, 'uploads/') === 0) {
            return $backend_url . '/' . $img;
        }
        if (strpos($img, '/uploads/') === 0) {
            return $backend_url . $img;
        }
        return $backend_url . '/uploads/products/' . $img;
    }, $images);
}

// Helper function để xử lý avatar URL
function getAvatarUrl($avatar) {
    if (empty($avatar)) {
        return null;
    }
    
    if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
        return $avatar;
    }
    
    $backend_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   '://' . $_SERVER['HTTP_HOST'] . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
    
    if (strpos($avatar, 'uploads/') === 0) {
        return $backend_url . '/' . $avatar;
    }
    if (strpos($avatar, '/uploads/') === 0) {
        return $backend_url . $avatar;
    }
    
    return $backend_url . '/uploads/store_avatars/' . $avatar;
}

try {
    $sql = "SELECT 
        p.*,
        p.quantity as initial_stock,
        p.sold as total_sold,
        (p.quantity - p.sold) as current_stock,
        s.store_name AS seller_name, 
        s.avatar AS seller_avatar
    FROM products p
    LEFT JOIN seller s ON p.seller_id = s.seller_id
    WHERE p.id = ? AND p.status = 'active'";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Tính tồn kho thực tế (không cho âm)
        $current_stock = max(0, (int)$row['current_stock']);
        
        // Xử lý mảng ảnh
        $imageUrls = getProductImageUrls($row['image']);
        
        // Xử lý tags, sizes, colors
        $tags = !empty($row['tags']) ? array_map('trim', explode(',', $row['tags'])) : [];
        $sizes = !empty($row['sizes']) ? array_map('trim', explode(',', $row['sizes'])) : [];
        $colors = !empty($row['colors']) ? array_map('trim', explode(',', $row['colors'])) : [];
        
        // Xử lý features - có thể từ field riêng hoặc từ description
        $features = [];
        if (!empty($row['features'])) {
            $features = array_map('trim', explode('|', $row['features']));
        } else if (!empty($row['description'])) {
            // Tách description thành features nếu không có field features
            $desc_lines = explode("\n", $row['description']);
            foreach ($desc_lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 10 && count($features) < 5) {
                    $features[] = $line;
                }
            }
        }
        
        // Nếu vẫn không có features, thêm mặc định
        if (empty($features)) {
            $features = [
                'Sản phẩm chính hãng 100%',
                'Bảo hành đổi trả trong 7 ngày',
                'Miễn phí vận chuyển cho đơn từ 200.000đ'
            ];
        }
        
        // Tạo specifications object
        $specifications = [];
        
        if (!empty($row['sku'])) {
            $specifications['Mã SKU'] = $row['sku'];
        }
        
        if (!empty($row['brand'])) {
            $specifications['Thương hiệu'] = $row['brand'];
        }
        
        if (!empty($row['category'])) {
            $specifications['Danh mục'] = $row['category'];
        }
        
        if (!empty($row['material'])) {
            $specifications['Chất liệu'] = $row['material'];
        }
        
        if (!empty($row['origin'])) {
            $specifications['Xuất xứ'] = $row['origin'];
        }
        
        if (!empty($row['weight'])) {
            $specifications['Trọng lượng'] = $row['weight'] . 'g';
        }
        
        if (!empty($row['length']) || !empty($row['width']) || !empty($row['height'])) {
            $dimensions = [];
            if (!empty($row['length'])) $dimensions[] = $row['length'];
            if (!empty($row['width'])) $dimensions[] = $row['width'];
            if (!empty($row['height'])) $dimensions[] = $row['height'];
            $specifications['Kích thước (D×R×C)'] = implode(' × ', $dimensions) . ' cm';
        }
        
        if (!empty($colors)) {
            $specifications['Màu sắc'] = implode(', ', $colors);
        }
        
        if (!empty($sizes)) {
            $specifications['Kích cỡ có sẵn'] = implode(', ', $sizes);
        }

        // Xác định trạng thái tồn kho
        $stock_status = 'in_stock';
        if ($current_stock == 0) {
            $stock_status = 'out_of_stock';
        } elseif ($current_stock <= 5) {
            $stock_status = 'low_stock';
        }

        echo json_encode([
            'success' => true,
            'product' => [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => (int)$row['price'],
                'originalPrice' => !empty($row['original_price']) ? (int)$row['original_price'] : null,
                'discount' => (int)$row['discount'],
                'rating' => (float)$row['rating'],
                'sold' => (int)$row['total_sold'],
                'initialStock' => (int)$row['initial_stock'],
                'inStock' => $current_stock,
                'stockStatus' => $stock_status,
                'availableQuantity' => $current_stock,
                
                // Thông tin cơ bản
                'brand' => $row['brand'] ?? '',
                'category' => $row['category'] ?? '',
                'description' => $row['description'] ?? '',
                'features' => $features,
                
                // Media
                'images' => $imageUrls,
                
                // Variants
                'sizes' => $sizes,
                'colors' => $colors,
                'tags' => $tags,
                
                // Specifications (đầy đủ)
                'specifications' => $specifications,
                
                // Thông tin chi tiết (raw data cho frontend xử lý thêm)
                'sku' => $row['sku'] ?? '',
                'material' => $row['material'] ?? '',
                'origin' => $row['origin'] ?? '',
                'weight' => !empty($row['weight']) ? (int)$row['weight'] : null,
                'length' => !empty($row['length']) ? (float)$row['length'] : null,
                'width' => !empty($row['width']) ? (float)$row['width'] : null,
                'height' => !empty($row['height']) ? (float)$row['height'] : null,
                
                // Seller info
                'seller_id' => (int)$row['seller_id'],
                'seller_name' => $row['seller_name'] ?? '',
                'seller_avatar' => getAvatarUrl($row['seller_avatar']),
                
                // Shipping & Status
                'shipping_fee' => !empty($row['shipping_fee']) ? (int)$row['shipping_fee'] : 0,
                'status' => $row['status'] ?? 'active',
                'flash_sale' => (int)($row['flash_sale'] ?? 0),
                'release_date' => $row['release_date'] ?? null,
                'visibility' => $row['visibility'] ?? null,
                
                // SEO & Meta
                'slug' => $row['slug'] ?? '',
                'keywords' => $row['keywords'] ?? '',
                'meta_title' => $row['meta_title'] ?? '',
                'meta_description' => $row['meta_description'] ?? '',
                
                // Timestamps
                'createdAt' => $row['created_at'] ?? null,
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Không tìm thấy sản phẩm hoặc sản phẩm không khả dụng'
        ], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}