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
    $currentUserId = $decoded->sub;
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

$data = json_decode(file_get_contents("php://input"), true);
$targetUserId = isset($data['user_id']) ? intval($data['user_id']) : null;
$action = isset($data['action']) ? $data['action'] : null; // 'follow' hoặc 'unfollow'

if (!$targetUserId || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu thông tin user_id hoặc action']);
    exit;
}

if ($currentUserId === $targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Không thể tự theo dõi chính mình']);
    exit;
}

if ($action === 'follow') {
    // Kiểm tra xem đã follow chưa
    $stmt_check = $conn->prepare("SELECT 1 FROM user_followers WHERE follower_id = ? AND following_id = ?");
    $stmt_check->bind_param("ii", $currentUserId, $targetUserId);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Bạn đã theo dõi người dùng này rồi']);
        exit;
    }
    $stmt_check->close();
    
    // Thêm follow
    $stmt = $conn->prepare("INSERT INTO user_followers (follower_id, following_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $currentUserId, $targetUserId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Đã theo dõi người dùng',
            'isFollowing' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Không thể theo dõi người dùng']);
    }
    $stmt->close();
    
} elseif ($action === 'unfollow') {
    // Xóa follow
    $stmt = $conn->prepare("DELETE FROM user_followers WHERE follower_id = ? AND following_id = ?");
    $stmt->bind_param("ii", $currentUserId, $targetUserId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Đã hủy theo dõi người dùng',
                'isFollowing' => false
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Bạn chưa theo dõi người dùng này']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Không thể hủy theo dõi người dùng']);
    }
    $stmt->close();
    
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Action không hợp lệ']);
}

$conn->close();
