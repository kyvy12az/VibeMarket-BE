<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
checkRateLimit(5, 10);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ POST']);
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
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['message_ids']) || !is_array($input['message_ids'])) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    $message_ids = $input['message_ids'];

    if (empty($message_ids)) {
        echo json_encode(['success' => true, 'message' => 'Không có tin nhắn nào để đánh dấu']);
        exit;
    }

    // Tạo placeholders cho IN clause
    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';

    // Kiểm tra quyền truy cập các tin nhắn
    $checkSql = "SELECT m.id FROM messages m
                 INNER JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                 WHERE m.id IN ($placeholders) AND cp.user_id = ? AND cp.left_at IS NULL";

    $params = array_merge($message_ids, [$user_id]);
    $types = str_repeat('i', count($message_ids)) . 'i';

    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param($types, ...$params);
    $checkStmt->execute();
    $validMessages = $checkStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($validMessages) !== count($message_ids)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập một số tin nhắn']);
        exit;
    }

    // Đánh dấu đã đọc (INSERT IGNORE để tránh duplicate)
    $insertSql = "INSERT IGNORE INTO message_reads (message_id, user_id) VALUES ";
    $insertValues = [];
    $insertParams = [];
    $insertTypes = '';

    foreach ($message_ids as $message_id) {
        $insertValues[] = "(?, ?)";
        $insertParams[] = $message_id;
        $insertParams[] = $user_id;
        $insertTypes .= 'ii';
    }

    $insertSql .= implode(', ', $insertValues);
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param($insertTypes, ...$insertParams);
    $insertStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Đã đánh dấu ' . $insertStmt->affected_rows . ' tin nhắn là đã đọc'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
