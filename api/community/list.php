<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$BASE_URL = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE"; 

$userId = get_user_id_from_token();
if (!$userId) $userId = 0;

$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$sql = "
    SELECT 
        p.id, p.title, p.content, p.created_at,
        u.name AS author_name, u.avatar AS author_avatar,

        (SELECT image_url FROM post_images WHERE post_id = p.id LIMIT 1) AS thumbnail,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments,

        EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) AS is_liked,
        EXISTS(SELECT 1 FROM post_saves WHERE post_id = p.id AND user_id = ?) AS is_saved

    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'public'
    ORDER BY p.created_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $userId, $userId, $offset, $limit);
$stmt->execute();
$res = $stmt->get_result();

$posts = [];
while ($p = $res->fetch_assoc()) {

    if (!empty($p["thumbnail"])) {
        if (!str_starts_with($p["thumbnail"], "http")) {
            $p["thumbnail"] = $BASE_URL . $p["thumbnail"];
        }
    }

    if (!empty($p["author_avatar"])) {
        if (!str_starts_with($p["author_avatar"], "http")) {
            $p["author_avatar"] = $BASE_URL . $p["author_avatar"];
        }
    }

    $posts[] = $p;
}

echo json_encode([
    "success" => true,
    "posts" => $posts
]);
