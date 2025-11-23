<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
$commentId = intval($body["comment_id"] ?? 0);
$content   = trim($body["content"] ?? "");

if (!$commentId || $content === "") {
    echo json_encode(["success" => false, "message" => "Missing data"]);
    exit;
}

$q = $conn->query("SELECT user_id FROM post_comments WHERE id = $commentId");
$row = $q->fetch_assoc();

if (!$row || $row["user_id"] != $userId) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
}

$stmt = $conn->prepare("UPDATE post_comments SET content = ? WHERE id = ?");
$stmt->bind_param("si", $content, $commentId);
$stmt->execute();

echo json_encode([
    "success" => true,
    "content" => $content
]);
