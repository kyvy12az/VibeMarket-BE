<?php
require_once '../../../config/database.php';
require_once '../../../config/jwt.php';
require_once '../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

try {
    // Lấy token từ header Authorization hoặc từ POST data
    $token = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? null;
    } else {
        // Lấy từ Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token) {
        throw new Exception('Thiếu token xác thực');
    }

    // Xác thực JWT
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $auth_user_id = $decoded->sub;
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ: ' . $e->getMessage());
    }

    // Lấy danh sách user online (trong vòng 5 phút gần đây)
    $sql = "SELECT u.id, u.name, u.avatar, us.status, us.last_seen
            FROM users u
            INNER JOIN user_status us ON u.id = us.user_id
            WHERE us.status = 'online' 
               OR (us.status = 'offline' AND us.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            ORDER BY us.status DESC, us.last_seen DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $onlineUsers = [];
    while ($row = $result->fetch_assoc()) {
        $isOnline = $row['status'] === 'online' ||
            (strtotime($row['last_seen']) > (time() - 300)); // 5 minutes

        $onlineUsers[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => $row['avatar'],
            'status' => $row['status'],
            'isOnline' => $isOnline,
            'lastSeen' => $row['last_seen']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $onlineUsers,
        'total' => count($onlineUsers),
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
