<?php
require_once __DIR__ . '/../../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

$postId = intval($_GET['post_id'] ?? 0);

if (!$postId) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id']);
    exit;
}

$sql = "
    SELECT c.*, u.name, u.avatar
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = $postId
    ORDER BY c.created_at ASC
";

$res = $conn->query($sql);

$flat = [];
while ($c = $res->fetch_assoc()) {
    $flat[] = [
        'id'         => (int)$c['id'],
        'content'    => $c['content'],
        'created_at' => $c['created_at'],
        'parent_id'  => $c['parent_id'] ? (int)$c['parent_id'] : null,
        'user'       => [
            'id'     => (int)$c['user_id'],
            'name'   => $c['name'],
            'avatar' => $c['avatar'],
        ],
        'replies' => []
    ];
}


$map = [];
$roots = [];

foreach ($flat as $c) {
    $map[$c['id']] = $c;
}

foreach ($flat as $c) {
    if ($c['parent_id']) {
        $map[$c['parent_id']]['replies'][] = &$map[$c['id']];
    } else {
        $roots[] = &$map[$c['id']];
    }
}

echo json_encode([
    'success' => true,
    'comments' => $roots
]);
