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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $sql = "SELECT 
                ch.*,
                caller.name as caller_name,
                caller.avatar as caller_avatar,
                receiver.name as receiver_name,
                receiver.avatar as receiver_avatar
            FROM call_history ch
            INNER JOIN users caller ON ch.caller_id = caller.id
            INNER JOIN users receiver ON ch.receiver_id = receiver.id
            WHERE ch.caller_id = ? OR ch.receiver_id = ?
            ORDER BY ch.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $user_id, $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $calls = [];
    while ($row = $result->fetch_assoc()) {
        $calls[] = [
            'id' => (int)$row['id'],
            'call_id' => $row['call_id'],
            'type' => $row['type'],
            'status' => $row['status'],
            'duration' => (int)$row['duration'],
            'created_at' => $row['created_at'],
            'is_incoming' => $row['receiver_id'] == $user_id,
            'caller' => [
                'id' => (int)$row['caller_id'],
                'name' => $row['caller_name'],
                'avatar' => $row['caller_avatar']
            ],
            'receiver' => [
                'id' => (int)$row['receiver_id'],
                'name' => $row['receiver_name'],
                'avatar' => $row['receiver_avatar']
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $calls,
        'pagination' => [
            'currentPage' => $page,
            'limit' => $limit,
            'total' => count($calls)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
