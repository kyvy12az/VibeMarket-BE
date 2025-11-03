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

    if (!$input || !isset($input['token']) || !isset($input['callId'])) {
        throw new Exception('Thiếu dữ liệu bắt buộc (token, callId)');
    }

    $token = $input['token'];
    $call_id = $input['callId'];

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Kiểm tra cuộc gọi có tồn tại không
    $stmt = $conn->prepare("SELECT * FROM calls WHERE id = ?");
    $stmt->bind_param('s', $call_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'exists' => false,
            'message' => 'Cuộc gọi không tồn tại'
        ]);
    } else {
        $call = $result->fetch_assoc();

        // Kiểm tra user có quyền xem thông tin call này không
        $conversation_id = $call['conversation_id'];
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->bind_param('ii', $conversation_id, $auth_user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['count'] == 0) {
            throw new Exception('Không có quyền xem thông tin cuộc gọi này');
        }

        echo json_encode([
            'success' => true,
            'exists' => true,
            'call' => [
                'id' => $call['id'],
                'conversation_id' => (int)$call['conversation_id'],
                'initiator_id' => (int)$call['initiator_id'],
                'type' => $call['type'],
                'status' => $call['status'],
                'start_time' => $call['start_time'],
                'end_time' => $call['end_time'],
                'ended_reason' => $call['ended_reason']
            ],
            'requester' => (int)$auth_user_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
