<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Dynamic BASE_URL for local and production
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($host, 'localhost') !== false) {
    $BASE_URL = 'http://' . $host . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
} else {
    $BASE_URL = 'https://' . $host;
}

$postId = intval($_GET['post_id'] ?? 0);
if (!$postId) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id']);
    exit;
}

$userId = get_user_id_from_token();
if (!$userId) $userId = 0; 

$sql = "
    SELECT 
        c.*, 
        u.name, 
        u.avatar,
        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS likes_count,
        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = {$userId}) AS is_liked
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = {$postId}
    ORDER BY c.created_at ASC
";

$res = $conn->query($sql);

$flat = [];
$map = [];
$roots = [];

while ($c = $res->fetch_assoc()) {
    $avatar = $c['avatar'];
    if (!empty($avatar) && !str_starts_with($avatar, "http")) {
        $avatar = $BASE_URL . $avatar;
    }

    $flat[] = [
        'id'         => (int)$c['id'],
        'content'    => $c['content'],
        'created_at' => $c['created_at'],
        'parent_id'  => $c['parent_id'] ? (int)$c['parent_id'] : null,

        'likes_count' => (int)$c['likes_count'],
        'is_liked'    => $c['is_liked'] ,

        'user' => [
            'id' => (int)$c['user_id'],
            'name' => $c['name'],
            'avatar' => $avatar,
        ],

        'replies' => []
    ];
}

foreach ($flat as $i => $row) {
    $map[$row['id']] = &$flat[$i];
}

foreach ($flat as $row) {
    if ($row['parent_id']) {
        $map[$row['parent_id']]['replies'][] = &$map[$row['id']];
    } else {
        $roots[] = &$map[$row['id']];
    }
}

echo json_encode([
    'success' => true,
    'comments' => $roots
]);
