<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$body   = json_decode(file_get_contents("php://input"), true);
$postId = intval($body["post_id"] ?? 0);

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit;
}

if (!$postId) {
    echo json_encode(["success" => false, "message" => "Missing post_id"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM post_saves WHERE post_id = ? AND user_id = ?");
$check->bind_param("ii", $postId, $userId);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $del = $conn->prepare("DELETE FROM post_saves WHERE id = ?");
    $del->bind_param("i", $row["id"]);
    $del->execute();

    echo json_encode(["success" => true, "saved" => false]);
} else {
    $ins = $conn->prepare("INSERT INTO post_saves (post_id, user_id) VALUES (?, ?)");
    $ins->bind_param("ii", $postId, $userId);
    $ok = $ins->execute();

    echo json_encode(["success" => $ok, "saved" => true]);
}
