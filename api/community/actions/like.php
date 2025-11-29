<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$check = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1");
$check->bind_param("ii", $postId, $userId);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();

    $del = $conn->prepare("DELETE FROM post_likes WHERE id = ?");
    $del->bind_param("i", $row["id"]);
    $del->execute();

    echo json_encode(["success" => true, "liked" => false]);
    exit;
}

$ins = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
$ins->bind_param("ii", $postId, $userId);
$ok = $ins->execute();

echo json_encode(["success" => $ok, "liked" => true]);
