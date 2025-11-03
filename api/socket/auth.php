<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
int_headers();
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
header('X-Debug-Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'null'));
header('X-Debug-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'null'));


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ POST']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['token'])) {
        throw new Exception('Thiếu token');
    }

    $token = $input['token'];

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $user_id = $decoded->sub;
    } catch (Exception $e) {
        throw new Exception('Token không hợp lệ');
    }

    // Lấy thông tin user
    $stmt = $conn->prepare("SELECT id, name, avatar FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User không tồn tại');
    }

    $user = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'avatar' => $user['avatar']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
