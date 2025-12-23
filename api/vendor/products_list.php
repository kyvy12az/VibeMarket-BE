<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../helpers/url.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu user_id']);
    exit;
}

/* ================= GET SELLER ================= */
$stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($seller_id);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy seller']);
    exit;
}
$stmt->close();

/* ================= BASE URL ================= */
$baseUrl = getBaseUrl();

/* ================= QUERY PRODUCTS ================= */
$sql = "SELECT 
    p.id,
    p.name,
    p.category,
    p.price,
    p.quantity as initial_stock,
    p.sold as sales,
    (p.quantity - p.sold) as stock,
    p.rating,
    p.image,
    p.status,
    p.created_at
FROM products p
WHERE p.seller_id = ?
ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {

    /* ===== HANDLE IMAGE (FULL URL) ===== */
    $image = null;
    $images = json_decode($row['image'], true);

    if (is_array($images) && count($images) > 0) {
        $img = $images[0];

        // Nếu đã là full URL
        if (preg_match('/^https?:\/\//', $img)) {
            $image = $img;
        }
        // Nếu là absolute path: /uploads/...
        elseif (strpos($img, '/') === 0) {
            $image = $baseUrl . $img;
        }
        // Nếu chỉ là filename
        else {
            $image = $baseUrl . '/uploads/products/' . $img;
        }
    }

    /* ===== STOCK & STATUS ===== */
    $actual_stock = max(0, (int)$row['stock']);

    $status = 'active';
    if ($actual_stock === 0) {
        $status = 'out_of_stock';
    } elseif ($row['status'] !== 'active') {
        $status = 'inactive';
    }

    /* ===== RESPONSE ===== */
    $products[] = [
        'id' => 'SP' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'name' => $row['name'],
        'category' => $row['category'] ?? 'Chưa phân loại',
        'price' => (int)$row['price'],
        'initial_stock' => (int)$row['initial_stock'],
        'stock' => $actual_stock,
        'sales' => (int)$row['sales'],
        'rating' => (float)$row['rating'],
        'image' => $image, // ✅ FULL URL
        'status' => $status,
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'products' => $products,
    'total' => count($products)
]);

$stmt->close();
$conn->close();
