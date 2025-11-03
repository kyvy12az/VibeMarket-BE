<?php
require_once '../../../config/database.php';
require_once '../../../config/jwt.php';
require_once '../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ POST']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['token']) || !isset($input['status'])) {
        throw new Exception('Thiếu dữ liệu bắt buộc (token, status)');
    }

    $token = $input['token'];
    $status = $input['status'];
    $user_id = isset($input['user_id']) ? $input['user_id'] : null;

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;

        // Nếu có user_id trong request, kiểm tra quyền
        if ($user_id && $auth_user_id != $user_id) {
            throw new Exception('Không có quyền cập nhật trạng thái của user khác');
        }

        // Sử dụng user_id từ token nếu không có trong request
        if (!$user_id) {
            $user_id = $auth_user_id;
        }
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    if (!in_array($status, ['online', 'offline'])) {
        throw new Exception('Trạng thái không hợp lệ');
    }

    // Cập nhật trạng thái user
    $stmt = $conn->prepare("
        INSERT INTO user_status (user_id, status) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE 
            status = ?, 
            last_seen = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('iss', $user_id, $status, $status);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật trạng thái thành công',
        'user_id' => (int)$user_id,
        'status' => $status
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
