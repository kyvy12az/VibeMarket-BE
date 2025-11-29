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

$product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

if (!$product_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
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

// Kiểm tra quyền sở hữu sản phẩm
$stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Không có quyền chỉnh sửa sản phẩm này']);
    exit;
}
$stmt->close();

// Validate dữ liệu
$name = isset($input['name']) ? trim($input['name']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$category = isset($input['category']) ? trim($input['category']) : '';
$brand = isset($input['brand']) ? trim($input['brand']) : '';
$price = isset($input['price']) ? intval($input['price']) : 0;
$original_price = isset($input['original_price']) ? intval($input['original_price']) : $price;
$discount = isset($input['discount']) ? intval($input['discount']) : 0;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 0;
$status = isset($input['status']) ? $input['status'] : 'active';
$shipping_fee = isset($input['shipping_fee']) ? intval($input['shipping_fee']) : 0;

// Flash sale fields
$flash_sale = isset($input['flash_sale']) ? (int)$input['flash_sale'] : 0;
$sale_price = isset($input['sale_price']) ? intval($input['sale_price']) : null;
$sale_quantity = isset($input['sale_quantity']) ? intval($input['sale_quantity']) : 0;

// Release date
$is_live = isset($input['is_live']) ? (int)$input['is_live'] : 0;
$release_date = isset($input['release_date']) && !empty($input['release_date']) ? $input['release_date'] : null;

// Arrays
$sizes = isset($input['sizes']) && is_array($input['sizes']) ? implode(',', $input['sizes']) : '';
$colors = isset($input['colors']) && is_array($input['colors']) ? implode(',', $input['colors']) : '';
$tags = isset($input['tags']) && is_array($input['tags']) ? implode(',', $input['tags']) : '';

if (empty($name) || $price <= 0) {
    echo json_encode(['success' => false, 'error' => 'Tên sản phẩm và giá bán là bắt buộc']);
    exit;
}

// Validate status
$valid_statuses = ['active', 'inactive'];
if (!in_array($status, $valid_statuses)) {
    $status = 'active';
}

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // Cập nhật sản phẩm
    $sql = "UPDATE products SET 
        name = ?,
        description = ?,
        category = ?,
        brand = ?,
        price = ?,
        original_price = ?,
        discount = ?,
        quantity = ?,
        status = ?,
        shipping_fee = ?,
        flash_sale = ?,
        sale_price = ?,
        sale_quantity = ?,
        is_live = ?,
        release_date = ?,
        sizes = ?,
        colors = ?,
        tags = ?
    WHERE id = ? AND seller_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssiiiisiiisissssii",
        $name,
        $description,
        $category,
        $brand,
        $price,
        $original_price,
        $discount,
        $quantity,
        $status,
        $shipping_fee,
        $flash_sale,
        $sale_price,
        $sale_quantity,
        $is_live,
        $release_date,
        $sizes,
        $colors,
        $tags,
        $product_id,
        $seller_id
    );

    if (!$stmt->execute()) {
        throw new Exception('Không thể cập nhật sản phẩm: ' . $stmt->error);
    }

    $stmt->close();

    // Xử lý upload ảnh mới (nếu có)
    if (isset($input['new_images']) && is_array($input['new_images']) && !empty($input['new_images'])) {
        // Lấy danh sách ảnh cũ
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->bind_result($old_images_json);
        $stmt->fetch();
        $stmt->close();

        $old_images = json_decode($old_images_json, true);
        if (!is_array($old_images)) {
            $old_images = [];
        }

        // Merge ảnh cũ và ảnh mới
        $all_images = array_merge($old_images, $input['new_images']);
        $images_json = json_encode($all_images);

        $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
        $stmt->bind_param("si", $images_json, $product_id);
        $stmt->execute();
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật sản phẩm thành công',
        'product_id' => $product_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();