<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$BASE_URL = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing post id"]);
    exit;
}

$userId = get_user_id_from_token();

$sql = "
    SELECT p.*, u.name AS author_name, u.avatar AS author_avatar
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND p.status != 'deleted'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    echo json_encode(["success" => false, "message" => "Post not found"]);
    exit;
}

if (!empty($post["author_avatar"]) && !str_starts_with($post["author_avatar"], "http")) {
    $post["author_avatar"] = $BASE_URL . $post["author_avatar"];
}

$imgs = [];
$imgRes = $conn->query("SELECT image_url FROM post_images WHERE post_id = $id");

while ($row = $imgRes->fetch_assoc()) {
    $url = $row['image_url'];
    if (!str_starts_with($url, "http")) {
        $url = $BASE_URL . $url;
    }
    $imgs[] = $url;
}

$tags = [];
$tagRes = $conn->query("SELECT tag FROM post_tags WHERE post_id = $id");
while ($row = $tagRes->fetch_assoc()) {
    $tags[] = $row['tag'];
}

$likes    = $conn->query("SELECT COUNT(*) AS c FROM post_likes WHERE post_id=$id")->fetch_assoc()['c'];
$comments = $conn->query("SELECT COUNT(*) AS c FROM post_comments WHERE post_id=$id")->fetch_assoc()['c'];
$saves    = $conn->query("SELECT COUNT(*) AS c FROM post_saves WHERE post_id=$id")->fetch_assoc()['c'];

$isLiked = $userId
    ? $conn->query("SELECT 1 FROM post_likes WHERE post_id=$id AND user_id=$userId LIMIT 1")->num_rows > 0
    : false;

$isSaved = $userId
    ? $conn->query("SELECT 1 FROM post_saves WHERE post_id=$id AND user_id=$userId LIMIT 1")->num_rows > 0
    : false;

$commentList = [];
$cRes = $conn->query("
    SELECT c.*, u.name, u.avatar
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = $id
    ORDER BY c.created_at DESC
    LIMIT 20
");

while ($c = $cRes->fetch_assoc()) {

    $avatar = $c["avatar"];
    if (!empty($avatar) && !str_starts_with($avatar, "http")) {
        $avatar = $BASE_URL . $avatar;
    }

    $commentList[] = [
        "id" => (int)$c["id"],
        "content" => $c["content"],
        "created_at" => $c["created_at"],
        "parent_id" => $c["parent_id"] ? (int)$c["parent_id"] : null,
        "user" => [
            "id" => (int)$c["user_id"],
            "name" => $c["name"],
            "avatar" => $avatar
        ]
    ];
}

$featuredProducts = [];
$fpRes = $conn->query("
    SELECT p.id, p.name, p.price, p.image
    FROM post_featured_products fp
    JOIN products p ON fp.product_id = p.id
    WHERE fp.post_id = $id
");

while ($row = $fpRes->fetch_assoc()) {
    $images = json_decode($row['image'], true);
    $firstImage = null;

    if (is_array($images) && !empty($images)) {
        $firstImage = $images[0];
        if (!str_starts_with($firstImage, "http")) {
            $firstImage = $BASE_URL . $firstImage;
        }
    }

    $featuredProducts[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "price" => (int)$row["price"],
        "image" => $firstImage
    ];
}


echo json_encode([
    "success" => true,
    "post" => [
        "id" => (int)$post["id"],
        "content" => $post["content"],
        "status" => $post["status"],
        "created_at" => $post["created_at"],
        "updated_at" => $post["updated_at"],

        "author" => [
            "id" => (int)$post["user_id"],
            "name" => $post["author_name"],
            "avatar" => $post["author_avatar"]
        ],

        "images" => $imgs,
        "tags" => $tags,
        "featured_products" => $featuredProducts,

        "likes_count" => (int)$likes,
        "comments_count" => (int)$comments,
        "saves_count" => (int)$saves,

        "is_liked" => $isLiked,
        "is_saved" => $isSaved,

        "comments" => $commentList
    ]
]);
