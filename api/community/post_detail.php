<?php
require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

$id     = intval($_GET['id'] ?? 0);
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing post id']);
    exit;
}

$sql = "
    SELECT 
        p.*, 
        u.name AS author_name, 
        u.avatar AS author_avatar
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND p.status != 'deleted'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!($post = $res->fetch_assoc())) {
    echo json_encode(['success' => false, 'message' => 'Post not found']);
    exit;
}

$imgs = [];
$imgRes = $conn->query("SELECT image_url FROM post_images WHERE post_id = $id");
while ($row = $imgRes->fetch_assoc()) {
    $imgs[] = $row['image_url'];
}

$tags = [];
$tagRes = $conn->query("SELECT tag FROM post_tags WHERE post_id = $id");
while ($row = $tagRes->fetch_assoc()) {
    $tags[] = $row['tag'];
}

$likes     = $conn->query("SELECT COUNT(*) AS c FROM post_likes WHERE post_id = $id")->fetch_assoc()['c'];
$comments  = $conn->query("SELECT COUNT(*) AS c FROM post_comments WHERE post_id = $id")->fetch_assoc()['c'];
$saves     = $conn->query("SELECT COUNT(*) AS c FROM post_saves WHERE post_id = $id")->fetch_assoc()['c'];
$isLiked = false; $isSaved = false;
if ($userId) {
    $isLiked = $conn->query("SELECT 1 FROM post_likes WHERE post_id=$id AND user_id=$userId LIMIT 1")->num_rows > 0;
    $isSaved = $conn->query("SELECT 1 FROM post_saves WHERE post_id=$id AND user_id=$userId LIMIT 1")->num_rows > 0;
}

$commentList = [];
$cRes = $conn->query("
    SELECT c.*, u.name, u.avatar
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = $id
    ORDER BY c.created_at DESC
    LIMIT 10
");
while ($c = $cRes->fetch_assoc()) {
    $commentList[] = [
        'id'         => (int)$c['id'],
        'content'    => $c['content'],
        'created_at' => $c['created_at'],
        'user'       => [
            'id'     => (int)$c['user_id'],
            'name'   => $c['name'],
            'avatar' => $c['avatar'],
        ],
        'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null,
    ];
}

echo json_encode([
    'success' => true,
    'post' => [
        'id'          => (int)$post['id'],
        'title'       => $post['title'],
        'content'     => $post['content'],
        'status'      => $post['status'],
        'created_at'  => $post['created_at'],
        'updated_at'  => $post['updated_at'],
        'author'      => [
            'id'     => (int)$post['user_id'],
            'name'   => $post['author_name'],
            'avatar' => $post['author_avatar'],
        ],
        'images'          => $imgs,
        'tags'            => $tags,
        'likes_count'     => (int)$likes,
        'comments_count'  => (int)$comments,
        'saves_count'     => (int)$saves,
        'is_liked'        => $isLiked,
        'is_saved'        => $isSaved,
        'comments'        => $commentList,
    ]
]);
