<?php
require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body = json_decode(file_get_contents('php://input'), true);
$postId   = intval($body['post_id'] ?? 0);
$userId   = intval($body['user_id'] ?? 0);
$content  = trim($body['content'] ?? '');
$parentId = isset($body['parent_id']) ? intval($body['parent_id']) : null;

if (!$postId || !$userId || $content === '') {
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

$sql = "INSERT INTO post_comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($parentId) {
    $stmt->bind_param("iiis", $postId, $userId, $parentId, $content);
} else {
    $null = null;
    $stmt->bind_param("iiis", $postId, $userId, $null, $content);
}
$ok = $stmt->execute();

if ($ok) {
    $commentId = $conn->insert_id;
    $conn->query("INSERT INTO activity_logs (user_id, type, reference_id) VALUES ($userId, 'comment', $postId)");

    $res = $conn->query("
        SELECT c.*, u.name, u.avatar 
        FROM post_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = $commentId
        LIMIT 1
    ");
    $c = $res->fetch_assoc();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id'         => (int)$c['id'],
            'content'    => $c['content'],
            'created_at' => $c['created_at'],
            'parent_id'  => $c['parent_id'] ? (int)$c['parent_id'] : null,
            'user'       => [
                'id'     => (int)$c['user_id'],
                'name'   => $c['name'],
                'avatar' => $c['avatar'],
            ]
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể thêm bình luận']);
}
