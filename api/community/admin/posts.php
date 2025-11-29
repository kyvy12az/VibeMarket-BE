<?php
require_once __DIR__ . '/../../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

$page    = max(1, intval($_GET['page'] ?? 1));
$limit   = max(1, intval($_GET['limit'] ?? 10));
$search  = trim($_GET['search'] ?? '');
$tag     = trim($_GET['tag'] ?? '');
$order   = $_GET['order'] ?? 'newest';  
$userId  = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

$offset = ($page - 1) * $limit;

$where = " WHERE p.status = 'public' ";
$params = [];
$types  = "";

if ($search !== '') {
    $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if ($tag !== '') {
    $where .= " AND EXISTS (
        SELECT 1 FROM post_tags t 
        WHERE t.post_id = p.id AND t.tag = ?
    )";
    $params[] = $tag;
    $types .= "s";
}

$countSql = "SELECT COUNT(*) AS total FROM posts p $where";
if ($types !== "") {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
} else {
    $total = $conn->query($countSql)->fetch_assoc()['total'];
}

$orderBy = "p.created_at DESC";
if ($order === 'popular') {
    $orderBy = "likes_count DESC, comments_count DESC, p.created_at DESC";
}

$sql = "
    SELECT 
        p.id, p.user_id, p.title, p.content, p.created_at,
        u.name AS author_name, u.avatar AS author_avatar,
        (SELECT image_url FROM post_images WHERE post_id = p.id LIMIT 1) AS thumbnail,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments_count,
        (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS saves_count
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    $where
";

$sql .= " ORDER BY $orderBy LIMIT ?, ?";
$params2 = $params;
$types2  = $types . "ii";
$params2[] = $offset;
$params2[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {

    $tagRows = $conn->query("SELECT tag FROM post_tags WHERE post_id = {$row['id']}");
    $tags = [];
    while ($t = $tagRows->fetch_assoc()) {
        $tags[] = $t['tag'];
    }

    $isLiked = false;
    $isSaved = false;
    if ($userId) {
        $resLike = $conn->query("SELECT 1 FROM post_likes WHERE post_id = {$row['id']} AND user_id = $userId LIMIT 1");
        $isLiked = $resLike && $resLike->num_rows > 0;

        $resSave = $conn->query("SELECT 1 FROM post_saves WHERE post_id = {$row['id']} AND user_id = $userId LIMIT 1");
        $isSaved = $resSave && $resSave->num_rows > 0;
    }

    $posts[] = [
        'id'            => (int)$row['id'],
        'title'         => $row['title'],
        'content'       => $row['content'],
        'excerpt'       => mb_substr($row['content'] ?? '', 0, 120) . '...',
        'thumbnail'     => $row['thumbnail'],
        'created_at'    => $row['created_at'],
        'author'        => [
            'id'     => (int)$row['user_id'],
            'name'   => $row['author_name'],
            'avatar' => $row['author_avatar'],
        ],
        'likes_count'    => (int)$row['likes_count'],
        'comments_count' => (int)$row['comments_count'],
        'saves_count'    => (int)$row['saves_count'],
        'tags'           => $tags,
        'is_liked'       => $isLiked,
        'is_saved'       => $isSaved,
    ];
}

echo json_encode([
    'success' => true,
    'meta' => [
        'page'      => $page,
        'limit'     => $limit,
        'total'     => (int)$total,
        'totalPage' => ceil($total / $limit),
    ],
    'posts' => $posts
]);
