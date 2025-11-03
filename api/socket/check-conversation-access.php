<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

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

    if (!$input || !isset($input['token']) || !isset($input['conversation_id']) || !isset($input['user_id'])) {
        throw new Exception('Thiếu dữ liệu bắt buộc (token, conversation_id, user_id)');
    }

    $token = $input['token'];
    $conversation_id = $input['conversation_id'];
    $user_id = $input['user_id'];

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;

        if (!$auth_user_id) {
            throw new Exception('Token không hợp lệ');
        }
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Kiểm tra quyền truy cập conversation
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
    ");
    $stmt->bind_param('ii', $conversation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $hasAccess = $result['count'] > 0;

    // Nếu có quyền truy cập, lấy thông tin conversation
    $conversationInfo = null;
    if ($hasAccess) {
        $stmt = $conn->prepare("
            SELECT id, type, name, avatar 
            FROM conversations 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $conversation_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $conversationInfo = $result->fetch_assoc();
        }
    }

    echo json_encode([
        'success' => true,
        'hasAccess' => $hasAccess,
        'conversation' => $conversationInfo
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
