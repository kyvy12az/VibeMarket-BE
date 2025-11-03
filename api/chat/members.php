<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
checkRateLimit(5, 10);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ GET']);
    exit;
}

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
    $conversation_id = $_GET['conversation_id'] ?? null;

    if (!$conversation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu conversation_id']);
        exit;
    }

    $accessSql = "SELECT c.id, c.type, c.name 
                  FROM conversations c
                  INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
                  WHERE c.id = ? AND cp.user_id = ? AND cp.left_at IS NULL";

    $accessStmt = $conn->prepare($accessSql);
    $accessStmt->bind_param('ii', $conversation_id, $user_id);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();

    if ($accessResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập conversation này']);
        exit;
    }

    $conversation = $accessResult->fetch_assoc();

    $membersSql = "SELECT 
                       u.id,
                       u.name,
                       u.email,
                       u.avatar,
                       cp.role,
                       cp.joined_at,
                       cp.left_at,
                       COALESCE(us.status, 'offline') as online_status,
                       us.last_seen,
                       c.created_by
                   FROM conversation_participants cp
                   INNER JOIN users u ON cp.user_id = u.id
                   INNER JOIN conversations c ON cp.conversation_id = c.id
                   LEFT JOIN user_status us ON u.id = us.user_id
                   WHERE cp.conversation_id = ? AND cp.left_at IS NULL
                   ORDER BY 
                       CASE WHEN cp.role = 'admin' THEN 1 ELSE 2 END,
                       u.name ASC";

    $membersStmt = $conn->prepare($membersSql);
    $membersStmt->bind_param('i', $conversation_id);
    $membersStmt->execute();
    $membersResult = $membersStmt->get_result();

    $members = [];
    $totalMembers = 0;
    $onlineMembers = 0;

    while ($row = $membersResult->fetch_assoc()) {
        $isOnline = $row['online_status'] === 'online';
        if ($isOnline) {
            $onlineMembers++;
        }
        $totalMembers++;

        $members[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'avatar' => $row['avatar'] ?: '/images/avatars/default-avatar.png',
            'role' => $row['role'],
            'isOwner' => (int)$row['id'] === (int)$row['created_by'],
            'onlineStatus' => $row['online_status'],
            'lastSeen' => $row['last_seen'],
            'isOnline' => $isOnline,
            'joinedAt' => $row['joined_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'conversation' => [
                'id' => (int)$conversation['id'],
                'type' => $conversation['type'],
                'name' => $conversation['name']
            ],
            'members' => $members,
            'stats' => [
                'totalMembers' => $totalMembers,
                'onlineMembers' => $onlineMembers,
                'offlineMembers' => $totalMembers - $onlineMembers
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
