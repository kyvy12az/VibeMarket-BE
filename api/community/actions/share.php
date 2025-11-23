<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body     = json_decode(file_get_contents('php://input'), true);
$postId   = intval($body['post_id'] ?? 0);
$platform = trim($body['platform'] ?? 'internal');

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO post_shares (post_id, user_id, platform) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $postId, $userId, $platform);
$ok = $stmt->execute();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Đã ghi nhận chia sẻ' : 'Không thể ghi nhận'
]);
