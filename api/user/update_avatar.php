<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Lấy token từ header
$headers = getallheaders();
$jwt = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không tồn tại']);
    exit;
}

// Decode JWT
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $userId = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
    exit;
}

// Kiểm tra file upload
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng chọn file ảnh']);
    exit;
}

$file = $_FILES['avatar'];

// Kiểm tra kích thước file (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Kích thước ảnh không được vượt quá 5MB']);
    exit;
}

// Kiểm tra định dạng file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)']);
    exit;
}

// Tạo tên file duy nhất
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;

// Đường dẫn lưu file
$uploadDir = '../../uploads/avatars/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploadPath = $uploadDir . $fileName;

// Di chuyển file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể tải lên ảnh']);
    exit;
}

// Lấy avatar cũ để xóa
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$oldAvatar = $userData['avatar'];
$stmt->close();

// Xóa avatar cũ nếu tồn tại và không phải avatar mặc định
if ($oldAvatar && strpos($oldAvatar, '/uploads/avatars/') !== false) {
    $oldAvatarPath = '../../' . str_replace('/uploads/', 'uploads/', $oldAvatar);
    if (file_exists($oldAvatarPath)) {
        unlink($oldAvatarPath);
    }
}

// Cập nhật avatar trong database
$avatarUrl = '/uploads/avatars/' . $fileName;
$stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->bind_param("si", $avatarUrl, $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật avatar thành công',
        'avatar' => $avatarUrl
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể cập nhật avatar']);
}

$stmt->close();
$conn->close();
