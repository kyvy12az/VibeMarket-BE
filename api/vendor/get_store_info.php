<?php
require_once '../../config/database.php';
require_once '../../config/url_helper.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['error' => 'Thiếu user_id']);
    exit;
}

// Lấy thông tin seller đầy đủ
$stmt = $conn->prepare("
    SELECT seller_id, store_name, avatar, cover_image, business_type, phone, email, 
           business_address, establish_year 
    FROM seller 
    WHERE user_id = ? AND status = 'approved'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $seller = $result->fetch_assoc();
    
    // Xử lý avatar URL using helper
    $avatar_url = getFullImageUrl($seller['avatar'] ? 'store_avatars/' . $seller['avatar'] : null);
    
    // Xử lý cover URL using helper
    $cover_url = getFullImageUrl($seller['cover_image'] ? 'store_covers/' . $seller['cover_image'] : null);
    
    echo json_encode([
        'success' => true,
        'seller_id' => $seller['seller_id'],
        'store_name' => $seller['store_name'],
        'avatar' => $avatar_url,
        'cover_image' => $cover_url,
        'business_type' => $seller['business_type'],
        'phone' => $seller['phone'],
        'email' => $seller['email'],
        'business_address' => $seller['business_address'],
        'establish_year' => $seller['establish_year']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Không tìm thấy thông tin cửa hàng hoặc tài khoản chưa được duyệt'
    ]);
}

$stmt->close();
$conn->close();
?>