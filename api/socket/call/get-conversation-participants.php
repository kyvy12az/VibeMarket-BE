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

    if (!$input || !isset($input['token']) || !isset($input['conversation_id'])) {
        throw new Exception('Thiếu dữ liệu bắt buộc (token, conversation_id)');
    }

    $token = $input['token'];
    $conversation_id = $input['conversation_id'];

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Kiểm tra user có quyền truy cập conversation không
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
    ");
    $stmt->bind_param('ii', $conversation_id, $auth_user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] == 0) {
        throw new Exception('Không có quyền truy cập cuộc trò chuyện này');
    }


    // Lấy thông tin conversation
    $stmt1 = $conn->prepare("
        SELECT id, type, name, avatar, created_by
        FROM conversations
        WHERE id = ?
    ");
    $stmt1->bind_param('i', $conversation_id);
    $stmt1->execute();
    $conversation = $stmt1->get_result()->fetch_assoc();

    if (!$conversation) {
        throw new Exception('Cuộc trò chuyện không tồn tại hoặc đã bị xóa');
    }

    $conversation_info = [
        'id' => (int)$conversation['id'],
        'type' => $conversation['type'],
        'name' => $conversation['name'],
        'avatar' => $conversation['avatar'],
        'created_by' => $conversation['created_by'] ?? null
    ];

    // Lấy danh sách participants
    $stmt2 = $conn->prepare("
        SELECT u.id, u.name, u.avatar
        FROM conversation_participants cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conversation_id = ? AND cp.left_at IS NULL
    ");
    $stmt2->bind_param('i', $conversation_id);
    $stmt2->execute();
    $result = $stmt2->get_result();

    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => $row['avatar']
        ];
    }

    echo json_encode([
        'success' => true,
        'conversation' => $conversation_info,
        'participants' => $participants,
        'total' => count($participants),
        'requester' => (int)$auth_user_id
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
