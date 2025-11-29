<?php
// Ngay đầu file, tắt display_errors cho môi trường production và bắt output không mong muốn
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');
// bắt mọi output phụ để log, đảm bảo trả JSON
ob_start();

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
checkRateLimit(30, 60, 'messages'); // 30 requests per minute

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
            getMessages($conn, $user_id);
            break;
        case 'POST':
            sendMessage($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hỗ trợ']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getMessages($conn, $user_id)
{
    $conversation_id = $_GET['conversation_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    if (!$conversation_id) {
        throw new Exception('Thiếu conversation_id');
    }

    if (!checkConversationAccess($conn, $conversation_id, $user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        return;
    }

    $countSql = "SELECT COUNT(*) as total FROM messages WHERE conversation_id = ? AND is_deleted = 0";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('i', $conversation_id);
    $countStmt->execute();
    $totalMessages = $countStmt->get_result()->fetch_assoc()['total'];

    $sql = "SELECT m.id, m.content, m.sender_id, m.type, m.file_url, m.created_at,
                   u.name as sender_name, u.avatar as sender_avatar,
                   CASE WHEN mr.read_at IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = ?
            WHERE m.conversation_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $user_id, $conversation_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'senderId' => (int)$row['sender_id'],
            'content' => $row['content'],
            'type' => $row['type'],
            'file_url' => $row['file_url'],
            'fileUrl' => $row['file_url'],
            'timestamp' => $row['created_at'],
            'isRead' => (bool)$row['is_read'],
            'sender' => [
                'id' => (int)$row['sender_id'],
                'name' => $row['sender_name'],
                'avatar' => $row['sender_avatar']
            ]
        ];
    }

    $messages = array_reverse($messages);

    $extraOutput = trim(ob_get_clean());
    if (!empty($extraOutput)) {
        error_log("[messages.php] Unexpected output before JSON: " . $extraOutput);
    }

    echo json_encode([
        'success' => true,
        'data' => $messages,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => ceil($totalMessages / $limit),
            'totalMessages' => (int)$totalMessages,
            'limit' => $limit
        ]
    ]);
}

function sendMessage($conn, $user_id)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    $conversation_id = $input['conversation_id'] ?? null;
    $content = trim($input['content'] ?? '');
    $type = $input['type'] ?? 'text';
    $file_url = $input['file_url'] ?? null;
    $temp_id = $input['temp_id'] ?? null;

    if (!$conversation_id) {
        throw new Exception('Thiếu conversation_id');
    }

    if (empty($content) && $type === 'text') {
        throw new Exception('Nội dung tin nhắn không được để trống');
    }

    if (!checkConversationAccess($conn, $conversation_id, $user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        return;
    }

    $sql = "INSERT INTO messages (conversation_id, sender_id, content, type, file_url) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iisss', $conversation_id, $user_id, $content, $type, $file_url);
    $stmt->execute();
    $message_id = $conn->insert_id;

    $updateSql = "UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('i', $conversation_id);
    $updateStmt->execute();

    $getSql = "SELECT m.*, u.name as sender_name, u.avatar as sender_avatar
               FROM messages m
               INNER JOIN users u ON m.sender_id = u.id
               WHERE m.id = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param('i', $message_id);
    $getStmt->execute();
    $messageData = $getStmt->get_result()->fetch_assoc();

    $responseData = [
        'id' => (int)$messageData['id'],
        'senderId' => (int)$messageData['sender_id'],
        'content' => $messageData['content'],
        'type' => $messageData['type'],
        'file_url' => $messageData['file_url'],
        'fileUrl' => $messageData['file_url'],
        'timestamp' => $messageData['created_at'],
        'tempId' => $temp_id,
        'sender' => [
            'id' => (int)$messageData['sender_id'],
            'name' => $messageData['sender_name'],
            'avatar' => $messageData['sender_avatar']
        ]
    ];
    notifyNodeServer($conversation_id, $responseData);

    $extraOutput = trim(ob_get_clean());
    if (!empty($extraOutput)) {
        error_log("[messages.php] Unexpected output before JSON: " . $extraOutput);
    }

    echo json_encode([
        'success' => true,
        'data' => $responseData
    ]);
}

function notifyNodeServer($conversationId, $messageData)
{
    // đảm bảo có biến node server (nếu chưa có trong config)
    global $nodejs_server;
    if (empty($nodejs_server)) {
        $nodejs_server = 'http://localhost:3000';
    }

    $postData = json_encode([
        'conversationId' => $conversationId,
        'message' => $messageData
    ]);

    $url = rtrim($nodejs_server, '/') . '/notify-message';

    // Use curl for more reliable HTTP call and better timeout handling
    if (!function_exists('curl_init')) {
        // fallback to file_get_contents but with longer timeout and suppressed warnings
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log("notifyNodeServer(file_get_contents) failed to {$url}: " . print_r(error_get_last(), true));
        } else {
            error_log("notifyNodeServer(file_get_contents) response: " . $response);
        }
        return;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        error_log("notifyNodeServer(curl) failed to {$url}: {$err}");
    } else {
        error_log("notifyNodeServer(curl) response: " . $response);
    }
    curl_close($ch);
}


function checkConversationAccess($conn, $conversation_id, $user_id)
{
    $sql = "SELECT COUNT(*) as count FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $conversation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result['count'] > 0;
}
