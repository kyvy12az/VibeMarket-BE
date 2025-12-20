<?php
require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../config/jwt.php";

header("Content-Type: application/json; charset=utf-8");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
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

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? "";

if ($action === "reply") {

    $postId = intval($body["post_id"] ?? 0);
    $parentId = intval($body["parent_id"] ?? 0);
    $content = trim($body["content"] ?? "");

    if ($postId <= 0 || $parentId <= 0 || $content === "") {
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit;
    }

    $sql = "INSERT INTO post_comments (post_id, user_id, parent_id, content)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $postId, $userId, $parentId, $content);
    $stmt->execute();

    $newId = $stmt->insert_id;

    $u = $conn->prepare("SELECT name, avatar FROM users WHERE id = ?");
    $u->bind_param("i", $userId);
    $u->execute();
    $userData = $u->get_result()->fetch_assoc();

    $avatar = $userData["avatar"];
    if (!empty($avatar) && !str_starts_with($avatar, "http")) {
        $avatar = $BASE_URL . $avatar;
    }

    echo json_encode([
        "success" => true,
        "comment" => [
            "id" => $newId,
            "content" => $content,
            "created_at" => date("Y-m-d H:i:s"),
            "likes" => 0,
            "liked" => false,
            "user" => [
                "name" => $userData["name"],
                "avatar" => $avatar
            ],
            "replies" => []
        ]
    ]);
    exit;
}

if ($action === "edit") {

    $commentId = intval($body["comment_id"] ?? 0);
    $content = trim($body["content"] ?? "");

    if ($commentId <= 0 || $content === "") {
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit;
    }

    $check = $conn->prepare("SELECT user_id FROM post_comments WHERE id = ?");
    $check->bind_param("i", $commentId);
    $check->execute();
    $owner = $check->get_result()->fetch_assoc();

    if (!$owner || $owner["user_id"] != $userId) {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    $update = $conn->prepare("UPDATE post_comments SET content = ? WHERE id = ?");
    $update->bind_param("si", $content, $commentId);
    $update->execute();

    echo json_encode(["success" => true, "message" => "Updated"]);
    exit;
}

if ($action === "delete") {

    $commentId = intval($body["comment_id"] ?? 0);

    if ($commentId <= 0) {
        echo json_encode(["success" => false, "message" => "Missing comment id"]);
        exit;
    }

    $check = $conn->prepare("SELECT user_id FROM post_comments WHERE id = ?");
    $check->bind_param("i", $commentId);
    $check->execute();
    $owner = $check->get_result()->fetch_assoc();

    if (!$owner || $owner["user_id"] != $userId) {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    $del = $conn->prepare("DELETE FROM post_comments WHERE id = ?");
    $del->bind_param("i", $commentId);
    $del->execute();

    echo json_encode(["success" => true, "deleted_id" => $commentId]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid action"]);
