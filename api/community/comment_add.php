<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$body     = json_decode(file_get_contents("php://input"), true);
$postId   = intval($body["post_id"] ?? 0);
$content  = trim($body["content"] ?? "");
$parentId = isset($body["parent_id"]) ? intval($body["parent_id"]) : null;

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit;
}

if (!$postId || $content === "") {
    echo json_encode(["success" => false, "message" => "Missing data"]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO post_comments (post_id, user_id, parent_id, content)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param("iiis", $postId, $userId, $parentId, $content);
$stmt->execute();

$commentId = $conn->insert_id;

$q = $conn->query("
    SELECT c.*, u.name, u.avatar
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = $commentId
");

$c = $q->fetch_assoc();

echo json_encode([
    "success" => true,
    "comment" => [
        "id" => (int)$c["id"],
        "content" => $c["content"],
        "created_at" => $c["created_at"],
        "parent_id" => $c["parent_id"] ? (int)$c["parent_id"] : null,
        "user" => [
            "id" => (int)$c["user_id"],
            "name" => $c["name"],
            "avatar" => $c["avatar"] ?: null,

        ]
    ]
]);
