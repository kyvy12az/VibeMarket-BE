<?php
require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../config/jwt.php";

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
$commentId = intval($body["comment_id"] ?? 0);

if ($commentId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid comment_id"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
$check->bind_param("ii", $userId, $commentId);
$check->execute();
$liked = $check->get_result()->num_rows > 0;

if ($liked) {
    $delete = $conn->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $delete->bind_param("ii", $userId, $commentId);
    $delete->execute();

    $conn->query("UPDATE post_comments SET likes_count = likes_count - 1 WHERE id = {$commentId}");

    echo json_encode(["success" => true, "liked" => false]);
} else {
    $insert = $conn->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
    $insert->bind_param("ii", $userId, $commentId);
    $insert->execute();

    $conn->query("UPDATE post_comments SET likes_count = likes_count + 1 WHERE id = {$commentId}");

    echo json_encode(["success" => true, "liked" => true]);
}
