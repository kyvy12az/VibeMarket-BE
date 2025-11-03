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

    if (!$input || !isset($input['token']) || !isset($input['id']) || !isset($input['conversationId']) || !isset($input['initiatorId'])) {
        throw new Exception('Thiếu dữ liệu bắt buộc (token, id, conversationId, initiatorId)');
    }

    $token = $input['token'];
    $id = $input['id'];
    $conversation_id = $input['conversationId'];
    $initiator_id = $input['initiatorId'];
    $type = $input['type'] ?? 'voice';
    $start_time = $input['startTime'] ?? date('Y-m-d H:i:s');

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;

        // Kiểm tra user có quyền tạo call này không (phải là initiator)
        if ($auth_user_id != $initiator_id) {
            throw new Exception('Không có quyền tạo cuộc gọi cho user khác');
        }
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Kiểm tra user có quyền truy cập conversation không
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
    ");
    $stmt->bind_param('ii', $conversation_id, $initiator_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] == 0) {
        throw new Exception('Không có quyền truy cập cuộc trò chuyện này');
    }

    // Lưu thông tin cuộc gọi
    $stmt = $conn->prepare("
        INSERT INTO calls (id, conversation_id, initiator_id, type, start_time)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('siiss', $id, $conversation_id, $initiator_id, $type, $start_time);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Lưu thông tin cuộc gọi thành công',
        'call_id' => $id
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
