<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
checkRateLimit(30, 60, 'conversations'); // 30 requests per minute

// **FIX: Debug log headers**
$headers = getallheaders();
error_log("Conversations.php - Received headers: " . print_r($headers, true));

// Helper: build base url and normalize avatar paths (avoid redeclare)
if (!function_exists('build_base_url')) {
    function build_base_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (false !== ($pos = strpos($script, '/api/'))) {
            $projectBase = substr($script, 0, $pos);
        } else {
            $projectBase = dirname(dirname(dirname($script)));
        }
        $projectBase = rtrim($projectBase, '/');
        return $protocol . '://' . $host . ($projectBase ? $projectBase : '');
    }
}

if (!function_exists('normalize_avatar_url')) {
    function normalize_avatar_url($avatar) {
        $avatar = trim((string)($avatar ?? ''));
        if ($avatar === '') return '';
        if (stripos($avatar, 'data:') === 0) return $avatar;

        // scheme-relative (//example.com/path) -> add protocol
        if (strpos($avatar, '//') === 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
            return $protocol . $avatar;
        }

        // If already absolute URL with scheme
        if (preg_match('#^https?://#i', $avatar)) return $avatar;

        // Provider-hosted avatars: Google / Zalo / other known hosts without scheme
        $lower = strtolower($avatar);
        if (strpos($lower, 'googleusercontent.com') !== false || strpos($lower, 'lh3.googleusercontent.com') !== false || strpos($lower, 'zalo') !== false) {
            // ensure https
            return (stripos($avatar, 'http') === 0) ? $avatar : 'https://' . ltrim($avatar, '/');
        }

        $avatar = ltrim($avatar, '/');
        // common avatar folders
        if (stripos($avatar, 'uploads/avatars/') === 0 || stripos($avatar, 'uploads/') === 0) {
            return rtrim(build_base_url(), '/') . '/' . $avatar;
        }

        // fallback: assume path relative to project base
        return rtrim(build_base_url(), '/') . '/' . $avatar;
    }
}

if (!isset($headers['Authorization'])) {
    error_log("Conversations.php - Missing Authorization header");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token', 'debug' => 'No Authorization header']);
    exit;
}

if (!preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
    error_log("Conversations.php - Invalid Authorization format: " . $headers['Authorization']);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ', 'debug' => 'Invalid Bearer format']);
    exit;
}

$jwt = $matches[1];
error_log("Conversations.php - Extracted JWT: " . substr($jwt, 0, 20) . "...");

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
    error_log("Conversations.php - Decoded user_id: " . $user_id);
} catch (Exception $e) {
    error_log("Conversations.php - JWT decode error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token hết hạn', 'debug' => $e->getMessage()]);
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
    // Get filter parameters from query string
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : null;
    $as_seller = isset($_GET['as_seller']) && $_GET['as_seller'] === 'true';

    $sql = "SELECT DISTINCT
                c.id,
                c.type,
                c.name,
                c.avatar,
                c.background_color,
                c.message_color,
                c.message_text_color,
                c.conversation_category,
                c.seller_id,
                s.store_name,
                s.avatar as seller_avatar,
                s.user_id as seller_user_id,
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
            LEFT JOIN seller s ON c.seller_id = s.seller_id
            WHERE cp.user_id = ? AND cp.left_at IS NULL";
    
    // Filter for seller's shop conversations
    if ($as_seller) {
        // Get conversations where user is the seller (shop owner viewing customer messages)
        $sql .= " AND c.conversation_category = 'shop' AND s.user_id = " . intval($user_id);
    } else {
        // Add category filter if specified (for customer side)
        if ($category === 'shop') {
            $sql .= " AND c.conversation_category = 'shop'";
        } elseif ($category === 'user') {
            $sql .= " AND (c.conversation_category = 'user' OR c.conversation_category IS NULL)";
        }
        
        // Add seller_id filter if specified
        if ($seller_id) {
            $sql .= " AND c.seller_id = " . intval($seller_id);
        }
    }
    
    $sql .= " ORDER BY c.updated_at DESC";

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
            'avatar' => normalize_avatar_url($row['avatar']),
            'background_color' => $row['background_color'],
            'message_color' => $row['message_color'],
            'message_text_color' => $row['message_text_color'],
            'conversation_category' => $row['conversation_category'] ?? 'user',
            'seller_id' => $row['seller_id'] ? (int)$row['seller_id'] : null,
            'seller_user_id' => $row['seller_user_id'] ? (int)$row['seller_user_id'] : null,
            'store_name' => $row['store_name'] ?? null,
            'seller_avatar' => normalize_avatar_url($row['seller_avatar'] ?? null),
            'unreadCount' => (int)$row['unread_count'],
            'isGroup' => $row['type'] === 'group',
            'isShopChat' => $row['conversation_category'] === 'shop',
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
            'avatar' => normalize_avatar_url($row['avatar']),
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
    $conversation_category = $input['conversation_category'] ?? 'user';
    $seller_id = isset($input['seller_id']) ? intval($input['seller_id']) : null;

    if (!in_array($user_id, $participants)) {
        $participants[] = $user_id;
    }

    if ($type === 'private' && count($participants) !== 2) {
        throw new Exception('Chat riêng tư chỉ có thể có 2 người');
    }

    if ($type === 'private') {
        $existing = checkExistingPrivateConversation($conn, $participants, $conversation_category, $seller_id);
        if ($existing) {
            // Return existing conversation instead of error
            echo json_encode([
                'success' => true,
                'data' => ['id' => $existing],
                'message' => 'Conversation already exists'
            ]);
            return;
        }
    }

    $conn->begin_transaction();

    try {
        // Tạo conversation
        $sql = "INSERT INTO conversations (type, name, avatar, created_by, conversation_category, seller_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssisi', $type, $name, $avatar, $user_id, $conversation_category, $seller_id);
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

function checkExistingPrivateConversation($conn, $participants, $conversation_category = 'user', $seller_id = null)
{
    $sql = "SELECT c.id FROM conversations c
            WHERE c.type = 'private' 
            AND c.conversation_category = ?";
    
    if ($seller_id) {
        $sql .= " AND c.seller_id = ?";
    }
    
    $sql .= " AND (SELECT COUNT(*) FROM conversation_participants cp 
                 WHERE cp.conversation_id = c.id 
                 AND cp.user_id IN (?, ?) 
                 AND cp.left_at IS NULL) = 2";

    $stmt = $conn->prepare($sql);
    
    if ($seller_id) {
        $stmt->bind_param('siii', $conversation_category, $seller_id, $participants[0], $participants[1]);
    } else {
        $stmt->bind_param('sii', $conversation_category, $participants[0], $participants[1]);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }

    return null;
}
