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

if (!$commentId) {
    echo json_encode(["success" => false, "message" => "Missing comment_id"]);
    exit;
}

$q = $conn->query("SELECT user_id FROM post_comments WHERE id = $commentId");
$row = $q->fetch_assoc();

if (!$row || $row["user_id"] != $userId) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
}

$conn->query("DELETE FROM post_comments WHERE id = $commentId");

echo json_encode(["success" => true]);
