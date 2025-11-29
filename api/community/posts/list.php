<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$BASE_URL = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE";

$userId = get_user_id_from_token();
if (!$userId) $userId = 0;

$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$sql = "
    SELECT 
        p.id, p.content, p.created_at,
        u.name AS author_name, u.avatar AS author_avatar,

        (
            SELECT GROUP_CONCAT(image_url SEPARATOR '|||')
            FROM post_images 
            WHERE post_id = p.id
        ) AS images,

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

$tagStmt = $conn->prepare("
    SELECT tag 
    FROM post_tags 
    WHERE post_id = ?
");

$fpStmt = $conn->prepare("
    SELECT 
        p.id,
        p.name,
        p.price,
        p.image
    FROM post_featured_products fp
    JOIN products p ON fp.product_id = p.id
    WHERE fp.post_id = ?
");

while ($p = $res->fetch_assoc()) {
    $postId = (int)$p['id'];

    $rawImages = $p['images'] ?? '';
    $imgArr = ($rawImages !== '') ? explode('|||', $rawImages) : [];

    $imgArr = array_map(function ($img) use ($BASE_URL) {
        if (!str_starts_with($img, "http")) {
            return $BASE_URL . $img;
        }
        return $img;
    }, $imgArr);

    $p["images"] = $imgArr;

    if (!empty($p["author_avatar"]) && !str_starts_with($p["author_avatar"], "http")) {
        $p["author_avatar"] = $BASE_URL . $p["author_avatar"];
    }

    $tags = [];
    $tagStmt->bind_param("i", $postId);
    $tagStmt->execute();
    $tagRes = $tagStmt->get_result();
    while ($rowTag = $tagRes->fetch_assoc()) {
        $tags[] = $rowTag['tag'];
    }

    $featuredProducts = [];
    $fpStmt->bind_param("i", $postId);
    $fpStmt->execute();
    $fpRes = $fpStmt->get_result();

    while ($row = $fpRes->fetch_assoc()) {
        $images = json_decode($row['image'], true);
        if (is_array($images) && !empty($images)) {
            $firstImage = $images[0];
            if (!str_starts_with($firstImage, "http")) {
                $firstImage = $BASE_URL . $firstImage;
            }
        } else {
            $firstImage = null;
        }

        $featuredProducts[] = [
            "id"    => (int)$row['id'],
            "name"  => $row['name'],
            "price" => (int)$row['price'],
            "image" => $firstImage,
        ];
    }

    $posts[] = [
        "id"         => $postId,
        "content"    => $p["content"],
        "created_at" => $p["created_at"],
        "author" => [
            "name"   => $p["author_name"] ?: "Người dùng ẩn danh",
            "avatar" => $p["author_avatar"] ?: null,
        ],

        "images"            => $p["images"],
        "tags"              => $tags,
        "featured_products" => $featuredProducts,
        "likes"             => (int)$p["likes"],
        "comments"          => (int)$p["comments"],
        "is_liked"          => (bool)$p["is_liked"],
        "is_saved"          => (bool)$p["is_saved"],
    ];
}

echo json_encode([
    "success" => true,
    "posts"   => $posts
]);
