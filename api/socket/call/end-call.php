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
    $reason = $input['reason'] ?? 'ended';
    $end_time = $input['endTime'] ?? date('Y-m-d H:i:s');

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Kiểm tra cuộc gọi có tồn tại không và user có quyền end call không
    $stmt = $conn->prepare("SELECT initiator_id, conversation_id FROM calls WHERE id = ? AND status != 'ended'");
    $stmt->bind_param('s', $call_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Cuộc gọi không tồn tại hoặc đã kết thúc');
    }

    $call_info = $result->fetch_assoc();
    $initiator_id = $call_info['initiator_id'];
    $conversation_id = $call_info['conversation_id'];

    // Kiểm tra quyền: user phải là initiator hoặc participant trong conversation
    $hasPermission = false;

    if ($auth_user_id == $initiator_id) {
        $hasPermission = true; // Initiator luôn có quyền end call
    } else {
        // Kiểm tra có phải participant không
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->bind_param('ii', $conversation_id, $auth_user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $hasPermission = true;
        }
    }

    if (!$hasPermission) {
        throw new Exception('Không có quyền kết thúc cuộc gọi này');
    }

    // Cập nhật thông tin cuộc gọi khi kết thúc
    $stmt = $conn->prepare("
        UPDATE calls 
        SET end_time = ?, status = 'ended', ended_reason = ? 
        WHERE id = ?
    ");
    $stmt->bind_param('sss', $end_time, $reason, $call_id);
    $stmt->execute();

    $affected_rows = $stmt->affected_rows;

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật cuộc gọi thành công',
        'call_id' => $call_id,
        'affected_rows' => $affected_rows,
        'ended_by' => (int)$auth_user_id
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
