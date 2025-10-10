<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

int_headers();

// Lấy dữ liệu từ body JSON
$data = json_decode(file_get_contents("php://input"), true);

// Nếu image là mảng => convert sang JSON string
if (isset($data['image']) && is_array($data['image'])) {
    $data['image'] = json_encode($data['image'], JSON_UNESCAPED_UNICODE);
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
