<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

checkRateLimit(5, 10);

// Xác thực JWT
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token']);
    exit;
}

if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
    exit;
}

$jwt = $matches[1];
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token hết hạn']);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            getUserConversations($conn, $user_id);
            break;
        case 'POST':
            createConversation($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hỗ trợ']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getUserConversations($conn, $user_id)
{
    $sql = "SELECT DISTINCT
                c.id,
                c.type,
                c.name,
                c.avatar,
                c.created_at,
                c.updated_at,
                (SELECT COUNT(*) FROM messages m 
                 WHERE m.conversation_id = c.id 
                 AND m.id NOT IN (
                     SELECT mr.message_id FROM message_reads mr 
                     WHERE mr.user_id = ? AND mr.message_id = m.id
                 ) AND m.sender_id != ?) as unread_count
            FROM conversations c
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ? AND cp.left_at IS NULL
            ORDER BY c.updated_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversation = [
            'id' => $row['id'],
            'type' => $row['type'],
            'name' => $row['name'],
            'avatar' => $row['avatar'],
            'unreadCount' => (int)$row['unread_count'],
            'isGroup' => $row['type'] === 'group',
            'participants' => getConversationParticipants($conn, $row['id'], $user_id),
            'lastMessage' => getLastMessage($conn, $row['id'])
        ];

        $conversations[] = $conversation;
    }

    echo json_encode(['success' => true, 'data' => $conversations]);
}

function getConversationParticipants($conn, $conversation_id, $current_user_id)
{
    $sql = "SELECT u.id, u.name, u.avatar, us.status, us.last_seen
            FROM users u
            INNER JOIN conversation_participants cp ON u.id = cp.user_id
            LEFT JOIN user_status us ON u.id = us.user_id
            WHERE cp.conversation_id = ? AND cp.left_at IS NULL";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => $row['avatar'],
            'isOnline' => $row['status'] === 'online',
            'lastSeen' => $row['last_seen']
        ];
    }

    return $participants;
}

function getLastMessage($conn, $conversation_id)
{
    $sql = "SELECT m.id, m.content, m.sender_id, m.type, m.created_at, u.name as sender_name
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return [
            'id' => (int)$row['id'],
            'content' => $row['content'],
            'senderId' => (int)$row['sender_id'],
            'senderName' => $row['sender_name'],
            'type' => $row['type'],
            'timestamp' => $row['created_at']
        ];
    }

    return null;
}

function createConversation($conn, $user_id)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['participants'])) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    $type = $input['type'] ?? 'private';
    $participants = $input['participants'];
    $name = $input['name'] ?? null;
    $avatar = $input['avatar'] ?? null;

    if (!in_array($user_id, $participants)) {
        $participants[] = $user_id;
    }

    if ($type === 'private' && count($participants) !== 2) {
        throw new Exception('Chat riêng tư chỉ có thể có 2 người');
    }

    if ($type === 'private') {
        $existing = checkExistingPrivateConversation($conn, $participants);
        if ($existing) {
            throw new Exception('Đã có cuộc trò chuyện riêng tư với người này');
        }
    }

    $conn->begin_transaction();

    try {
        // Tạo conversation
        $sql = "INSERT INTO conversations (type, name, avatar, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $type, $name, $avatar, $user_id);
        $stmt->execute();
        $conversation_id = $conn->insert_id;

        // Thêm participants
        $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);

        foreach ($participants as $participant_id) {
            $role = ($participant_id == $user_id && $type === 'group') ? 'admin' : 'member';
            $stmt->bind_param('iis', $conversation_id, $participant_id, $role);
            $stmt->execute();
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'data' => ['id' => $conversation_id]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function checkExistingPrivateConversation($conn, $participants)
{
    $sql = "SELECT c.id FROM conversations c
            WHERE c.type = 'private' 
            AND (SELECT COUNT(*) FROM conversation_participants cp 
                 WHERE cp.conversation_id = c.id 
                 AND cp.user_id IN (?, ?) 
                 AND cp.left_at IS NULL) = 2";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $participants[0], $participants[1]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }

    return null;
}
