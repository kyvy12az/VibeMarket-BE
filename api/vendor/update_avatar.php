<?php
require_once '../../config/database.php';
require_once '../../config/url_helper.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Xử lý OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu user_id']);
    exit;
}

// Kiểm tra file upload
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Không có file được upload hoặc có lỗi xảy ra']);
    exit;
}

// Lấy thông tin seller
$stmt = $conn->prepare("SELECT seller_id, avatar FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy thông tin cửa hàng']);
    exit;
}

$seller = $result->fetch_assoc();
$seller_id = $seller['seller_id'];
$old_avatar = $seller['avatar'];
$stmt->close();

// Validate file
$file = $_FILES['avatar'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File quá lớn. Kích thước tối đa là 5MB']);
    exit;
}

// Get upload directory using helper
$upload_dir = getUploadDirectory('avatar');

// Tạo tên file unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'store_' . $seller_id . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Upload file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Không thể upload file']);
    exit;
}

// Delete old avatar using helper
if ($old_avatar) {
    deleteOldFile($old_avatar, 'avatar');
}

// Cập nhật database - chỉ lưu filename
$stmt = $conn->prepare("UPDATE seller SET avatar = ? WHERE seller_id = ?");
$stmt->bind_param("si", $filename, $seller_id);

if ($stmt->execute()) {
    // Tạo URL đầy đủ sử dụng helper
    $avatar_url = getFullImageUrl('vendor/avatars/' . $filename) . '?t=' . time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật avatar thành công',
        'avatar_url' => $avatar_url,
        'filename' => $filename
    ]);
} else {
    unlink($filepath);
    echo json_encode(['success' => false, 'error' => 'Không thể cập nhật database']);
}

$stmt->close();
$conn->close();
?>