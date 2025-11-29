<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body   = json_decode(file_get_contents("php://input"), true);
$postId = intval($body["post_id"] ?? 0);
$reason = trim($body["reason"] ?? '');

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit;
}

if (!$reason) $reason = "Không ghi lý do";

$stmt = $conn->prepare("INSERT INTO post_reports (post_id, user_id, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $postId, $userId, $reason);
$stmt->execute();

echo json_encode(["success" => true, "message" => "Đã gửi báo cáo"]);
