<?php
require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$postId = intval($_GET['post_id'] ?? 0);
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

if (!$postId) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id']);
    exit;
}

$totalRes = $conn->query("SELECT COUNT(*) AS c FROM post_comments WHERE post_id = $postId");
$total = $totalRes->fetch_assoc()['c'];

$sql = "
    SELECT c.*, u.name, u.avatar
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = $postId
    ORDER BY c.created_at ASC
    LIMIT $offset, $limit
";
$res = $conn->query($sql);

$comments = [];
while ($c = $res->fetch_assoc()) {
    $comments[] = [
        'id'         => (int)$c['id'],
        'content'    => $c['content'],
        'created_at' => $c['created_at'],
        'parent_id'  => $c['parent_id'] ? (int)$c['parent_id'] : null,
        'user'       => [
            'id'     => (int)$c['user_id'],
            'name'   => $c['name'],
            'avatar' => $c['avatar'],
        ]
    ];
}

echo json_encode([
    'success' => true,
    'meta' => [
        'page'      => $page,
        'limit'     => $limit,
        'total'     => (int)$total,
        'totalPage' => ceil($total / $limit)
    ],
    'comments' => $comments
]);
