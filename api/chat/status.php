<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
// checkRateLimit(5, 10);

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
            getOnlineUsers($conn, $user_id);
            break;
        case 'POST':
            updateUserStatus($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hỗ trợ']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function updateUserStatus($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $status = $input['status'] ?? 'online';
    
    if (!in_array($status, ['online', 'offline'])) {
        throw new Exception('Trạng thái không hợp lệ');
    }
    
    $sql = "INSERT INTO user_status (user_id, status) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE status = ?, last_seen = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $user_id, $status, $status);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
}

function getOnlineUsers($conn, $user_id) {
    // Lấy danh sách user online từ các cuộc trò chuyện mà user hiện tại tham gia
    $sql = "SELECT DISTINCT u.id, u.name, u.avatar, us.status, us.last_seen
            FROM users u
            INNER JOIN conversation_participants cp1 ON u.id = cp1.user_id
            INNER JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
            LEFT JOIN user_status us ON u.id = us.user_id
            WHERE cp2.user_id = ? AND u.id != ? 
            AND cp1.left_at IS NULL AND cp2.left_at IS NULL
            AND (us.status = 'online' OR us.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            ORDER BY us.status DESC, us.last_seen DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => $row['avatar'],
            'isOnline' => $row['status'] === 'online',
            'lastSeen' => $row['last_seen']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $users]);
}